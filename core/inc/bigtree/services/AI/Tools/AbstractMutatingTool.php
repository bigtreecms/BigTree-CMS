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

			$conversation_id = (int)($context->conversation_id !== "" ? $context->conversation_id : 0);
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

			$proposal = $this->proposals->create($context->user, $conversation_id, $tool, $summary, $preview, $payload);

			return AIToolResult::proposal($summary, $preview, (string)$proposal["id"]);
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
