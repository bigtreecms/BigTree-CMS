<?php
	namespace BigTree\Services\AI\Tools;

	/**
	 * The seam the page-mutating AI tools call, implemented by PageService so tools
	 * reuse the exact create/pending-change logic the REST routes use — pending-change
	 * flow, route uniqueness, resource allocation, cache invalidation, and hooks all
	 * come for free, and permission is re-checked inside these methods (never in the
	 * model or the tool wrapper).
	 *
	 * Kept separate from SearchToolBackend so read and mutate seams evolve
	 * independently; PageService can implement both.
	 */
	interface PageToolBackend {
		/**
		 * Subtrees the user may create a page under, for a create_page needs_input
		 * prompt. Root (id 0) is offered only to administrators/developers.
		 *
		 * @param object|array $user
		 * @return list<array{id:int,title:string,path:string}>
		 */
		public function aiWritableParents($user): array;

		/**
		 * Validate a proposed page creation without writing anything: parent access,
		 * template existence, and the route that would actually be assigned. Returns
		 * one of:
		 *   ["denied" => string]            — permission problem the model should explain
		 *   ["error" => string]             — recoverable bad-argument problem
		 *   ["ok" => true, "summary" => string, "preview" => array, "payload" => array]
		 *
		 * @param array<string,mixed> $args Model-supplied arguments.
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiValidatePageCreate(array $args, $user): array;

		/**
		 * Execute an approved page creation from a stored, validated payload. Re-checks
		 * permission at approval time (a since-revoked rank must fail) and honors the
		 * publisher/editor split — a publisher writes a live page, everyone else queues
		 * a NEW pending change. Returns a structured outcome describing which happened.
		 *
		 * @param array<string,mixed> $payload The validated payload from aiValidatePageCreate.
		 * @param object|array $user
		 * @return array{mode:string,title:string,page_id?:int,pending_change_id?:int,path?:string}
		 * @throws \BigTree\Api\Exceptions\AuthorizationException when permission no longer holds.
		 */
		public function aiCreatePage(array $payload, $user): array;

		/**
		 * The navigable page tree around a parent for get_page_tree: the parent's
		 * viewable children, plus whether this user may create or edit under it.
		 * Returns ["error" => string] when the parent id is unknown.
		 *
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiPageTree(int $parent, $user): array;

		/**
		 * Validate a proposed page edit without writing anything: page existence,
		 * edit access, template/route where the model supplied them. Same return shape
		 * as aiValidatePageCreate (denied | error | ok+summary+preview+payload).
		 *
		 * @param array<string,mixed> $args Model-supplied arguments (must include page id).
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiValidatePageUpdate(array $args, $user): array;

		/**
		 * Execute an approved page edit from a stored, validated payload. Re-checks
		 * edit access, publishes live for a publisher or queues an EDIT pending change
		 * otherwise, and returns a structured outcome.
		 *
		 * @param array<string,mixed> $payload
		 * @param object|array $user
		 * @return array<string,mixed>
		 * @throws \BigTree\Api\Exceptions\AuthorizationException when permission no longer holds.
		 */
		public function aiUpdatePage(array $payload, $user): array;

		/**
		 * Validate an edit to a page's template content (resources), optionally
		 * switching template in the same proposal. Returns denied | error |
		 * ok+summary+preview+payload.
		 *
		 * @param array<string,mixed> $args
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiValidatePageContentUpdate(array $args, $user): array;

		/**
		 * Apply an approved page-content edit. Re-checks edit access.
		 *
		 * @param array<string,mixed> $payload
		 * @param object|array $user
		 * @return array<string,mixed>
		 * @throws \BigTree\Api\Exceptions\AuthorizationException
		 */
		public function aiUpdatePageContent(array $payload, $user): array;

		/**
		 * Validate a proposed page archive without writing anything: existence and
		 * publisher access (archiving is a publish-level action). Same return shape.
		 *
		 * @param array<string,mixed> $args
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiValidatePageArchive(array $args, $user): array;

		/**
		 * Execute an approved page archive from a stored, validated payload. Re-checks
		 * publisher access and archives the page (and its descendants) live.
		 *
		 * @param array<string,mixed> $payload
		 * @param object|array $user
		 * @return array<string,mixed>
		 * @throws \BigTree\Api\Exceptions\AuthorizationException when permission no longer holds.
		 */
		public function aiArchivePage(array $payload, $user): array;

		/**
		 * Validate restoring an archived page — the inverse of archive. Returns
		 * denied | error | ok+summary+preview+payload.
		 *
		 * @param array<string,mixed> $args
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiValidatePageUnarchive(array $args, $user): array;

		/**
		 * Apply an approved unarchive. Re-checks publisher access.
		 *
		 * @param array<string,mixed> $payload
		 * @param object|array $user
		 * @return array<string,mixed>
		 * @throws \BigTree\Api\Exceptions\AuthorizationException
		 */
		public function aiUnarchivePage(array $payload, $user): array;

		/**
		 * Validate moving a page to a new parent. Returns denied | error |
		 * ok+summary+preview+payload.
		 *
		 * @param array<string,mixed> $args
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiValidatePageMove(array $args, $user): array;

		/**
		 * Apply an approved move, rewriting the moved subtree's paths. Re-checks
		 * publisher access on the page and edit access at the destination.
		 *
		 * @param array<string,mixed> $payload
		 * @param object|array $user
		 * @return array<string,mixed>
		 * @throws \BigTree\Api\Exceptions\AuthorizationException
		 */
		public function aiMovePage(array $payload, $user): array;
	}
