<?php
	namespace BigTree\Services\AI\Tools;

	/**
	 * The seam the resource (media library) read tools call, implemented by
	 * ResourceService. Both tools require folder rank ≥ e (editor) on the folder they
	 * touch, and results are filtered to folders the user can access — the same
	 * permission model the REST media routes use.
	 *
	 * Read-only: the assistant never uploads, moves, or deletes files.
	 */
	interface ResourceToolBackend {
		/**
		 * The contents of a resource folder (subfolders + files) for list_resources.
		 * Returns ["denied" => string] when the user lacks edit access to the folder.
		 *
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiListResources(int $folder, int $limit, $user): array;

		/**
		 * Files whose name or filename matches a query, filtered to folders the user
		 * can edit, for search_files.
		 *
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiSearchFiles(string $query, int $limit, $user): array;
	}
