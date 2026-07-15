<?php
	namespace BigTree\Services\AI;

	/**
	 * Contract for a single AI tool. Permission awareness is expressed in three
	 * places, all resolved from the user:
	 *
	 *   - isAvailable(): registry-level gate. A tool a user cannot use is never
	 *     offered to the model, so the model cannot hallucinate the capability.
	 *   - definition(): the function schema may vary by user (e.g. a create tool
	 *     can describe only the subtrees this user may write to).
	 *   - execute(): re-checks permission server-side. The model is never trusted
	 *     as the enforcement layer.
	 */
	interface AIToolInterface {
		/**
		 * Stable tool name exposed to the model (snake_case).
		 */
		public function name(): string;

		/**
		 * "read" | "mutate" | "elicit". Drivers use this to decide whether a call
		 * can run unattended (read) or must be staged for approval (mutate).
		 */
		public function kind(): string;

		/**
		 * Whether this user may use the tool at all. Governs both what the model is
		 * offered and a hard re-check before execution.
		 *
		 * @param object|array $user
		 */
		public function isAvailable($user): bool;

		/**
		 * OpenAI-style function-tool definition for this user. Shape:
		 *   ["type" => "function", "function" => ["name", "description", "parameters"]]
		 *
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function definition($user): array;

		/**
		 * Run the tool. Must re-verify permissions and return a structured result.
		 *
		 * @param array<string,mixed> $args Arguments supplied by the model.
		 */
		public function execute(array $args, AIToolContext $context): AIToolResult;
	}
