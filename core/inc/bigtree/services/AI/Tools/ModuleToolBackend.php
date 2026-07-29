<?php
	namespace BigTree\Services\AI\Tools;

	/**
	 * The seam the module AI tools call, implemented by ModuleService.
	 *
	 * Developer-only and two-phase throughout. `create_module` makes a bare module
	 * record (name, route, group, class, icon) and nothing else; `scaffold_module`
	 * makes a usable one — the table, its columns, the form, the landing view and the
	 * actions — with the whole plan on the proposal card and no DDL until a developer
	 * approves it (audit #11 C1). Developer level is re-checked at validation and at
	 * approval on both.
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
		 * Validate a proposed module scaffold — the record plus the table, form,
		 * landing view and actions that make it usable — without writing anything.
		 * Returns denied | error | ok+summary+preview+payload, with the full build plan
		 * (table name, every column and its SQL type, form, view, actions) on the
		 * preview.
		 *
		 * @param array<string,mixed> $args
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiValidateModuleScaffold(array $args, $user): array;

		/**
		 * Apply an approved module scaffold from a stored payload, running the DDL.
		 * Re-checks developer level and re-plans from the payload.
		 *
		 * @param array<string,mixed> $payload
		 * @param object|array $user
		 * @return array<string,mixed>
		 * @throws \BigTree\Api\Exceptions\AuthorizationException
		 */
		public function aiScaffoldModule(array $payload, $user): array;

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

		/**
		 * Validate creating a module group. Developer-only. Gives create_module's and
		 * update_module's group question an answer other than the groups that already
		 * exist. Returns denied | error | ok+summary+preview+payload.
		 *
		 * @param array<string,mixed> $args
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiValidateModuleGroupCreate(array $args, $user): array;

		/**
		 * Apply an approved module group creation. Re-checks developer level.
		 *
		 * @param array<string,mixed> $payload
		 * @param object|array $user
		 * @return array<string,mixed>
		 * @throws \BigTree\Api\Exceptions\AuthorizationException
		 */
		public function aiCreateModuleGroup(array $payload, $user): array;
	}
