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
	 * quietly skipping whatever they can't find — and read by audit #21's catalog
	 * contract as well, which asks the complementary question (every *declared* type
	 * is authorable somewhere) off the same list, since two lists that both have to
	 * name the same type are two lists that will eventually disagree.
	 *
	 * @return array<string,string>
	 */
	function ai_unregistered_field_types(): array {

		return [
			// Its value is generated server-side from the entry's own address columns
			// (BigTreeGeocoding, applyEntryProcessors) and written into `latitude` and
			// `longitude` rather than a column of its own, so adding one to a form is
			// not the whole of installing it and the field picker has never offered it.
			// FieldTypeDomain refuses to write a value for it for the same reason
			// (DERIVED_TYPES).
			"geocoding" => "legacy only — derived server-side into latitude/longitude, not offered by the registry",
			// `many-to-many` sat here for the same "declared everywhere, absent from
			// getCachedFieldTypes" reason until audit #21 A2, which found the absence
			// was the bug rather than the state of the world: everything else in the
			// stack implemented it, so the catalog omission made one advertised
			// argument a promise that could never be kept and left two guards defending
			// a path nothing could reach. It is offered for modules now and needs no
			// excuse.
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

	/**
	 * Audit #13 guard E2: the closed-vocabulary contract.
	 *
	 * #11 A2 was a hand-written type list nobody derived. #12 B1 was a relation
	 * whose candidate ids no tool could produce. #13 A2 is the same mistake a third
	 * time: `icon` is a free string over a 54-slug vocabulary, declared as "Optional
	 * icon identifier.", validated nowhere, and discoverable only by copying a slug
	 * off some other module. A human never types this field — they click a grid —
	 * so the assistant is the only writer without a picker, and the model reaches for
	 * Lucide names ("newspaper", "calendar-days") and gets a fallback box.
	 *
	 * Every enumerated domain the assistant writes into has to be one derivation
	 * shared by every copy of it, and every tool argument over such a domain has to
	 * name the validator that checks it. The single source is `BigTree\Api\ModuleIcons`
	 * (moved off the legacy `BigTreeAdmin::$IconClasses`, which now just points at
	 * it): `IconDomain` reads it, `GET /module-icons` serves it to the SPA, and the
	 * SPA no longer keeps a static copy — so the set-equality below is between the
	 * source, the validator, the legacy mirror and the endpoint, and the SPA leg is
	 * a coverage check instead (see the following test).
	 *
	 * @return array<string,array<string,mixed>>
	 */
	function ai_closed_vocabularies(): array {

		return [
			'BigTree\Api\ModuleIcons' => [
				"what" => "the module icon vocabulary the designer's picker offers",
				"domain" => [\BigTree\Services\AI\IconDomain::class, "slugs"],
				// Every AI tool argument written from this vocabulary.
				"arguments" => ["create_module.icon", "update_module.icon", "scaffold_module.icon"],
				// Every seam that has to consult the domain — staging and approval both,
				// per the standing "never trusted on the way back out" rule.
				"seams" => [
					[\BigTree\Services\ModuleService::class, "aiValidateModuleCreate"],
					[\BigTree\Services\ModuleService::class, "aiValidateModuleUpdate"],
					[\BigTree\Services\ModuleService::class, "aiValidateModuleScaffold"],
					[\BigTree\Services\ModuleService::class, "aiCreateModule"],
					[\BigTree\Services\ModuleService::class, "aiUpdateModule"],
					[\BigTree\Services\ModuleService::class, "aiScaffoldModule"],
				],
			],
			'BigTreeAdmin::$ActionClasses' => [
				"what" => "the same vocabulary plus the five action-only glyphs",
				"exempt" => "a module action's glyph is only reachable through creating or editing an action, "
					. "which is declined — scaffold_module sets its own from a fixed list of four",
			],
		];
	}

	/** The slug keys the SPA's LEGACY_ICON_MAP maps to a Lucide glyph, parsed as text. */
	function ai_spa_icon_map_keys(): array {
		$source = (string)@file_get_contents(SERVER_ROOT . "spa/src/lib/legacyIcons.ts");

		if (!preg_match('/LEGACY_ICON_MAP:\s*Record<string,\s*LucideIcon>\s*=\s*\{(.*?)\n\};/s', $source, $match)) {

			return [];
		}

		// Keys are bare identifiers at the start of a line: `news: Newspaper,`.
		preg_match_all('/^\t([a-z0-9_]+):/m', $match[1], $keys);

		return $keys[1];
	}

	/**
	 * E2a: one vocabulary, one source, no drift. The PHP source of truth
	 * (BigTree\Api\ModuleIcons), the domain the assistant validates against, the
	 * legacy property that now points at it, and the endpoint that serves it to the
	 * SPA must all be the same set. (The SPA's own coverage is the next test — it no
	 * longer holds a copy of the list to compare set-equal.)
	 */
	function test_the_icon_vocabulary_is_one_set_everywhere_it_appears() {
		$canonical = \BigTree\Api\ModuleIcons::slugs();
		$domain = \BigTree\Services\AI\IconDomain::slugs();
		$legacy = \BigTreeAdmin::$IconClasses;
		$endpoint = (new \BigTree\Services\ModuleService())->listIcons(new \BigTree\Api\Request())->body["data"]["icons"];

		T::ok(count($canonical) > 40, "the canonical vocabulary was read (" . count($canonical) . " slugs)");

		sort($canonical);
		sort($domain);
		sort($legacy);
		sort($endpoint);

		T::equals($domain, $canonical, "IconDomain reads the ModuleIcons source rather than copying it");
		T::equals($legacy, $canonical, "the legacy BigTreeAdmin::\$IconClasses property still mirrors the source");
		T::equals($endpoint, $canonical, "and GET /module-icons serves exactly that set to the SPA");
	}

	/**
	 * E2a′: the SPA holds no copy of the vocabulary to drift, and every slug the
	 * endpoint can return has a glyph in the SPA's presentation map — so a fetched
	 * slug always renders, never as the <Box /> fallback. This is what replaces the
	 * old three-way set-equality now that the list is served rather than mirrored.
	 */
	function test_the_spa_fetches_the_vocabulary_and_covers_every_slug() {
		$source = (string)@file_get_contents(SERVER_ROOT . "spa/src/lib/legacyIcons.ts");

		T::ok($source !== "", "the SPA icon module was read");
		T::ok(
			!preg_match('/export\s+const\s+MODULE_ICON_SLUGS/', $source),
			"the SPA no longer declares a static MODULE_ICON_SLUGS — the list is fetched from GET /module-icons"
		);

		$map_keys = ai_spa_icon_map_keys();
		T::ok($map_keys !== [], "LEGACY_ICON_MAP was parsed out of the SPA (" . count($map_keys) . " keys)");

		$canonical = \BigTree\Api\ModuleIcons::slugs();
		$uncovered = array_values(array_diff($canonical, $map_keys));

		T::equals(
			implode(", ", $uncovered),
			"",
			"every slug the endpoint can return has a Lucide glyph, so none renders as the fallback box"
		);
	}

	/**
	 * E2b: every tool argument over a closed vocabulary is classified, and every
	 * classified argument is really declared. The enumeration runs from the tools, so
	 * a new tool gaining an `icon` argument fails here rather than shipping unchecked.
	 */
	function test_every_closed_vocabulary_argument_names_its_validator() {
		$registry = ai_wiring_registry();
		$developer = ai_wiring_user(2);
		$declared = [];

		foreach ($registry->availableTools($developer) as $tool) {
			$properties = $tool->definition($developer)["function"]["parameters"]["properties"] ?? [];

			foreach (is_array($properties) ? array_keys($properties) : [] as $argument) {
				$declared[] = $tool->name() . "." . $argument;
			}
		}

		$claimed = [];

		foreach (ai_closed_vocabularies() as $vocabulary => $entry) {
			if (isset($entry["exempt"])) {
				T::ok(trim((string)$entry["exempt"]) !== "", "{$vocabulary} states why nothing writes into it");

				continue;
			}

			foreach ((array)($entry["arguments"] ?? []) as $argument) {
				$claimed[] = $argument;
				T::ok(in_array($argument, $declared, true), "{$vocabulary} is written by the declared argument {$argument}");
			}

			foreach ((array)($entry["seams"] ?? []) as [$class, $method]) {
				$body = ai_surface_method_body($class, $method);
				T::ok(
					strpos($body, "IconDomain") !== false,
					"{$method} consults the domain rather than trusting the value"
				);
			}
		}

		// The other direction: an `icon` argument nobody classified.
		$unclassified = [];

		foreach ($declared as $argument) {
			if (substr($argument, -5) === ".icon" && !in_array($argument, $claimed, true)) {
				$unclassified[] = $argument;
			}
		}

		T::equals(implode(", ", $unclassified), "", "no tool declares an icon argument outside the vocabulary contract");
	}

	/**
	 * E2c: the domain refuses what the picker couldn't produce, and says what the
	 * options are — the difference between a wall and a way forward.
	 */
	function test_the_icon_domain_refuses_a_slug_the_picker_has_no_grid_cell_for() {
		$refusal = (string)\BigTree\Services\AI\IconDomain::violation("newspaper");

		T::ok($refusal !== "", "a Lucide-shaped name the CMS has never had is refused");
		T::ok(strpos($refusal, "news") !== false, "and the refusal lists the slugs that do exist");

		T::equals(\BigTree\Services\AI\IconDomain::violation("news"), null, "a real slug is accepted");
		T::equals(\BigTree\Services\AI\IconDomain::violation(""), null, "and no icon at all is always fine");
		T::equals(\BigTree\Services\AI\IconDomain::violation("News"), null, "case is not the model's problem");
	}

	/**
	 * E2d: the vocabulary is discoverable, not guessable. get_module hands back the
	 * whole list, the way get_module_schema hands back a list field's options.
	 */
	function test_the_icon_vocabulary_is_offered_on_the_read_side() {
		$body = ai_surface_method_body(\BigTree\Services\ModuleService::class, "aiGetModule");

		T::ok(strpos($body, "icon_options") !== false, "get_module offers the icon vocabulary");

		foreach (["create_module", "update_module", "scaffold_module"] as $name) {
			$tool = ai_wiring_registry()->get($name);
			T::ok($tool !== null, "{$name} is registered");
			$description = (string)($tool->definition(ai_wiring_user(2))["function"]["parameters"]["properties"]["icon"]["description"] ?? "");
			T::ok(
				strpos($description, "get_module") !== false,
				"{$name}'s icon argument points at the tool that lists the valid slugs"
			);
		}
	}
