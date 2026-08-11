<?php
	namespace BigTree\Services\AI;

	/**
	 * The value domain for a record's Open Graph metadata.
	 *
	 * `bigtree_open_graph` carries four authored columns — `title`, `description`,
	 * `type` and `image` — plus `image_width` / `image_height`, which are measured
	 * rather than written. Both AI seams that can set them (a page's and a module
	 * entry's) carried title and description only, and the reason both of them stated
	 * was wrong: "OG images are file references, which the assistant never fabricates".
	 * `image` is a `varchar(1024)` holding a URL on this code path — the admin renders
	 * it as a bare text input with `placeholder="https://"` — and `type` is a
	 * four-option `<select>`, the single most authorable shape in the framework, which
	 * neither seam mentioned at all. So "set up the social preview for the new pricing
	 * page" produced a card that looked complete, wrote half the record, and said
	 * nothing (audit #22 A2).
	 *
	 * One class rather than a pair of lists on two services and four tool definitions:
	 * the arguments mean the same thing on a page and on an entry, and a domain restated
	 * per seam is a domain that disagrees with itself the moment one seam grows a rule —
	 * the lesson FieldTypeDomain, FieldOptionDomain and ResourceReferenceDomain each
	 * encode.
	 */
	class OpenGraphDomain {
		/**
		 * The assistant's flat spelling of the record, mapped to the columns it holds.
		 *
		 * `image_width` / `image_height` are deliberately absent. BigTreeCMS::drawHeadTags
		 * computes them with getimagesize() at render time for any image under WWW_ROOT,
		 * and for a remote URL there is nothing to measure without fetching it — so
		 * omitting them costs the `og:image:width` / `og:image:height` meta tags on
		 * remote images only, which is exactly what the REST path already does for any
		 * client that doesn't send them.
		 */
		public const FIELDS = [
			"og_title" => "title",
			"og_description" => "description",
			"og_type" => "type",
			"og_image" => "image",
		];

		/**
		 * The `type` values the assistant may write.
		 *
		 * The domain is owned by the admin's own control rather than by a field
		 * definition: `SharingTab` in spa/src/pages/PageEdit.tsx and `OpenGraphSection`
		 * in spa/src/renderer/forms/OpenGraphSection.tsx both render this exact list as a
		 * `<select>`, plus an empty option for "unset".
		 */
		public const TYPES = ["website", "article", "profile", "video.movie"];

		// bigtree_open_graph.image's column width.
		private const IMAGE_MAX_LENGTH = 1024;

		/**
		 * Check one Open Graph argument's value, as a recoverable error.
		 *
		 * `og_image`'s shape rule is the one `link` fields already have, through the
		 * shared implementation. `ipl://` / `irl://` / `{wwwroot}` are accepted for the
		 * same reason linkShapeViolation accepts them everywhere else — they are what
		 * BigTree itself stores, and BigTreeCMS::getOpenGraph untranslates them back to
		 * hard URLs before drawHeadTags echoes the meta tag. A bare path is refused,
		 * because nothing resolves one.
		 *
		 * @return string|null Null when the value is acceptable.
		 */
		public static function violation(string $argument, string $value): ?string {
			$raw = trim($value);

			if ($argument === "og_type") {
				if ($raw === "" || in_array($raw, self::TYPES, true)) {

					return null;
				}

				return "\"{$raw}\" isn't an Open Graph type. Use one of: " . implode(", ", self::TYPES)
					. " — or an empty string to clear it.";
			}

			if ($argument !== "og_image") {

				return null;
			}

			$bad_link = FieldTypeDomain::linkShapeViolation("Open Graph image", "link", $raw);

			if ($bad_link !== null) {

				return $bad_link;
			}

			return ColumnDomain::maxLengthViolation("Open Graph image", self::IMAGE_MAX_LENGTH, $raw);
		}

		/**
		 * The first Open Graph argument in a set whose value is out of domain, or null
		 * when they all pass. Run at staging and again at approval on both seams, like
		 * every other value check.
		 *
		 * @param array<string,mixed> $args
		 */
		public static function error(array $args): ?string {
			foreach (array_keys(self::FIELDS) as $argument) {
				if (!array_key_exists($argument, $args)) {

					continue;
				}

				$violation = self::violation($argument, (string)$args[$argument]);

				if ($violation !== null) {

					return $violation;
				}
			}

			return null;
		}

		/**
		 * A stored record keyed by the arguments that write it, for a read payload.
		 *
		 * @param array<string,mixed> $stored
		 * @return array<string,string>
		 */
		public static function detail(array $stored): array {
			$out = [];

			foreach (self::FIELDS as $argument => $key) {
				$out[$argument] = (string)($stored[$key] ?? "");
			}

			return $out;
		}
	}
