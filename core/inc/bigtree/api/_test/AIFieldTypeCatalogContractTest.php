<?php
	/**
	 * Audit #21 guard B7: the field-type catalog contract.
	 *
	 * Three lists have to agree about what a field type is and nothing asserted that
	 * they did:
	 *
	 *  - `field-type-schemas.php` says what a type's value and settings look like,
	 *  - `FieldTypeService::getCachedFieldTypes()` says what may be *authored* — it
	 *    is what the Module Designer's field picker offers, what `GET /field-types`
	 *    serves, and what every AI authoring seam gates a proposed type against,
	 *  - the SPA's `fieldRegistry` says what can be drawn.
	 *
	 * A2 is what a disagreement costs. `many-to-many` was declared in the schemas,
	 * drawn by `RelationField`, validated on every entry write by
	 * `AutoModuleService::validateMtm`, given its own no-column branch in
	 * `ModuleService::columnSqlType` and its own entry in
	 * `Resources::AI_RENDER_REQUIRED_SETTINGS` — and was missing from the one list
	 * that decides what can be authored, so an m2m field was refused as an unknown
	 * type before any of that machinery was consulted. Two guards were defending a
	 * path nothing could reach, and `ScaffoldModuleTool` advertised an argument it
	 * would always refuse.
	 *
	 * The two legs below are the PHP halves of the agreement. Neither is a list
	 * somebody remembers to extend: a new schema entry, or a new render-required
	 * entry, fails here until somebody decides which use cases it belongs to.
	 */

	use BigTree\Api\Resources;
	use BigTree\Services\FieldTypeService;

	/**
	 * The use cases an AI seam can author a field list for: a module scaffold, a
	 * template and a callout. Read from Resources' own mapping rather than restated,
	 * since a second hand-written copy is the drift this file is about.
	 *
	 * @return list<string>
	 */
	function ai_catalog_authoring_use_cases(): array {
		$reflection = new ReflectionClass(Resources::class);
		$map = $reflection->getConstant("SURFACE_USE_CASES");

		return array_values(array_unique(array_map("strval", is_array($map) ? $map : [])));
	}

	/**
	 * B7a: every declared field type is authorable somewhere, or exempt with a
	 * reason. Fails on `many-to-many` as the catalog stood at 5432de9d5.
	 */
	function test_every_declared_field_type_is_authorable_or_exempt() {
		$installable = [];

		foreach (ai_field_use_cases() as $use_case) {
			$installable = array_merge($installable, FieldTypeService::availableFieldTypeIds($use_case));
		}

		$installable = array_unique($installable);
		T::ok($installable !== [], "the field-type catalog resolved");

		// One exemption list, shared with AIFieldTypeDomainTest's E2/E3. Two lists that
		// both have to name the same type is the drift this file exists to catch.
		$exempt = ai_unregistered_field_types();
		$orphans = [];

		foreach (array_keys(FieldTypeService::schemas()) as $type) {
			$type = (string)$type;

			if (in_array($type, $installable, true)) {

				continue;
			}

			if (!isset($exempt[$type])) {
				$orphans[] = $type;

				continue;
			}

			T::ok(
				strlen(trim((string)$exempt[$type])) >= 30,
				"\"{$type}\" is exempt from the catalog with a stated reason"
			);
		}

		T::equals(
			implode(", ", $orphans),
			"",
			"every field type field-type-schemas.php declares can be authored on some surface"
		);

		// And no exemption outlives the omission it describes: a type that has since
		// been added to the catalog should lose its excuse rather than keep it.
		$stale = array_values(array_intersect(array_keys($exempt), $installable));
		T::equals(implode(", ", $stale), "", "no exemption names a field type the catalog now offers");
	}

	/**
	 * B7b: every render-blocking settings rule defends a type an authoring surface
	 * can actually receive.
	 *
	 * This is the leg that fails on `many-to-many` today: its five `mtm-*` keys were
	 * added by audit #12 A2 and the gate they feed was unreachable, because the
	 * catalog check runs first and refused the field as an unknown type. A rule
	 * nothing can reach is not a rule — it is a test passing over an empty path.
	 */
	function test_every_render_required_setting_names_an_authorable_field_type() {
		$authorable = [];

		foreach (ai_catalog_authoring_use_cases() as $use_case) {
			$authorable = array_merge($authorable, FieldTypeService::availableFieldTypeIds($use_case));
		}

		$authorable = array_unique($authorable);
		T::ok(ai_catalog_authoring_use_cases() !== [], "the AI authoring surfaces resolved");

		$unreachable = [];

		foreach (array_keys(Resources::AI_RENDER_REQUIRED_SETTINGS) as $type) {
			if (!in_array((string)$type, $authorable, true)) {
				$unreachable[] = (string)$type;
			}
		}

		T::equals(
			implode(", ", $unreachable),
			"",
			"every render-required settings rule guards a field type an authoring surface can receive"
		);
	}
