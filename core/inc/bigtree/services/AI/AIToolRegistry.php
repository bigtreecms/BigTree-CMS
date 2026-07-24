<?php
	namespace BigTree\Services\AI;

	/**
	 * Assembles the per-user toolset for an agent loop. Two jobs:
	 *
	 *   - definitions(): the function schemas offered to the model, filtered to the
	 *     tools this user may actually use.
	 *   - execute(): dispatches a model tool call, re-checking availability first so
	 *     a hallucinated or since-revoked capability is denied server-side.
	 *
	 * Registration is open so future drivers (chat) and extensions can add tools
	 * without touching the core loop.
	 */
	class AIToolRegistry {
		/** @var array<string,AIToolInterface> name => tool */
		private $tools = [];

		public function register(AIToolInterface $tool): void {
			$this->tools[$tool->name()] = $tool;
		}

		public function has(string $name): bool {

			return isset($this->tools[$name]);
		}

		public function get(string $name): ?AIToolInterface {

			return $this->tools[$name] ?? null;
		}

		/**
		 * Tools this user may use, in registration order.
		 *
		 * @param object|array $user
		 * @return list<AIToolInterface>
		 */
		public function availableTools($user): array {
			$out = [];

			foreach ($this->tools as $tool) {
				if ($tool->isAvailable($user)) {
					$out[] = $tool;
				}
			}

			return $out;
		}

		/**
		 * OpenAI-style tool definitions for every tool available to this user.
		 *
		 * @param object|array $user
		 * @return list<array<string,mixed>>
		 */
		public function definitions($user): array {
			$out = [];

			foreach ($this->availableTools($user) as $tool) {
				$out[] = $tool->definition($user);
			}

			return $out;
		}

		/**
		 * Dispatch a model tool call. Unknown tools error; tools the user cannot use
		 * are denied here even if the model somehow named them.
		 *
		 * @param array<string,mixed> $args
		 */
		public function execute(string $name, array $args, AIToolContext $context): AIToolResult {
			$tool = $this->get($name);

			if ($tool === null) {
				return AIToolResult::error("Unknown tool: " . $name);
			}

			if (!$tool->isAvailable($context->user)) {
				return AIToolResult::denied("You do not have access to this action.");
			}

			$kind = $tool->kind();

			if (!in_array($kind, self::KINDS, true)) {

				// A typo'd kind is how a tool ends up outside every rule keyed on it.
				return AIToolResult::error(
					"Tool \"{$name}\" declares an unknown kind \"{$kind}\" and cannot be run."
				);
			}

			// Validate the arguments against the tool's own published schema before
			// dispatch. Every seam used to cast a wrongly-shaped argument to empty —
			// silent data loss for a replace-semantics argument — so the shape check
			// lives here, once, ahead of every tool (core and extension). A repairable
			// stringified object/array is fixed in place; anything else is a
			// recoverable error the model can act on.
			$shape_error = AIToolArgs::validate($tool->definition($context->user), $args);

			if ($shape_error !== null) {

				return AIToolResult::error($shape_error);
			}

			$result = $tool->execute($args, $context);

			// kind() is the contract the whole approval model rests on, and until now
			// nothing in production enforced it — an extension tool could declare
			// "read" and mutate the CMS mid-turn with no card, no approval, no audit
			// row and no staleness check. The enforceable half of that contract is the
			// shape of the result: only a mutating tool may stage a proposal, and a
			// mutating tool may only ever stage one.
			if ($result->type === AIToolResult::PROPOSAL && $kind !== "mutate") {

				return AIToolResult::error(
					"Tool \"{$name}\" staged a change but is declared as a \"{$kind}\" tool."
				);
			}

			if ($kind === "mutate" && !in_array($result->type, self::MUTATE_RESULT_TYPES, true)) {

				return AIToolResult::error(
					"Tool \"{$name}\" is a mutating tool, so it must stage a change for approval rather than "
						. "returning a result directly."
				);
			}

			return $result;
		}

		/** The kinds a tool may declare. See AIToolInterface::kind(). */
		private const KINDS = ["read", "mutate", "elicit"];

		/**
		 * What a "mutate" tool is allowed to return. Anything else means it did its
		 * work during the turn instead of staging it — the one thing the two-phase
		 * model exists to prevent.
		 */
		private const MUTATE_RESULT_TYPES = [
			AIToolResult::PROPOSAL,
			AIToolResult::DENIED,
			AIToolResult::ERROR,
			AIToolResult::NEEDS_INPUT,
		];
	}
