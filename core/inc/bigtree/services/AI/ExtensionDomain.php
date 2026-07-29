<?php
	namespace BigTree\Services\AI;

	/**
	 * Who owns a JSON-DB record, and what that means for editing it.
	 *
	 * `ExtensionService` files an installed extension's templates, callouts, modules,
	 * settings, field types and feeds into the very same stores the site's own live
	 * in, keyed `{ext}*{local}` with an `extension` key. Nothing on the AI surface
	 * knew that key existed: `TemplateService::present()`, `aiListTemplates()`,
	 * `CalloutService::present()`, `aiGetCallout()` and `ModuleService::present()`
	 * all omitted it, and none of the three update validate seams consulted it. So an
	 * extension-owned template read back as an ordinary one, and a developer approved
	 * a field addition to it with nothing on the card to say what would happen next.
	 *
	 * What happens next is that it is reverted. `BigTreeAdmin::installExtension`
	 * deletes every module, template, callout, field type and feed belonging to the
	 * extension and re-imports them from the new manifest — the one component it
	 * deliberately spares is settings, "because they have user data". The edit is
	 * temporary and nothing said so.
	 *
	 * Warned rather than refused (audit #13 D5): refusing outright would make the
	 * assistant the only writer that can't do something the developer UI does. The
	 * developer keeps the choice; they just get to make it knowing.
	 */
	class ExtensionDomain {
		/**
		 * The extension that owns a record, or "" when the site does.
		 *
		 * Read from the `extension` key rather than from the `*` in the id: a module
		 * keeps its own id when it is packaged (only its route is namespaced), so the
		 * key is the only thing all five component types agree on.
		 *
		 * @param array<string,mixed>|null $record
		 */
		public static function owner(?array $record): string {

			return trim((string)($record["extension"] ?? ""));
		}

		/**
		 * The non-fatal warning an edit to an extension-owned record carries, or ""
		 * when the site owns it.
		 *
		 * @param array<string,mixed>|null $record
		 * @param string $noun What the record is, for the sentence ("template", "callout", "module").
		 */
		public static function warning(?array $record, string $noun): string {
			$owner = self::owner($record);

			if ($owner === "") {

				return "";
			}

			return "This {$noun} belongs to the “{$owner}” extension, not to this site. Upgrading the extension "
				. "deletes its components and re-imports them from the new package, so this change is reverted "
				. "the next time it is updated — the durable version of it is a change to the extension itself.";
		}
	}
