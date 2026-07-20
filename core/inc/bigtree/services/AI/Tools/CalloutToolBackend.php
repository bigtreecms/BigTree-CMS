<?php
	namespace BigTree\Services\AI\Tools;

	/**
	 * The seam the create_callout AI tool calls, implemented by CalloutService.
	 * Callouts are a developer resource (the tool gates on level 2 and the backend
	 * re-checks), created two-phase with a preview of the callout's fields. Field
	 * cleaning matches the REST create path via Resources::clean.
	 */
	interface CalloutToolBackend {
		/**
		 * Read one callout's definition including its full field list, so the
		 * assistant can see what it's changing before update_callout replaces it.
		 * Returns denied | error | ["callout" => [...]].
		 *
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiGetCallout(string $callout_id, $user): array;

		/**
		 * Validate a proposed callout creation without writing: developer level and id
		 * availability. Returns denied | error | ok+summary+preview+payload.
		 *
		 * @param array<string,mixed> $args
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiValidateCalloutCreate(array $args, $user): array;

		/**
		 * Apply an approved callout creation from a stored payload. Re-checks developer.
		 *
		 * @param array<string,mixed> $payload
		 * @param object|array $user
		 * @return array<string,mixed>
		 * @throws \BigTree\Api\Exceptions\AuthorizationException
		 */
		public function aiCreateCallout(array $payload, $user): array;

		/**
		 * Validate a proposed callout edit without writing: developer level, existence,
		 * field types, and that the display field survives a field-list replacement.
		 * Returns denied | error | ok+summary+preview+payload.
		 *
		 * @param array<string,mixed> $args
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiValidateCalloutUpdate(array $args, $user): array;

		/**
		 * Apply an approved callout edit from a stored payload. Re-checks developer.
		 * The callout's render file is never rewritten — it's authored code.
		 *
		 * @param array<string,mixed> $payload
		 * @param object|array $user
		 * @return array<string,mixed>
		 * @throws \BigTree\Api\Exceptions\AuthorizationException
		 */
		public function aiUpdateCallout(array $payload, $user): array;
	}
