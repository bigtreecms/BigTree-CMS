<?php
	namespace BigTree\Services\AI\Tools;

	/**
	 * The seam the add_tags AI tool calls, implemented by TagService.
	 *
	 * Tagging is an administrator concern (level ≥ 1, matching can_manage_tags and the
	 * admin-only search_tags), and the target page must also be editable by the user.
	 * Missing tags are created as part of tagging, then linked into bigtree_tags_rel —
	 * the same relationship the page editor writes. Scoped to pages for v1.
	 */
	interface TagToolBackend {
		/**
		 * Validate adding tags to a page without writing: administrator level, page
		 * existence + edit access, and a non-empty tag list. Returns
		 * denied | error | ok+summary+preview+payload.
		 *
		 * @param array<string,mixed> $args
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiValidateAddTags(array $args, $user): array;

		/**
		 * Apply an approved tag addition from a stored payload. Re-checks level and
		 * page edit access, find-or-creates each tag, and links it to the page.
		 *
		 * @param array<string,mixed> $payload
		 * @param object|array $user
		 * @return array<string,mixed>
		 * @throws \BigTree\Api\Exceptions\AuthorizationException
		 */
		public function aiAddTags(array $payload, $user): array;

		/**
		 * Validate removing tags from a page or module entry. Returns denied | error |
		 * ok+summary+preview+payload.
		 *
		 * @param array<string,mixed> $args
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiValidateRemoveTags(array $args, $user): array;

		/**
		 * Apply an approved tag removal. Detaches only — the tag rows survive.
		 * Re-checks edit access on the target.
		 *
		 * @param array<string,mixed> $payload
		 * @param object|array $user
		 * @return array<string,mixed>
		 * @throws \BigTree\Api\Exceptions\AuthorizationException
		 */
		public function aiRemoveTags(array $payload, $user): array;

		/**
		 * Validate merging tags into one. Administrator-only: this manages the site's
		 * shared vocabulary rather than one record's tags, and it deletes tag rows.
		 * Returns denied | error | ok+summary+preview+payload.
		 *
		 * @param array<string,mixed> $args
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiValidateTagMerge(array $args, $user): array;

		/**
		 * Apply an approved tag merge: rewrite the relations, delete the merged tags,
		 * recount usage. Re-checks administrator level.
		 *
		 * @param array<string,mixed> $payload
		 * @param object|array $user
		 * @return array<string,mixed>
		 * @throws \BigTree\Api\Exceptions\AuthorizationException
		 */
		public function aiMergeTags(array $payload, $user): array;

		/**
		 * Validate renaming a tag. Administrator-only. A rename onto an existing name
		 * is refused and pointed at merge_tags. Returns denied | error |
		 * ok+summary+preview+payload.
		 *
		 * @param array<string,mixed> $args
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiValidateTagRename(array $args, $user): array;

		/**
		 * Apply an approved tag rename. Re-checks administrator level and that the
		 * new name is still free.
		 *
		 * @param array<string,mixed> $payload
		 * @param object|array $user
		 * @return array<string,mixed>
		 * @throws \BigTree\Api\Exceptions\AuthorizationException
		 */
		public function aiRenameTag(array $payload, $user): array;
	}
