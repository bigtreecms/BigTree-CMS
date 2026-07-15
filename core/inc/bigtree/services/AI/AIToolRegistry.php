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

			return $tool->execute($args, $context);
		}
	}
