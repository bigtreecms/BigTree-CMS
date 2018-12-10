<?php
	// BigTree 4.4 -- prerelease

	$resource_converter = function($resources) {
		global $resource_converter;

		foreach ($resources as $key => $item) {
			$settings = $item["settings"] ?: $item["options"];
			$was_string = false;

			if (is_string($settings)) {
				$was_string = true;
				$settings = json_decode($settings, true);
			}

			if ($item["type"] == "matrix") {
				$settings["columns"] = $resource_converter($settings["columns"]);
			} elseif ($item["type"] == "upload" || !empty($settings["image"])) {
				$item["type"] = "image";
				unset($settings["image"]);
			} elseif ($item["type"] == "photo-gallery") {
				$item["type"] = "media-gallery";
				$settings["disable_youtube"] = "on";
				$settings["disable_vimeo"] = "on";

				if (empty($settings["disable_captions"])) {
					$settings["columns"] = [["type" => "text", "id" => "caption", "title" => "Caption"]];
				} else {
					$settings["columns"] = [];
				}
			}

			if ($was_string) {
				$settings = json_encode($settings);
			}

			$item["settings"] = $settings;
			$resources[$key] = $item;
		}

		return $resources;
	};

	// Update template resources
	$templates = BigTreeJSONDB::getAll("templates");

	foreach ($templates as $item) {
		$item["resources"] = $resource_converter($item["resources"]);
		BigTreeJSONDB::update("templates", $item["id"], $item);
	}

	// Update callout resources
	$callouts = BigTreeJSONDB::getAll("callouts");

	foreach ($callouts as $item) {
		$item["resources"] = $resource_converter($item["resources"]);
		BigTreeJSONDB::update("callouts", $item["id"], $item);
	}

	// Update settings
	$settings = BigTreeJSONDB::getAll("settings");

	foreach ($settings as $item) {
		if ($item["type"] == "matrix") {
			$item["settings"]["columns"] = $resource_converter($item["settings"]["columns"]);
		} elseif ($item["type"] == "upload" || !empty($item["settings"]["image"])) {
			$item["type"] = "image";
			unset($item["settings"]["image"]);
		} elseif ($item["type"] == "photo-gallery") {
			$item["type"] = "media-gallery";
			$item["settings"]["disable_youtube"] = "on";
			$item["settings"]["disable_vimeo"] = "on";

			if (empty($item["settings"]["disable_captions"])) {
				$item["settings"]["columns"] = [["type" => "text", "id" => "caption", "title" => "Caption"]];
			} else {
				$item["settings"]["columns"] = [];
			}
		}

		BigTreeJSONDB::update("settings", $item["id"], $item);
	}

	// Update module forms
	$modules = BigTreeJSONDB::getAll("modules");

	foreach ($modules as $module) {
		if (is_array($module["forms"]) && count($module["forms"])) {
			$subset = BigTreeJSONDB::getSubset("modules", $module["id"]);

			foreach ($module["forms"] as $form) {
				$form["fields"] = $resource_converter($form["fields"]);
				$subset->update("forms", $form["id"], $form);
			}
		}
	}

	echo BigTree::json([
		"complete" => true,
		"response" => "Upgrading to BigTree 4.4 revision 3"
	]);
	
	$admin->updateInternalSettingValue("bigtree-internal-revision", 402);
