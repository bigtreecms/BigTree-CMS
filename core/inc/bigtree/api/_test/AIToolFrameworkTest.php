<?php
	/**
	 * AI tool framework: result envelope, per-user registry filtering + dispatch,
	 * capability summary, and the read-only search tools' permission gates.
	 *
	 * Pure — uses a fake SearchToolBackend so no DB or live provider is touched.
	 */

	use BigTree\Services\AI\AIToolResult;
	use BigTree\Services\AI\AIToolContext;
	use BigTree\Services\AI\AIToolRegistry;
	use BigTree\Services\AI\CapabilitySummary;
	use BigTree\Services\AI\Tools\SearchToolBackend;
	use BigTree\Services\AI\Tools\SearchPagesTool;
	use BigTree\Services\AI\Tools\SearchTagsTool;
	use BigTree\Services\AI\Tools\SearchUsersTool;
	use BigTree\Services\AI\Tools\GetPageTool;

	if (!class_exists("FakeSearchBackend")) {
		class FakeSearchBackend implements SearchToolBackend {
			public function searchPages($q, $limit, $user): array {

				return [["id" => 1, "nav_title" => "Home"], ["id" => 1, "nav_title" => "Home dup"]];
			}

			public function searchModules($q, $limit, $user): array {

				return [["id" => "modules-1", "name" => "News"]];
			}

			public function searchModuleEntries($q, $limit, $user): array {

				return [["module" => ["id" => "modules-1"], "items" => [["id" => 3]]]];
			}

			public function searchTags($q, $limit): array {

				return [["id" => 5, "tag" => "news"]];
			}

			public function searchUsers($q, $limit): array {

				return [["id" => 7, "name" => "Ada"]];
			}

			public function semanticSearch($q, $limit, $user): array {

				return ["pages" => [], "entries" => []];
			}

			public function getPageDetail($id, $user): array {
				if ((int)$id === 1) {
					return [
						"payload" => ["id" => 1, "title" => "Home"],
						"artifact" => ["id" => 1, "nav_title" => "Home"],
					];
				}

				return ["error" => "not found"];
			}

			public function getModuleEntryDetail($module_id, $entry_id, $user): array {

				return ["error" => "not found"];
			}

			public function extractKeywords(string $q): array {
				$q = trim($q);

				return $q === "" ? [] : [$q];
			}
		}
	}

	function ai_fake_user(int $level) {

		return (object)["id" => 100 + $level, "level" => $level, "permissions" => []];
	}

	function test_ai_tool_result_payload_shapes() {
		$ok = AIToolResult::ok(["pages" => [1, 2]], ["pages" => [["id" => 1]]]);
		T::ok($ok->isOk(), "ok() is ok");
		$ok_payload = $ok->toModelPayload();
		T::equals($ok_payload["status"], "ok", "ok payload status");
		T::equals($ok_payload["pages"], [1, 2], "ok payload merges data");
		T::equals($ok->artifacts["pages"][0]["id"], 1, "ok keeps artifacts off the model payload");
		T::ok(!isset($ok_payload["artifacts"]), "artifacts are not sent to the model");

		$denied = AIToolResult::denied("no access", ["ask an admin"]);
		$dp = $denied->toModelPayload();
		T::equals($dp["status"], "denied", "denied status");
		T::equals($dp["reason"], "no access", "denied reason");
		T::equals($dp["alternatives"], ["ask an admin"], "denied alternatives");

		$needs = AIToolResult::needsInput("Where?", [["id" => 1, "label" => "Root"]]);
		$np = $needs->toModelPayload();
		T::equals($np["status"], "needs_input", "needs_input status");
		T::equals($np["question"], "Where?", "needs_input question");

		$prop = AIToolResult::proposal("Create page", ["title" => "X"], "prop-1");
		$pp = $prop->toModelPayload();
		T::equals($pp["status"], "proposal", "proposal status");
		T::equals($pp["proposal_id"], "prop-1", "proposal id");

		$err = AIToolResult::error("query required");
		$ep = $err->toModelPayload();
		T::equals($ep["status"], "error", "error status");
		T::equals($ep["error"], "query required", "error message");
	}

	function test_ai_capability_summary() {
		T::equals(CapabilitySummary::level(ai_fake_user(2)), 2, "level from object");
		T::equals(CapabilitySummary::level(["level" => 1]), 1, "level from array");
		T::equals(CapabilitySummary::roleLabel(0), "editor", "level 0 is editor");
		T::equals(CapabilitySummary::roleLabel(1), "administrator", "level 1 is administrator");
		T::equals(CapabilitySummary::roleLabel(2), "developer", "level 2 is developer");

		$editor = CapabilitySummary::forUser(ai_fake_user(0));
		T::ok(!$editor["can_manage_users"], "editor cannot manage users");
		T::ok(!$editor["can_manage_templates"], "editor cannot manage templates");

		$admin = CapabilitySummary::forUser(ai_fake_user(1));
		T::ok($admin["can_manage_users"], "admin can manage users");
		T::ok(!$admin["can_manage_templates"], "admin cannot manage templates");

		$dev = CapabilitySummary::forUser(ai_fake_user(2));
		T::ok($dev["can_manage_templates"], "developer can manage templates");
		T::ok($dev["is_developer"], "developer flag set");

		T::ok(strpos(CapabilitySummary::promptText($editor = ai_fake_user(0)), "editor") !== false, "prompt names the role");
	}

	function test_ai_registry_filters_by_user() {
		$backend = new FakeSearchBackend();
		$registry = new AIToolRegistry();
		$registry->register(new SearchPagesTool($backend));
		$registry->register(new SearchTagsTool($backend));
		$registry->register(new SearchUsersTool($backend));

		$editor_names = array_map(function ($t) {

			return $t->name();
		}, $registry->availableTools(ai_fake_user(0)));

		T::ok(in_array("search_pages", $editor_names, true), "editor sees search_pages");
		T::ok(!in_array("search_tags", $editor_names, true), "editor does not see search_tags");
		T::ok(!in_array("search_users", $editor_names, true), "editor does not see search_users");

		$admin_names = array_map(function ($t) {

			return $t->name();
		}, $registry->availableTools(ai_fake_user(1)));

		T::ok(in_array("search_tags", $admin_names, true), "admin sees search_tags");
		T::ok(in_array("search_users", $admin_names, true), "admin sees search_users");

		$editor_defs = $registry->definitions(ai_fake_user(0));
		T::equals(count($editor_defs), 1, "editor definitions only include search_pages");
		T::equals($editor_defs[0]["function"]["name"], "search_pages", "definition is OpenAI-shaped");
	}

	function test_ai_registry_execute_and_denials() {
		$backend = new FakeSearchBackend();
		$registry = new AIToolRegistry();
		$registry->register(new SearchPagesTool($backend));
		$registry->register(new SearchTagsTool($backend));
		$registry->register(new GetPageTool($backend));

		$ctx_editor = new AIToolContext(ai_fake_user(0), 8);

		// Unknown tool -> error.
		$unknown = $registry->execute("no_such_tool", [], $ctx_editor);
		T::equals($unknown->toModelPayload()["status"], "error", "unknown tool errors");

		// search_pages runs, dedupes model data, but keeps raw rows as artifacts.
		$pages = $registry->execute("search_pages", ["query" => "home"], $ctx_editor);
		T::ok($pages->isOk(), "search_pages ok for editor");
		T::equals(count($pages->data["pages"]), 1, "duplicate id collapsed in model data");
		T::equals(count($pages->artifacts["pages"]), 2, "raw rows preserved as artifacts");

		// Empty query -> recoverable error.
		$empty = $registry->execute("search_pages", ["query" => "  "], $ctx_editor);
		T::equals($empty->toModelPayload()["status"], "error", "empty query errors");

		// Editor is blocked from search_tags at the registry (tool hidden) ...
		$denied = $registry->execute("search_tags", ["query" => "news"], $ctx_editor);
		T::equals($denied->toModelPayload()["status"], "denied", "registry denies hidden tool");

		// ... but an admin can run it.
		$ctx_admin = new AIToolContext(ai_fake_user(1), 8);
		$tags = $registry->execute("search_tags", ["query" => "news"], $ctx_admin);
		T::ok($tags->isOk(), "admin runs search_tags");
		T::equals($tags->artifacts["tags"][0]["id"], 5, "tag artifact collected");

		// get_page maps backend detail to payload + artifact.
		$page = $registry->execute("get_page", ["id" => 1], $ctx_editor);
		T::ok($page->isOk(), "get_page ok");
		T::equals($page->data["title"], "Home", "get_page payload passed through");
		T::equals($page->artifacts["pages"][0]["id"], 1, "get_page artifact collected");

		$missing = $registry->execute("get_page", ["id" => 999], $ctx_editor);
		T::equals($missing->toModelPayload()["status"], "error", "missing page errors");
	}
