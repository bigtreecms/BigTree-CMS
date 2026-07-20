<?php
	namespace BigTree\Services\AI\Tools;

	/**
	 * The seam the create_module AI tool calls, implemented by ModuleService.
	 *
	 * Developer-only and two-phase. The assistant creates a bare module record (name,
	 * route, group, class, icon) — it deliberately does NOT scaffold a database table
	 * (that path runs irreversible DDL and belongs in the Module Designer). Developer
	 * level is re-checked at validation and approval.
	 */
	interface ModuleToolBackend {
		/**
		 * Read one module's definition plus what setup it's still missing. Returns
		 * denied | error | ["module" => [...]].
		 *
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiGetModule(string $module_id, $user): array;

		/**
		 * Validate a proposed module creation without writing: developer level, a valid
		 * unique route. Returns denied | error | ok+summary+preview+payload.
		 *
		 * @param array<string,mixed> $args
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiValidateModuleCreate(array $args, $user): array;

		/**
		 * Apply an approved module creation from a stored payload. Re-checks developer.
		 *
		 * @param array<string,mixed> $payload
		 * @param object|array $user
		 * @return array<string,mixed>
		 * @throws \BigTree\Api\Exceptions\AuthorizationException
		 */
		public function aiCreateModule(array $payload, $user): array;

		/**
		 * Validate a proposed edit to a module's name/group/icon. Route and the Module
		 * Designer surface (table/forms/views/actions) are deliberately out of scope.
		 * Returns denied | error | ok+summary+preview+payload.
		 *
		 * @param array<string,mixed> $args
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiValidateModuleUpdate(array $args, $user): array;

		/**
		 * Apply an approved module edit from a stored payload. Re-checks developer.
		 *
		 * @param array<string,mixed> $payload
		 * @param object|array $user
		 * @return array<string,mixed>
		 * @throws \BigTree\Api\Exceptions\AuthorizationException
		 */
		public function aiUpdateModule(array $payload, $user): array;
	}
