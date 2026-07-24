<?php
	/**
	 * Phase 3 proposal framework: the two-phase CreatePageTool (needs_input, denial,
	 * recoverable error, and proposal staging) and the ProposalStore's pure helpers
	 * (present shape, expiry).
	 *
	 * Pure — a fake PageToolBackend scripts validation outcomes and a fake
	 * ProposalStore captures staged proposals without a DB, so no database or live
	 * provider is touched. (ai_fake_user is defined in AIToolFrameworkTest; all
	 * *Test.php files are required before any test_* runs.)
	 */

	use BigTree\Services\AI\AIToolContext;
	use BigTree\Services\AI\AIToolResult;
	use BigTree\Services\AI\ProposalStore;
	use BigTree\Services\AI\Tools\CreatePageTool;
	use BigTree\Services\AI\Tools\PageToolBackend;

	if (!class_exists("FakePageToolBackend")) {
		class FakePageToolBackend implements PageToolBackend {
			/** @var list<array{id:int,title:string,path:string}> */
			public $parents = [];
			/** @var array<string,mixed> */
			public $validation = ["ok" => true, "summary" => "Create page.", "preview" => [], "payload" => []];
			/** @var array<string,mixed> */
			public $created = ["mode" => "pending", "title" => "X"];

			public $parents_has_more = false;

			public function aiWritableParents($user): array {

				return ["parents" => $this->parents, "has_more" => $this->parents_has_more];
			}

			public function aiValidatePageCreate(array $args, $user): array {

				return $this->validation;
			}

			public function aiCreatePage(array $payload, $user): array {

				return $this->created;
			}

			// Phase 4 page seam — scripted returns, captured calls for assertions.
			/** @var array<string,mixed> */
			public $tree = ["parent" => ["id" => 0], "children" => [], "can_create_here" => true];
			/** @var array<string,mixed> */
			public $update_validation = ["ok" => true, "summary" => "Update page.", "preview" => [], "payload" => []];
			/** @var array<string,mixed> */
			public $archive_validation = ["ok" => true, "summary" => "Archive page.", "preview" => [], "payload" => []];
			/** @var array<string,mixed>|null */
			public $updated = null;
			/** @var array<string,mixed>|null */
			public $archived = null;

			public function aiPageTree(int $parent, $user, int $offset = 0): array {

				return $this->tree;
			}

			public function aiValidatePageUpdate(array $args, $user): array {

				return $this->update_validation;
			}

			public function aiUpdatePage(array $payload, $user): array {
				$this->updated = $payload;

				return ["mode" => "published", "title" => "X", "page_id" => 1];
			}

			/** @var array<string,mixed> */
			public $content_validation = ["ok" => true, "summary" => "Update page content.", "preview" => [], "payload" => []];
			/** @var array<string,mixed>|null */
			public $content_updated = null;

			public function aiValidatePageContentUpdate(array $args, $user): array {

				return $this->content_validation;
			}

			public function aiUpdatePageContent(array $payload, $user): array {
				$this->content_updated = $payload;

				return ["mode" => "published", "title" => "X", "page_id" => 1];
			}

			public function aiValidatePageArchive(array $args, $user): array {

				return $this->archive_validation;
			}

			public function aiArchivePage(array $payload, $user): array {
				$this->archived = $payload;

				return ["mode" => "archived", "title" => "X", "page_id" => 1];
			}

			/** @var array<string,mixed> */
			public $unarchive_validation = ["ok" => true, "summary" => "Restore page.", "preview" => [], "payload" => []];
			/** @var array<string,mixed> */
			public $move_validation = ["ok" => true, "summary" => "Move page.", "preview" => [], "payload" => []];
			/** @var array<string,mixed>|null */
			public $unarchived = null;
			/** @var array<string,mixed>|null */
			public $moved = null;

			public function aiValidatePageUnarchive(array $args, $user): array {

				return $this->unarchive_validation;
			}

			public function aiUnarchivePage(array $payload, $user): array {
				$this->unarchived = $payload;

				return ["mode" => "unarchived", "title" => "X", "page_id" => 1];
			}

			public function aiValidatePageMove(array $args, $user): array {

				return $this->move_validation;
			}

			public function aiMovePage(array $payload, $user): array {
				$this->moved = $payload;

				return ["mode" => "moved", "title" => "X", "page_id" => 1, "path" => "/new/path"];
			}

			// Audit #2 phase 4 — page revisions.
			/** @var array<string,mixed> */
			public $revisions = ["page_id" => 1, "page_title" => "X", "revisions" => [
				["id" => 7, "title" => "X", "saved" => true, "description" => "Before rewrite", "updated_at" => "2026-07-01 09:00:00", "author_name" => "Tim"],
			]];
			/** @var array<string,mixed> */
			public $restore_validation = ["ok" => true, "summary" => "Restore revision.", "preview" => [], "payload" => []];
			/** @var array<string,mixed>|null */
			public $restored = null;

			public function aiPageRevisions(int $page_id, int $limit, $user): array {

				return $this->revisions;
			}

			public function aiValidateRevisionRestore(array $args, $user): array {

				return $this->restore_validation;
			}

			public function aiRestoreRevision(array $payload, $user): array {
				$this->restored = $payload;

				return ["mode" => "restored", "page_id" => 1, "revision_id" => 7, "title" => "X"];
			}

			// Audit #3 (B3) — SEO rating read and the named-revision save.
			/** @var array<string,mixed> */
			public $seo_rating = [
				"page_id" => 1, "page_title" => "X", "available" => true, "score" => 74,
				"recommendations" => ["Add a meta description."],
			];
			/** @var array<string,mixed> */
			public $save_revision_validation = ["ok" => true, "summary" => "Save revision.", "preview" => [], "payload" => []];
			/** @var array<string,mixed>|null */
			public $saved_revision = null;

			public function aiPageSeoRating(int $page_id, $user): array {

				return $this->seo_rating;
			}

			public function aiValidateSaveRevision(array $args, $user): array {

				return $this->save_revision_validation;
			}

			public function aiSaveRevision(array $payload, $user): array {
				$this->saved_revision = $payload;

				return ["mode" => "saved", "page_id" => 1, "revision_id" => 9, "description" => "Before rewrite"];
			}
		}
	}

	if (!class_exists("FakeProposalStore")) {
		/**
		 * Captures staged proposals in memory, overriding the one DB-touching method
		 * CreatePageTool uses so the tool can be tested without a database.
		 */
		class FakeProposalStore extends ProposalStore {
			/** @var list<array<string,mixed>> */
			public $created = [];

			public function create($user, int $conversation_id, string $tool, string $summary, array $preview, array $payload): array {
				$row = [
					"id" => "prop-fake-" . (count($this->created) + 1),
					"conversation" => $conversation_id,
					"tool" => $tool,
					"summary" => $summary,
					"preview" => $preview,
					"payload" => $payload,
					"status" => self::PENDING,
				];
				$this->created[] = $row;

				return $row;
			}
		}
	}

	function create_page_tool(FakePageToolBackend $backend, FakeProposalStore $store): CreatePageTool {

		return new CreatePageTool($backend, $store);
	}

	function test_create_page_needs_input_without_parent() {
		$backend = new FakePageToolBackend();
		$backend->parents = [
			["id" => 0, "title" => "Top level (site root)", "path" => ""],
			["id" => 3, "title" => "Blog", "path" => "blog"],
		];
		$store = new FakeProposalStore();
		$tool = create_page_tool($backend, $store);

		$result = $tool->execute(["nav_title" => "New Post"], new AIToolContext(ai_fake_user(1), 8, "5"));

		T::equals($result->type, AIToolResult::NEEDS_INPUT, "no parent → needs_input");
		T::equals(count($result->options), 2, "offers the writable parents");
		T::equals($result->options[1]["id"], 3, "option carries the parent id");
		T::equals($result->options[1]["description"], "/blog", "option describes the path");
		T::equals(count($store->created), 0, "needs_input stages nothing");
	}

	function test_create_page_needs_input_marks_partial_parent_list() {
		$backend = new FakePageToolBackend();
		$backend->parents = [
			["id" => 0, "title" => "Top level (site root)", "path" => ""],
			["id" => 3, "title" => "Blog", "path" => "blog"],
		];
		$backend->parents_has_more = true;
		$store = new FakeProposalStore();
		$tool = create_page_tool($backend, $store);

		$result = $tool->execute(["nav_title" => "New Post"], new AIToolContext(ai_fake_user(1), 8, "5"));

		T::equals($result->type, AIToolResult::NEEDS_INPUT, "no parent → needs_input");
		T::ok(stripos($result->question, "partial list") !== false, "a truncated parent list says so in the prompt");
		T::equals(count($result->options), 2, "and still offers the parents it does have");
	}

	function test_create_page_denied_without_writable_parents() {
		$backend = new FakePageToolBackend();
		$backend->parents = [];
		$store = new FakeProposalStore();
		$tool = create_page_tool($backend, $store);

		$result = $tool->execute(["nav_title" => "Orphan"], new AIToolContext(ai_fake_user(0), 8, "5"));

		T::equals($result->type, AIToolResult::DENIED, "no writable subtree → denied");
		T::ok(count($result->alternatives) >= 1, "denial offers an alternative");
	}

	function test_create_page_error_without_nav_title() {
		$tool = create_page_tool(new FakePageToolBackend(), new FakeProposalStore());

		$result = $tool->execute(["parent" => 3], new AIToolContext(ai_fake_user(1), 8, "5"));

		T::equals($result->type, AIToolResult::ERROR, "missing nav_title → recoverable error");
	}

	function test_create_page_surfaces_validation_denial() {
		$backend = new FakePageToolBackend();
		$backend->validation = ["denied" => "You cannot create a page here."];
		$store = new FakeProposalStore();
		$tool = create_page_tool($backend, $store);

		$result = $tool->execute(["nav_title" => "X", "parent" => 9], new AIToolContext(ai_fake_user(0), 8, "5"));

		T::equals($result->type, AIToolResult::DENIED, "validation denial → denied");
		T::equals(count($store->created), 0, "a denied validation stages nothing");
	}

	function test_create_page_surfaces_validation_error() {
		$backend = new FakePageToolBackend();
		$backend->validation = ["error" => "Template \"nope\" does not exist."];
		$tool = create_page_tool($backend, new FakeProposalStore());

		$result = $tool->execute(["nav_title" => "X", "parent" => 3], new AIToolContext(ai_fake_user(1), 8, "5"));

		T::equals($result->type, AIToolResult::ERROR, "validation error → recoverable error");
	}

	function test_create_page_stages_proposal_when_valid() {
		$backend = new FakePageToolBackend();
		$backend->validation = [
			"ok" => true,
			"summary" => "Create page “Hello” under Blog.",
			"preview" => ["nav_title" => "Hello", "mode" => "pending"],
			"payload" => ["parent" => 3, "nav_title" => "Hello", "route" => "hello"],
		];
		$store = new FakeProposalStore();
		$tool = create_page_tool($backend, $store);

		$result = $tool->execute(["nav_title" => "Hello", "parent" => 3], new AIToolContext(ai_fake_user(0), 8, "7"));

		T::equals($result->type, AIToolResult::PROPOSAL, "valid → proposal");
		T::equals($result->proposal_id, "prop-fake-1", "proposal carries the stored id");
		T::equals($result->summary, "Create page “Hello” under Blog.", "summary passed through");
		T::equals(count($store->created), 1, "exactly one proposal staged");
		T::equals($store->created[0]["conversation"], 7, "proposal scoped to the conversation");
		T::equals($store->created[0]["payload"]["route"], "hello", "validated payload stored, not model args");

		$payload = $result->toModelPayload();
		T::equals($payload["status"], "proposal", "model sees proposal status, not a done result");
		T::equals($payload["proposal_id"], "prop-fake-1", "model gets the id to reference");
	}

	function test_create_page_kind_is_mutate() {
		$tool = create_page_tool(new FakePageToolBackend(), new FakeProposalStore());

		T::equals($tool->kind(), "mutate", "create_page is a mutating tool");
		T::ok($tool->isAvailable(ai_fake_user(0)), "create_page is offered to editors (execute gates specifics)");
	}

	function test_proposal_store_present_shape() {
		$store = new ProposalStore();
		$row = [
			"id" => "prop-1",
			"conversation" => 4,
			"user" => 2,
			"tool" => "create_page",
			"summary" => "Create page X.",
			"preview" => json_encode(["nav_title" => "X"]),
			"payload" => json_encode(["parent" => 0, "secret" => "value"]),
			"status" => "approved",
			"result" => json_encode(["mode" => "published", "page_id" => 12]),
			"created_at" => "2026-07-14 10:00:00",
			"expires_at" => "2026-07-15 10:00:00",
		];

		$presented = $store->present($row);

		T::equals($presented["proposal_id"], "prop-1", "present exposes proposal_id");
		T::equals($presented["preview"]["nav_title"], "X", "preview decoded");
		T::equals($presented["result"]["page_id"], 12, "result decoded");
		T::equals($presented["status"], "approved", "status passed through");
		T::ok(!isset($presented["payload"]), "raw execute payload never sent to the SPA");
	}

	function test_proposal_store_is_expired() {
		$store = new ProposalStore();

		T::ok($store->isExpired(["expires_at" => date("Y-m-d H:i:s", time() - 60)]), "past expiry is expired");
		T::ok(!$store->isExpired(["expires_at" => date("Y-m-d H:i:s", time() + 3600)]), "future expiry is not expired");
	}

	/** True when the proposals table is reachable in this harness. */
	function _proposalstore_db_available(): bool {
		try {
			(new ProposalStore())->ensureTable();

			return true;
		} catch (\Throwable $e) {
			echo "  (skipped — database unavailable in this harness: " . $e->getMessage() . ")\n";

			return false;
		}
	}

	function test_proposal_claim_is_single_winner() {
		if (!_proposalstore_db_available()) {

			return;
		}

		$store = new ProposalStore();
		$user = ["id" => 987654];
		$row = $store->create($user, 987654001, "create_page", "Create page.", [], ["parent" => 0]);
		$id = (string)$row["id"];

		try {
			T::ok($store->claimPending($id), "first claim wins");
			T::ok(!$store->claimPending($id), "second claim on the same row loses (already claimed)");

			// The row is now in the transient 'approving' state.
			$after = $store->loadOwned($id, $user);
			T::equals($after["status"], ProposalStore::APPROVING, "claimed row is in the approving state");

			// Restoring it (the execute-throws path) makes it approvable again.
			$store->restorePending($id);
			$restored = $store->loadOwned($id, $user);
			T::equals($restored["status"], ProposalStore::PENDING, "restorePending returns the row to pending");
			T::ok($store->claimPending($id), "a restored row can be claimed again");
		} finally {
			SQL::query("DELETE FROM " . ProposalStore::TABLE . " WHERE id = ?", $id);
		}
	}

	function test_proposal_reject_claim_is_single_winner() {
		if (!_proposalstore_db_available()) {

			return;
		}

		$store = new ProposalStore();
		$user = ["id" => 987655];
		$row = $store->create($user, 987655001, "create_page", "Create page.", [], ["parent" => 0]);
		$id = (string)$row["id"];

		try {
			T::ok($store->claimPending($id, ProposalStore::REJECTED), "reject claim wins on a pending row");
			T::ok(!$store->claimPending($id, ProposalStore::REJECTED), "a second reject claim loses");
			T::ok(!$store->claimPending($id), "an approve claim also loses once rejected");
			T::equals($store->loadOwned($id, $user)["status"], ProposalStore::REJECTED, "row settled as rejected");
		} finally {
			SQL::query("DELETE FROM " . ProposalStore::TABLE . " WHERE id = ?", $id);
		}
	}

	function test_proposal_delete_for_conversation_cascades() {
		if (!_proposalstore_db_available()) {

			return;
		}

		$store = new ProposalStore();
		$user = ["id" => 987656];
		$conversation = 987656001;
		$a = $store->create($user, $conversation, "create_page", "A", [], []);
		$b = $store->create($user, $conversation, "create_page", "B", [], []);
		$other = $store->create($user, 987656002, "create_page", "Other", [], []);

		try {
			$store->deleteForConversation($conversation);

			T::equals($store->loadOwned((string)$a["id"], $user), null, "deleted conversation's proposal is gone (approve would 404)");
			T::equals($store->loadOwned((string)$b["id"], $user), null, "all of the conversation's proposals are removed");
			T::ok($store->loadOwned((string)$other["id"], $user) !== null, "a proposal in another conversation is untouched");
		} finally {
			SQL::query("DELETE FROM " . ProposalStore::TABLE . " WHERE user = ?", 987656);
		}
	}
