<?php
	namespace BigTree\Services\AI\Tools;

	use BigTree\Services\AI\AIToolContext;
	use BigTree\Services\AI\AIToolInterface;
	use BigTree\Services\AI\AIToolResult;
	use BigTree\Services\AI\ContentLock;
	use BigTree\Services\AI\ProposalFingerprint;
	use BigTree\Services\AI\ProposalStore;

	/**
	 * Shared base for two-phase mutating tools. A mutating tool never changes the CMS
	 * inside execute(): it validates, stages a proposal in the ProposalStore, and
	 * returns AIToolResult::proposal(...). The actual write happens later, from the
	 * stored payload, when the user approves (see AIChatService::approveProposal).
	 *
	 * kind() is fixed to "mutate" so a driver can tell these apart from read tools.
	 */
	abstract class AbstractMutatingTool implements AIToolInterface {
		/**
		 * Reserved payload key carrying the staged staleness fingerprint. Stripped
		 * before the payload reaches an execute seam, so no seam has to know about it.
		 */
		const FINGERPRINT_KEY = "__fingerprint__";

		/**
		 * Reserved payload key carrying the staged content-lock descriptor, so the
		 * approval can re-ask who holds the record rather than replaying a snapshot
		 * taken up to 24 hours earlier. Stripped before the payload reaches an execute
		 * seam, like the fingerprint.
		 */
		const LOCK_KEY = "__lock__";

		/**
		 * Reserved payload key naming the still-pending proposal this one depends on,
		 * so approving them out of order refuses with the prerequisite named rather
		 * than writing against a container that doesn't exist yet. Stripped before
		 * dispatch like the fingerprint and the lock.
		 */
		const DEPENDS_ON_KEY = "__depends_on__";

		/** @var ProposalStore */
		protected $proposals;

		public function __construct(ProposalStore $proposals) {
			$this->proposals = $proposals;
		}

		public function kind(): string {

			return "mutate";
		}

		public function isAvailable($user): bool {

			return true;
		}

		/**
		 * Turn a backend validation result into a tool result: a denial or recoverable
		 * error passes straight through, and a valid one stages a proposal (scoped to
		 * the conversation) and returns its id. Every mutating tool's execute() ends
		 * here, so the validate→stage contract stays identical across the catalog and
		 * nothing is written during the turn.
		 *
		 * @param array<string,mixed> $validation One of:
		 *   ["denied" => string] | ["error" => string] |
		 *   ["needs_input" => ["question" => string, "options" => list<array>]] |
		 *   ["ok" => true, "summary" => string, "preview" => array, "payload" => array]
		 */
		protected function stageFromValidation(array $validation, AIToolContext $context, string $tool): AIToolResult {
			$conversation_id = (int)($context->conversation_id !== "" ? $context->conversation_id : 0);

			// Resolved before the error and needs_input branches below, because the
			// seam attaches the descriptor *alongside* whichever of them it was going
			// to return: "there is no callout group called Promos" is only the right
			// answer when nothing in this conversation is already proposing to create
			// one. See AIToolResult::needsPriorChange.
			$prior = $this->resolvePriorChange($validation["prior_change"] ?? null, $conversation_id);

			if ($prior !== null && !empty($prior["blocking"])) {

				return AIToolResult::needsPriorChange(
					$prior["message"],
					(string)$prior["proposal"]["id"],
					(string)$prior["proposal"]["tool"]
				);
			}

			if (isset($validation["denied"])) {

				return AIToolResult::denied((string)$validation["denied"], is_array($validation["alternatives"] ?? null) ? $validation["alternatives"] : []);
			}

			if (isset($validation["error"])) {

				return AIToolResult::error((string)$validation["error"]);
			}

			// A module with more than one entry form has no safe default — each form
			// writes to its own table, so guessing would validate against one and
			// write to another. The backend hands back the choice; turn it into a
			// question for the user rather than picking for them.
			if (!empty($validation["ambiguous_form"])) {

				return AIToolResult::needsInput(
					"Which form should I use for this module?",
					array_map(function (array $form): array {

						return [
							"id" => (string)$form["id"],
							"label" => (string)($form["title"] !== "" ? $form["title"] : $form["id"]),
							"description" => "Table: " . (string)$form["table"],
						];
					}, is_array($validation["forms"] ?? null) ? $validation["forms"] : [])
				);
			}

			// The general form of the same idea: a backend that can't proceed without a
			// choice hands back the question and its options rather than guessing or
			// failing with a wall the model has to rediscover ("group does not exist").
			if (is_array($validation["needs_input"] ?? null)) {

				return AIToolResult::needsInput(
					(string)($validation["needs_input"]["question"] ?? "I need a bit more information."),
					is_array($validation["needs_input"]["options"] ?? null) ? $validation["needs_input"]["options"] : []
				);
			}

			$summary = (string)($validation["summary"] ?? "");
			$preview = is_array($validation["preview"] ?? null) ? $validation["preview"] : [];
			$payload = is_array($validation["payload"] ?? null) ? $validation["payload"] : [];

			// A backend may name the record's concurrent-edit lock; say so on the card
			// if someone else is holding it. Resolved here rather than per seam for the
			// same reason the fingerprint is, and folded into the summary so both the
			// user reading the card and the model reading the tool result see it.
			// See ContentLock for why this warns rather than refuses.
			$lock = $validation["lock"] ?? null;
			$holder = ContentLock::heldBy($lock, $context->user);

			if ($holder !== null) {
				$summary .= ContentLock::note($lock, $context->user);
				// Also delivered as its own field, so the card can render it as a
				// warning rather than leaving it buried in a paragraph of summary
				// text that ProposalCard single-line truncates.
				$preview["content_lock"] = [
					"holder" => $holder,
					"kind" => ContentLock::kindOf($lock),
				];
			}

			// Stored so the approval can re-ask. The staged note is a snapshot from up
			// to 24 hours before the approve click, which is exactly long enough for
			// the answer to have changed in either direction.
			if (is_array($lock) && $lock) {
				$payload[self::LOCK_KEY] = $lock;
			}

			// A backend may describe what its proposal is *about*; hash it now so the
			// approval can tell whether the card still describes the record. Done once
			// here rather than per seam so the check can't drift tool by tool. See
			// ProposalFingerprint and AIChatService::assertNotStale.
			$fingerprint = $validation["fingerprint"] ?? null;

			if (is_array($fingerprint) && $fingerprint) {
				// A descriptor that can't be hashed is not an opt-out — a seam that
				// wants no staleness check emits no descriptor at all. Refusing here
				// means a malformed one (a misspelled type, a missing table) surfaces
				// as a failed tool call rather than as a card that quietly protects
				// nothing: the typo recurs identically at approval, so the comparison
				// would pass and the staleness check would never fire.
				$unsupported = ProposalFingerprint::unsupportedReason($fingerprint);

				if ($unsupported !== null) {

					return AIToolResult::error(
						"This change could not be staged safely: {$unsupported}. This is a problem with the "
							. "\"{$tool}\" tool itself — report it rather than retrying."
					);
				}

				$payload[self::FINGERPRINT_KEY] = [
					"descriptor" => $fingerprint,
					"hash" => ProposalFingerprint::compute($fingerprint),
				];
			}

			// A non-blocking prerequisite: this change *can* be staged against the world
			// as it stands, but it refers to something another pending proposal would
			// create (the classic case is a redirect pointing at a page that is itself
			// still on a card). Both are legitimate; only the order matters, so record
			// it rather than refusing.
			if ($prior !== null) {
				$payload[self::DEPENDS_ON_KEY] = [
					"id" => (string)$prior["proposal"]["id"],
					"tool" => (string)$prior["proposal"]["tool"],
					"summary" => (string)$prior["proposal"]["summary"],
				];
				$summary .= " " . $prior["message"];
				$preview["depends_on"] = $prior["message"];
			}

			$proposal = $this->proposals->create($context->user, $conversation_id, $tool, $summary, $preview, $payload);

			return AIToolResult::proposal($summary, $preview, (string)$proposal["id"]);
		}

		/**
		 * Match a seam's "this needs something that doesn't exist yet" descriptor
		 * against the conversation's own unapproved proposals.
		 *
		 * The seams can't ask this themselves — they are handed args and a user, never
		 * a conversation — and doing it here rather than per seam keeps one definition
		 * of what "already proposed" means, the same way the fingerprint and the lock
		 * are resolved once for the whole catalog.
		 *
		 * @param mixed $descriptor ["tools"|"tool" => …, "value" => string, "keys" => list<string>,
		 *                           "label" => string, "blocking" => bool]
		 * @return array{proposal:array<string,mixed>,message:string,blocking:bool}|null
		 */
		private function resolvePriorChange($descriptor, int $conversation_id): ?array {
			if (!is_array($descriptor) || !$descriptor || $conversation_id <= 0) {

				return null;
			}

			$tools = $descriptor["tools"] ?? $descriptor["tool"] ?? [];
			$tools = array_values(array_filter(array_map("strval", is_array($tools) ? $tools : [$tools])));

			if (!$tools) {

				return null;
			}

			$value = self::comparableValue($descriptor["value"] ?? "");
			$keys = is_array($descriptor["keys"] ?? null) ? $descriptor["keys"] : ["name"];
			$blocking = !array_key_exists("blocking", $descriptor) || !empty($descriptor["blocking"]);

			foreach ($this->proposals->pendingForConversation($conversation_id, $tools) as $row) {
				if ($value !== "" && !self::proposalDescribes($row, $keys, $value)) {

					continue;
				}

				$label = trim((string)($descriptor["label"] ?? ""));
				$summary = trim((string)($row["summary"] ?? ""));
				$message = $blocking
					? ($label !== "" ? "{$label} doesn't exist yet" : "What this needs doesn't exist yet")
						. ", but this conversation has an unapproved proposal that would create it"
						. ($summary !== "" ? " (“" . self::firstSentence($summary) . "”)" : "")
						. ". Ask the user to approve that card first, then propose this again — nothing has been "
						. "written yet."
					: "Note: this refers to something that doesn't exist yet but is covered by an unapproved "
						. "proposal in this conversation"
						. ($summary !== "" ? " (“" . self::firstSentence($summary) . "”)" : "")
						. ". Approve that one first, or this will point at nothing.";

				return ["proposal" => $row, "message" => $message, "blocking" => $blocking];
			}

			return null;
		}

		/**
		 * Whether a pending proposal is the one that would create $value.
		 *
		 * Matched against the payload first and the preview second: the payload is what
		 * will actually be written, but a derived value the approver was shown (a
		 * page's full path, say) only exists on the preview.
		 *
		 * @param array<string,mixed> $row
		 * @param list<string> $keys
		 */
		private static function proposalDescribes(array $row, array $keys, string $value): bool {
			$payload = json_decode((string)($row["payload"] ?? ""), true);
			$preview = json_decode((string)($row["preview"] ?? ""), true);
			$sources = [is_array($payload) ? $payload : [], is_array($preview) ? $preview : []];

			foreach ($keys as $key) {
				foreach ($sources as $source) {
					if (!isset($source[$key]) || is_array($source[$key])) {

						continue;
					}

					if (self::comparableValue($source[$key]) === $value) {

						return true;
					}
				}
			}

			return false;
		}

		/**
		 * Normalize a name or a path for comparison. Case and surrounding slashes are
		 * the two differences that routinely separate what the model typed from what
		 * the staged proposal stored ("Promos" vs "promos", "/pricing" vs "pricing").
		 *
		 * @param mixed $value
		 */
		private static function comparableValue($value): string {

			return mb_strtolower(trim(trim((string)$value), "/"));
		}

		/** A staged summary's opening sentence, for quoting one card inside another's message. */
		private static function firstSentence(string $summary): string {
			$end = mb_strpos($summary, ". ");
			$sentence = $end === false ? $summary : mb_substr($summary, 0, $end + 1);

			return rtrim(mb_substr(trim($sentence), 0, 160));
		}

		/**
		 * Wraps an OpenAI-style function tool definition.
		 *
		 * @param array<string,mixed> $parameters
		 * @return array<string,mixed>
		 */
		protected function functionDefinition(string $name, string $description, array $parameters): array {

			return [
				"type" => "function",
				"function" => [
					"name" => $name,
					"description" => $description,
					"parameters" => $parameters,
				],
			];
		}
	}
