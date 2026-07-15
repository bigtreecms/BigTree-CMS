<?php
	namespace BigTree\Services\AI\Tools;

	/**
	 * The seam the template AI tools call, implemented by TemplateService.
	 *
	 * Reads (list/get) are available to every user so the assistant can offer real
	 * templates when helping create or edit a page. Writes (create/update) are a
	 * developer concern — the tools that use them gate on level 2 — and are two-phase:
	 * validate now, apply from the stored payload only on approval.
	 */
	interface TemplateToolBackend {
		/**
		 * All templates as compact rows (id, name, module, level, routed, field ids)
		 * for list_templates.
		 *
		 * @return list<array<string,mixed>>
		 */
		public function aiListTemplates(): array;

		/**
		 * One template's full definition (including its resource fields) for
		 * get_template, or ["error" => string] when the id is unknown.
		 *
		 * @return array<string,mixed>
		 */
		public function aiGetTemplate(string $id): array;

		/**
		 * Validate a proposed template creation without writing: id availability and
		 * field shape. Returns denied | error | ok+summary+preview+payload.
		 *
		 * @param array<string,mixed> $args
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiValidateTemplateCreate(array $args, $user): array;

		/**
		 * Apply an approved template creation from a stored payload. Re-checks that the
		 * acting user is a developer.
		 *
		 * @param array<string,mixed> $payload
		 * @param object|array $user
		 * @return array<string,mixed>
		 * @throws \BigTree\Api\Exceptions\AuthorizationException
		 */
		public function aiCreateTemplate(array $payload, $user): array;

		/**
		 * Validate a proposed template update. Same return shape as the create variant.
		 *
		 * @param array<string,mixed> $args
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiValidateTemplateUpdate(array $args, $user): array;

		/**
		 * Apply an approved template update from a stored payload. Re-checks developer.
		 *
		 * @param array<string,mixed> $payload
		 * @param object|array $user
		 * @return array<string,mixed>
		 * @throws \BigTree\Api\Exceptions\AuthorizationException
		 */
		public function aiUpdateTemplate(array $payload, $user): array;
	}
