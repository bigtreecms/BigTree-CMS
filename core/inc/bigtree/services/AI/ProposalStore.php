<?php
	namespace BigTree\Services\AI;

	use SQL;

	/**
	 * The server-side store for staged mutations (Phase 3).
	 *
	 * A mutating tool never changes the CMS during a chat turn. Instead it validates
	 * the request, writes a *proposal* here (status "pending"), and returns its id as
	 * an AIToolResult::proposal(...). The SPA renders that as a confirmation card; the
	 * change happens only when the user approves, at which point the acting service
	 * re-checks permission and executes from the stored, validated payload — never
	 * from anything the model round-tripped.
	 *
	 * Proposals are per-user and per-conversation. A non-owner load returns null so
	 * approve/reject 404 without leaking that the row exists, and each proposal has a
	 * short TTL so a stale card can't be approved into a live change days later.
	 */
	class ProposalStore {
		const TABLE = "bigtree_ai_proposals";

		const PENDING = "pending";
		const APPROVING = "approving";
		const APPROVED = "approved";
		const REJECTED = "rejected";
		const EXPIRED = "expired";

		// An approval that ran and came back ["mode" => "error"]. Roughly twenty
		// approval-time re-validation branches return that instead of throwing, and
		// they were all recorded as APPROVED — a green badge over a change that never
		// happened, with no audit row and no way to retry. A failed proposal keeps its
		// error on the card and stays claimable so the user can fix the cause and
		// approve again.
		const FAILED = "failed";

		/** Statuses an approve/reject may still act on. */
		const ACTIONABLE = [self::PENDING, self::FAILED];

		// Rows this many days past expiry are opportunistically purged on create()
		// so the table stays bounded while recently resolved cards remain renderable
		// in conversation history.
		const PURGE_AFTER_DAYS = 30;

		// How long a staged proposal stays approvable. Long enough for a real
		// review, short enough that permission checks at approval reflect roughly
		// the same authorization landscape as when it was staged.
		const TTL_SECONDS = 86400;

		/** @var bool Memoized table-existence probe (once per request). */
		private static $table_ready = false;

		/**
		 * Stage a validated mutation. Returns the stored row (including its id) so the
		 * caller can hand the id to the model and the SPA.
		 *
		 * @param object|array $user
		 * @param array<string,mixed> $payload Validated, execute-ready arguments.
		 * @param array<string,mixed> $preview Human/SPA-facing diff or field summary.
		 * @return array<string,mixed>
		 */
		public function create($user, int $conversation_id, string $tool, string $summary, array $preview, array $payload): array {
			$this->ensureTable();

			$id = self::newId();
			$now = time();
			$created_at = date("Y-m-d H:i:s", $now);
			$expires_at = date("Y-m-d H:i:s", $now + self::TTL_SECONDS);

			$row = [
				"id" => $id,
				"conversation" => $conversation_id,
				"user" => (int)$this->userId($user),
				"tool" => $tool,
				"summary" => $summary,
				"preview" => json_encode($preview),
				"payload" => json_encode($payload),
				"status" => self::PENDING,
				"result" => null,
				"created_at" => $created_at,
				"expires_at" => $expires_at,
			];

			SQL::insert(self::TABLE, $row);

			$this->purgeExpired();

			return $row;
		}

		/**
		 * Opportunistically delete proposals well past expiry so the table stays
		 * bounded. Best-effort and once-ish per request (piggybacks on create()); a
		 * failure here must never fail the staging that triggered it.
		 */
		private function purgeExpired(): void {
			try {
				SQL::query(
					"DELETE FROM " . self::TABLE . " WHERE expires_at < (NOW() - INTERVAL " . (int)self::PURGE_AFTER_DAYS . " DAY)"
				);
			} catch (\Throwable $e) {
				// Housekeeping is best-effort; swallow so a staged mutation still returns.
			}
		}

		/**
		 * Claim an actionable proposal with a compare-and-set so two concurrent
		 * approvals (double-click, two tabs, a retried request) can't both execute.
		 * Flips pending/failed → $to only if the row is still actionable; returns
		 * whether exactly one row changed. A false return means someone else already
		 * resolved it.
		 */
		public function claimPending(string $id, string $to = self::APPROVING): bool {
			$this->ensureTable();

			$result = SQL::query(
				"UPDATE " . self::TABLE . " SET status = ? WHERE id = ? AND status IN (?, ?)",
				$to,
				$id,
				self::PENDING,
				self::FAILED
			);

			return $result->rows() === 1;
		}

		/**
		 * Return a proposal claimed with claimPending() back to pending — used when
		 * executing an approved mutation throws, so a revoked permission (403) leaves
		 * the proposal approvable again rather than stranded in the "approving" state.
		 */
		public function restorePending(string $id): void {
			SQL::query(
				"UPDATE " . self::TABLE . " SET status = ? WHERE id = ? AND status = ?",
				self::PENDING,
				$id,
				self::APPROVING
			);
		}

		/**
		 * Delete every proposal scoped to a conversation. Called when the conversation
		 * itself is deleted so a stale pending card can't be approved into a live change
		 * after its entire context is gone, and resolved rows don't leak forever.
		 */
		public function deleteForConversation(int $conversation_id): void {
			$this->ensureTable();

			SQL::query("DELETE FROM " . self::TABLE . " WHERE conversation = ?", $conversation_id);
		}

		/**
		 * Load a proposal only if it belongs to $user; a non-owner (or missing row)
		 * returns null so callers 404 without leaking existence. Also lazily flips a
		 * pending-but-expired proposal to "expired" so it can never be approved.
		 *
		 * @param object|array $user
		 * @return array<string,mixed>|null
		 */
		public function loadOwned(string $id, $user): ?array {
			$this->ensureTable();

			if ($id === "") {

				return null;
			}

			$row = SQL::fetch("SELECT * FROM " . self::TABLE . " WHERE id = ?", $id);

			if (!$row) {

				return null;
			}

			if ((int)$row["user"] !== (int)$this->userId($user)) {

				return null;
			}

			if (in_array($row["status"], self::ACTIONABLE, true) && $this->isExpired($row)) {
				SQL::update(self::TABLE, $id, ["status" => self::EXPIRED]);
				$row["status"] = self::EXPIRED;
			}

			return $row;
		}

		/**
		 * Proposals in a conversation that are still waiting on the user, newest
		 * first, optionally narrowed to a set of tools.
		 *
		 * Audit #9 A1: every validate seam reads the database as it stands *now*, so a
		 * two-step intent ("create a callout group and put this callout in it") had its
		 * second step validated against a world where the first hadn't happened. This
		 * is how a seam asks "is the thing I can't find already staged?" — a single
		 * indexed read on the `conversation` key that exists for listForConversation().
		 *
		 * Raw rows (payload included), not present()ed: the caller matches on what the
		 * pending change would create.
		 *
		 * @param list<string>|string|null $tools
		 * @return list<array<string,mixed>>
		 */
		public function pendingForConversation(int $conversation_id, $tools = null): array {
			$this->ensureTable();

			if ($conversation_id <= 0) {

				return [];
			}

			$tools = $tools === null ? [] : (is_array($tools) ? array_values($tools) : [$tools]);
			$sql = "SELECT * FROM " . self::TABLE . " WHERE conversation = ? AND status = ? AND expires_at > NOW()";
			$args = [$conversation_id, self::PENDING];

			if ($tools) {
				$sql .= " AND tool IN (" . implode(", ", array_fill(0, count($tools), "?")) . ")";
				$args = array_merge($args, $tools);
			}

			$sql .= " ORDER BY created_at DESC, id DESC";

			return SQL::fetchAll(...array_merge([$sql], $args));
		}

		/**
		 * Every proposal in a conversation, oldest first, presented for the SPA. Used
		 * on conversation reload so approved/rejected cards keep their resolved state.
		 *
		 * @return list<array<string,mixed>>
		 */
		public function listForConversation(int $conversation_id): array {
			$this->ensureTable();

			$rows = SQL::fetchAll(
				"SELECT * FROM " . self::TABLE . " WHERE conversation = ? ORDER BY created_at ASC, id ASC",
				$conversation_id
			);

			return array_map(function ($row) {

				return $this->present($row);
			}, $rows);
		}

		/**
		 * Record the outcome of a resolved proposal.
		 *
		 * @param array<string,mixed>|null $result
		 */
		public function markResolved(string $id, string $status, ?array $result = null): void {
			SQL::update(self::TABLE, $id, [
				"status" => $status,
				"result" => $result !== null ? json_encode($result) : null,
			]);
		}

		/**
		 * Whether a row has outlived its TTL. Static so callers holding a presented row
		 * rather than the store — the model-context replay is the one that matters —
		 * ask the same question the same way; `$this->isExpired()` still works.
		 *
		 * @param array<string,mixed> $row Needs only `expires_at`, which present() carries.
		 */
		public static function isExpired(array $row): bool {
			$expires = strtotime((string)($row["expires_at"] ?? ""));

			return $expires !== false && $expires < time();
		}

		/**
		 * Shape a stored row for the wire: decode the JSON columns, drop the raw
		 * execute payload (never needed by the SPA), keep summary + preview + status.
		 *
		 * @param array<string,mixed> $row
		 * @return array<string,mixed>
		 */
		public function present(array $row): array {
			$preview = json_decode((string)($row["preview"] ?? ""), true);
			$result = json_decode((string)($row["result"] ?? ""), true);

			return [
				"proposal_id" => (string)$row["id"],
				"tool" => (string)($row["tool"] ?? ""),
				"summary" => (string)($row["summary"] ?? ""),
				"preview" => is_array($preview) ? $preview : [],
				"status" => (string)($row["status"] ?? ""),
				"result" => is_array($result) ? $result : null,
				"created_at" => $row["created_at"] ?? null,
				"expires_at" => $row["expires_at"] ?? null,
			];
		}

		/**
		 * @param array<string,mixed> $row
		 * @return array<string,mixed>
		 */
		public function decodePayload(array $row): array {
			$payload = json_decode((string)($row["payload"] ?? ""), true);

			return is_array($payload) ? $payload : [];
		}

		private static function newId(): string {

			return "prop-" . bin2hex(random_bytes(16));
		}

		/**
		 * @param object|array $user
		 */
		private function userId($user): int {
			if (is_object($user)) {

				return (int)($user->id ?? 0);
			}

			if (is_array($user)) {

				return (int)($user["id"] ?? 0);
			}

			return 0;
		}

		/**
		 * Create the proposal table if missing (memoized). Canonical DDL lives here so
		 * revision 509 and a first live proposal agree on the schema.
		 */
		public function ensureTable(): void {
			if (self::$table_ready) {

				return;
			}

			if (!SQL::tableExists(self::TABLE)) {
				self::ensureTables();
			}

			self::$table_ready = true;
		}

		/**
		 * Idempotent CREATE TABLE (IF NOT EXISTS); called by revision 509 and
		 * ensureTable().
		 */
		public static function ensureTables(): void {
			SQL::query(
				"CREATE TABLE IF NOT EXISTS `" . self::TABLE . "` (
					`id` VARCHAR(64) NOT NULL,
					`conversation` BIGINT UNSIGNED NOT NULL,
					`user` INT UNSIGNED NOT NULL DEFAULT 0,
					`tool` VARCHAR(64) NOT NULL,
					`summary` MEDIUMTEXT NULL,
					`preview` MEDIUMTEXT NULL,
					`payload` MEDIUMTEXT NULL,
					`status` VARCHAR(16) NOT NULL DEFAULT 'pending',
					`result` MEDIUMTEXT NULL,
					`created_at` DATETIME NOT NULL,
					`expires_at` DATETIME NOT NULL,
					PRIMARY KEY (`id`),
					KEY `conversation` (`conversation`, `created_at`),
					KEY `user_status` (`user`, `status`)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
			);
		}
	}
