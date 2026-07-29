<?php
	/**
	 * Audit #11 guards E2 and E3: the AI value path's idea of what a field type is
	 * has to match the CMS's.
	 *
	 * Audits #1–#10 all read a field definition and trusted it. #11 A2 audited the
	 * lists doing the reading: across `AI_SIMPLE_FIELD_TYPES`,
	 * `AI_SIMPLE_RESOURCE_TYPES` and the two setting lists, ten of eighteen entries
	 * named field types this CMS has never had, and the one real type that was
	 * missing (`link`) was missing precisely because a long plausible list reads as a
	 * reviewed one.
	 *
	 *  - E2 asserts every type the assistant may write is a type the CMS can actually
	 *    install on a form, template, callout or setting. Fails on ten ids as the
	 *    lists stood at 612ef9e10.
	 *  - E3 asserts the coverage is total and reasoned: every installable type is
	 *    either settable or refused with a stated reason, and no value path keeps a
	 *    private type list that could disagree with the others. Fails on `link`,
	 *    which was settable as a setting and refused on pages and entries.
	 */

	use BigTree\Services\AutoModuleService;
	use BigTree\Services\FieldTypeService;
	use BigTree\Services\PageService;
	use BigTree\Services\SettingService;
	use BigTree\Services\AI\FieldTypeDomain;

	/** The four use cases a field type can be installed for. */
	function ai_field_use_cases(): array {

		return ["modules", "templates", "callouts", "settings"];
	}

	/**
	 * Types that appear in stored form definitions but that the field-type registry
	 * doesn't offer, with the reason. Kept explicit so E2/E3 stay total rather than
	 * quietly skipping whatever they can't find.
	 *
	 * @return array<string,string>
	 */
	function ai_unregistered_field_types(): array {

		return [
			// Declared in field-type-schemas.php and handled everywhere (validateMtm,
			// the `__mtm__` write argument) but absent from getCachedFieldTypes, so the
			// Module Designer can't add a new one. Legacy forms still carry them.
			"many-to-many" => "legacy only — real in stored forms, not offered by the registry",
			// Same shape: processed by applyEntryProcessors, not installable.
			"geocoding" => "legacy only — derived server-side, not offered by the registry",
		];
	}

	/**
	 * E2: every field type the assistant may write is one the CMS actually has.
	 *
	 * The failing ids at 612ef9e10 were htmleditor, simple-editor, code, number,
	 * currency, phone, email, color, select and radio.
	 */
	function test_every_settable_field_type_is_installable() {
		$installable = [];

		foreach (ai_field_use_cases() as $use_case) {
			$installable = array_merge($installable, FieldTypeService::availableFieldTypeIds($use_case));
		}

		$installable = array_unique($installable);

		// Asked of a *universe*, not of the settable set: a check that only enumerates
		// what the classifier already returns can never see a name the classifier lets
		// through some other way, which is exactly how ten fictional ids survived four
		// audits inside a list nobody enumerated against the registry.
		$universe = array_unique(array_merge(
			array_map("strval", array_keys(FieldTypeService::schemas())),
			$installable,
			ai_phantom_field_types()
		));
		$phantom = [];

		foreach ($universe as $type) {
			if (
				FieldTypeDomain::isSettable((string)$type)
				&& !in_array($type, $installable, true)
				&& !isset(ai_unregistered_field_types()[$type])
			) {
				$phantom[] = (string)$type;
			}
		}

		T::equals(implode(", ", $phantom), "", "every settable field type is installable somewhere");

		// The specific fiction A2 found, named so a reintroduction fails loudly.
		foreach (ai_phantom_field_types() as $phantom_type) {
			T::ok(
				!FieldTypeDomain::isSettable($phantom_type),
				"\"{$phantom_type}\" is not a field type this CMS has, and isn't settable"
			);
		}
	}

	/**
	 * The ten ids the AI type allowlists named at 612ef9e10 that this CMS has never
	 * had. Kept by name so reintroducing one fails rather than passing inertly.
	 *
	 * @return list<string>
	 */
	function ai_phantom_field_types(): array {

		return [
			"htmleditor", "simple-editor", "code", "number", "currency",
			"phone", "email", "color", "select", "radio",
		];
	}

	/**
	 * E3: every installable field type is classified — settable, or refused with a
	 * reason a human could act on.
	 */
	function test_every_installable_field_type_is_classified() {
		$unreasoned = [];

		foreach (ai_field_use_cases() as $use_case) {
			foreach (FieldTypeService::availableFieldTypeIds($use_case) as $type) {
				$refusal = FieldTypeDomain::refusal((string)$type);

				if ($refusal === "") {

					continue;
				}

				// A refusal has to say something. The old wall said the same sentence
				// for an upload, a matrix and a relationship.
				if (strlen($refusal) < 30) {
					$unreasoned[] = "{$use_case}/{$type}";
				}
			}
		}

		T::equals(
			implode(", ", $unreasoned),
			"",
			"every refused field type is refused with a stated reason"
		);

		// The reasons are differentiated, not one wall wearing five hats.
		$reasons = array_unique([
			FieldTypeDomain::refusal("image"),
			FieldTypeDomain::refusal("matrix"),
			FieldTypeDomain::refusal("route"),
		]);
		T::equals(count($reasons), 3, "an upload, a matrix and a generated route are refused differently");
	}

	/**
	 * E3: the value paths share one classification rather than keeping private lists
	 * that can disagree. This is the structural half — the reason `link` could be
	 * settable in one place and refused in two for the life of the framework.
	 */
	function test_no_ai_value_path_keeps_a_private_field_type_list() {
		$sources = [
			"PageService" => __DIR__ . "/../../services/PageService.php",
			"AutoModuleService" => __DIR__ . "/../../services/AutoModuleService.php",
			"SettingService" => __DIR__ . "/../../services/SettingService.php",
		];
		$survivors = [];

		foreach ($sources as $label => $path) {
			$source = (string)file_get_contents($path);

			foreach (["AI_SIMPLE_FIELD_TYPES", "AI_SIMPLE_RESOURCE_TYPES", "AI_UNSETTABLE_SETTING_TYPES",
				"AI_SCALAR_SETTING_TYPES"] as $const) {
				if (strpos($source, "const {$const}") !== false) {
					$survivors[] = "{$label}::{$const}";
				}
			}

			T::ok(
				strpos($source, "FieldTypeDomain::") !== false,
				"{$label} classifies field types through the shared derivation"
			);
		}

		T::equals(implode(", ", $survivors), "", "no value path declares its own settable-type list");
	}

	/**
	 * A2/B3: `link` is a real, first-class string type. It was authorable as a
	 * setting and invisible on pages and entries, which is the drift E3 exists to
	 * stop — so it gets its own leg.
	 */
	function test_link_is_settable_and_shape_checked() {
		T::ok(FieldTypeDomain::isSettable("link"), "`link` is settable");
		T::ok(
			in_array("link", PageService::AI_LINK_BEARING_TYPES, true),
			"`link` is still recognised as carrying a link token"
		);

		T::ok(
			FieldTypeDomain::linkShapeViolation("Read more", "link", "our pricing page") !== null,
			"a link field refuses prose"
		);
		T::equals(
			FieldTypeDomain::linkShapeViolation("Read more", "link", "https://example.com/pricing/"),
			null,
			"and accepts a full address"
		);
		T::equals(
			FieldTypeDomain::linkShapeViolation("Read more", "link", "ipl://cGFnZXM6NDI="),
			null,
			"a stored internal-link token round-trips"
		);
		T::equals(
			FieldTypeDomain::linkShapeViolation("Read more", "link", "mailto:hi@example.com"),
			null,
			"a mailto address is a link"
		);
		T::equals(
			FieldTypeDomain::linkShapeViolation("Read more", "link", ""),
			null,
			"emptiness is `required`'s business, not this check's"
		);
		T::equals(
			FieldTypeDomain::linkShapeViolation("Headline", "text", "our pricing page"),
			null,
			"and the check says nothing about any other type"
		);
	}

	/**
	 * A2: the markup subset is one type. `htmleditor`, `simple-editor` and `code`
	 * were three quarters of a list describing a set of one — `simple` is a *setting*
	 * on `html`, not a type of its own.
	 */
	function test_the_markup_subset_names_only_real_types() {
		foreach (PageService::AI_HTML_RESOURCE_TYPES as $type) {
			T::ok(
				in_array($type, FieldTypeService::availableFieldTypeIds("templates"), true),
				"the markup type \"{$type}\" is installable on a template"
			);
		}

		foreach (PageService::AI_LINK_BEARING_TYPES as $type) {
			T::ok(
				FieldTypeDomain::isSettable($type),
				"the link-bearing type \"{$type}\" is one the assistant can actually write"
			);
		}
	}

	/**
	 * A3: `sub_type` is where email-ness and website-ness live in this CMS, so both
	 * schemas have to hand it to the model. Without it a field the developer marked
	 * as an email address is presented as an unadorned text field.
	 */
	function test_both_value_schemas_emit_sub_type() {
		foreach ([
			"entries" => [AutoModuleService::class, "aiEntrySchema"],
			"page content" => [PageService::class, "aiTemplateResourceSchema"],
		] as $label => [$class, $method]) {
			$body = ai_surface_method_body($class, $method);
			T::ok(strpos($body, '"sub_type"') !== false, "the {$label} schema emits sub_type");
		}

		// And it is a declared setting of the type that carries it, not a key someone
		// invented at this seam (E1's rule, applied forwards).
		T::ok(
			in_array("sub_type", ai_field_setting_ids("text"), true),
			"`sub_type` is a declared setting of the `text` field type"
		);
	}
