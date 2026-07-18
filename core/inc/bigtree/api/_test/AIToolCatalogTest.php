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
	use BigTree\Services\AI\Tools\GetPendingChangeTool;
	use BigTree\Services\AI\Tools\PublishPendingChangeTool;
	use BigTree\Services\AI\Tools\CreateModuleEntryTool;
	use BigTree\Services\AI\Tools\UpdateModuleEntryTool;
	use BigTree\Services\AI\Tools\DeleteModuleEntryTool;
	use BigTree\Services\AI\Tools\SetModuleEntryFlagTool;
	use BigTree\Services\AI\Tools\AddTagsTool;
	use BigTree\Services\AI\Tools\RemoveTagsTool;
	use BigTree\Services\AI\Tools\RejectPendingChangeTool;
	use BigTree\Services\AI\Tools\CreateUserTool;
	use BigTree\Services\AI\Tools\UpdateUserTool;
	use BigTree\Services\AI\Tools\CreateCalloutTool;
	use BigTree\Services\AI\Tools\CreateModuleTool;
	use BigTree\Services\AI\Tools\GetModuleTool;
	use BigTree\Services\AI\Tools\GetModuleSchemaTool;
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

			/** @var array<string,mixed> */
			public $one_change = ["pending_change" => [
				"id" => 3, "title" => "Draft", "mine" => true, "can_publish" => false, "is_new_item" => false,
				"changes" => [["column" => "title", "from" => "Old", "to" => "New"]],
			]];

			public function aiPendingChanges(int $limit, $user): array { return $this->changes; }
			public function aiGetPendingChange(int $id, $user): array { return $this->one_change; }
			public function aiValidatePublishChange(array $args, $user): array { return $this->validation; }
			public function aiPublishChange(array $payload, $user): array { $this->executed = $payload; return ["mode" => "published"]; }
			public function aiValidateRejectChange(array $args, $user): array { return $this->validation; }
			public function aiRejectChange(array $payload, $user): array { $this->executed = $payload; return ["mode" => "rejected"]; }
		}
	}

	if (!class_exists("FakeModuleEntryBackend")) {
		class FakeModuleEntryBackend implements ModuleEntryToolBackend {
			use AiValidatable;

			/** @var array<string,mixed> */
			public $schema = ["schema" => ["module_id" => "m", "fields" => [
				["column" => "title", "type" => "text", "required" => true, "assistant_can_set" => true],
				["column" => "hero", "type" => "upload", "required" => true, "assistant_can_set" => false],
			]]];

			public function aiModuleSchema(string $module_id, string $form_id, $user): array { return $this->schema; }
			public function aiValidateEntryCreate(array $args, $user): array { return $this->validation; }
			public function aiCreateEntry(array $payload, $user): array { $this->executed = $payload; return ["mode" => "published"]; }
			public function aiValidateEntryUpdate(array $args, $user): array { return $this->validation; }
			public function aiUpdateEntry(array $payload, $user): array { $this->executed = $payload; return ["mode" => "pending"]; }
			public function aiValidateEntryFlag(array $args, $user): array { return $this->validation; }
			public function aiSetEntryFlag(array $payload, $user): array { $this->executed = $payload; return ["mode" => "updated"]; }
			public function aiValidateEntryDelete(array $args, $user): array { return $this->validation; }
			public function aiDeleteEntry(array $payload, $user): array { $this->executed = $payload; return ["mode" => "deleted"]; }
		}
	}

	if (!class_exists("FakeTagBackend")) {
		class FakeTagBackend implements TagToolBackend {
			use AiValidatable;

			public function aiValidateAddTags(array $args, $user): array { return $this->validation; }
			public function aiAddTags(array $payload, $user): array { $this->executed = $payload; return ["mode" => "tagged"]; }
			public function aiValidateRemoveTags(array $args, $user): array { return $this->validation; }
			public function aiRemoveTags(array $payload, $user): array { $this->executed = $payload; return ["mode" => "untagged"]; }
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

			/** @var array<string,mixed> */
			public $module = ["module" => ["id" => "modules-fake", "name" => "Fake", "is_complete" => false, "missing_setup" => ["a database table"]]];

			public function aiGetModule(string $module_id, $user): array { return $this->module; }
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
		$create_user = new CreateUserTool(new FakeUserBackend(), $store);

		foreach ([$setting, $create_user] as $tool) {
			T::ok(!$tool->isAvailable(ai_fake_user(0)), $tool->name() . " hidden from editors");
			T::ok($tool->isAvailable(ai_fake_user(1)), $tool->name() . " offered to admins");
		}

		// Even if an editor somehow names it, execute denies before staging.
		$denied = $setting->execute(["id" => "x", "value" => 1], new AIToolContext(ai_fake_user(0), 8, "1"));
		T::equals($denied->type, AIToolResult::DENIED, "editor denied at update_setting execute");
		T::equals(count($store->created), 0, "nothing staged for a denied editor");
	}

	function test_tagging_tools_are_offered_to_editors() {
		$store = new FakeProposalStore();

		// Tagging is deliberately NOT admin-gated at the tool level: attaching an
		// existing tag is something the page editor already lets any editor do. The
		// admin-only half — coining a brand-new tag — is enforced in the backend,
		// where it can tell the two cases apart.
		foreach ([new AddTagsTool(new FakeTagBackend(), $store), new RemoveTagsTool(new FakeTagBackend(), $store)] as $tool) {
			T::ok($tool->isAvailable(ai_fake_user(0)), $tool->name() . " offered to editors");
			T::ok($tool->isAvailable(ai_fake_user(1)), $tool->name() . " offered to admins");
		}
	}

	function test_tagging_tools_accept_pages_and_module_entries() {
		$store = new FakeProposalStore();
		$add = new AddTagsTool(new FakeTagBackend(), $store);
		$remove = new RemoveTagsTool(new FakeTagBackend(), $store);
		$context = new AIToolContext(ai_fake_user(0), 8, "1");

		$page = $add->execute(["page_id" => 3, "tags" => ["news"]], $context);
		T::equals($page->type, AIToolResult::PROPOSAL, "add_tags stages for a page");

		$entry = $add->execute(["module_id" => "news", "entry_id" => 7, "tags" => ["news"]], $context);
		T::equals($entry->type, AIToolResult::PROPOSAL, "add_tags stages for a module entry");

		$off = $remove->execute(["page_id" => 3, "tags" => ["news"]], $context);
		T::equals($off->type, AIToolResult::PROPOSAL, "remove_tags stages");

		// Neither target identified — a recoverable error, not a silent no-op.
		$neither = $add->execute(["tags" => ["news"]], $context);
		T::equals($neither->type, AIToolResult::ERROR, "add_tags with no target errors");
		T::equals(
			$remove->execute(["tags" => ["news"]], $context)->type,
			AIToolResult::ERROR,
			"remove_tags with no target errors"
		);
	}

	function test_reject_pending_change_tool() {
		$store = new FakeProposalStore();
		$tool = new RejectPendingChangeTool(new FakePendingBackend(), $store);

		// The decline half of the review flow — offered to editors because the backend
		// also allows a change's own author to withdraw it.
		T::ok($tool->isAvailable(ai_fake_user(0)), "reject_pending_change offered to editors");

		$staged = $tool->execute(["change_id" => 3], new AIToolContext(ai_fake_user(0), 8, "1"));
		T::equals($staged->type, AIToolResult::PROPOSAL, "reject_pending_change stages");

		$missing = $tool->execute([], new AIToolContext(ai_fake_user(0), 8, "1"));
		T::equals($missing->type, AIToolResult::ERROR, "a missing change_id is a recoverable error");
	}

	function test_admin_mutating_tools_stage_for_admin() {
		$store = new FakeProposalStore();
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

	function test_get_module_tool_surfaces_incomplete_setup() {
		$backend = new FakeModuleBackend();
		$tool = new GetModuleTool($backend);

		T::equals($tool->kind(), "read", "get_module is a read tool");
		T::ok($tool->isAvailable(ai_fake_user(0)), "offered to editors — access is re-checked per module");

		$missing = $tool->execute(["module_id" => "modules-fake"], new AIToolContext(ai_fake_user(2), 8));
		T::equals($missing->type, AIToolResult::OK, "returns the module");
		T::ok(!$missing->data["module"]["is_complete"], "a shell module reports itself incomplete");
		T::ok(!empty($missing->data["module"]["missing_setup"]), "and enumerates what it still needs");

		$blank = $tool->execute(["module_id" => "  "], new AIToolContext(ai_fake_user(2), 8));
		T::equals($blank->type, AIToolResult::ERROR, "a blank module_id is a recoverable error");

		$backend->module = ["denied" => "You do not have access to this module."];
		$denied = $tool->execute(["module_id" => "modules-fake"], new AIToolContext(ai_fake_user(0), 8));
		T::equals($denied->type, AIToolResult::DENIED, "a backend denial surfaces as denied, not error");
	}

	function test_get_pending_change_tool_returns_a_diff() {
		$backend = new FakePendingBackend();
		$tool = new GetPendingChangeTool($backend);

		T::equals($tool->kind(), "read", "get_pending_change is a read tool");

		$result = $tool->execute(["change_id" => 3], new AIToolContext(ai_fake_user(0), 8));
		T::equals($result->type, AIToolResult::OK, "returns the change");
		T::ok(!empty($result->data["pending_change"]["changes"]), "carries the field-level diff");
		T::equals(
			$result->data["pending_change"]["changes"][0]["from"],
			"Old",
			"the diff shows the value being replaced"
		);

		$missing = $tool->execute([], new AIToolContext(ai_fake_user(0), 8));
		T::equals($missing->type, AIToolResult::ERROR, "a missing change_id is a recoverable error");

		$backend->one_change = ["denied" => "not yours"];
		$denied = $tool->execute(["change_id" => 9], new AIToolContext(ai_fake_user(0), 8));
		T::equals($denied->type, AIToolResult::DENIED, "someone else's change is denied, not errored");
	}

	function test_get_module_schema_tool_flags_unsettable_fields() {
		$backend = new FakeModuleEntryBackend();
		$tool = new GetModuleSchemaTool($backend);

		T::equals($tool->kind(), "read", "get_module_schema is a read tool");

		$result = $tool->execute(["module_id" => "news"], new AIToolContext(ai_fake_user(0), 8));
		T::equals($result->type, AIToolResult::OK, "returns the schema");

		$fields = $result->data["schema"]["fields"];
		T::ok($fields[0]["assistant_can_set"], "a simple field is marked settable");
		T::ok(!$fields[1]["assistant_can_set"], "a complex field is marked unsettable");
		T::ok($fields[1]["required"], "and its requiredness is still visible");

		$blank = $tool->execute(["module_id" => "  "], new AIToolContext(ai_fake_user(0), 8));
		T::equals($blank->type, AIToolResult::ERROR, "a blank module_id is a recoverable error");

		// A module with several forms asks which to use rather than guessing.
		$backend->schema = ["ambiguous_form" => true, "forms" => [
			["id" => "form-a", "title" => "Article", "table" => "t_articles"],
			["id" => "form-b", "title" => "Press", "table" => "t_press"],
		]];
		$ambiguous = $tool->execute(["module_id" => "news"], new AIToolContext(ai_fake_user(0), 8));
		T::equals($ambiguous->type, AIToolResult::NEEDS_INPUT, "several forms → asks which");
		T::equals(count($ambiguous->options), 2, "offers both forms");
		T::equals($ambiguous->options[0]["id"], "form-a", "options carry the form id");
	}

	function test_entry_tools_ask_which_form_on_a_multi_form_module() {
		$store = new FakeProposalStore();
		$backend = new FakeModuleEntryBackend();

		// The backend reports the ambiguity; every entry-mutating tool must turn it
		// into a question instead of silently writing against the wrong table.
		$backend->validation = ["ambiguous_form" => true, "forms" => [
			["id" => "form-a", "title" => "Article", "table" => "t_articles"],
			["id" => "form-b", "title" => "", "table" => "t_press"],
		]];

		$create = (new CreateModuleEntryTool($backend, $store))
			->execute(["module_id" => "news", "data" => ["title" => "x"]], new AIToolContext(ai_fake_user(2), 8, "1"));
		T::equals($create->type, AIToolResult::NEEDS_INPUT, "create_module_entry asks which form");
		T::equals(count($store->created), 0, "nothing staged while the form is unresolved");
		T::equals($create->options[1]["label"], "form-b", "a title-less form falls back to its id as the label");

		$update = (new UpdateModuleEntryTool($backend, $store))
			->execute(["module_id" => "news", "entry_id" => 4, "data" => ["title" => "x"]], new AIToolContext(ai_fake_user(2), 8, "1"));
		T::equals($update->type, AIToolResult::NEEDS_INPUT, "update_module_entry asks which form");

		$flag = (new SetModuleEntryFlagTool($backend, $store))
			->execute(["module_id" => "news", "entry_id" => 4, "flag" => "archived", "value" => true], new AIToolContext(ai_fake_user(2), 8, "1"));
		T::equals($flag->type, AIToolResult::NEEDS_INPUT, "set_module_entry_flag asks which form");

		$delete = (new DeleteModuleEntryTool($backend, $store))
			->execute(["module_id" => "news", "entry_id" => 4], new AIToolContext(ai_fake_user(2), 8, "1"));
		T::equals($delete->type, AIToolResult::NEEDS_INPUT, "delete_module_entry asks which form");
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
