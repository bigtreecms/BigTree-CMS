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
	}
