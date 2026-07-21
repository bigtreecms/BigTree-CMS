<?php
	namespace BigTree\Services\AI\Tools;

	/**
	 * The seam between the read-only search tools and the search implementation.
	 * SearchService implements this so the tools can perform lookups without
	 * depending on its internals or duplicating its permission-filtered queries.
	 *
	 * Every method is already permission-filtered by the underlying service.
	 * Rows are returned to the tool, which packages them as model data + navigable
	 * artifacts; collection/merge stays in the driver.
	 */
	interface SearchToolBackend {
		/**
		 * @param object|array $user
		 * @return list<array<string,mixed>>
		 */
		public function searchPages($q, $limit, $user): array;

		/**
		 * @param object|array $user
		 * @return list<array<string,mixed>>
		 */
		public function searchModules($q, $limit, $user): array;

		/**
		 * @param object|array $user
		 * @return list<array<string,mixed>> Groups: [{module, items}]
		 */
		public function searchModuleEntries($q, $limit, $user): array;

		/**
		 * @return list<array<string,mixed>>
		 */
		public function searchTags($q, $limit): array;

		/**
		 * @return list<array<string,mixed>>
		 */
		public function searchUsers($q, $limit): array;

		/**
		 * @param object|array $user
		 * @return array{pages?:list,entries?:list,settings?:list}
		 */
		public function semanticSearch($q, $limit, $user): array;

		/**
		 * Fetch one page for get_page. The id may be a live page's numeric id or a
		 * "p"-prefixed pending-change id addressing an unpublished draft, matching
		 * what update_page accepts. A draft resolves no artifact — there is no live
		 * page to navigate to.
		 *
		 * @param mixed $id
		 * @param object|array $user
		 * @return array{error?:string,payload?:array,artifact?:array}
		 */
		public function getPageDetail($id, $user): array;

		/**
		 * @param object|array $user
		 * @return array{error?:string,payload?:array,artifact?:array}
		 */
		public function getModuleEntryDetail($module_id, $entry_id, $user): array;

		/**
		 * Reduce a conversational phrase to content keywords.
		 *
		 * @return list<string>
		 */
		public function extractKeywords(string $q): array;
	}
