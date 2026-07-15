<?php
	namespace BigTree\Services\AI\Tools;

	/**
	 * The seam the module-entry AI tools call, implemented by AutoModuleService.
	 *
	 * The assistant can only set the module form's simple scalar fields (text, number,
	 * select, checkbox, date, …). Complex fields — uploads, matrices, relationships,
	 * geocoding, routes — are omitted from the schema and rejected if supplied, so the
	 * assistant never has to synthesize a file reference or a relation row.
	 *
	 * Module-level access is re-checked (edit to stage, publisher to write live), and
	 * for group-based-permission modules the specific row is checked via
	 * assertCanEditRow at approval. Writes go through BigTreeAutoModule so pending-change
	 * flow, audit, and hooks come for free.
	 */
	interface ModuleEntryToolBackend {
		/**
		 * Validate a proposed new module entry without writing: module existence, edit
		 * access, the simple-field schema, and required fields. When no data is
		 * supplied it returns an error listing the settable fields (schema discovery).
		 * Returns denied | error | ok+summary+preview+payload.
		 *
		 * @param array<string,mixed> $args
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiValidateEntryCreate(array $args, $user): array;

		/**
		 * Apply an approved entry creation from a stored payload. Re-checks module edit
		 * access; a publisher writes live, an editor queues a pending entry.
		 *
		 * @param array<string,mixed> $payload
		 * @param object|array $user
		 * @return array<string,mixed>
		 * @throws \BigTree\Api\Exceptions\AuthorizationException
		 */
		public function aiCreateEntry(array $payload, $user): array;

		/**
		 * Validate a proposed entry edit without writing: module + entry existence,
		 * edit access (including per-row gbp), and the simple-field schema. Same return
		 * shape as the create variant.
		 *
		 * @param array<string,mixed> $args
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiValidateEntryUpdate(array $args, $user): array;

		/**
		 * Apply an approved entry edit from a stored payload. Re-checks access; a
		 * publisher writes live, an editor submits a pending change.
		 *
		 * @param array<string,mixed> $payload
		 * @param object|array $user
		 * @return array<string,mixed>
		 * @throws \BigTree\Api\Exceptions\AuthorizationException
		 */
		public function aiUpdateEntry(array $payload, $user): array;
	}
