<?php
	namespace BigTree;
	
	/**
	 * @global array $bigtree
	 */
	
	CSRF::verify();
	
	$results = AuditTrail::search($_GET["user"], $_GET["table"], $_GET["entry"], $_GET["start"], $_GET["end"]);
	$json_data = array();
	
	// Setup a cache so we don't query for things more than once
	$cache = array();
	$colors = array(
		"created" => '<span style="color: green;">'.Text::translate("Created").'</span>',
		"deleted" => '<span style="color: #CC0000;">'.Text::translate("Deleted").'</span>',
		"updated" => Text::translate("Updated"),
		"updated-value" => Text::translate("Updated"),
		"unarchived" => Text::translate("Unarchived"),
		"unarchived-inherited" => Text::translate("Unarchived (inherited)"),
		"archived" => Text::translate("Archived"),
		"archived-inherited" => Text::translate("Archived (inherited)"),
		"saved-draft" => Text::translate("Saved Draft"),
		"created-pending" => '<span style="color: green;">'.Text::translate("Created Pending").'</span>',
		"cleared-empty" => Text::translate("Cleared Empty")
	);
	
	foreach ($results as $result) {
		$link = $data = false;
		$title = $result["entry"];
		
		// Grab related data from the cache if it exists
		if (isset($cache[$result["table"]][$result["entry"]])) {
			$data = $cache[$result["table"]][$result["entry"]];
		}
		
		// Pending entries may no longer exist, so only pull live ones
		if (is_numeric($result["entry"])) {
			// Extensions
			if ($result["table"] == "bigtree_extensions") {
				if (!$data) {
					$data = SQL::fetch("SELECT name FROM bigtree_extensions WHERE id = ?", $result["entry"]);
				}
				$title = $data ? $data["name"] : $result["entry"];
				$link = $data ? DEVELOPER_ROOT."extensions/edit/".$result["entry"]."/" : false;
			}
			
			// Feeds
			if ($result["table"] == "bigtree_feeds") {
				if (!$data) {
					$data = SQL::fetch("SELECT name FROM bigtree_feeds WHERE id = ?", $result["entry"]);
				}
				$title = $data ? $data["name"] : $result["entry"];
				$link = $data ? DEVELOPER_ROOT."feeds/edit/".$result["entry"]."/" : false;
			}
			
			// Field Types
			if ($result["table"] == "bigtree_field_types") {
				if (!$data) {
					$data = SQL::fetch("SELECT name FROM bigtree_field_types WHERE id = ?", $result["entry"]);
				}
				$title = $data ? $data["name"] : $result["entry"];
				$link = $data ? DEVELOPER_ROOT."field-types/edit/".$result["entry"]."/" : false;
			}
			
			// Settings
			if ($result["table"] == "bigtree_settings") {
				if (!$data) {
					$data = SQL::fetch("SELECT name,system FROM bigtree_settings WHERE id = ?", $result["entry"]);
				}
				if (!$data || $data["system"]) {
					$title = $result["entry"];
				} elseif ($data) {
					$title = $data["name"];
					$link = DEVELOPER_ROOT."settings/edit/".$result["entry"]."/";
				}
			}
			
			// Callouts
			if ($result["table"] == "bigtree_callouts") {
				if (!$data) {
					$data = SQL::fetch("SELECT name FROM bigtree_callouts WHERE id = ?", $result["entry"]);
				}
				$title = $data ? $data["name"] : $result["entry"];
				$link = $data ? DEVELOPER_ROOT."callouts/edit/".$result["entry"]."/" : false;
			}
			
			// Callout Groups
			if ($result["table"] == "bigtree_callout_groups") {
				if (!$data) {
					$data = SQL::fetch("SELECT name FROM bigtree_callout_groups WHERE id = ?", $result["entry"]);
				}
				$title = $data ? $data["name"] : $result["entry"];
				$link = $data ? DEVELOPER_ROOT."callouts/groups/edit/".$result["entry"]."/" : false;
			}
			
			// Templates
			if ($result["table"] == "bigtree_templates") {
				if (!$data) {
					$data = SQL::fetch("SELECT name FROM bigtree_templates WHERE id = ?", $result["entry"]);
				}
				$title = $data ? $data["name"] : $result["entry"];
				$link = $data ? DEVELOPER_ROOT."templates/edit/".$result["entry"]."/" : false;
			}
			
			// Modules
			if ($result["table"] == "bigtree_modules") {
				if (!$data) {
					$data = SQL::fetch("SELECT name FROM bigtree_modules WHERE id = ?", $result["entry"]);
				}
				$title = $data ? $data["name"] : $result["entry"];
				$link = $data ? DEVELOPER_ROOT."modules/edit/".$result["entry"]."/" : false;
			}
			
			// Module Groups
			if ($result["table"] == "bigtree_module_groups") {
				if (!$data) {
					$data = SQL::fetch("SELECT name FROM bigtree_module_groups WHERE id = ?", $result["entry"]);
				}
				$title = $data ? $data["name"] : $result["entry"];
				$link = $data ? DEVELOPER_ROOT."modules/groups/edit/".$result["entry"]."/" : false;
			}
			
			// Module Interfaces
			if ($result["table"] == "bigtree_module_interfaces") {
				if (!$data) {
					$data = SQL::fetch("SELECT title,type FROM bigtree_module_interfaces WHERE id = ?", $result["entry"]);
				}
				if (!$data) {
					$title = $result["entry"];
				} else {
					$title = $data["title"];
					if ($data["type"] == "form") {
						$link = DEVELOPER_ROOT."modules/forms/edit/".$result["entry"]."/";
					} elseif ($data["type"] == "view") {
						$link = DEVELOPER_ROOT."modules/views/edit/".$result["entry"]."/";
					} elseif ($data["type"] == "embeddable-form") {
						$link = DEVELOPER_ROOT."modules/embeds/edit/".$result["entry"]."/";
					} elseif ($data["type"] == "report") {
						$link = DEVELOPER_ROOT."modules/reports/edit/".$result["entry"]."/";
					} else {
						list($extension, $interface) = explode("*", $data["type"]);
						$link = DEVELOPER_ROOT."modules/interfaces/build/$extension/$interface/?id=".$result["entry"];
					}
				}
			}
			
			// Module Actions
			if ($result["table"] == "bigtree_module_actions") {
				if (!$data) {
					$data = SQL::fetch("SELECT name FROM bigtree_module_actions WHERE id = ?", $result["entry"]);
				}
				$title = $data ? $data["name"] : $result["entry"];
				$link = $data ? DEVELOPER_ROOT."modules/actions/edit/".$result["entry"]."/" : false;
			}
			
			// Users
			if ($result["table"] == "bigtree_users") {
				if (!$data) {
					$data = SQL::fetch("SELECT name FROM bigtree_users WHERE id = ?", $result["entry"]);
				}
				$title = $data ? $data["name"] : $result["entry"];
				$link = $data ? ADMIN_ROOT."users/edit/".$result["entry"]."/" : false;
			}
			
			// Pages
			if ($result["table"] == "bigtree_pages") {
				if (!$data) {
					$data = SQL::fetch("SELECT nav_title FROM bigtree_pages WHERE id = ?", $result["entry"]);
				}
				$title = $data ? $data["nav_title"] : $result["entry"];
				$link = $data ? ADMIN_ROOT."pages/edit/".$result["entry"]."/" : false;
			}
			
			// Resources
			if ($result["table"] == "bigtree_resources") {
				if (!$data) {
					$data = SQL::fetch("SELECT file FROM bigtree_resources WHERE id = ?", $result["entry"]);
				}
				if ($data) {
					$path = pathinfo($data["file"]);
					$title = $path["basename"];
				} else {
					$title = $result["entry"];
				}
			}
			
			// Resource Folders
			if ($result["table"] == "bigtree_resource_folders") {
				if (!$data) {
					$data = SQL::fetch("SELECT name FROM bigtree_resource_folders WHERE id = ?", $result["entry"]);
				}
				$title = $data ? $data["name"] : $result["entry"];
			}
			
			// Tags
			if ($result["table"] == "bigtree_tags") {
				if (!$data) {
					$data = SQL::fetch("SELECT tag FROM bigtree_tags WHERE id = ?", $result["entry"]);
				}
				$title = $data ? $data["tag"] : $result["entry"];
			}
			
			// Not a bigtree_ table? See if we have a form for it.
			if (strpos($result["table"], "bigtree_") === false) {
				if (!$data) {
					$data = SQL::fetch("SELECT id FROM bigtree_module_interfaces WHERE type = 'form' AND `table` = ?", $result["table"]);
				}
				if ($data) {
					$action = SQL::fetch("SELECT route, module FROM bigtree_module_actions 
										  WHERE interface = ? AND route LIKE 'edit%'", $data["id"]);
					$module = SQL::fetch("SELECT route FROM bigtree_modules WHERE id = ?", $action["module"]);
					if (!empty($action) && !empty($module)) {
						$title = "View Entry";
						$link = ADMIN_ROOT.$module["route"]."/".$action["route"]."/".$result["entry"]."/";
					}
				}
			}
		} else {
			if (substr($result["entry"], 0, 1) == "p") {
				$title = Text::translate("Pending Entry");
			} else {
				$title = Text::translate(ucwords(str_replace("-", " ", $result["entry"])));
			}
			
			$link = "";
		}
		
		$json_data[] = array(
			"date" => date($bigtree["config"]["date_format"]." @ g:ia", strtotime($result["date"])),
			"user" => '<a target="_blank" href="'.ADMIN_ROOT.'users/edit/'.$result["user"]["id"].'/">'.$result["user"]["name"].'</a>',
			"table" => $result["table"],
			"entry" => $link ? '<a href="'.$link.'" target="_blank">'.$title.'</a>' : $title,
			"action" => $colors[$result["type"]] ?: Text::translate(ucwords(str_replace("-", " ", $result["type"]))),
			"type" => $result["type"]
		);
		
		// Save data to cache if we retrieved some
		if ($data && !isset($cache[$result["table"]][$result["entry"]])) {
			$cache[$result["table"]][$result["entry"]] = $data;
		}
	}
?>
<div id="audit_trail_table"></div>
<script>
	BigTreeTable({
		container: "#audit_trail_table",
		columns: {
			date: { title: "<?=Text::translate("Date", true)?>" },
			user: { title: "<?=Text::translate("User", true)?>" },
			table: { title: "<?=Text::translate("Table", true)?>" },
			entry: { title: "<?=Text::translate("Entry", true)?>", size: 0.35 },
			action: { title: "<?=Text::translate("Action", true)?>", size: 150 }
		},
		data: <?=json_encode($json_data)?>,
		searchable: true,
		perPage: 10,
		title: "<?=Text::translate("Audit Results", true)?>"
	});
</script>