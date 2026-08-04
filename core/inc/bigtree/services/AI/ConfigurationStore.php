<?php
	namespace BigTree\Services\AI;

	/**
	 * The one sentence the assistant says when a write to the JSON configuration
	 * store did not land, and the result shape that carries it.
	 *
	 * Audit #17 A3. Every template, callout, module and group the assistant can
	 * author lives in `custom/json-db/*.json`, and `BigTreeJSONDB::save()` discarded
	 * its `file_put_contents` return — so an unwritable directory, a full disk or a
	 * partial write was indistinguishable from success. The approval seam returned
	 * `mode: published`, `recordApprovalAudit` wrote a `via=ai_assistant` row for a
	 * change that isn't there, and the card went green.
	 *
	 * Every other write surface the assistant reaches was made to fail loudly by an
	 * earlier audit: a false from `createItem` becomes `mode: error` (audit #5),
	 * failed scaffold DDL becomes a FAILED card (audit #12), and `TemplateScaffold`
	 * refuses an unwritable directory rather than claiming it wrote a render file.
	 * `mode: error` is what makes a card FAILED rather than approved, and a FAILED
	 * card stays claimable — which is exactly right here, because the cause (a
	 * permission, a full disk) is fixable and the same proposal can then be approved
	 * again.
	 */
	class ConfigurationStore {
		/**
		 * Deliberately names the directory: the realistic causes are all things a
		 * developer fixes on the server, and "it didn't save" without saying where
		 * sends them looking in the database.
		 */
		public const WRITE_FAILED = "That change could not be saved — BigTree's configuration store "
			. "(custom/json-db) could not be written. Check the directory's permissions and free space, "
			. "then approve this again.";

		/**
		 * @return array{mode:string,message:string}
		 */
		public static function failure(): array {

			return ["mode" => "error", "message" => self::WRITE_FAILED];
		}
	}
