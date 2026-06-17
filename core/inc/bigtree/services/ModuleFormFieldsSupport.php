<?php
	namespace BigTree\Services;

	use BigTree;

	/**
	 * Shared form-field normalization for module collaborators. Both
	 * ModuleService (scaffold + embed-form CRUD) and the extracted
	 * ModuleFormService (form CRUD) need to clean the developer-supplied field
	 * arrays into the canonical stored shape before persisting them. This trait
	 * holds that one helper so both can `use` it instead of duplicating or
	 * coupling back into ModuleService.
	 *
	 * cleanFormFields is pure (no $this-> dependencies) — keep it that way so it
	 * stays trivially shareable across both collaborators.
	 */
	trait ModuleFormFieldsSupport {
		private function cleanFormFields($fields) {
			if (!is_array($fields)) {
				return [];
			}
			$out = [];

			foreach ($fields as $key => $data) {
				if (!is_array($data)) {
					continue;
				}
				$column = (string)($data["column"] ?? (is_string($key) ? $key : ""));

				if ($column === "") {
					continue;
				}
				$settings = $data["settings"] ?? ($data["options"] ?? []);

				if (is_string($settings)) {
					$settings = json_decode($settings, true) ?: [];
				}
				$settings = BigTree::arrayFilterRecursive(is_array($settings) ? $settings : []);
				$out[] = [
					"column" => $column,
					"type" => BigTree::safeEncode((string)($data["type"] ?? "text")),
					"title" => BigTree::safeEncode((string)($data["title"] ?? "")),
					"subtitle" => BigTree::safeEncode((string)($data["subtitle"] ?? "")),
					"settings" => $settings,
				];
			}

			return $out;
		}
	}
