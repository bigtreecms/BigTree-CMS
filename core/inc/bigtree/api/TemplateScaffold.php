<?php
	namespace BigTree\Api;

	/**
	 * Writes the front-end stub file a newly created template or callout renders
	 * from.
	 *
	 * A basic template renders from `templates/basic/{id}.php`; a *routed* template
	 * is a directory, `templates/routed/{id}/default.php`, with a sibling file per
	 * route segment (this is what legacy's developer UI wrote). Callouts render from
	 * `templates/callouts/{id}.php`. Legacy BigTree's developer UI scaffolded these
	 * on create; the REST/SPA rewrite dropped that, so a template could be created
	 * and immediately assigned to pages that then rendered *nothing* — with no error
	 * to explain why. This restores the behavior for every creation path.
	 *
	 * A template's resources are exposed to its file as bare variables ($page_header)
	 * and as $resources["page_header"]; a callout's are exposed as $callout["field_id"].
	 * The stubs echo each defined field so a new template/callout renders something
	 * meaningful immediately and shows the developer the variable names to work with.
	 * Every stub written here must be loadable PHP — the router includes it directly,
	 * so a parse error in it 500s every page on the template.
	 */
	class TemplateScaffold {
		/**
		 * Write the stub for a template. Returns the repo-relative path written, or
		 * "" when a file already exists there (an existing file is never touched).
		 *
		 * @param list<array<string,mixed>> $resources
		 * @throws \RuntimeException when the target directory or file isn't writable
		 */
		public static function template(string $id, array $resources, bool $routed): string {
			return self::write(self::templatePath($id, $routed), self::templateBody($resources, $routed));
		}

		/**
		 * Where a template's stub belongs.
		 *
		 * A routed template gets `routed/{id}/default.php`, not `routed/{id}.php`.
		 * The flat form does render (through the fallback in utils.php), but it makes
		 * the stub's own comment — "add sibling files in this directory to handle
		 * sub-routes" — false, and following it collides with the routed-template
		 * namespace: `routed/{id}/` is exactly where those siblings go.
		 */
		public static function templatePath(string $id, bool $routed): string {
			$safe = self::safeId($id);

			return $routed
				? "templates/routed/{$safe}/default.php"
				: "templates/basic/{$safe}.php";
		}

		/**
		 * Write the stub for a callout. Returns the repo-relative path written, or ""
		 * when a file already exists there.
		 *
		 * @param list<array<string,mixed>> $resources
		 * @throws \RuntimeException when the target directory or file isn't writable
		 */
		public static function callout(string $id, array $resources): string {
			$relative = "templates/callouts/" . self::safeId($id) . ".php";

			return self::write($relative, self::calloutBody($resources));
		}

		/**
		 * Whether a stub can be written for a template — used to fail a proposal at
		 * validation time rather than half-way through an approved write.
		 */
		public static function templateIsWritable(string $id, bool $routed): bool {

			return self::isWritable(self::templatePath($id, $routed));
		}

		/** Whether a stub can be written for a callout. */
		public static function calloutIsWritable(string $id): bool {

			return self::isWritable("templates/callouts/" . self::safeId($id) . ".php");
		}

		/**
		 * The id reduced to characters that are safe in a filename. Ids are urlified
		 * upstream, so this is belt-and-braces against a path separator ever reaching
		 * a file write.
		 */
		private static function safeId(string $id): string {

			return preg_replace("/[^a-zA-Z0-9_-]/", "", $id) ?: "";
		}

		/** True when $relative can be created (its directory exists and is writable). */
		private static function isWritable(string $relative): bool {
			$path = SERVER_ROOT . $relative;

			if (file_exists($path)) {

				// Already present — nothing will be written, so nothing can fail.
				return true;
			}

			$directory = dirname($path);

			// A routed template's directory doesn't exist yet on first creation; what
			// matters then is whether its parent can be written into.
			if (!is_dir($directory)) {
				$parent = dirname($directory);

				return is_dir($parent) && is_writable($parent);
			}

			return is_writable($directory);
		}

		/**
		 * Create $relative with $body unless it already exists. Returns the relative
		 * path on write, "" when skipped.
		 */
		private static function write(string $relative, string $body): string {
			$path = SERVER_ROOT . $relative;

			// Never clobber a file a developer may have already written by hand.
			if (file_exists($path)) {

				return "";
			}

			$directory = dirname($path);

			if (!is_dir($directory) && !@mkdir($directory, 0777, true) && !is_dir($directory)) {
				throw new \RuntimeException("Could not create directory {$directory}");
			}

			if (!is_writable($directory)) {
				throw new \RuntimeException("Directory {$directory} is not writable");
			}

			if (@file_put_contents($path, $body) === false) {
				throw new \RuntimeException("Could not write {$relative}");
			}

			return $relative;
		}

		/**
		 * @param list<array<string,mixed>> $resources
		 */
		public static function templateBody(array $resources, bool $routed): string {
			$out = "";

			if ($routed) {
				// A routed template's file is its default (no-subpage) view; the
				// developer adds sibling files for each route segment.
				$out .= "<?php\n\t// Routed template — this file handles the template's root URL.\n"
					. "\t// Add sibling files in this directory (one per route segment) to handle sub-routes.\n?>\n";
			}

			if (!$resources) {

				return $out . "<div class=\"typography\">\n\t<!-- This template has no fields yet. -->\n</div>\n";
			}

			$out .= "<div class=\"typography\">\n";

			foreach ($resources as $resource) {
				$id = (string)($resource["id"] ?? "");

				if ($id === "") {
					continue;
				}

				$out .= self::templateField($id, (string)($resource["type"] ?? "text"), (string)($resource["title"] ?? $id));
			}

			$out .= "</div>\n";

			return $out;
		}

		/**
		 * Render-scope names a template field must never be scaffolded as a bare
		 * variable, because router.php has already bound them to something else and
		 * the stub would echo the wrong thing (or a whole object).
		 */
		private const RESERVED_VARIABLES = ["page", "cms", "resources", "callouts", "bigtree", "admin", "db", "nav"];

		/**
		 * How a template field is read inside a stub.
		 *
		 * router.php exposes resources both as bare variables ($page_header) and as
		 * $resources["page_header"], so the bare form is used when — and only when —
		 * the id is a valid PHP label that shadows nothing. Anything else falls back
		 * to the array form: `<?=$page-header?>` is a fatal parse/constant error, and
		 * because the stub is included directly by the router it would take down every
		 * page on the template rather than just rendering the field wrong.
		 */
		private static function accessor(string $id): string {
			if (preg_match("/^[A-Za-z_][A-Za-z0-9_]*$/", $id) && !in_array($id, self::RESERVED_VARIABLES, true)) {

				return '$' . $id;
			}

			return '$resources[' . var_export($id, true) . ']';
		}

		/** One resource's markup inside a template stub. */
		private static function templateField(string $id, string $type, string $title): string {
			$variable = self::accessor($id);
			$label = self::comment($title);

			if ($type === "image") {

				return "\t<!-- {$label} -->\n"
					. "\t<?php if (!empty({$variable})) { ?>\n"
					. "\t<img src=\"<?={$variable}?>\" alt=\"\">\n"
					. "\t<?php } ?>\n";
			}

			if ($type === "html") {

				return "\t<!-- {$label} -->\n\t<?={$variable}?>\n";
			}

			if ($type === "callouts") {

				return "\t<!-- {$label} -->\n"
					. "\t<?php foreach ((array){$variable} as \$callout) { ?>\n"
					. "\t<?php include SERVER_ROOT.\"templates/callouts/\".\$callout[\"type\"].\".php\"; ?>\n"
					. "\t<?php } ?>\n";
			}

			return "\t<!-- {$label} -->\n\t<p><?=" . $variable . "?></p>\n";
		}

		/**
		 * @param list<array<string,mixed>> $resources
		 */
		public static function calloutBody(array $resources): string {
			if (!$resources) {

				return "<div class=\"callout\">\n\t<!-- This callout has no fields yet. -->\n</div>\n";
			}

			$out = "<div class=\"callout\">\n";

			foreach ($resources as $resource) {
				$id = (string)($resource["id"] ?? "");

				if ($id === "") {
					continue;
				}

				$type = (string)($resource["type"] ?? "text");
				$label = self::comment((string)($resource["title"] ?? $id));
				$accessor = '$callout[' . var_export($id, true) . ']';

				if ($type === "image") {
					$out .= "\t<!-- {$label} -->\n"
						. "\t<?php if (!empty({$accessor})) { ?>\n"
						. "\t<img src=\"<?={$accessor}?>\" alt=\"\">\n"
						. "\t<?php } ?>\n";
				} elseif ($type === "html") {
					$out .= "\t<!-- {$label} -->\n\t<?={$accessor}?>\n";
				} else {
					$out .= "\t<!-- {$label} -->\n\t<p><?={$accessor}?></p>\n";
				}
			}

			$out .= "</div>\n";

			return $out;
		}

		/** A field title made safe to sit inside an HTML comment. */
		private static function comment(string $title): string {

			return str_replace(["--", "<", ">"], ["-", "", ""], $title);
		}
	}
