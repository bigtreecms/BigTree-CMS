<?php
	/**
	 * Phase 4 tool catalog: the read tools (capabilities, page tree, templates, media,
	 * settings, pending changes) and the two-phase mutating + developer tools (page
	 * update/archive, module entries, tags, settings, users, templates, callouts,
	 * modules).
	 *
	 * Pure — every backend is a fake scripting validation outcomes, and staging reuses
	 * the FakeProposalStore from AIProposalFrameworkTest, so no DB or live provider is
	 * touched. ai_fake_user is defined in AIToolFrameworkTest; all *Test.php files are
	 * required before any test_* runs.
	 */

	use BigTree\Services\AI\AIToolContext;
	use BigTree\Services\AI\AIToolResult;
	use BigTree\Services\AI\AIToolRegistry;
	use BigTree\Services\AI\Tools\GetMyCapabilitiesTool;
	use BigTree\Services\AI\Tools\GetPageTreeTool;
	use BigTree\Services\AI\Tools\UpdatePageTool;
	use BigTree\Services\AI\Tools\ArchivePageTool;
	use BigTree\Services\AI\Tools\ListTemplatesTool;
	use BigTree\Services\AI\Tools\GetTemplateTool;
	use BigTree\Services\AI\Tools\CreateTemplateTool;
	use BigTree\Services\AI\Tools\UpdateTemplateTool;
	use BigTree\Services\AI\Tools\ListResourcesTool;
	use BigTree\Services\AI\Tools\SearchFilesTool;
	use BigTree\Services\AI\Tools\GetSettingsTool;
	use BigTree\Services\AI\Tools\UpdateSettingTool;
	use BigTree\Services\AI\Tools\GetPendingChangesTool;
	use BigTree\Services\AI\Tools\PublishPendingChangeTool;
	use BigTree\Services\AI\Tools\CreateModuleEntryTool;
	use BigTree\Services\AI\Tools\UpdateModuleEntryTool;
	use BigTree\Services\AI\Tools\AddTagsTool;
	use BigTree\Services\AI\Tools\CreateUserTool;
	use BigTree\Services\AI\Tools\UpdateUserTool;
	use BigTree\Services\AI\Tools\CreateCalloutTool;
	use BigTree\Services\AI\Tools\CreateModuleTool;
	use BigTree\Services\AI\Tools\TemplateToolBackend;
	use BigTree\Services\AI\Tools\ResourceToolBackend;
	use BigTree\Services\AI\Tools\SettingToolBackend;
	use BigTree\Services\AI\Tools\PendingChangeToolBackend;
	use BigTree\Services\AI\Tools\ModuleEntryToolBackend;
	use BigTree\Services\AI\Tools\TagToolBackend;
	use BigTree\Services\AI\Tools\UserToolBackend;
	use BigTree\Services\AI\Tools\CalloutToolBackend;
	use BigTree\Services\AI\Tools\ModuleToolBackend;

	// A validation result any *Validate* seam can be scripted to return, plus a captured
	// "executed payload" so approval-path tests can assert what would be written.
	if (!trait_exists("AiValidatable")) {
		trait AiValidatable {
			/** @var array<string,mixed> */
			public $validation = ["ok" => true, "summary" => "Do it.", "preview" => ["k" => "v"], "payload" => ["p" => 1]];
			/** @var array<string,mixed>|null */
			public $executed = null;
		}
	}

	if (!class_exists("FakeTemplateBackend")) {
		class FakeTemplateBackend implements TemplateToolBackend {
			use AiValidatable;

			/** @var list<array<string,mixed>> */
			public $templates = [["id" => "basic", "name" => "Basic", "field_count" => 2]];
			/** @var array<string,mixed> */
			public $one = ["template" => ["id" => "basic", "name" => "Basic"]];
			/** @var array<string,mixed> */
			public $update_validation = ["ok" => true, "summary" => "Update template.", "preview" => [], "payload" => []];

			public function aiListTemplates(): array { return $this->templates; }
			public function aiGetTemplate(string $id): array { return $this->one; }
			public function aiValidateTemplateCreate(array $args, $user): array { return $this->validation; }
			public function aiCreateTemplate(array $payload, $user): array { $this->executed = $payload; return ["mode" => "created"]; }
			public function aiValidateTemplateUpdate(array $args, $user): array { return $this->update_validation; }
			public function aiUpdateTemplate(array $payload, $user): array { $this->executed = $payload; return ["mode" => "updated"]; }
		}
	}

	if (!class_exists("FakeResourceBackend")) {
		class FakeResourceBackend implements ResourceToolBackend {
			/** @var array<string,mixed> */
			public $list = ["folder" => 0, "folders" => [], "resources" => []];
			/** @var array<string,mixed> */
			public $search = ["resources" => []];

			public function aiListResources(int $folder, int $limit, $user): array { return $this->list; }
			public function aiSearchFiles(string $query, int $limit, $user): array { return $this->search; }
		}
	}

	if (!class_exists("FakeSettingBackend")) {
		class FakeSettingBackend implements SettingToolBackend {
			use AiValidatable;

			/** @var array<string,mixed> */
			public $settings = ["settings" => [["id" => "site-name", "value" => "Acme"]]];

			public function aiGetSettings(string $query, int $limit, $user): array { return $this->settings; }
			public function aiValidateSettingUpdate(array $args, $user): array { return $this->validation; }
			public function aiUpdateSetting(array $payload, $user): array { $this->executed = $payload; return ["mode" => "updated"]; }
		}
	}

	if (!class_exists("FakePendingBackend")) {
		class FakePendingBackend implements PendingChangeToolBackend {
			use AiValidatable;

			/** @var array<string,mixed> */
			public $changes = ["pending_changes" => [["id" => 3, "mine" => true, "can_publish" => false]]];

			public function aiPendingChanges(int $limit, $user): array { return $this->changes; }
			public function aiValidatePublishChange(array $args, $user): array { return $this->validation; }
			public function aiPublishChange(array $payload, $user): array { $this->executed = $payload; return ["mode" => "published"]; }
		}
	}

	if (!class_exists("FakeModuleEntryBackend")) {
		class FakeModuleEntryBackend implements ModuleEntryToolBackend {
			use AiValidatable;

			public function aiValidateEntryCreate(array $args, $user): array { return $this->validation; }
			public function aiCreateEntry(array $payload, $user): array { $this->executed = $payload; return ["mode" => "published"]; }
			public function aiValidateEntryUpdate(array $args, $user): array { return $this->validation; }
			public function aiUpdateEntry(array $payload, $user): array { $this->executed = $payload; return ["mode" => "pending"]; }
		}
	}

	if (!class_exists("FakeTagBackend")) {
		class FakeTagBackend implements TagToolBackend {
			use AiValidatable;

			public function aiValidateAddTags(array $args, $user): array { return $this->validation; }
			public function aiAddTags(array $payload, $user): array { $this->executed = $payload; return ["mode" => "tagged"]; }
		}
	}

	if (!class_exists("FakeUserBackend")) {
		class FakeUserBackend implements UserToolBackend {
			use AiValidatable;

			/** @var array<string,mixed> */
			public $update_validation = ["ok" => true, "summary" => "Update user.", "preview" => [], "payload" => []];

			public function aiValidateUserCreate(array $args, $user): array { return $this->validation; }
			public function aiCreateUser(array $payload, $user): array { $this->executed = $payload; return ["mode" => "created"]; }
			public function aiValidateUserUpdate(array $args, $user): array { return $this->update_validation; }
			public function aiUpdateUser(array $payload, $user): array { $this->executed = $payload; return ["mode" => "updated"]; }
		}
	}

	if (!class_exists("FakeCalloutBackend")) {
		class FakeCalloutBackend implements CalloutToolBackend {
			use AiValidatable;

			public function aiValidateCalloutCreate(array $args, $user): array { return $this->validation; }
			public function aiCreateCallout(array $payload, $user): array { $this->executed = $payload; return ["mode" => "created"]; }
		}
	}

	if (!class_exists("FakeModuleBackend")) {
		class FakeModuleBackend implements ModuleToolBackend {
			use AiValidatable;

			public function aiValidateModuleCreate(array $args, $user): array { return $this->validation; }
			public function aiCreateModule(array $payload, $user): array { $this->executed = $payload; return ["mode" => "created"]; }
		}
	}

	// — Read tools —

	function test_get_my_capabilities_tool() {
		$tool = new GetMyCapabilitiesTool();
		T::equals($tool->kind(), "read", "capabilities tool is read");
		T::ok($tool->isAvailable(ai_fake_user(0)), "offered to editors");

		$editor = $tool->execute([], new AIToolContext(ai_fake_user(0), 8))->data;
		T::ok(!$editor["capabilities"]["can_manage_users"], "editor cannot manage users");

		$dev = $tool->execute([], new AIToolContext(ai_fake_user(2), 8))->data;
		T::ok($dev["capabilities"]["can_manage_templates"], "developer can manage templates");
	}

	function test_get_page_tree_tool() {
		$backend = new FakePageToolBackend();
		$backend->tree = [
			"parent" => ["id" => 0, "nav_title" => "Top level", "path" => ""],
			"children" => [["id" => 5, "nav_title" => "Blog", "path" => "/blog"]],
			"can_create_here" => true,
		];
		$tool = new GetPageTreeTool($backend);

		$result = $tool->execute(["parent" => 0], new AIToolContext(ai_fake_user(0), 8));
		T::ok($result->isOk(), "page tree ok");
		T::equals(count($result->data["children"]), 1, "children passed through");
		T::equals($result->artifacts["pages"][0]["id"], 5, "child surfaced as a navigable artifact");
	}

	function test_get_page_tree_tool_error_passthrough() {
		$backend = new FakePageToolBackend();
		$backend->tree = ["error" => "nope"];
		$tool = new GetPageTreeTool($backend);

		$result = $tool->execute(["parent" => 99], new AIToolContext(ai_fake_user(0), 8));
		T::equals($result->type, AIToolResult::ERROR, "backend error becomes a recoverable error");
	}

	function test_template_read_tools() {
		$backend = new FakeTemplateBackend();
		$list = (new ListTemplatesTool($backend))->execute([], new AIToolContext(ai_fake_user(0), 8));
		T::ok($list->isOk(), "list_templates ok for an editor");
		T::equals($list->data["templates"][0]["id"], "basic", "templates passed through");

		$get = (new GetTemplateTool($backend))->execute(["id" => "basic"], new AIToolContext(ai_fake_user(0), 8));
		T::ok($get->isOk(), "get_template ok");

		$missing = (new GetTemplateTool($backend))->execute(["id" => ""], new AIToolContext(ai_fake_user(0), 8));
		T::equals($missing->type, AIToolResult::ERROR, "empty id errors");
	}

	function test_resource_read_tools() {
		$backend = new FakeResourceBackend();
		$list = (new ListResourcesTool($backend))->execute([], new AIToolContext(ai_fake_user(0), 8));
		T::ok($list->isOk(), "list_resources ok");

		$backend->list = ["denied" => "no access"];
		$denied = (new ListResourcesTool($backend))->execute(["folder" => 4], new AIToolContext(ai_fake_user(0), 8));
		T::equals($denied->type, AIToolResult::DENIED, "backend denial surfaces as denied");

		$empty = (new SearchFilesTool($backend))->execute(["query" => "  "], new AIToolContext(ai_fake_user(0), 8));
		T::equals($empty->type, AIToolResult::ERROR, "empty query errors");
	}

	function test_get_settings_tool_admin_gate() {
		$backend = new FakeSettingBackend();
		$tool = new GetSettingsTool($backend);

		T::ok(!$tool->isAvailable(ai_fake_user(0)), "hidden from editors");
		T::ok($tool->isAvailable(ai_fake_user(1)), "offered to admins");

		$denied = $tool->execute([], new AIToolContext(ai_fake_user(0), 8));
		T::equals($denied->type, AIToolResult::DENIED, "editor is denied at execute even if named");

		$ok = $tool->execute([], new AIToolContext(ai_fake_user(1), 8));
		T::ok($ok->isOk(), "admin reads settings");
	}

	function test_get_pending_changes_tool() {
		$tool = new GetPendingChangesTool(new FakePendingBackend());
		$result = $tool->execute([], new AIToolContext(ai_fake_user(0), 8));
		T::ok($result->isOk(), "pending changes ok");
		T::equals(count($result->data["pending_changes"]), 1, "changes passed through");
	}

	// — Mutating tools (staging) —

	function test_update_page_tool_stages_and_validates() {
		$backend = new FakePageToolBackend();
		$backend->update_validation = ["ok" => true, "summary" => "Update page.", "preview" => ["x" => 1], "payload" => ["id" => 2]];
		$store = new FakeProposalStore();
		$tool = new UpdatePageTool($backend, $store);

		$missing = $tool->execute([], new AIToolContext(ai_fake_user(0), 8, "1"));
		T::equals($missing->type, AIToolResult::ERROR, "missing id errors before touching the backend");

		$staged = $tool->execute(["id" => 2, "title" => "New"], new AIToolContext(ai_fake_user(0), 8, "1"));
		T::equals($staged->type, AIToolResult::PROPOSAL, "valid update stages a proposal");
		T::equals($store->created[0]["tool"], "update_page", "proposal tagged with the tool");
	}

	function test_archive_page_tool_denies_editor() {
		$backend = new FakePageToolBackend();
		$backend->archive_validation = ["denied" => "publisher required"];
		$store = new FakeProposalStore();
		$tool = new ArchivePageTool($backend, $store);

		$result = $tool->execute(["id" => 2], new AIToolContext(ai_fake_user(0), 8, "1"));
		T::equals($result->type, AIToolResult::DENIED, "archive denial passes through");
		T::equals(count($store->created), 0, "a denial stages nothing");
	}

	function test_module_entry_tools_stage() {
		$backend = new FakeModuleEntryBackend();
		$store = new FakeProposalStore();

		$create = (new CreateModuleEntryTool($backend, $store))
			->execute(["module_id" => "m1", "data" => ["title" => "x"]], new AIToolContext(ai_fake_user(0), 8, "1"));
		T::equals($create->type, AIToolResult::PROPOSAL, "create_module_entry stages");

		$update = (new UpdateModuleEntryTool($backend, $store))
			->execute(["module_id" => "m1", "entry_id" => 4, "data" => ["title" => "y"]], new AIToolContext(ai_fake_user(0), 8, "1"));
		T::equals($update->type, AIToolResult::PROPOSAL, "update_module_entry stages");

		$bad = (new UpdateModuleEntryTool($backend, $store))
			->execute(["module_id" => "m1"], new AIToolContext(ai_fake_user(0), 8, "1"));
		T::equals($bad->type, AIToolResult::ERROR, "update without entry_id errors");
	}

	function test_admin_mutating_tools_gate_by_level() {
		$store = new FakeProposalStore();
		$setting = new UpdateSettingTool(new FakeSettingBackend(), $store);
		$tag = new AddTagsTool(new FakeTagBackend(), $store);
		$create_user = new CreateUserTool(new FakeUserBackend(), $store);

		foreach ([$setting, $tag, $create_user] as $tool) {
			T::ok(!$tool->isAvailable(ai_fake_user(0)), $tool->name() . " hidden from editors");
			T::ok($tool->isAvailable(ai_fake_user(1)), $tool->name() . " offered to admins");
		}

		// Even if an editor somehow names it, execute denies before staging.
		$denied = $setting->execute(["id" => "x", "value" => 1], new AIToolContext(ai_fake_user(0), 8, "1"));
		T::equals($denied->type, AIToolResult::DENIED, "editor denied at update_setting execute");
		T::equals(count($store->created), 0, "nothing staged for a denied editor");
	}

	function test_admin_mutating_tools_stage_for_admin() {
		$store = new FakeProposalStore();
		$tag_backend = new FakeTagBackend();
		$staged = (new AddTagsTool($tag_backend, $store))
			->execute(["page_id" => 3, "tags" => ["news"]], new AIToolContext(ai_fake_user(1), 8, "1"));
		T::equals($staged->type, AIToolResult::PROPOSAL, "admin add_tags stages");

		$user_backend = new FakeUserBackend();
		$user = (new UpdateUserTool($user_backend, $store))
			->execute(["user_id" => 9, "name" => "Ada"], new AIToolContext(ai_fake_user(1), 8, "1"));
		T::equals($user->type, AIToolResult::PROPOSAL, "admin update_user stages");
	}

	function test_publish_pending_change_tool() {
		$backend = new FakePendingBackend();
		$backend->validation = ["denied" => "not a publisher"];
		$store = new FakeProposalStore();
		$tool = new PublishPendingChangeTool($backend, $store);

		T::ok($tool->isAvailable(ai_fake_user(0)), "offered to everyone (publisher is object-scoped)");

		$denied = $tool->execute(["change_id" => 3], new AIToolContext(ai_fake_user(0), 8, "1"));
		T::equals($denied->type, AIToolResult::DENIED, "non-publisher denied at validation");
	}

	// — Developer tools —

	function test_developer_tools_hidden_from_non_developers() {
		$store = new FakeProposalStore();
		$tools = [
			new CreateTemplateTool(new FakeTemplateBackend(), $store),
			new UpdateTemplateTool(new FakeTemplateBackend(), $store),
			new CreateCalloutTool(new FakeCalloutBackend(), $store),
			new CreateModuleTool(new FakeModuleBackend(), $store),
		];

		foreach ($tools as $tool) {
			T::ok(!$tool->isAvailable(ai_fake_user(0)), $tool->name() . " hidden from editors");
			T::ok(!$tool->isAvailable(ai_fake_user(1)), $tool->name() . " hidden from admins");
			T::ok($tool->isAvailable(ai_fake_user(2)), $tool->name() . " offered to developers");
		}
	}

	function test_developer_tools_stage_for_developer() {
		$store = new FakeProposalStore();
		$tpl = (new CreateTemplateTool(new FakeTemplateBackend(), $store))
			->execute(["id" => "landing"], new AIToolContext(ai_fake_user(2), 8, "1"));
		T::equals($tpl->type, AIToolResult::PROPOSAL, "create_template stages for a developer");

		$mod = (new CreateModuleTool(new FakeModuleBackend(), $store))
			->execute(["name" => "Press"], new AIToolContext(ai_fake_user(2), 8, "1"));
		T::equals($mod->type, AIToolResult::PROPOSAL, "create_module stages for a developer");
	}

	// — Registry filtering across the catalog —

	function test_catalog_registry_filters_by_level() {
		$store = new FakeProposalStore();
		$registry = new AIToolRegistry();
		$registry->register(new GetMyCapabilitiesTool());
		$registry->register(new GetSettingsTool(new FakeSettingBackend()));
		$registry->register(new UpdateSettingTool(new FakeSettingBackend(), $store));
		$registry->register(new CreateTemplateTool(new FakeTemplateBackend(), $store));

		$names = function ($level) use ($registry) {

			return array_map(function ($t) {

				return $t->name();
			}, $registry->availableTools(ai_fake_user($level)));
		};

		$editor = $names(0);
		T::ok(in_array("get_my_capabilities", $editor, true), "editor sees capabilities");
		T::ok(!in_array("get_settings", $editor, true), "editor does not see get_settings");
		T::ok(!in_array("update_setting", $editor, true), "editor does not see update_setting");
		T::ok(!in_array("create_template", $editor, true), "editor does not see create_template");

		$admin = $names(1);
		T::ok(in_array("get_settings", $admin, true), "admin sees get_settings");
		T::ok(in_array("update_setting", $admin, true), "admin sees update_setting");
		T::ok(!in_array("create_template", $admin, true), "admin does not see create_template");

		$dev = $names(2);
		T::ok(in_array("create_template", $dev, true), "developer sees create_template");
	}
