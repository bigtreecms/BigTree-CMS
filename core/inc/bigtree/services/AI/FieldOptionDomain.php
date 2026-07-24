<?php
	namespace BigTree\Services\AI;

	use BigTree\Services\ModuleFormService;

	/**
	 * The option-domain check for a `list` field, shared by the module-entry path and
	 * the page-content path.
	 *
	 * Audit #5 gave module entries a full option domain — resolve a `list` field's
	 * static/db/state/country choices, then refuse a value that matches none of them
	 * (matching a label back to its stored value). None of that reached the page
	 * side, so a `list` template field accepted any string and the next human save
	 * silently rewrote it. This class is the one implementation both paths call, so
	 * they cannot drift apart again.
	 *
	 * `resolve()` needs a DB for db-driven lists; `violation()` is pure.
	 */
	class FieldOptionDomain {
		/**
		 * The resolved choices for a field, or [] when it isn't a `list` (or a list
		 * whose options can't be resolved — a misconfigured db list is nobody's cue to
		 * refuse every write).
		 *
		 * @param array<string,mixed> $field  The field/resource record (carries settings).
		 * @return list<array{value:string,label:string}>
		 */
		public static function resolve(array $field, string $type, string $column): array {
			if ($type !== "list") {

				return [];
			}

			try {

				return (new ModuleFormService())->resolveListOptions($field, $column);
			} catch (\Throwable $e) {

				return [];
			}
		}

		/**
		 * Refuse a value that isn't one of a list field's options. A recognisable
		 * label is matched back to its stored value in place; an empty value is
		 * `required`'s business and left to the gates. Returns null when acceptable.
		 *
		 * @param array<string,mixed> $field The schema entry (must carry "options").
		 */
		public static function violation(array $field, string &$value): ?string {
			$options = is_array($field["options"] ?? null) ? $field["options"] : [];

			if (!$options || $value === "") {

				return null;
			}

			$values = array_map("strval", array_column($options, "value"));

			if (in_array($value, $values, true)) {

				return null;
			}

			foreach ($options as $option) {
				if (strcasecmp((string)($option["label"] ?? ""), $value) === 0) {
					$value = (string)$option["value"];

					return null;
				}
			}

			$listed = array_map(function (array $option): string {
				$label = (string)($option["label"] ?? "");
				$option_value = (string)($option["value"] ?? "");

				return $label !== "" && $label !== $option_value ? "{$label} ({$option_value})" : $option_value;
			}, array_slice($options, 0, 30));

			return "\"{$value}\" isn't one of the options for \"" . (string)($field["title"] ?? $field["column"] ?? $field["id"] ?? "")
				. "\". Choose one of: " . implode(", ", $listed)
				. (count($options) > 30 ? ", …" : "") . ".";
		}
	}
