<?php
	/**
	 * Audit #22 guard B5: the value-domain parity contract.
	 *
	 * Every guard in this suite tests a seam against a *tool argument* or a *route body
	 * field*. None of them tests the value seams against each other — and that is
	 * exactly the gap audit #22 A1 fell through. Audits #11 B1 and #11 B2 changed the
	 * answer to "may the assistant write a value for this field type?" for five types
	 * (`image-reference`, `file-reference`, `video-reference`, `one-to-many`,
	 * `many-to-many`) by adding two overrides to `FieldTypeDomain::refusal()`. Two of
	 * the three seams that accept a value against a field type were given the resolvers
	 * that make those types safe. The third — `SettingService::aiCheckSettingValue` —
	 * was not, and nothing noticed for eleven audits: it fell through to "anything left
	 * is a scalar type with no enumerable domain, store as given" and wrote whatever the
	 * model produced into a first-class, admin-creatable reference setting.
	 *
	 * So the contract is: a seam that accepts a value for a type `refusal()` admits must
	 * name the domain that owns that type. The domain classes exist precisely because
	 * there is one implementation of each rule; a seam that names none of them is a
	 * seam re-deciding the rule by omission.
	 *
	 * Direct bodies only, the lesson `AIDestructiveDisclosureTest` encodes: expanding
	 * helpers makes everything look like everything. A seam that reaches its domain
	 * through a wrapper declares that wrapper as an exemption, and the exemption is
	 * checked too — one named hop, whose own body must carry the token.
	 *
	 * An exemption is always a claim that the check still happens. It is never a licence
	 * for a seam to skip it.
	 */

	use BigTree\Services\AutoModuleService;
	use BigTree\Services\PageService;
	use BigTree\Services\SettingService;
	use BigTree\Services\AI\FieldTypeDomain;

	/**
	 * The seams that accept a proposed value against a field type. Each takes a value
	 * the model wrote, decides whether it may be stored, and returns the form to store.
	 *
	 * @return array<string,array{0:string,1:string}> label => [class, method]
	 */
	function ai_value_domain_seams(): array {

		return [
			"page content (aiSiftResourceContent)" => [PageService::class, "aiSiftResourceContent"],
			"module entry (aiSiftEntryData)" => [AutoModuleService::class, "aiSiftEntryData"],
			"setting value (aiCheckSettingValue)" => [SettingService::class, "aiCheckSettingValue"],
		];
	}

	/**
	 * The value domains, each owning a family of field types and named in a seam by one
	 * token.
	 *
	 * The reference family is read from `FieldTypeDomain` rather than listed, so a
	 * fourth reference type is loud in every seam the day it is added — which is the
	 * half of A1's cause this map is here to remove.
	 *
	 * @return array<string,array{types:list<string>,token:string,owner:string}>
	 */
	function ai_value_domains(): array {

		return [
			"reference" => [
				"types" => FieldTypeDomain::referenceTypes(),
				"token" => "ResourceReferenceDomain::resolve",
				"owner" => "core/inc/bigtree/services/AI/ResourceReferenceDomain.php",
			],
			"one-to-many" => [
				"types" => ["one-to-many"],
				"token" => "RelationDomain::resolveOneToMany",
				"owner" => "core/inc/bigtree/services/AI/RelationDomain.php",
			],
			"many-to-many" => [
				"types" => ["many-to-many"],
				"token" => "RelationDomain::resolveManyToMany",
				"owner" => "core/inc/bigtree/services/AI/RelationDomain.php",
			],
			"list" => [
				"types" => ["list"],
				"token" => "FieldOptionDomain",
				"owner" => "core/inc/bigtree/services/AI/FieldOptionDomain.php",
			],
			"link" => [
				"types" => ["link"],
				"token" => "FieldTypeDomain::linkShapeViolation",
				"owner" => "core/inc/bigtree/services/AI/FieldTypeDomain.php",
			],
		];
	}

	/**
	 * Where a seam's domain check happens when it is not in the seam's own body.
	 *
	 * Three kinds, all verifiable — an exemption nobody can check is a comment:
	 *
	 *  - `via` [class, method]: one declared hop. The seam calls that method by name and
	 *    that method's body carries the domain's token.
	 *  - `own` [class, method]: the seam calls that method by name and it implements the
	 *    same rule itself, for a reason the entry has to give.
	 *  - `refused` "phrase": the seam refuses the type outright, and the phrase is in its
	 *    body — so deleting the refusal fails here rather than turning into a silent
	 *    fall-through, which is precisely how A1 read.
	 *
	 * @return array<string,array<string,array<string,mixed>>> seam => domain => exemption
	 */
	function ai_value_domain_exemptions(): array {

		return [
			"page content (aiSiftResourceContent)" => [
				"many-to-many" => [
					"refused" => "many-to-many relationship, which only works on a",
					"why" => "a many-to-many is a connecting-table relation between two module tables, and a "
						. "page's content is a JSON blob with no row of its own on either side; the page write "
						. "path has no \$mtm argument to hand a descriptor to",
				],
				"link" => [
					"via" => [PageService::class, "aiLinkShapeViolation"],
					"why" => "the seam's schema entries carry the title and type as separate keys, so the shared "
						. "check is called through a one-line wrapper that unpacks them",
				],
			],
			"module entry (aiSiftEntryData)" => [
				"list" => [
					"via" => [AutoModuleService::class, "aiOptionViolation"],
					"why" => "called through the wrapper audit #7 B1 added when the option domain was extracted, "
						. "which exists so the entry path and the page path cannot drift apart again",
				],
			],
			"setting value (aiCheckSettingValue)" => [
				"many-to-many" => [
					"refused" => "many-to-many relationship, which only works on a",
					"why" => "a setting is a single stored value with no row for a connecting table to relate; the "
						. "settings use case does not offer the type, and an extension that registers it is "
						. "refused with that reason rather than by the scalar guard's wrong one",
				],
				"list" => [
					"own" => [SettingService::class, "aiCheckListSettingValue"],
					"why" => "FieldOptionDomain::violation reads a precomputed `options` key that the module-form "
						. "and template schema passes build and a setting definition has none of; the settings "
						. "check resolves the same static/db domain from the definition's own settings inline. "
						. "It is deliberately looser for state/country lists, matching how loosely the admin "
						. "treats them — sharing the domain would silently start enforcing them",
				],
			],
		];
	}

	/** The types of a domain family that `refusal()` currently admits. */
	function ai_value_domain_settable_types(array $domain): array {

		return array_values(array_filter($domain["types"], function (string $type): bool {

			return FieldTypeDomain::isSettable($type);
		}));
	}

	/**
	 * B5: every value seam routes every settable type family through the domain that
	 * owns it.
	 *
	 * Fails on `aiCheckSettingValue`'s reference and one-to-many cells as the tree stood
	 * at 73fbcac35, which is the A1 defect.
	 */
	function test_every_value_seam_routes_each_type_family_through_its_domain() {
		$exemptions = ai_value_domain_exemptions();
		$unrouted = [];

		foreach (ai_value_domain_seams() as $seam => [$class, $method]) {
			$body = ai_surface_method_body($class, $method);
			T::ok($body !== "", "{$seam}'s source was read");

			foreach (ai_value_domains() as $name => $domain) {
				if (!ai_value_domain_settable_types($domain)) {

					continue;
				}

				if (strpos($body, $domain["token"]) !== false) {

					continue;
				}

				$exemption = $exemptions[$seam][$name] ?? null;

				if ($exemption === null) {
					$unrouted[] = "{$seam}: {$name} (" . implode(", ", ai_value_domain_settable_types($domain))
						. ") reaches no domain";

					continue;
				}

				$failure = ai_value_domain_exemption_failure($seam, $name, $domain, $body, $exemption);

				if ($failure !== "") {
					$unrouted[] = $failure;
				}
			}
		}

		T::equals(
			implode("; ", $unrouted),
			"",
			"every value seam routes each settable type family through the domain that owns it"
		);
	}

	/**
	 * Why an exemption doesn't hold, or "" when it does.
	 *
	 * @param array<string,mixed> $domain
	 * @param array<string,mixed> $exemption
	 */
	function ai_value_domain_exemption_failure(
		string $seam,
		string $name,
		array $domain,
		string $body,
		array $exemption
	): string {
		if (trim((string)($exemption["why"] ?? "")) === "") {

			return "{$seam}: the {$name} exemption states no reason";
		}

		if (isset($exemption["refused"])) {

			return strpos($body, (string)$exemption["refused"]) !== false
				? ""
				: "{$seam}: {$name} is claimed refused, but \"" . (string)$exemption["refused"]
					. "\" is no longer in the seam";
		}

		$hop = $exemption["via"] ?? $exemption["own"] ?? null;

		if (!is_array($hop) || !method_exists($hop[0], $hop[1])) {

			return "{$seam}: the {$name} exemption names no method that exists";
		}

		if (strpos($body, $hop[1]) === false) {

			return "{$seam}: {$name} is claimed to be checked in {$hop[1]}, which the seam no longer calls";
		}

		// A `via` hop is a wrapper over the shared domain, so it has to still carry the
		// token. An `own` hop is a separate implementation by declaration — the reason
		// field is what carries that claim, and the call above is what proves it runs.
		if (isset($exemption["via"]) && strpos(ai_surface_method_body($hop[0], $hop[1]), $domain["token"]) === false) {

			return "{$seam}: {$name} is claimed to reach {$domain["token"]} through {$hop[1]}, which no longer "
				. "calls it";
		}

		return "";
	}

	/**
	 * The declared domains are real: a token nothing implements is a guard checking for
	 * something that no longer exists, which passes forever (the shape
	 * AIWriteSideEffectParityTest's reality leg guards).
	 */
	function test_every_declared_value_domain_exists() {
		$missing = [];

		foreach (ai_value_domains() as $name => $domain) {
			$file = SERVER_ROOT . $domain["owner"];

			if (!is_readable($file)) {
				$missing[] = "{$name}: {$domain["owner"]} is not readable";

				continue;
			}

			// The token as a seam writes it (`Class::method`), against the owner's own
			// declaration (`function method`).
			$member = strpos($domain["token"], "::") !== false
				? substr($domain["token"], strpos($domain["token"], "::") + 2)
				: "";
			$source = (string)file_get_contents($file);

			if ($member !== "" && strpos($source, "function {$member}(") === false) {
				$missing[] = "{$name}: {$domain["owner"]} declares no {$member}()";
			}

			T::ok(
				ai_value_domain_settable_types($domain) !== [],
				"the {$name} domain still owns at least one type the assistant may write"
			);
		}

		T::equals(implode("; ", $missing), "", "every declared value domain is a class that implements it");
	}

	/**
	 * …and no exemption outlives the difference it excuses. A seam that has since
	 * adopted the domain directly, while keeping its exemption, hides the next
	 * divergence in the same cell — which is how the settings seam looked accounted for
	 * while it was the one seam accounting for nothing.
	 */
	function test_no_value_domain_exemption_is_stale() {
		$seams = ai_value_domain_seams();
		$domains = ai_value_domains();
		$stale = [];

		foreach (ai_value_domain_exemptions() as $seam => $entries) {
			if (!isset($seams[$seam])) {
				$stale[] = "{$seam} (no such value seam)";

				continue;
			}

			$body = ai_surface_method_body($seams[$seam][0], $seams[$seam][1]);

			foreach ($entries as $name => $exemption) {
				if (!isset($domains[$name])) {
					$stale[] = "{$seam}: {$name} (no such value domain)";

					continue;
				}

				if (strpos($body, $domains[$name]["token"]) !== false) {
					$stale[] = "{$seam}: {$name}";
				}
			}
		}

		T::equals(implode(", ", $stale), "", "no exemption excuses a domain the seam now names directly");
	}
