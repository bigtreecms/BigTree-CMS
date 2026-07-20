<?php
	namespace BigTree\Services;

	use BigTree\Api\Request;
	use BigTree\Api\Response;
	use BigTree\Api\Pagination;
	use BigTree\Api\Exceptions\AuthorizationException;
	use BigTree\Api\Exceptions\BadRequestException;
	use BigTree\Api\Exceptions\NotFoundException;
	use BigTree\Services\AI\AIToolContext;
	use BigTree\Services\AI\AIToolResult;
	use BigTree\Services\AI\AgentLoop;
	use BigTree\Services\AI\AIToolRegistry;
	use BigTree\Services\AI\CapabilitySummary;
	use BigTree\Services\AI\ExtensionTools;
	use BigTree\Services\AI\PromptGuard;
	use BigTree\Services\AI\ProposalStore;
	use BigTree\Services\AI\Tools\CreatePageTool;
	use BigTree\Services\AI\Tools\GetMyCapabilitiesTool;
	use BigTree\Services\AI\Tools\GetPageTreeTool;
	use BigTree\Services\AI\Tools\UpdatePageTool;
	use BigTree\Services\AI\Tools\UpdatePageContentTool;
	use BigTree\Services\AI\Tools\ArchivePageTool;
	use BigTree\Services\AI\Tools\UnarchivePageTool;
	use BigTree\Services\AI\Tools\MovePageTool;
	use BigTree\Services\AI\Tools\SetModuleEntryFlagTool;
	use BigTree\Services\AI\Tools\DeleteModuleEntryTool;
	use BigTree\Services\AI\Tools\ListTemplatesTool;
	use BigTree\Services\AI\Tools\GetTemplateTool;
	use BigTree\Services\AI\Tools\GetModuleTool;
	use BigTree\Services\AI\Tools\GetModuleSchemaTool;
	use BigTree\Services\AI\Tools\CreateTemplateTool;
	use BigTree\Services\AI\Tools\UpdateTemplateTool;
	use BigTree\Services\AI\Tools\ListResourcesTool;
	use BigTree\Services\AI\Tools\SearchFilesTool;
	use BigTree\Services\AI\Tools\GetSettingsTool;
	use BigTree\Services\AI\Tools\UpdateSettingTool;
	use BigTree\Services\AI\Tools\GetPendingChangesTool;
	use BigTree\Services\AI\Tools\GetPendingChangeTool;
	use BigTree\Services\AI\Tools\PublishPendingChangeTool;
	use BigTree\Services\AI\Tools\CreateModuleEntryTool;
	use BigTree\Services\AI\Tools\UpdateModuleEntryTool;
	use BigTree\Services\AI\Tools\AddTagsTool;
	use BigTree\Services\AI\Tools\RemoveTagsTool;
	use BigTree\Services\AI\Tools\RejectPendingChangeTool;
	use BigTree\Services\AI\Tools\CreateUserTool;
	use BigTree\Services\AI\Tools\UpdateUserTool;
	use BigTree\Services\AI\Tools\CreateCalloutTool;
	use BigTree\Services\AI\Tools\GetCalloutTool;
	use BigTree\Services\AI\Tools\GetAuditTrailTool;
	use BigTree\Services\AI\Tools\GetPageRevisionsTool;
	use BigTree\Services\AI\Tools\RestorePageRevisionTool;
	use BigTree\Services\AI\Tools\UpdateCalloutTool;
	use BigTree\Services\AI\Tools\CreateModuleTool;
	use BigTree\Services\AI\Tools\UpdateModuleTool;
	use BigTreeAI;
	use BigTreeCMS;
	use SQL;

	/**
	 * The AI assistant chat endpoint (Phase 2 of the tool framework).
	 *
	 * A turn is: authenticate → gate on features.chat → throttle → resolve the
	 * user's conversation → replay its plain text history → drive the shared
	 * AgentLoop over the same read-only tool registry search uses → persist the
	 * user + assistant turns → return the answer, the tools it ran, and any
	 * navigable artifacts for the SPA to deep-link.
	 *
	 * Conversations are per-user: every read re-scopes on the acting user's id and
	 * a mismatch 404s rather than leaking that the row exists.
	 *
	 * Phase 3 adds two-phase mutating tools (create_page): a mutating tool never
	 * changes the CMS during a turn — it stages a proposal in the ProposalStore and
	 * returns its id. The change happens only when the user approves, at which point
	 * approveProposal re-checks permission and executes from the stored payload.
	 */
	class AIChatService {
		const CONVERSATIONS_TABLE = "bigtree_ai_conversations";
		const MESSAGES_TABLE = "bigtree_ai_messages";

		const MAX_MESSAGE_LENGTH = 4000;
		const TITLE_MAX = 120;
		const TOOL_LIMIT = 8;
		const MAX_ROUNDS = 5;
		// The default per-request provider timeout (mirrors ai.php's cURL default).
		// Used only to size the PHP execution budget for a full multi-round turn.
		const PROVIDER_TIMEOUT_SECONDS = 60;
		// A conversation is a bounded context window; cap turns so history replay
		// (and its token cost) can't grow without limit.
		const MAX_MESSAGES_PER_CONVERSATION = 100;

		// Per-user fixed-window throttle on the paid loop (own key, same shape as
		// SearchService::throttleAiSearch).
		const RATE_LIMIT = 20;
		const RATE_WINDOW = 60;
		const RATE_CACHE = "org.bigtreecms.ai-chat-rate";

		/** @var bool Memoized so the table-existence probe runs at most once per request. */
		private static $tables_ready = false;

		/**
		 * POST /ai/chat — one assistant turn (buffered).
		 * Body: { message: string, conversation_id?: int }
		 */
		public function chat(Request $request) {
			$this->extendExecutionBudget();
			$turn = $this->setupTurn($request);
			$loop = new AgentLoop($turn["ai"], $turn["registry"], self::MAX_ROUNDS);

			$artifacts = $this->emptyArtifacts();
			$proposals = [];

			$run = $loop->run($turn["messages"], $turn["context"], [
				"max_tokens" => 1024,
				"temperature" => 0.3,
				"final_max_tokens" => 700,
			], $this->turnCollector($artifacts, $proposals));

			if ($run["answer"] === null && $run["error"] !== null) {
				$this->rollbackFailedTurn($proposals, $turn["new_conversation"], $turn["conversation_id"]);

				throw new BadRequestException($run["error"], "ai_provider_error");
			}

			$persisted = $this->persistTurn($turn, $run, $proposals);

			return Response::ok([
				"conversation_id" => $turn["conversation_id"],
				"title" => (string)$turn["conversation"]["title"],
				"message" => [
					"id" => $persisted["assistant_id"],
					"role" => "assistant",
					"content" => $persisted["answer"],
					"tool_activity" => $run["tool_activity"],
					"proposals" => $proposals,
					"created_at" => $persisted["now"],
				],
				"artifacts" => $this->presentArtifacts($artifacts),
			], [
				"rounds" => $run["rounds"],
			]);
		}

		/**
		 * POST /ai/chat/stream — one assistant turn, streamed over Server-Sent Events.
		 * Body: { message: string, conversation_id?: int }
		 *
		 * Setup (feature gate, throttle, conversation resolution, message limits) runs
		 * first so a rejection is a normal JSON error. Once setup passes we switch the
		 * connection to text/event-stream and emit, in order:
		 *
		 *   event: token   { text }         — an answer token as it is generated
		 *   event: reset   {}               — discard streamed text (a tool round)
		 *   event: tool    { name, status } — a tool ran
		 *   event: done    { conversation_id, title, message, artifacts }
		 *   event: error   { message }      — provider failure (terminal)
		 *
		 * The `done` payload is authoritative: the client renders from it, so the token
		 * stream is only a live preview. This method emits then exits, bypassing the
		 * Kernel's JSON envelope (as Response::stream does for downloads).
		 */
		public function chatStream(Request $request) {
			$this->extendExecutionBudget();
			$turn = $this->setupTurn($request);

			$this->beginEventStream($request);

			// Emit the conversation id up front so a client that started a brand-new
			// thread can reconcile even if the connection drops before `done` — without
			// this it still has conversationId === null and a retry would fork a second
			// conversation, stranding the persisted turn. Sent before any tokens so it
			// never counts as "answer received" for the client's fallback decision.
			$this->sse("meta", [
				"conversation_id" => $turn["conversation_id"],
				"title" => (string)$turn["conversation"]["title"],
			]);

			$loop = new AgentLoop($turn["ai"], $turn["registry"], self::MAX_ROUNDS);
			$artifacts = $this->emptyArtifacts();
			$proposals = [];

			$run = $loop->runStreaming($turn["messages"], $turn["context"], [
				"max_tokens" => 1024,
				"temperature" => 0.3,
				"final_max_tokens" => 700,
			], function (array $event): void {
				$type = (string)($event["type"] ?? "");

				if ($type === "text") {
					$this->sse("token", ["text" => (string)($event["text"] ?? "")]);
				} elseif ($type === "reset") {
					$this->sse("reset", []);
				} elseif ($type === "tool") {
					$this->sse("tool", [
						"name" => (string)($event["name"] ?? ""),
						"status" => (string)($event["status"] ?? ""),
					]);
				}
			}, $this->turnCollector($artifacts, $proposals));

			if ($run["answer"] === null && $run["error"] !== null) {
				$this->rollbackFailedTurn($proposals, $turn["new_conversation"], $turn["conversation_id"]);
				$this->sse("error", ["message" => (string)$run["error"]]);

				exit;
			}

			$persisted = $this->persistTurn($turn, $run, $proposals);

			$this->sse("done", [
				"conversation_id" => $turn["conversation_id"],
				"title" => (string)$turn["conversation"]["title"],
				"message" => [
					"id" => $persisted["assistant_id"],
					"role" => "assistant",
					"content" => $persisted["answer"],
					"tool_activity" => $run["tool_activity"],
					"proposals" => $proposals,
					"created_at" => $persisted["now"],
				],
				"artifacts" => $this->presentArtifacts($artifacts),
				"rounds" => $run["rounds"],
			]);

			exit;
		}

		/**
		 * Raise the PHP execution time limit to cover a full multi-round turn. A turn
		 * can legitimately run every round to the provider timeout plus a final
		 * synthesis call, which exceeds a typical max_execution_time; without this a
		 * long-but-valid turn is killed mid-flight. No-op under CLI (limit already 0).
		 */
		private function extendExecutionBudget(): void {
			// Rounds + final call, each up to the provider timeout, plus slack for the
			// tool DB work between calls.
			$budget = (self::MAX_ROUNDS + 1) * self::PROVIDER_TIMEOUT_SECONDS + 30;

			set_time_limit($budget);
		}

		/**
		 * Shared per-turn setup for chat() and chatStream(): gate on features.chat,
		 * validate the message, throttle, resolve or create the (user-scoped)
		 * conversation, enforce the length cap, and assemble the model messages +
		 * per-user tool registry + context. Throws the same ApiExceptions both paths
		 * surface as JSON before any streaming begins.
		 *
		 * @return array<string,mixed>
		 */
		private function setupTurn(Request $request): array {
			$ai = new BigTreeAI();

			if (!$ai->isFeatureEnabled("chat")) {
				throw new BadRequestException(
					"The AI assistant is not enabled. Configure an AI service and turn on the assistant under Developer → Configure → AI.",
					"ai_chat_disabled"
				);
			}

			$message = trim((string)($request->body["message"] ?? ""));

			if ($message === "") {
				throw new BadRequestException("message required", "missing_message");
			}

			if (mb_strlen($message) > self::MAX_MESSAGE_LENGTH) {
				throw new BadRequestException("message is too long", "message_too_long");
			}

			$user = $request->user;

			// Guard the paid provider loop before any DB seed or API call.
			$this->throttleChat($user);
			$this->ensureReady();

			$conversation_id = (int)($request->body["conversation_id"] ?? 0);
			$conversation = $conversation_id > 0
				? $this->loadOwnedConversation($conversation_id, $user)
				: null;

			if ($conversation_id > 0 && $conversation === null) {
				throw new NotFoundException("Conversation not found", "conversation_not_found");
			}

			$history = $conversation ? $this->loadMessages((int)$conversation["id"]) : [];

			if (count($history) >= self::MAX_MESSAGES_PER_CONVERSATION) {
				throw new BadRequestException(
					"This conversation has reached its message limit — start a new one.",
					"conversation_full"
				);
			}

			// A conversation id is needed to scope any proposals a mutating tool stages,
			// so create the conversation up front rather than lazily after the turn. A
			// provider failure later rolls this back so no empty thread is left behind.
			$new_conversation = $conversation === null;

			if ($new_conversation) {
				$conversation = $this->createConversation($user, $message);
			}

			$conversation_id = (int)$conversation["id"];

			$store = new ProposalStore();

			return [
				"ai" => $ai,
				"user" => $user,
				"message" => $message,
				"conversation" => $conversation,
				"conversation_id" => $conversation_id,
				"new_conversation" => $new_conversation,
				"messages" => self::buildModelMessages($this->systemPrompt($user), $history, $message),
				"store" => $store,
				"registry" => $this->buildRegistry($store),
				"context" => new AIToolContext($user, self::TOOL_LIMIT, (string)$conversation_id),
			];
		}

		/**
		 * The AgentLoop tool-result callback shared by both turn methods: accumulate
		 * navigable artifacts and any staged proposals from each tool call.
		 *
		 * @param array<string,list<array<string,mixed>>> $artifacts
		 * @param list<array<string,mixed>> $proposals
		 */
		private function turnCollector(array &$artifacts, array &$proposals): callable {

			return function (AIToolResult $result, array $call) use (&$artifacts, &$proposals): void {
				$this->collectArtifacts($artifacts, $result->artifacts);

				if ($result->type === AIToolResult::PROPOSAL) {
					$proposals[] = [
						"proposal_id" => $result->proposal_id,
						"tool" => (string)($call["name"] ?? ""),
						"summary" => $result->summary,
						"preview" => $result->preview,
						"status" => ProposalStore::PENDING,
						"result" => null,
					];
				}
			};
		}

		/**
		 * Persist the user + assistant turns and touch the conversation. Returns the
		 * assistant message id, its (string) answer, and the shared timestamp.
		 *
		 * @param array<string,mixed> $turn From setupTurn().
		 * @param array<string,mixed> $run  From AgentLoop::run/runStreaming.
		 * @param list<array<string,mixed>> $proposals
		 * @return array{assistant_id:int,answer:string,now:string}
		 */
		private function persistTurn(array $turn, array $run, array $proposals): array {
			$answer = (string)($run["answer"] ?? "");
			$now = date("Y-m-d H:i:s");
			$conversation_id = (int)$turn["conversation_id"];

			$this->insertMessage($conversation_id, "user", (string)$turn["message"], null, null, $now);
			$assistant_id = $this->insertMessage(
				$conversation_id,
				"assistant",
				$answer,
				$run["tool_activity"],
				$proposals ? ["proposals" => array_column($proposals, "proposal_id")] : null,
				$now
			);
			$this->touchConversation($conversation_id, $now);

			return ["assistant_id" => $assistant_id, "answer" => $answer, "now" => $now];
		}

		/**
		 * Discard what a failed turn staged: any proposals from an earlier round, and
		 * a just-created empty thread (which mutating tools forced us to create up
		 * front). Keeps a provider failure from leaving orphaned rows behind.
		 *
		 * @param list<array<string,mixed>> $proposals
		 */
		private function rollbackFailedTurn(array $proposals, bool $new_conversation, int $conversation_id): void {
			foreach ($proposals as $proposal) {
				SQL::delete(ProposalStore::TABLE, (string)$proposal["proposal_id"]);
			}

			if ($new_conversation) {
				SQL::delete(self::CONVERSATIONS_TABLE, $conversation_id);
			}
		}

		// — SSE transport —

		/**
		 * Switch the connection to an SSE stream: flush any output buffering, send the
		 * event-stream headers (plus the CORS headers the bypassed Kernel tail would
		 * otherwise add), and disable proxy buffering so tokens reach the client live.
		 */
		private function beginEventStream(Request $request): void {
			while (ob_get_level() > 0) {
				@ob_end_flush();
			}

			@ini_set("zlib.output_compression", "0");

			if (!headers_sent()) {
				header("Content-Type: text/event-stream; charset=utf-8");
				header("Cache-Control: no-cache, no-transform");
				header("Connection: keep-alive");
				// Defeat nginx/FastCGI response buffering for this stream.
				header("X-Accel-Buffering: no");

				// We emit and exit, bypassing the Kernel's Response::send tail — so the
				// CORS headers the Cors middleware would normally append must be sent
				// here for cross-origin (fetch-based) SSE to reach the browser.
				foreach (\BigTree\Api\Middleware\Cors::headersFor($request) as $name => $value) {
					header($name . ": " . $value);
				}
			}
		}

		/**
		 * Emit one SSE event and flush it to the wire.
		 *
		 * @param array<string,mixed> $data
		 */
		private function sse(string $event, array $data): void {
			echo "event: " . $event . "\n";
			echo "data: " . json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n";

			if (ob_get_level() > 0) {
				@ob_flush();
			}

			flush();
		}

		/**
		 * The per-user toolset for a chat turn: the read tools search shares, the Phase
		 * 4 read tools (page tree, templates, media, settings, pending changes,
		 * capabilities), and every two-phase mutating tool (pages, module entries, tags,
		 * settings, users) plus the developer tools (templates, callouts, modules). The
		 * registry filters by user (admin/developer tools are hidden from lower levels)
		 * and every executor re-checks server-side, so registering a tool here never
		 * bypasses permission.
		 */
		private function buildRegistry(ProposalStore $store): AIToolRegistry {
			$registry = (new SearchService())->buildAiSearchRegistry(EmbeddingService::isEnabled());

			$pages = new PageService();
			$templates = new TemplateService();
			$resources = new ResourceService();
			$settings = new SettingService();
			$pending = new PendingChangeService();
			$entries = new AutoModuleService();
			$tags = new TagService();
			$users = new UserService();
			$callouts = new CalloutService();
			$modules = new ModuleService();

			// Read tools (offered per level; object-scoped access re-checked in each backend).
			$registry->register(new GetMyCapabilitiesTool());
			$registry->register(new GetPageTreeTool($pages));
			$registry->register(new ListTemplatesTool($templates));
			$registry->register(new GetTemplateTool($templates));
			$registry->register(new ListResourcesTool($resources));
			$registry->register(new SearchFilesTool($resources));
			$registry->register(new GetSettingsTool($settings));
			$registry->register(new GetPendingChangesTool($pending));
			$registry->register(new GetModuleTool($modules));
			$registry->register(new GetModuleSchemaTool($entries));
			$registry->register(new GetPendingChangeTool($pending));
			$registry->register(new GetCalloutTool($callouts));
			$registry->register(new GetAuditTrailTool(new AuditService()));
			$registry->register(new GetPageRevisionsTool($pages));

			// Two-phase mutating tools.
			$registry->register(new CreatePageTool($pages, $store));
			$registry->register(new UpdatePageTool($pages, $store));
			$registry->register(new UpdatePageContentTool($pages, $store));
			$registry->register(new ArchivePageTool($pages, $store));
			$registry->register(new UnarchivePageTool($pages, $store));
			$registry->register(new MovePageTool($pages, $store));
			$registry->register(new RestorePageRevisionTool($pages, $store));
			$registry->register(new CreateModuleEntryTool($entries, $store));
			$registry->register(new UpdateModuleEntryTool($entries, $store));
			$registry->register(new SetModuleEntryFlagTool($entries, $store));
			$registry->register(new DeleteModuleEntryTool($entries, $store));
			$registry->register(new PublishPendingChangeTool($pending, $store));
			$registry->register(new RejectPendingChangeTool($pending, $store));
			$registry->register(new AddTagsTool($tags, $store));
			$registry->register(new RemoveTagsTool($tags, $store));
			$registry->register(new UpdateSettingTool($settings, $store));
			$registry->register(new CreateUserTool($users, $store));
			$registry->register(new UpdateUserTool($users, $store));

			// Developer-only tools (hidden from non-developers' registry).
			$registry->register(new CreateTemplateTool($templates, $store));
			$registry->register(new UpdateTemplateTool($templates, $store));
			$registry->register(new CreateCalloutTool($callouts, $store));
			$registry->register(new UpdateCalloutTool($callouts, $store));
			$registry->register(new CreateModuleTool($modules, $store));
			$registry->register(new UpdateModuleTool($modules, $store));

			// Extension-registered tools (mirror how extensions plug into routes).
			// Core names always win; each still filters per user and re-checks on
			// execute, so an extension adds capability but never bypasses permission.
			ExtensionTools::registerInto($registry, $store);

			return $registry;
		}

		/**
		 * GET /ai/conversations — the acting user's conversations, most-recent first.
		 */
		public function listConversations(Request $request) {
			$this->ensureReady();
			$user_id = (int)($request->user->id ?? 0);

			return Pagination::paginate(
				$request,
				"SELECT COUNT(*) FROM " . self::CONVERSATIONS_TABLE . " WHERE `user` = ?",
				"SELECT id, title, created_at, updated_at FROM " . self::CONVERSATIONS_TABLE
					. " WHERE `user` = ? ORDER BY updated_at DESC",
				[$user_id],
				function ($row) {

					return $this->presentConversation($row);
				},
				25
			);
		}

		/**
		 * GET /ai/conversations/{id} — a conversation and its messages (owner only).
		 */
		public function getConversation(Request $request) {
			$this->ensureReady();
			$conversation = $this->loadOwnedConversation($request->id(), $request->user);

			if ($conversation === null) {
				throw new NotFoundException("Conversation not found", "conversation_not_found");
			}

			// Proposals keep mutable status (pending → approved/rejected/expired), so
			// resolve them live from the store and attach to the message that staged
			// them rather than snapshotting into the message row.
			$proposals_by_id = [];

			foreach ((new ProposalStore())->listForConversation((int)$conversation["id"]) as $proposal) {
				$proposals_by_id[$proposal["proposal_id"]] = $proposal;
			}

			$messages = array_map(function ($row) use ($proposals_by_id) {

				return $this->presentMessage($row, $proposals_by_id);
			}, $this->loadMessages((int)$conversation["id"]));

			return Response::ok([
				"conversation" => $this->presentConversation($conversation),
				"messages" => $messages,
			]);
		}

		/**
		 * DELETE /ai/conversations/{id} — remove a conversation and its messages
		 * (owner only).
		 */
		public function deleteConversation(Request $request) {
			$this->ensureReady();
			$conversation = $this->loadOwnedConversation($request->id(), $request->user);

			if ($conversation === null) {
				throw new NotFoundException("Conversation not found", "conversation_not_found");
			}

			SQL::query("DELETE FROM " . self::MESSAGES_TABLE . " WHERE conversation = ?", (int)$conversation["id"]);
			SQL::delete(self::CONVERSATIONS_TABLE, (int)$conversation["id"]);

			// Drop the conversation's staged proposals too — a pending one must not
			// survive the deletion of its entire context and stay approvable, and
			// resolved outcomes already live in the audit trail.
			(new ProposalStore())->deleteForConversation((int)$conversation["id"]);

			return Response::noContent();
		}

		/**
		 * POST /ai/proposals/{id}/approve — execute a staged mutation.
		 *
		 * The write runs from the stored, validated payload (never anything the model
		 * round-tripped) and the acting service re-checks permission at this moment, so
		 * a rank revoked between staging and approval fails with a 403 and the proposal
		 * stays pending. A successful approval records the outcome and returns it.
		 */
		public function approveProposal(Request $request) {
			$this->ensureReady();

			$store = new ProposalStore();
			$id = (string)$request->routeParam("id");
			$proposal = $store->loadOwned($id, $request->user);

			if ($proposal === null) {
				throw new NotFoundException("Proposal not found", "proposal_not_found");
			}

			$this->assertPending($proposal);

			// Claim the proposal before executing so two concurrent approvals can't both
			// pass the pending check and run the mutation twice. A lost race means it was
			// already resolved (or rejected) out from under us.
			if (!$store->claimPending($id)) {
				throw new BadRequestException("This proposal has already been resolved.", "proposal_resolved");
			}

			$payload = $store->decodePayload($proposal);

			try {
				$result = $this->executeProposal((string)$proposal["tool"], $payload, $request->user);
			} catch (\Throwable $e) {
				// A revoked permission (403) or any execution failure leaves the proposal
				// approvable again rather than stranded mid-claim.
				$store->restorePending($id);

				throw $e;
			}

			$store->markResolved($id, ProposalStore::APPROVED, $result);

			// Record the change in the audit trail, marked as originating from the AI
			// assistant so the audit UI can distinguish and filter it. Best-effort:
			// an audit failure must not fail an already-applied change.
			$this->recordApprovalAudit($request, (string)$proposal["tool"], $payload, $result);

			return Response::ok([
				"proposal" => $store->present($store->loadOwned($id, $request->user)),
			]);
		}

		/**
		 * POST /ai/proposals/{id}/reject — discard a staged mutation without running it.
		 */
		public function rejectProposal(Request $request) {
			$this->ensureReady();

			$store = new ProposalStore();
			$id = (string)$request->routeParam("id");
			$proposal = $store->loadOwned($id, $request->user);

			if ($proposal === null) {
				throw new NotFoundException("Proposal not found", "proposal_not_found");
			}

			$this->assertPending($proposal);

			// Same compare-and-set as approve so an approve/reject race resolves once.
			if (!$store->claimPending($id, ProposalStore::REJECTED)) {
				throw new BadRequestException("This proposal has already been resolved.", "proposal_resolved");
			}

			return Response::ok([
				"proposal" => $store->present($store->loadOwned($id, $request->user)),
			]);
		}

		/**
		 * Guard that a proposal is still actionable; a resolved or expired one can't be
		 * approved or rejected again.
		 *
		 * @param array<string,mixed> $proposal
		 */
		private function assertPending(array $proposal): void {
			$status = (string)($proposal["status"] ?? "");

			if ($status === ProposalStore::PENDING) {

				return;
			}

			if ($status === ProposalStore::EXPIRED) {

				throw new BadRequestException("This proposal has expired — ask the assistant again.", "proposal_expired");
			}

			throw new BadRequestException("This proposal has already been " . $status . ".", "proposal_resolved");
		}

		/**
		 * Dispatch an approved proposal to the service that performs the real write.
		 * Each branch re-checks permission internally (that is the whole point of the
		 * two-phase flow), so this method never trusts the stored payload's authority.
		 *
		 * @param array<string,mixed> $payload
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		private function executeProposal(string $tool, array $payload, $user): array {
			switch ($tool) {
				case "create_page":
					return (new PageService())->aiCreatePage($payload, $user);

				case "update_page":
					return (new PageService())->aiUpdatePage($payload, $user);

				case "update_page_content":
					return (new PageService())->aiUpdatePageContent($payload, $user);

				case "archive_page":
					return (new PageService())->aiArchivePage($payload, $user);

				case "unarchive_page":
					return (new PageService())->aiUnarchivePage($payload, $user);

				case "move_page":
					return (new PageService())->aiMovePage($payload, $user);

				case "restore_page_revision":
					return (new PageService())->aiRestoreRevision($payload, $user);

				case "create_module_entry":
					return (new AutoModuleService())->aiCreateEntry($payload, $user);

				case "update_module_entry":
					return (new AutoModuleService())->aiUpdateEntry($payload, $user);

				case "set_module_entry_flag":
					return (new AutoModuleService())->aiSetEntryFlag($payload, $user);

				case "delete_module_entry":
					return (new AutoModuleService())->aiDeleteEntry($payload, $user);

				case "publish_pending_change":
					return (new PendingChangeService())->aiPublishChange($payload, $user);

				case "reject_pending_change":
					return (new PendingChangeService())->aiRejectChange($payload, $user);

				case "add_tags":
					return (new TagService())->aiAddTags($payload, $user);

				case "remove_tags":
					return (new TagService())->aiRemoveTags($payload, $user);

				case "update_setting":
					return (new SettingService())->aiUpdateSetting($payload, $user);

				case "create_user":
					return (new UserService())->aiCreateUser($payload, $user);

				case "update_user":
					return (new UserService())->aiUpdateUser($payload, $user);

				case "create_template":
					return (new TemplateService())->aiCreateTemplate($payload, $user);

				case "update_template":
					return (new TemplateService())->aiUpdateTemplate($payload, $user);

				case "create_callout":
					return (new CalloutService())->aiCreateCallout($payload, $user);

				case "update_callout":
					return (new CalloutService())->aiUpdateCallout($payload, $user);

				case "create_module":
					return (new ModuleService())->aiCreateModule($payload, $user);

				case "update_module":
					return (new ModuleService())->aiUpdateModule($payload, $user);

				default:

					return $this->executeExtensionProposal($tool, $payload, $user);
			}
		}

		/**
		 * Dispatch an approved proposal that a core tool doesn't own to the extension
		 * tool that staged it. The tool is resolved from the same registry the turn
		 * used and must implement Tools\ApprovableTool; executeApproved re-checks
		 * permission from the stored payload. An unknown tool 400s rather than
		 * silently no-op'ing.
		 *
		 * @param array<string,mixed> $payload
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		private function executeExtensionProposal(string $tool, array $payload, $user): array {
			$instance = $this->buildRegistry(new ProposalStore())->get($tool);

			return self::dispatchApprovable($instance, $payload, $user);
		}

		/**
		 * Dispatch a resolved extension tool for an approved proposal, applying the
		 * coarse availability gate first. Static + public so the approval gate can be
		 * exercised with a fixture tool without standing up the full registry.
		 *
		 * @param mixed $instance The registry-resolved tool (or null for an unknown one).
		 * @param array<string,mixed> $payload
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public static function dispatchApprovable($instance, array $payload, $user): array {
			if ($instance instanceof \BigTree\Services\AI\Tools\ApprovableTool) {
				// Defense-in-depth: mirror AIToolRegistry::execute's turn-path gate so an
				// extension that forgets its object-scoped re-check still can't hand a
				// level-0 editor a one-click write after their access was revoked. This is
				// the coarse level gate, not a substitute for the documented per-object
				// re-check inside executeApproved.
				if (!$instance->isAvailable($user)) {
					throw new AuthorizationException("You no longer have access to this action.");
				}

				return $instance->executeApproved($payload, $user);
			}

			throw new BadRequestException("Unknown proposal type.", "unknown_proposal");
		}

		/**
		 * Write an audit-trail entry for an approved proposal, tagged via=ai_assistant
		 * so the audit UI can surface AI-originated changes. Best-effort: swallow any
		 * failure so an audit hiccup can't fail a change that already succeeded.
		 *
		 * @param array<string,mixed> $payload The stored payload the change ran from.
		 * @param array<string,mixed> $result  The tool's outcome (mode + ids).
		 */
		private function recordApprovalAudit(Request $request, string $tool, array $payload, array $result): void {
			$descriptor = self::auditDescriptor($tool, $payload, $result);

			if ($descriptor === null) {

				return;
			}

			try {
				AuditService::write(
					$descriptor["table"],
					$descriptor["entry"],
					$descriptor["type"],
					(int)$this->userId($request->user),
					[
						"ip" => $request->ip,
						"user_agent" => substr((string)$request->user_agent, 0, 255),
						"request_id" => $request->request_id,
						"method" => $request->method,
						"path" => substr((string)$request->path, 0, 255),
						"via" => "ai_assistant",
					]
				);
			} catch (\Throwable $e) {
				@error_log("[BigTree AI] audit write for approved proposal failed: " . $e->getMessage());
			}
		}

		/**
		 * Map an approved core tool + its outcome to the audit facts (table, type,
		 * entry). Returns null to skip auditing: a mode="error" outcome (the change
		 * didn't happen) or an extension tool (default) with no core descriptor — an
		 * extension writes its own audit inside executeApproved if it wants one.
		 * Static + pure so it can be unit-tested without a request.
		 *
		 * @param array<string,mixed> $payload
		 * @param array<string,mixed> $result
		 * @return array{table:string,type:string,entry:string}|null
		 */
		public static function auditDescriptor(string $tool, array $payload, array $result): ?array {
			$mode = (string)($result["mode"] ?? "");

			if ($mode === "error") {

				return null;
			}

			// A pending (non-publisher) write is a proposed change, not a live one;
			// mark the type so the audit row reflects that.
			$pending = $mode === "pending";

			switch ($tool) {
				case "create_page":
					return self::descriptor("bigtree_pages", $pending ? "pending-created" : "created", $result["page_id"] ?? $result["pending_change_id"] ?? "");

				case "update_page":
				case "update_page_content":
					return self::descriptor("bigtree_pages", $pending ? "pending-updated" : "updated", $result["page_id"] ?? "");

				case "archive_page":
					return self::descriptor("bigtree_pages", "archived", $result["page_id"] ?? "");

				case "unarchive_page":
					return self::descriptor("bigtree_pages", "unarchived", $result["page_id"] ?? "");

				case "move_page":
					return self::descriptor("bigtree_pages", "moved", $result["page_id"] ?? "");

				case "restore_page_revision":
					return self::descriptor("bigtree_pages", "revision-restored", $result["page_id"] ?? "");

				case "create_module_entry":
					return self::descriptor((string)($payload["table"] ?? ""), $pending ? "pending-created" : "created", $result["entry_id"] ?? "");

				case "update_module_entry":
					return self::descriptor((string)($payload["table"] ?? ""), $pending ? "pending-updated" : "updated", $result["entry_id"] ?? "");

				case "set_module_entry_flag":
					return self::descriptor((string)($payload["table"] ?? ""), "updated", $result["entry_id"] ?? $payload["entry_id"] ?? "");

				case "delete_module_entry":
					return self::descriptor((string)($payload["table"] ?? ""), "deleted", $result["entry_id"] ?? $payload["entry_id"] ?? "");

				case "publish_pending_change":
					return self::descriptor("bigtree_pending_changes", "published", $payload["change_id"] ?? "");

				case "reject_pending_change":
					return self::descriptor("bigtree_pending_changes", "rejected", $result["change_id"] ?? $payload["change_id"] ?? "");

				// Tag tools carry their own target table: tagging a module entry must
				// audit against that module's table, not bigtree_pages.
				case "add_tags":
					return self::descriptor((string)($result["table"] ?? ""), "tagged", $result["entry_id"] ?? "");

				case "remove_tags":
					return self::descriptor((string)($result["table"] ?? ""), "untagged", $result["entry_id"] ?? "");

				case "update_setting":
					return self::descriptor("bigtree_settings", "updated", $result["id"] ?? $payload["id"] ?? "");

				case "create_user":
					return self::descriptor("bigtree_users", "created", $result["user_id"] ?? "");

				case "update_user":
					return self::descriptor("bigtree_users", "updated", $result["user_id"] ?? $payload["user_id"] ?? "");

				case "create_template":
					return self::descriptor("bigtree_templates", "created", $result["id"] ?? $payload["id"] ?? "");

				case "update_template":
					return self::descriptor("bigtree_templates", "updated", $result["id"] ?? $payload["id"] ?? "");

				case "create_callout":
					return self::descriptor("bigtree_callouts", "created", $result["id"] ?? $payload["id"] ?? "");

				case "update_callout":
					return self::descriptor("bigtree_callouts", "updated", $result["id"] ?? $payload["id"] ?? "");

				case "create_module":
					return self::descriptor("bigtree_modules", "created", $result["id"] ?? "");

				case "update_module":
					return self::descriptor("bigtree_modules", "updated", $result["id"] ?? $payload["id"] ?? "");

				default:

					return null;
			}
		}

		/**
		 * @param mixed $entry
		 * @return array{table:string,type:string,entry:string}|null Null when the table is unknown.
		 */
		private static function descriptor(string $table, string $type, $entry): ?array {
			if ($table === "") {

				return null;
			}

			return ["table" => $table, "type" => $type, "entry" => (string)$entry];
		}

		// — model context —

		/**
		 * Rebuild the model's message array: the system prompt, the conversation's
		 * plain user/assistant turns (tool call/result turns are NOT replayed —
		 * they are heavy and reference data that may be stale), then the new turn.
		 * Pure so it can be unit-tested without a DB.
		 *
		 * @param list<array<string,mixed>> $history_rows
		 * @return list<array<string,mixed>>
		 */
		public static function buildModelMessages(string $system_prompt, array $history_rows, string $new_message): array {
			$messages = [[
				"role" => "system",
				"content" => $system_prompt,
			]];

			foreach ($history_rows as $row) {
				$role = (string)($row["role"] ?? "");
				$content = (string)($row["content"] ?? "");

				if (($role === "user" || $role === "assistant") && $content !== "") {
					$messages[] = [
						"role" => $role,
						"content" => $content,
					];
				}
			}

			$messages[] = [
				"role" => "user",
				"content" => $new_message,
			];

			return $messages;
		}

		/**
		 * Identity + capability-aware system prompt. Public so tests can assert its
		 * contents without driving a full request.
		 *
		 * @param object|array $user
		 */
		public function systemPrompt($user): string {
			$site_title = $this->siteTitle();
			$lines = [];
			$lines[] = "You are the BigTree CMS assistant, embedded in the admin of \"{$site_title}\".";
			$lines[] = "You help the signed-in user work with their CMS: finding pages, modules, module entries, tags, and users, and answering questions about the site's content.";
			$lines[] = "";
			$lines[] = CapabilitySummary::promptText($user);
			$lines[] = "";
			$lines[] = "How to work:";
			$lines[] = "- Use the provided tools to look things up rather than guessing. Never invent ids, titles, paths, or counts.";
			$lines[] = "- When calling search tools, pass 1–3 short keywords, not a whole sentence.";
			$lines[] = "- Prefer looking up the exact item before proposing a change to it: use get_page_tree, get_page, get_template, list_templates, get_settings, get_pending_changes, and the search tools to find real ids and current values first.";
			$lines[] = "- Answer briefly and in plain language, referring to items by their real titles.";
			$lines[] = "- If you cannot find something after searching, say so and suggest how to refine the request.";
			$lines[] = "";
			$lines[] = "Making changes:";
			$lines[] = "- You cannot change anything directly. Tools that modify the CMS (create/update/archive pages, module entries, tags, settings, users, and developer resources) only PROPOSE a change: the user sees a confirmation card and must approve it before anything happens.";
			$lines[] = "- After calling a mutating tool, never say the change is done. Say you have prepared it and ask the user to review and approve the card. If the tool returns needs_input, ask the user the question it provides; if it returns an error listing the fields it needs, gather them and try again.";
			$lines[] = "- Only propose a change the user actually asked for. Do not invent pages, titles, field values, or other content.";
			$lines[] = "- If a tool is denied, explain the limit plainly and offer the path that would work (a pending draft, or asking someone with the right access) instead of retrying.";
			$lines[] = "";
			$lines[] = "Out of scope — explain, don't attempt:";
			$lines[] = "These are things you cannot do at any permission level, no matter the user's role. If asked, say plainly that you can't do it and point to where in the admin it's done. Do not invent a tool, improvise a workaround, or use an unrelated tool to approximate it.";

			foreach (CapabilitySummary::outOfScopeLines() as $line) {
				$lines[] = $line;
			}

			$lines[] = "";

			foreach (PromptGuard::safetyRules() as $rule) {
				$lines[] = $rule;
			}

			return implode("\n", $lines);
		}

		private function siteTitle(): string {
			global $bigtree;

			$title = trim((string)($bigtree["config"]["title"] ?? ""));

			return $title !== "" ? $title : "this site";
		}

		// — persistence —

		/**
		 * Load a conversation only if it belongs to $user; a non-owner (or missing
		 * row) returns null so the caller 404s without leaking existence.
		 *
		 * @param object|array $user
		 * @return array<string,mixed>|null
		 */
		private function loadOwnedConversation(int $id, $user): ?array {
			if ($id < 1) {

				return null;
			}

			$row = SQL::fetch(
				"SELECT id, `user`, title, created_at, updated_at FROM " . self::CONVERSATIONS_TABLE . " WHERE id = ?",
				$id
			);

			if (!$row) {

				return null;
			}

			if ((int)$row["user"] !== (int)($this->userId($user))) {

				return null;
			}

			return $row;
		}

		/**
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		private function createConversation($user, string $first_message): array {
			$now = date("Y-m-d H:i:s");
			$title = $this->deriveTitle($first_message);
			$id = (int)SQL::insert(self::CONVERSATIONS_TABLE, [
				"user" => (int)$this->userId($user),
				"title" => $title,
				"created_at" => $now,
				"updated_at" => $now,
			]);

			return [
				"id" => $id,
				"user" => (int)$this->userId($user),
				"title" => $title,
				"created_at" => $now,
				"updated_at" => $now,
			];
		}

		/**
		 * @return list<array<string,mixed>>
		 */
		private function loadMessages(int $conversation_id): array {

			return SQL::fetchAll(
				"SELECT id, role, content, tool_calls, tool_results, created_at FROM " . self::MESSAGES_TABLE
					. " WHERE conversation = ? ORDER BY id ASC",
				$conversation_id
			);
		}

		/**
		 * @param list<array<string,mixed>>|null $tool_calls Tool-activity rows for the SPA.
		 * @param array<string,mixed>|null $tool_results Side-channel JSON (e.g. staged proposal ids).
		 */
		private function insertMessage(int $conversation_id, string $role, string $content, ?array $tool_calls, ?array $tool_results, string $at): int {

			return (int)SQL::insert(self::MESSAGES_TABLE, [
				"conversation" => $conversation_id,
				"role" => $role,
				"content" => $content,
				"tool_calls" => $tool_calls !== null ? json_encode($tool_calls) : null,
				"tool_results" => $tool_results !== null ? json_encode($tool_results) : null,
				"created_at" => $at,
			]);
		}

		private function touchConversation(int $id, string $at): void {
			SQL::update(self::CONVERSATIONS_TABLE, $id, ["updated_at" => $at]);
		}

		private function deriveTitle(string $message): string {
			$title = trim(preg_replace('/\s+/u', " ", $message) ?? $message);

			if (mb_strlen($title) > self::TITLE_MAX) {
				$title = mb_substr($title, 0, self::TITLE_MAX - 1) . "…";
			}

			return $title;
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

		// — presenters —

		/**
		 * @param array<string,mixed> $row
		 * @return array<string,mixed>
		 */
		private function presentConversation(array $row): array {

			return [
				"id" => (int)$row["id"],
				"title" => (string)($row["title"] ?? ""),
				"created_at" => $row["created_at"] ?? null,
				"updated_at" => $row["updated_at"] ?? null,
			];
		}

		/**
		 * @param array<string,mixed> $row
		 * @param array<string,array<string,mixed>> $proposals_by_id proposal_id => presented proposal
		 * @return array<string,mixed>
		 */
		private function presentMessage(array $row, array $proposals_by_id = []): array {
			$activity = [];

			if (!empty($row["tool_calls"])) {
				$decoded = json_decode((string)$row["tool_calls"], true);
				$activity = is_array($decoded) ? $decoded : [];
			}

			$proposals = [];

			if (!empty($row["tool_results"])) {
				$decoded = json_decode((string)$row["tool_results"], true);

				foreach ($decoded["proposals"] ?? [] as $proposal_id) {
					if (isset($proposals_by_id[(string)$proposal_id])) {
						$proposals[] = $proposals_by_id[(string)$proposal_id];
					}
				}
			}

			return [
				"id" => (int)$row["id"],
				"role" => (string)($row["role"] ?? ""),
				"content" => (string)($row["content"] ?? ""),
				"tool_activity" => $activity,
				"proposals" => $proposals,
				"created_at" => $row["created_at"] ?? null,
			];
		}

		// — artifacts —

		/**
		 * @return array<string,list<array<string,mixed>>>
		 */
		private function emptyArtifacts(): array {

			return [
				"pages" => [],
				"modules" => [],
				"entries" => [],
				"tags" => [],
				"users" => [],
			];
		}

		/**
		 * Merge a tool result's navigable artifacts into the accumulator, deduping
		 * flat groups by id and entry groups by module + item id. Bounded N (a few
		 * tool calls, each capped), so linear scans are fine.
		 *
		 * @param array<string,list<array<string,mixed>>> $collected
		 * @param array<string,list<array<string,mixed>>> $artifacts
		 */
		private function collectArtifacts(array &$collected, array $artifacts): void {
			foreach (["pages", "modules", "tags", "users"] as $group) {
				foreach ($artifacts[$group] ?? [] as $row) {
					if (!is_array($row)) {

						continue;
					}

					$id = (string)($row["id"] ?? "");

					if ($id === "" || $this->hasId($collected[$group], $id)) {

						continue;
					}

					$collected[$group][] = $row;
				}
			}

			foreach ($artifacts["entries"] ?? [] as $group) {
				$this->mergeEntryGroup($collected["entries"], $group);
			}
		}

		/**
		 * @param list<array<string,mixed>> $rows
		 */
		private function hasId(array $rows, string $id): bool {
			foreach ($rows as $row) {
				if (is_array($row) && (string)($row["id"] ?? "") === $id) {

					return true;
				}
			}

			return false;
		}

		/**
		 * @param list<array<string,mixed>> $entries
		 * @param mixed $group
		 */
		private function mergeEntryGroup(array &$entries, $group): void {
			if (!is_array($group)) {

				return;
			}

			// Entry groups are associative; normalize to a string-keyed map so the
			// merged value matches the $entries element type when it's appended below.
			$normalized = [];

			foreach ($group as $key => $value) {
				$normalized[(string)$key] = $value;
			}

			$group = $normalized;
			$mid = (string)($group["module"]["id"] ?? "");

			if ($mid === "") {

				return;
			}

			foreach ($entries as $i => $existing) {
				if ((string)($existing["module"]["id"] ?? "") !== $mid) {

					continue;
				}

				foreach ($group["items"] ?? [] as $item) {
					$iid = (string)($item["id"] ?? "");

					if ($iid === "" || $this->hasId($entries[$i]["items"] ?? [], $iid)) {

						continue;
					}

					$entries[$i]["items"][] = $item;
				}

				return;
			}

			$entries[] = $group;
		}

		/**
		 * Strip internal ranking fields off collected artifacts before the wire.
		 *
		 * @param array<string,list<array<string,mixed>>> $collected
		 * @return array<string,list<array<string,mixed>>>
		 */
		private function presentArtifacts(array $collected): array {
			$out = [];

			foreach ($collected as $group => $rows) {
				$clean = [];

				foreach ($rows as $row) {
					if (is_array($row)) {
						unset($row["_score"], $row["_distance"]);

						if (isset($row["items"]) && is_array($row["items"])) {
							$row["items"] = array_map(function ($item) {
								if (is_array($item)) {
									unset($item["_score"], $item["_distance"]);
								}

								return $item;
							}, $row["items"]);
						}
					}

					$clean[] = $row;
				}

				$out[$group] = $clean;
			}

			return $out;
		}

		// — throttle + schema —

		/**
		 * Per-user fixed-window rate limit on the paid loop.
		 *
		 * @param object|array $user
		 * @throws BadRequestException when the window's allowance is exhausted.
		 */
		private function throttleChat($user): void {
			$user_id = (int)$this->userId($user);

			if ($user_id < 1) {

				return;
			}

			$key = (string)$user_id;
			$now = time();
			$record = BigTreeCMS::cacheGet(self::RATE_CACHE, $key);
			$window_start = is_array($record) ? (int)($record["window_start"] ?? 0) : 0;
			$count = is_array($record) ? (int)($record["count"] ?? 0) : 0;

			if ($now - $window_start >= self::RATE_WINDOW) {
				$window_start = $now;
				$count = 0;
			}

			if ($count >= self::RATE_LIMIT) {

				throw new BadRequestException(
					"Too many messages — try again shortly.",
					"ai_rate_limited"
				);
			}

			BigTreeCMS::cachePut(self::RATE_CACHE, $key, [
				"window_start" => $window_start,
				"count" => $count + 1,
			]);
		}

		/**
		 * Create the chat tables if missing (memoized per request). Canonical DDL
		 * lives here so revision 508 and a first live turn agree on the schema.
		 */
		private function ensureReady(): void {
			if (self::$tables_ready) {

				return;
			}

			if (!SQL::tableExists(self::CONVERSATIONS_TABLE) || !SQL::tableExists(self::MESSAGES_TABLE)) {
				self::ensureTables();
			}

			if (!SQL::tableExists(ProposalStore::TABLE)) {
				ProposalStore::ensureTables();
			}

			self::$tables_ready = true;
		}

		/**
		 * Canonical CREATE TABLE for the conversation + message store. Idempotent
		 * (CREATE TABLE IF NOT EXISTS); called by revision 508 and ensureReady().
		 */
		public static function ensureTables(): void {
			SQL::query(
				"CREATE TABLE IF NOT EXISTS `" . self::CONVERSATIONS_TABLE . "` (
					`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
					`user` INT UNSIGNED NOT NULL DEFAULT 0,
					`title` VARCHAR(255) NOT NULL DEFAULT '',
					`created_at` DATETIME NOT NULL,
					`updated_at` DATETIME NOT NULL,
					PRIMARY KEY (`id`),
					KEY `user_updated` (`user`, `updated_at`)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
			);

			SQL::query(
				"CREATE TABLE IF NOT EXISTS `" . self::MESSAGES_TABLE . "` (
					`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
					`conversation` BIGINT UNSIGNED NOT NULL,
					`role` VARCHAR(16) NOT NULL,
					`content` MEDIUMTEXT NOT NULL,
					`tool_calls` MEDIUMTEXT NULL,
					`tool_results` MEDIUMTEXT NULL,
					`created_at` DATETIME NOT NULL,
					PRIMARY KEY (`id`),
					KEY `conversation` (`conversation`, `id`)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
			);
		}
	}
