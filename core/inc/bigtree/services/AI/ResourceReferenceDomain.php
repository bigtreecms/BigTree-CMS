<?php
	namespace BigTree\Services\AI;

	use BigTree\Api\Flag;
	use BigTree\Services\PermissionService;
	use BigTree\Services\ResourceAllocationService;
	use SQL;

	/**
	 * The value domain for the three reference field types — `image-reference`,
	 * `file-reference` and `video-reference`.
	 *
	 * Audit #11 B1. Every one of these was refused with the "the assistant only sets
	 * simple text-like fields" wall, on the reasoning that the assistant must never
	 * fabricate a file. But their stored value is not a file: it is a
	 * `bigtree_resources` id, or a `resource://` URL the CMS resolves to one — see
	 * core/admin/field-types/image-reference/process.php, which does exactly that and
	 * nothing else. Pointing at a row that already exists in a library the assistant
	 * can already enumerate (`list_resources`, `search_files`) is a lookup, not a
	 * fabrication, and conflating the two is what made the assistant unable to
	 * complete a record on any content model with a required photo — a news module, a
	 * staff directory, a product catalogue.
	 *
	 * What a lookup still has to earn, and what this class checks:
	 *
	 *  - the row exists;
	 *  - the user can see the folder it is in (`PermissionService::userHasFolderAccess`
	 *    with "v", the same check both read seams already make, so the assistant can
	 *    never file a resource into a record that its own reader would hide);
	 *  - it is the right kind of thing — an image for an `image-reference`, a video
	 *    for a `video-reference`;
	 *  - it satisfies the field's own `min_width` / `min_height`, which the admin's
	 *    picker enforces and the server never did.
	 *
	 * Run at staging *and* at approval, per the standing "never trusted on the way
	 * back out" rule: both sifts call this, and a resource deleted inside a proposal's
	 * 24h life additionally fails the `resources` fingerprint rather than writing a
	 * dangling id.
	 *
	 * Uploading is still declined, and deliberately: nothing here creates a resource.
	 */
	class ResourceReferenceDomain {
		/**
		 * Resolve and validate one proposed reference value.
		 *
		 * @param array<string,mixed> $field The AI schema entry (carries title/settings).
		 * @param object|array $user
		 * @return array{value?:string,error?:string}
		 */
		public static function resolve(array $field, string $type, string $value, $user): array {
			$title = (string)($field["title"] ?? $field["column"] ?? $field["id"] ?? "This field");
			$raw = trim($value);

			// Clearing is legal here; whether it is allowed is `required`'s business,
			// and the create/update gates own that.
			if ($raw === "") {

				return ["value" => ""];
			}

			$row = self::lookup($raw);

			if (!$row) {

				return ["error" => "“{$title}” points at a file in the Files library, and \""
					. (mb_strlen($raw) > 60 ? mb_substr($raw, 0, 60) . "…" : $raw)
					. "\" isn't one. Find the file with search_files or list_resources and set this to the "
					. "numeric \"id\" it returns."];
			}

			if (!PermissionService::userHasFolderAccess($user, (int)($row["folder"] ?? 0), "v")) {

				return ["error" => "“{$title}” would point at a file in a folder you can't see, so you can't "
					. "attach it. Pick a file from search_files or list_resources — those only return files "
					. "you have access to."];
			}

			$kind = self::kindViolation($title, $type, $row);

			if ($kind !== null) {

				return ["error" => $kind];
			}

			$size = self::sizeViolation($title, $type, $field, $row);

			if ($size !== null) {

				return ["error" => $size];
			}

			return ["value" => (string)(int)$row["id"]];
		}

		/**
		 * The row a proposed value names, or null.
		 *
		 * A bare id is the value the field stores and the value both read seams hand
		 * the model, so it is the expected form. A `resource://` URL and a stored file
		 * path are accepted for the same reason process.php accepts them: they are
		 * what the rest of the CMS writes, and a model echoing back something it read
		 * should not be refused for it.
		 *
		 * @return array<string,mixed>|null
		 */
		private static function lookup(string $raw): ?array {
			if (ctype_digit($raw)) {
				$row = SQL::fetch("SELECT * FROM bigtree_resources WHERE id = ?", (int)$raw);

				return $row ?: null;
			}

			$file = strpos($raw, "resource://") === 0 ? substr($raw, strlen("resource://")) : $raw;
			$row = ResourceAllocationService::getResourceByFile($file);

			return is_array($row) ? $row : null;
		}

		/**
		 * An image reference holding a PDF, or a video reference holding a photo. The
		 * admin's pickers only offer the right kind; nothing on the API path did.
		 *
		 * @param array<string,mixed> $row
		 */
		private static function kindViolation(string $title, string $type, array $row): ?string {
			$name = (string)($row["name"] ?? $row["file"] ?? "that file");

			if ($type === "image-reference" && !Flag::isOn($row["is_image"] ?? null)) {

				return "“{$title}” is an image reference, and \"{$name}\" isn't an image. Pick a file that "
					. "search_files reports with is_image: true.";
			}

			if ($type === "video-reference" && !Flag::isOn($row["is_video"] ?? null)) {

				return "“{$title}” is a video reference, and \"{$name}\" isn't a video. Pick a file that "
					. "search_files reports with is_video: true.";
			}

			return null;
		}

		/**
		 * The field's own `min_width` / `min_height`, which the image-reference picker
		 * enforces in the admin and the server has never enforced anywhere.
		 *
		 * @param array<string,mixed> $field
		 * @param array<string,mixed> $row
		 */
		private static function sizeViolation(string $title, string $type, array $field, array $row): ?string {
			if ($type !== "image-reference") {

				return null;
			}

			$settings = is_array($field["settings"] ?? null) ? $field["settings"] : [];
			$min_width = (int)($settings["min_width"] ?? 0);
			$min_height = (int)($settings["min_height"] ?? 0);
			$width = (int)($row["width"] ?? 0);
			$height = (int)($row["height"] ?? 0);
			$name = (string)($row["name"] ?? $row["file"] ?? "that file");

			if ($min_width > 0 && $width > 0 && $width < $min_width) {

				return "“{$title}” needs an image at least {$min_width}px wide, and \"{$name}\" is {$width}px. "
					. "Pick a larger one.";
			}

			if ($min_height > 0 && $height > 0 && $height < $min_height) {

				return "“{$title}” needs an image at least {$min_height}px tall, and \"{$name}\" is {$height}px. "
					. "Pick a larger one.";
			}

			return null;
		}

		/**
		 * What a stored reference id actually names, for a read payload — B4's half of
		 * B1. The entry read seam flattened every column, so a photo came back as the
		 * string "12": an opaque integer with nothing saying it was a resource id or
		 * which file it named, which is no basis for "replace the photo". The page
		 * read seam showed nothing at all.
		 *
		 * Folder-filtered like every other resource read, so this can't become a way
		 * to learn the names of files the user can't see.
		 *
		 * @param object|array $user
		 * @return array<string,mixed>|null
		 */
		public static function describe(string $value, $user): ?array {
			$raw = trim($value);

			if ($raw === "") {

				return null;
			}

			$row = self::lookup($raw);

			if (!$row || !PermissionService::userHasFolderAccess($user, (int)($row["folder"] ?? 0), "v")) {

				return null;
			}

			return [
				"id" => (int)$row["id"],
				"name" => (string)($row["name"] ?? ""),
				"file" => (string)($row["file"] ?? ""),
				"is_image" => Flag::isOn($row["is_image"] ?? null),
				// A row can arrive from the by-file cache rather than a SELECT *, so
				// every key is read defensively — a describe() that warns is a read
				// tool that emits a PHP notice into a chat turn.
				"width" => ($row["width"] ?? null) !== null ? (int)$row["width"] : null,
				"height" => ($row["height"] ?? null) !== null ? (int)$row["height"] : null,
			];
		}

		/**
		 * The resource ids a sifted data set files into reference columns, for the
		 * proposal's fingerprint. A resource deleted inside the 24h TTL then fails the
		 * card rather than writing an id that points at nothing.
		 *
		 * @param array<string,array<string,mixed>> $schema Keyed by column/resource id.
		 * @param array<string,mixed> $data
		 * @return list<int>
		 */
		public static function referencedIds(array $schema, array $data): array {
			$ids = [];

			foreach ($data as $key => $value) {
				$type = (string)($schema[$key]["type"] ?? "");

				if (!FieldTypeDomain::isResourceReference($type) || !is_scalar($value)) {

					continue;
				}

				$id = (int)trim((string)$value);

				if ($id > 0) {
					$ids[] = $id;
				}
			}

			$ids = array_values(array_unique($ids));
			sort($ids);

			return $ids;
		}
	}
