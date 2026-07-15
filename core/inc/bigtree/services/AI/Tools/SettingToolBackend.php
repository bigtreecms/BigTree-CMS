<?php
	namespace BigTree\Services\AI\Tools;

	/**
	 * The seam the settings AI tools call, implemented by SettingService.
	 *
	 * Reading and writing setting VALUES is an administrator concern (level ≥ 1); the
	 * tools gate there and the backend re-checks. The assistant never touches setting
	 * DEFINITIONS (that is level 2 in the REST API and out of scope here), and it
	 * refuses internal, encrypted, and locked settings entirely — encrypted values are
	 * never even read into the model.
	 */
	interface SettingToolBackend {
		/**
		 * Non-internal settings (optionally filtered by a query) with their plaintext
		 * values; encrypted values are omitted. Returns ["denied" => string] when the
		 * user is not an administrator.
		 *
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiGetSettings(string $query, int $limit, $user): array;

		/**
		 * Validate a proposed setting-value change without writing: administrator
		 * level, existence, and that the setting is not internal/encrypted/locked.
		 * Returns denied | error | ok+summary+preview+payload.
		 *
		 * @param array<string,mixed> $args
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiValidateSettingUpdate(array $args, $user): array;

		/**
		 * Apply an approved setting-value change from a stored payload. Re-checks
		 * administrator level and the internal/encrypted/locked guards.
		 *
		 * @param array<string,mixed> $payload
		 * @param object|array $user
		 * @return array<string,mixed>
		 * @throws \BigTree\Api\Exceptions\AuthorizationException
		 */
		public function aiUpdateSetting(array $payload, $user): array;
	}
