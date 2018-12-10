<?php
	// BigTree 4.4 -- prerelease
	
	// We're going to convert all the database configuration to JSON
	$used_ids = [];
	$get_unique_id = function($prefix) use ($used_ids) {
		while (empty($uniq_id) || in_array($uniq_id, $used_ids)) {
			$uniq_id = $prefix.uniqid(true);
		}

		return $uniq_id;
	};

	$groups = SQL::fetchAll("SELECT * FROM bigtree_callout_groups");
	$json = [];

	foreach ($groups as $group) {
		$group["callouts"] = json_decode($group["callouts"], true);
		$json[] = $group;
	}

	BigTree::putFile(SERVER_ROOT."custom/json-db/callout-groups.json", BigTree::json($json));

	$callouts = SQL::fetchAll("SELECT * FROM bigtree_callouts");
	$json = [];

	foreach ($callouts as $callout) {
		$callout["resources"] = json_decode($callout["resources"], true);
		$json[] = $callout;
	}

	BigTree::putFile(SERVER_ROOT."custom/json-db/callouts.json", BigTree::json($json));

	$field_types = SQL::fetchAll("SELECT * FROM bigtree_field_types");
	$json = [];

	foreach ($field_types as $field_type) {
		$field_type["use_cases"] = json_decode($field_type["use_cases"], true);
		$json[] = $field_type;
	}

	BigTree::putFile(SERVER_ROOT."custom/json-db/field-types.json", BigTree::json($json));
	
	$settings = SQL::fetchAll("SELECT * FROM bigtree_settings WHERE system != 'on'");
	$json = [];

	foreach ($settings as $setting) {
		$setting["settings"] = json_decode($setting["settings"], true);
		unset($setting["system"]);
		unset($setting["value"]);
		$json[] = $setting;
	}

	BigTree::putFile(SERVER_ROOT."custom/json-db/settings.json", BigTree::json($json));

	$feeds = SQL::fetchAll("SELECT * FROM bigtree_feeds");
	$feeds_rel = [];
	$json = [];

	foreach ($feeds as $feed) {
		$old_id = $feed["id"];
		$feed["id"] = $get_unique_id("feeds-");
		$feed["fields"] = json_decode($feed["fields"], true);
		$feed["settings"] = json_decode($feed["settings"], true);
		$json[] = $feed;
		$feeds_rel[$old_id] = $feed["id"];

		SQL::update("bigtree_audit_trail", ["table" => "bigtree_feeds", "entry" => $old_id], ["entry" => $feed["id"]]);
	}

	BigTree::putFile(SERVER_ROOT."custom/json-db/feeds.json", BigTree::json($json));

	$module_groups = SQL::fetchAll("SELECT * FROM bigtree_module_groups ORDER BY position DESC, id ASC");
	$module_groups_rel = [];
	$json = [];
	$position = count($module_groups);

	foreach ($module_groups as $group) {
		$old_group_id = $group["id"];
		unset($group["id"]);
		$group["id"] = $get_unique_id("module-groups-");
		$group["position"] = $position--;
		$json[] = $group;
		$module_groups_rel[$old_group_id] = $group["id"];
	}

	BigTree::putFile(SERVER_ROOT."custom/json-db/module-groups.json", BigTree::json($json));

	$modules = SQL::fetchAll("SELECT * FROM bigtree_modules");
	$modules_rel = [];
	$modules_json = [];

	foreach ($modules as $module) {
		$old_module_id = $module["id"];
		$module["id"] = $get_unique_id("modules-");

		SQL::update("bigtree_audit_trail", ["table" => "bigtree_modules", "entry" => $old_module_id], ["entry" => $module["id"]]);

		if ($module["group"]) {
			$module["group"] = isset($module_groups_rel[$module["group"]]) ? $module_groups_rel[$module["group"]] : null;
		}

		$module["gbp"] = json_decode($module["gbp"], true);
		$module["actions"] = [];
		$module["embeddable-forms"] = [];
		$module["forms"] = [];
		$module["reports"] = [];
		$module["views"] = [];

		// Embedabble Forms
		$module_embeds = SQL::fetchAll("SELECT * FROM bigtree_module_embeds WHERE module = ?", $old_module_id);
		$json = [];
		
		foreach ($module_embeds as $embed) {
			unset($embed["module"]);
			$embed["fields"] = json_decode($embed["fields"], true);
			$embed["hooks"] = json_decode($embed["hooks"], true);
		
			$module["embeddable-forms"][] = $embed;
		}

		$module_views = SQL::fetchAll("SELECT * FROM bigtree_module_views WHERE module = ?", $old_module_id);
		$views_rel = [];

		foreach ($module_views as $view) {
			unset($view["module"]);
			$old_view_id = $view["id"];
			$view["id"] = $get_unique_id("views-");
			$view["fields"] = json_decode($view["fields"], true);
			$view["settings"] = json_decode($view["settings"], true);
			$view["actions"] = json_decode($view["actions"], true);
			$views_rel[$old_view_id] = $view["id"];
			$module["views"][] = $view;
		}

		$module_forms = SQL::fetchAll("SELECT * FROM bigtree_module_forms WHERE module = ?", $old_module_id);
		$forms_rel = [];
		
		foreach ($module_forms as $form) {
			unset($form["module"]);
			$old_form_id = $form["id"];
			$form["id"] = $get_unique_id("forms-");
			$form["fields"] = json_decode($form["fields"], true);
			$form["hooks"] = json_decode($form["hooks"], true);
	
			if ($form["return_view"]) {
				$form["return_view"] = isset($views_rel[$form["return_view"]]) ? $views_rel[$form["return_view"]] : null;
			}
	
			$module["forms"][] = $form;
			$forms_rel[$old_form_id] = $form["id"];
		}

		// Update module views now that we have updated form IDs
		foreach ($module["views"] as $index => $view) {
			if ($view["related_form"]) {
				$module["views"][$index]["related_form"] = isset($forms_rel[$view["related_form"]]) ? $forms_rel[$view["related_form"]] : null;
			}
		}

		$reports = SQL::fetchAll("SELECT * FROM bigtree_module_reports WHERE module = ?", $old_module_id);
		$reports_rel = [];
	
		foreach ($reports as $report) {
			unset($report["module"]);
			$old_report_id = $report["id"];
			$report["id"] = $get_unique_id("reports-");
			$report["filters"] = json_decode($report["filters"], true);
			$report["fields"] = json_decode($report["fields"], true);
	
			if ($report["view"]) {
				$report["view"] = isset($views_rel[$report["view"]]) ? $views_rel[$report["view"]] : null;
			}
	
			$module["reports"][] = $report;
			$reports_rel[$old_report_id] = $report["id"];
		}

		$actions = SQL::fetchAll("SELECT * FROM bigtree_module_actions WHERE module = ? ORDER BY position DESC, id ASC", $old_module_id);
		$position = count($actions);

		foreach ($actions as $action) {
			unset($action["module"]);
			$action["id"] = $get_unique_id("actions-");
			$action["position"] = $position--;			

			if ($action["report"]) {
				$action["report"] = isset($reports_rel[$action["report"]]) ? $reports_rel[$action["report"]] : null;
			}

			if ($action["form"]) {
				$action["form"] = isset($forms_rel[$action["form"]]) ? $forms_rel[$action["form"]] : null;
			}

			if ($action["view"]) {
				$action["view"] = isset($views_rel[$action["view"]]) ? $views_rel[$action["view"]] : null;
			}

			$module["actions"][] = $action;
		}

		$modules_json[] = $module;
		$modules_rel[$old_module_id] = $module["id"];
	}

	BigTree::putFile(SERVER_ROOT."custom/json-db/modules.json", BigTree::json($modules_json));

	$templates = SQL::fetchAll("SELECT * FROM bigtree_templates ORDER BY position DESC, id ASC");
	$json = [];
	$position = count($templates);

	foreach ($templates as $template) {
		$template["hooks"] = json_decode($template["hooks"], true);
		$template["resources"] = json_decode($template["resources"], true);
		$template["position"] = $position--;

		if ($template["module"]) {
			$template["module"] = $modules_rel[$template["module"]];
		} else {
			$template["module"] = null;
		}

		$json[] = $template;
	}

	BigTree::putFile(SERVER_ROOT."custom/json-db/templates.json", BigTree::json($json));

	// Update user's module permissions based on the new IDs
	$users = SQL::fetchAll("SELECT * FROM bigtree_users");

	foreach ($users as $user) {
		$permissions = json_decode($user["permissions"], true);
		$new_permissions = $permissions;

		foreach ($permissions["module"] as $old_module_id => $permission) {
			unset($new_permissions["module"][$old_module_id]);
			$new_permissions["module"][$modules_rel[$old_module_id]] = $permission;
		}

		foreach ($permissions["module_gbp"] as $old_module_id => $permission_set) {
			unset($new_permissions["module_gbp"][$old_module_id]);
			$new_permissions["module_gbp"][$modules_rel[$old_module_id]] = $permission_set;
		}

		SQL::update("bigtree_users", $user["id"], ["permissions" => $new_permissions]);
	}

	$extensions = SQL::fetchAll("SELECT * FROM bigtree_extensions WHERE type = 'extension'");
	$json = [];

	foreach ($extensions as $extension) {
		$extension["manifest"] = json_decode($extension["manifest"], true);

		if (is_array($extension["manifest"]["components"]["modules"])) {
			foreach ($extension["manifest"]["components"]["modules"] as &$module) {
				$module["id"] = $modules_rel[$module["id"]];
			}
		}

		if (is_array($extension["manifest"]["components"]["feeds"])) {
			foreach ($extension["manifest"]["components"]["feeds"] as &$feed) {
				$feed["id"] = $feeds_rel[$feed["id"]];
			}
		}

		if (is_array($extension["manifest"]["components"]["module_groups"])) {
			foreach ($extension["manifest"]["components"]["module_groups"] as &$group) {
				$group["id"] = $module_groups_rel[$group["id"]];
			}
		}

		$json[] = $extension;
	}

	BigTree::putFile(SERVER_ROOT."custom/json-db/extensions.json", BigTree::json($json));

	$admin->updateInternalSettingValue("bigtree-internal-revision", 400);

	echo BigTree::json([
		"complete" => true,
		"response" => "Upgrading to BigTree 4.4 revision 1"
	]);
	