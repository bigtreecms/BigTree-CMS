<?php
	namespace BigTree;
	
	/**
	 * @global array $bigtree
	 * @global array $policy
	 * @global string $policy_text
	 */
	
	// We set this header so that when the user reloads the page form element changes don't stick (since we're only tracking explicit changes back to the JSON objects for Alerts and Permissions)
	header("Cache-Control: no-store");
	$user = new User(end($bigtree["commands"]));

	// Stop if this is a 404 or the user is editing someone higher than them.
	if (!$user || $user->Level > Auth::user()->Level) {
		Auth::stop("The user you are trying to edit no longer exists or you are not allowed to edit this user.",
					Router::getIncludePath("admin/layouts/_error.php"));
	}
	
	// Show gravatar as header icon
	$bigtree["gravatar"] = $user->Email;

	// We need to gather all the page levels that should be expanded (anything that isn't "inherit" should have its parents pre-opened)
	$page_ids = [];
	$pre_opened_parents = [];
	$pre_opened_folders = [];
		
	foreach ($user->Permissions["page"] as $id => $permission) {
		if ($permission != "i") {
			$page_ids[] = $id;
		}
	}

	foreach ($user->Alerts as $id => $on) {
		$page_ids[] = $id;
	}
	
	$page_ids = array_unique($page_ids);

	foreach ($page_ids as $id) {
		$pre_opened_parents = array_merge($pre_opened_parents, Page::getLineage($id));
	}

	// Gather up the parents for resource folders that should be open by default.
	foreach ($user->Permissions["resources"] as $id => $permission) {
		if ($permission != "i") {
			$folder = new ResourceFolder($id);
			$pre_opened_folders[] = $folder->Parent;
		}
	}
	
	function _local_userDrawNavLevel($parent, $depth, $alert_above = false, $children = false) {
		global $user, $pre_opened_parents;
		
		if (!$children) {
			$page = new Page($parent, false);
			$children = $page->Children;
		}
		
		if (count($children)) {
?>
<ul class="depth_<?=$depth?>">
	<?php
			foreach ($children as $child) {
				$grandchildren = $child->Children;
				$alert_below = ($alert_above || (isset($user->Alerts[$child->ID]) && $user->Alerts[$child->ID])) ? true : false;
	?>
	<li>
		<span class="depth"></span>
		<a class="permission_label<?php if (!$grandchildren) { ?> disabled<?php } ?><?php if ($user->Level > 0) { ?> permission_label_admin<?php } ?><?php if (in_array($child->ID,$pre_opened_parents)) { ?> expanded<?php } ?>" href="#" data-id="<?=$child->ID?>" data-depth="<?=$depth?>"><?=$child->NavigationTitle?></a>
		<span class="permission_alerts"><input title="Alerts" type="checkbox" data-category="Alerts" data-key="<?=$child->ID?>" name="alerts[<?=$child->ID?>]"<?php if ((isset($user->Alerts[$child->ID]) && $user->Alerts[$child->ID] == "on") || $alert_above) { ?> checked="checked"<?php } ?><?php if ($alert_above) { ?> disabled="disabled"<?php } ?>/></span>
		<span class="permission_level"<?php if ($user->Level > 0) { ?> style="display: none;"<?php } ?>>
			<input title="Publisher" type="radio" data-category="Page" data-key="<?=$child->ID?>" name="permissions[page][<?=$child->ID?>]" value="p" <?php if ($user->Permissions["page"][$child->ID] == "p") { ?>checked="checked" <?php } ?>/>
		</span>
		<span class="permission_level"<?php if ($user->Level > 0) { ?> style="display: none;"<?php } ?>>
			<input title="Editor" type="radio" data-category="Page" data-key="<?=$child->ID?>" name="permissions[page][<?=$child->ID?>]" value="e" <?php if ($user->Permissions["page"][$child->ID] == "e") { ?>checked="checked" <?php } ?>/>
		</span>
		<span class="permission_level"<?php if ($user->Level > 0) { ?> style="display: none;"<?php } ?>>
			<input title="No Access" type="radio" data-category="Page" data-key="<?=$child->ID?>" name="permissions[page][<?=$child->ID?>]" value="n" <?php if ($user->Permissions["page"][$child->ID] == "n") { ?>checked="checked" <?php } ?>/>
		</span>
		<span class="permission_level"<?php if ($user->Level > 0) { ?> style="display: none;"<?php } ?>>
			<input title="Inherit Access" type="radio" data-category="Page" data-key="<?=$child->ID?>" name="permissions[page][<?=$child->ID?>]" value="i" <?php if (!$user->Permissions["page"][$child->ID] || $user->Permissions["page"][$child->ID] == "i") { ?>checked="checked" <?php } ?>/>
		</span>
		<?php
				if (in_array($child->ID,$pre_opened_parents)) {
					_local_userDrawNavLevel($child->ID, $depth + 1, $alert_below, $grandchildren);
				}
		?>
	</li>
	<?php
			}
	?>
</ul>
<?php
		}
	}
	
	function _local_userDrawFolderLevel($parent, $depth, $children = false) {
		global $user, $pre_opened_folders;
		
		if (!$children) {
			$children = ResourceFolder::allByParent($parent, "name ASC");
		}
		
		if (count($children)) {
?>
<ul class="depth_<?=$depth?>"<?php if ($depth > 2 && !in_array($parent,$pre_opened_folders)) { ?> style="display: none;"<?php } ?>>
	<?php
			foreach ($children as $folder) {
				$grandchildren = ResourceFolder::allByParent($folder->ID, "name ASC");
	?>
	<li>
		<span class="depth"></span>
		<a class="permission_label folder_label<?php if (!count($grandchildren)) { ?> disabled<?php } ?><?php if (in_array($folder->ID,$pre_opened_folders)) { ?> expanded<?php } ?>" href="#"><?=$folder->Name?></a>
		<span class="permission_level"><input title="Creator" type="radio" data-category="Resource" data-key="<?=$folder->ID?>" name="permissions[resources][<?=$folder->ID?>]" value="p" <?php if ($user->Permissions["resources"][$folder->ID] == "p") { ?>checked="checked" <?php } ?>/></span>
		<span class="permission_level"><input title="Consumer" type="radio" data-category="Resource" data-key="<?=$folder->ID?>" name="permissions[resources][<?=$folder->ID?>]" value="e" <?php if ($user->Permissions["resources"][$folder->ID] == "e") { ?>checked="checked" <?php } ?>/></span>
		<span class="permission_level"><input title="No Access" type="radio" data-category="Resource" data-key="<?=$folder->ID?>" name="permissions[resources][<?=$folder->ID?>]" value="n" <?php if ($user->Permissions["resources"][$folder->ID] == "n") { ?>checked="checked" <?php } ?>/></span>
		<span class="permission_level"><input title="Inherit Access" type="radio" data-category="Resource" data-key="<?=$folder->ID?>" name="permissions[resources][<?=$folder->ID?>]" value="i" <?php if (!$user->Permissions["resources"][$folder->ID] || $user->Permissions["resources"][$folder->ID] == "i") { ?>checked="checked" <?php } ?>/></span>
		<?php _local_userDrawFolderLevel($folder->ID, $depth + 1, $grandchildren) ?>
	</li>
	<?php
			}
	?>
</ul>
<?php
		}
	}
	
	// Handle submission errors
	$error = "";
	
	if (isset($_SESSION["bigtree_admin"]["update_user"])) {
		$saved = $_SESSION["bigtree_admin"]["update_user"];
		unset($_SESSION["bigtree_admin"]["update_user"]);
		
		$user->Name = Text::htmlEncode($saved["name"]);
		$user->Email = Text::htmlEncode($saved["email"]);
		$user->Company = Text::htmlEncode($saved["company"]);
		$user->Alerts = json_decode($saved["alerts"], true);
		$user->DailyDigest = empty($saved["daily_digest"]) ? false : true;
		$user->Level = intval($saved["level"]);
		
		$permission_data = json_decode($saved["permissions"], true);
		$user->Permissions = array(
			"page" => $permission_data["Page"],
			"module" => $permission_data["Module"],
			"resources" => $permission_data["Resource"],
			"module_gbp" => $permission_data["ModuleGBP"]
		);
		
		$error = $saved["error"];
	}

	
	$groups = ModuleGroup::all("name ASC", true);
	$groups[] = array("id" => 0, "name" => Text::translate("- Ungrouped -"));
?>
<div class="container">
	<?php
		if (Auth::user()->Level > 1) {
	?>
	<div class="developer_buttons">
		<a href="<?=ADMIN_ROOT?>developer/audit/search/?user=<?=$user->ID?>&<?php CSRF::drawGETToken(); ?>" title="<?=Text::translate("View Audit Trail for User", true)?>">
			<?=Text::translate("View Audit Trail for User")?>
			<span class="icon_small icon_small_trail"></span>
		</a>
		<?php
			if (!empty($user->TwoFactorSecret)) {
		?>
		<a href="<?=ADMIN_ROOT?>developer/security/remove-2fa/?user=<?=$user-ID?>&<?php CSRF::drawGETToken(); ?>" title="<?=Text::translate("Remove Two Factor Authentication for User", true)?>">
			<?=Text::translate("Remove Two Factor Authentication for User")?>
			<span class="icon_small icon_small_warning"></span>
		</a>
		<?php
			}
		?>
	</div>
	<?php
		}
	?>

	<form class="module" action="<?=ADMIN_ROOT?>users/update/" method="post">
		<?php CSRF::drawPOSTToken(); ?>
		<input type="hidden" name="id" value="<?=$user->ID?>" />
		<section>
			<p class="error_message"<?php if (!$error) { ?> style="display: none;"<?php } ?>><?=Text::translate("Errors found! Please fix the highlighted fields before submitting.")?></p>
			<div class="left">
				<fieldset<?php if ($error == "email") { ?> class="form_error"<?php } ?> style="position: relative;">
					<label for="user_field_email" class="required"><?=Text::translate("Email")?> <small>(<?=Text::translate("Profile images from")?> <a href="http://www.gravatar.com/" target="_blank">Gravatar</a>)</small> <?php if ($error == "email") { ?><span class="form_error_reason"><?=Text::translate("Already In Use By Another User")?></span><?php } ?></label>
					<input id="user_field_email" type="text" class="required email" name="email" autocomplete="off" value="<?=$user->Email?>" tabindex="1" />
					<span class="gravatar"<?php if ($user->Email) { ?> style="display: block;"<?php } ?>><img src="<?=User::gravatar($user->Email, 36)?>" alt="" /></span>
				</fieldset>
				
				<fieldset<?php if ($error == "password") { ?> class="form_error"<?php } ?> >
					<label for="password_field"><?=Text::translate("Password")?> <small>(<?=Text::translate("Leave blank to remain unchanged")?>)</small> <?php if ($error == "password") { ?><span class="form_error_reason"><?=Text::translate("Did Not Meet Requirements")?></span><?php } ?></label>
					<input type="password" name="password" value="" tabindex="3" autocomplete="off" id="password_field"<?php if ($policy_text) { ?> class="has_tooltip" data-tooltip="<?=htmlspecialchars($policy_text)?>"<?php } ?> />
					<?php if ($policy_text) { ?>
					<p class="password_policy"><?=Text::translate("Password Policy In Effect")?></p>
					<?php } ?>
				</fieldset>

				<?php if ($user->ID != Auth::user()->ID) { ?>
				<fieldset<?php if ($error == "email") { ?> class="form_error"<?php } ?> >
					<label for="user_level" class="required"><?=Text::translate("User Level")?></label>
					<select name="level" tabindex="5" id="user_level">
						<option value="0"<?php if ($user->Level == "0") { ?> selected="selected"<?php } ?>><?=Text::translate("Normal User")?></option>
						<option value="1"<?php if ($user->Level == "1") { ?> selected="selected"<?php } ?>><?=Text::translate("Administrator")?></option>
						<?php if (Auth::user()->Level > 1) { ?><option value="2"<?php if ($user->Level == "2") { ?> selected="selected"<?php } ?>><?=Text::translate("Developer")?></option><?php } ?>
					</select>
				</fieldset>
				<?php } ?>
			</div>
			<div class="right">
				<fieldset>
					<label for="user_field_name"><?=Text::translate("Name")?></label>
					<input id="user_field_name" type="text" name="name" value="<?=$user->Name?>" tabindex="2" />
				</fieldset>
				
				<fieldset>
					<label for="user_field_company"><?=Text::translate("Company")?></label>
					<input id="user_field_company" type="text" name="company" value="<?=$user->Company?>" tabindex="4" />
				</fieldset>
				
				<br />
				
				<fieldset>
					<input id="user_field_digest" type="checkbox" name="daily_digest" tabindex="4" <?php if ($user->DailyDigest) { ?> checked="checked"<?php } ?> />
					<label for="user_field_digest" class="for_checkbox"><?=Text::translate("Daily Digest Email")?></label>
				</fieldset>
			</div>
		</section>
		<section class="sub" id="permission_section">
			<fieldset class="last">
				<label><?=Text::translate("Permissions")?>
					<small id="admin_user_message"<?php if ($user->Level < 1) { ?> style="display: none;"<?php } ?>>(<?=Text::translate("this user is an <strong>administrator</strong> and is a publisher of the entire site")?>)</small>
					<small id="regular_user_message"<?php if ($user->Level > 0) { ?> style="display: none;"<?php } ?>>(<?=Text::translate("for module sub-permissions \"No Access\" inherits from the main permission level")?>)</small>
				</label>
			
				<div class="user_permissions form_table">
					<header<?php if ($user->Level > 0) { ?> style="display: none;"<?php } ?>>
						<nav>
							<a href="#page_permissions" class="active"><?=Text::translate("Pages")?></a>
							<a href="#module_permissions"><?=Text::translate("Modules")?></a>
							<a href="#resource_permissions"><?=Text::translate("Resources")?></a>
						</nav>
					</header>
					<div id="page_permissions">
						<div class="labels sticky_controls">
							<span class="permission_label<?php if ($user->Level > 0) { ?> permission_label_admin<?php } ?>"><?=Text::translate("Page")?></span>
							<span class="permission_alerts"><?=Text::translate("Content Alerts")?></span>
							<span class="permission_level"<?php if ($user->Level > 0) { ?> style="display: none;"<?php } ?>><?=Text::translate("Publisher")?></span>
							<span class="permission_level"<?php if ($user->Level > 0) { ?> style="display: none;"<?php } ?>><?=Text::translate("Editor")?></span>
							<span class="permission_level"<?php if ($user->Level > 0) { ?> style="display: none;"<?php } ?>><?=Text::translate("No Access")?></span>
							<span class="permission_level"<?php if ($user->Level > 0) { ?> style="display: none;"<?php } ?>><?=Text::translate("Inherit")?></span>
						</div>
						<section>
							<ul class="depth_1">
								<li class="top">
									<span class="depth"></span>
									<a class="permission_label expanded<?php if ($user->Level > 0) { ?> permission_label_admin<?php } ?>" href="#"><?=Text::translate("All Pages")?></a>
									<span class="permission_alerts"><input title="Content Age Alerts" type="checkbox" name="alerts[0]"<?php if ($user->Alerts[0] == "on") { ?> checked="checked"<?php } ?>/></span>
									<span class="permission_level"<?php if ($user->Level > 0) { ?> style="display: none;"<?php } ?>>
										<input title="Publisher" type="radio" data-category="Page" data-key="0" name="permissions[page][0]" value="p" <?php if ($user->Permissions["page"][0] == "p") { ?>checked="checked" <?php } ?>/>
									</span>
									<span class="permission_level"<?php if ($user->Level > 0) { ?> style="display: none;"<?php } ?>>
										<input title="Editor" type="radio" data-category="Page" data-key="0" name="permissions[page][0]" value="e" <?php if ($user->Permissions["page"][0] == "e") { ?>checked="checked" <?php } ?>/>
									</span>
									<span class="permission_level"<?php if ($user->Level > 0) { ?> style="display: none;"<?php } ?>>
										<input title="No Access" type="radio" data-category="Page" data-key="0" name="permissions[page][0]" value="n" <?php if ($user->Permissions["page"][0] == "n" || !$user->Permissions["page"][0]) { ?>checked="checked" <?php } ?>/>
									</span>
									<span class="permission_level"<?php if ($user->Level > 0) { ?> style="display: none;"<?php } ?>>&nbsp;</span>
									<?php _local_userDrawNavLevel(0,2,$user->Alerts[0]) ?>
								</li>
							</ul>
						</section>
					</div>
					
					<div id="module_permissions" style="display: none;">
						<div class="labels sticky_controls">
							<span class="permission_label permission_label_wider"><?=Text::translate("Module")?></span>
							<span class="permission_level"><?=Text::translate("Publisher")?></span>
							<span class="permission_level"><?=Text::translate("Editor")?></span>
							<span class="permission_level"><?=Text::translate("No Access")?></span>
						</div>
						<section>
							<ul class="depth_1">
								<?php
									foreach ($groups as $group) {
										$modules = Module::allByGroup($group["id"], "name ASC");
										
										if (count($modules)) {
								?>
								<li class="module_group">
									<span class="module_group_name"><?=$group["name"]?></span>
								</li>
								<?php
											foreach ($modules as $module) {
												$closed = true;
												$gbp_categories = [];
												$gbp = $module->GroupBasedPermissions;
												
												// Determine whether we have access to anything in this section (default to open) or not (default to closed)
												if (is_array($user->Permissions["module_gbp"][$module->ID])) {
													foreach ($user->Permissions["module_gbp"][$module->ID] as $id => $permission) {
														if ($permission != "n") {
															$closed = false;
														}
													}
												}

												if (!empty($gbp["enabled"])) {
													if (SQL::tableExists($gbp["other_table"])) {
														if (!empty($gbp["other_table"]) && !empty($gbp["title_field"])) {
															$title_field = str_replace("`","",$gbp["title_field"]);
															$other_table = str_replace("`","",$gbp["other_table"]);
															$gbp_categories = SQL::fetchAll("SELECT id, `$title_field` AS `title` FROM `$other_table` ORDER BY `$title_field` ASC");
															
															// Run parser on the name if it exists
															if (!empty($gbp["item_parser"])) {
																foreach ($gbp_categories as &$category) {
																	$category["title"] = call_user_func($gbp["item_parser"], $category["title"], $category["id"]);
																}
															}
														}
													}
												}
								?>
								<li>
									<span class="depth"></span>
									<a class="permission_label permission_label_wider<?php if (!count($gbp_categories)) { ?> disabled<?php } ?><?php if (!$closed) { ?>  expanded<?php } ?>" href="#"><?=$module->Name?></a>
									<span class="permission_level"><input title="Publisher" type="radio" data-category="Module" data-key="<?=$module->ID?>" name="permissions[module][<?=$module->ID?>]" value="p" <?php if ($user->Permissions["module"][$module->ID] == "p") { ?>checked="checked" <?php } ?>/></span>
									<span class="permission_level"><input title="Editor" type="radio" data-category="Module" data-key="<?=$module->ID?>" name="permissions[module][<?=$module->ID?>]" value="e" <?php if ($user->Permissions["module"][$module->ID] == "e") { ?>checked="checked" <?php } ?>/></span>
									<span class="permission_level"><input title="No Access" type="radio" data-category="Module" data-key="<?=$module->ID?>" name="permissions[module][<?=$module->ID?>]" value="n" <?php if (!$user->Permissions["module"][$module->ID] || $user->Permissions["module"][$module->ID] == "n") { ?>checked="checked" <?php } ?>/></span>
									<?php if (count($gbp_categories)) { ?>
									<ul class="depth_2"<?php if ($closed) { ?> style="display: none;"<?php } ?>>
										<?php foreach ($gbp_categories as $category) { ?>
										<li>
											<span class="depth"></span>
											<a class="permission_label permission_label_wider disabled" href="#"><?=$gbp["name"]?>: <?=$category["title"]?></a>
											<span class="permission_level"><input title="Publisher" type="radio" data-category="ModuleGBP" data-key="<?=$module->ID?>" data-sub-key="<?=$category["id"]?>" name="permissions[module_gbp][<?=$module->ID?>][<?=$category["id"]?>]" value="p" <?php if ($user->Permissions["module_gbp"][$module->ID][$category["id"]] == "p") { ?>checked="checked" <?php } ?>/></span>
											<span class="permission_level"><input title="Editor" type="radio" data-category="ModuleGBP" data-key="<?=$module->ID?>" data-sub-key="<?=$category["id"]?>" name="permissions[module_gbp][<?=$module->ID?>][<?=$category["id"]?>]" value="e" <?php if ($user->Permissions["module_gbp"][$module->ID][$category["id"]] == "e") { ?>checked="checked" <?php } ?>/></span>
											<span class="permission_level"><input title="No Access" type="radio" data-category="ModuleGBP" data-key="<?=$module->ID?>" data-sub-key="<?=$category["id"]?>" name="permissions[module_gbp][<?=$module->ID?>][<?=$category["id"]?>]" value="n" <?php if (!$user->Permissions["module_gbp"][$module->ID][$category["id"]] || $user->Permissions["module_gbp"][$module->ID][$category["id"]] == "n") { ?>checked="checked" <?php } ?>/></span>
										</li>
										<?php } ?>
									</ul>
									<?php } ?>
								</li>
								<?php
											}
										}
									}
								?>
							</ul>
						</section>
					</div>
					
					<div id="resource_permissions" style="display: none;">
						<div class="labels sticky_controls">
							<span class="permission_label folder_label"><?=Text::translate("Folder")?></span>
							<span class="permission_level"><?=Text::translate("Creator")?></span>
							<span class="permission_level"><?=Text::translate("Consumer")?></span>
							<span class="permission_level"><?=Text::translate("No Access")?></span>
							<span class="permission_level"><?=Text::translate("Inherit")?></span>
						</div>
						<section>
							<ul class="depth_1">
								<li class="top">
									<span class="depth"></span>
									<a class="permission_label folder_label expanded" href="#"><?=Text::translate("Home Folder")?></a>
									<span class="permission_level"><input title="Creator" type="radio" data-category="Resource" data-key="0" name="permissions[resources][0]" value="p" <?php if ($user->Permissions["resources"][0] == "p") { ?>checked="checked" <?php } ?>/></span>
									<span class="permission_level"><input title="Consumer" type="radio" data-category="Resource" data-key="0" name="permissions[resources][0]" value="e" <?php if ($user->Permissions["resources"][0] == "e" || !$user->Permissions["resources"][0]) { ?>checked="checked" <?php } ?>/></span>
									<span class="permission_level"><input title="No Access" type="radio" data-category="Resource" data-key="0" name="permissions[resources][0]" value="n" <?php if ($user->Permissions["resources"][0] == "n") { ?>checked="checked" <?php } ?>/></span>
									<span class="permission_level">&nbsp;</span>
									<?php _local_userDrawFolderLevel(0,2) ?>
								</li>
							</ul>
						</section>
					</div>
				</div>
			</fieldset>
		</section>
		<footer>
			<input id="edit_user_submit" type="submit" class="blue" value="<?=Text::translate("Update", true)?>" />
		</footer>
	</form>
</div>

<script>
	BigTree.localPages = false;
	$.ajax("<?=ADMIN_ROOT?>ajax/users/pages-json/", { complete: function(r) {
		BigTree.localPages = r.responseJSON;
	}});

	<?php
		// We prefer to keep these as objects as arrays can break numeric-ness, but we need PHP 5.3
		if (strnatcmp(phpversion(),'5.3') >= 0) {
	?>
	var BigTreeUserForm = {
		Alerts: <?=json_encode($user->Alerts,JSON_FORCE_OBJECT)?>,
		Permissions: {
			Page: <?=json_encode($user->Permissions["page"],JSON_FORCE_OBJECT)?>,
			Module: <?=json_encode($user->Permissions["module"],JSON_FORCE_OBJECT)?>,
			ModuleGBP: <?=json_encode($user->Permissions["module_gbp"],JSON_FORCE_OBJECT)?>,
			Resource: <?=json_encode($user->Permissions["resources"],JSON_FORCE_OBJECT)?>
		}
	};
	<?php
		} else {
	?>
	var BigTreeUserForm = {
		Alerts: <?=json_encode($user->Alerts)?>,
		Permissions: {
			Page: <?=json_encode($user->Permissions["page"])?>,
			Module: <?=json_encode($user->Permissions["module"])?>,
			ModuleGBP: <?=json_encode($user->Permissions["module_gbp"])?>,
			Resource: <?=json_encode($user->Permissions["resources"])?>
		}
	};
	<?php
		}
	?>

	BigTreeFormValidator("form.module");
	BigTreePasswordInput("input[type=password]");
	
	$("form.module").submit(function(ev) {
		$("#edit_user_submit").val("<?=Text::translate("Saving Permisions...", true)?>").prop("disabled",true);
		var permissions = $('<input name="permissions" type="hidden" />').val(json_encode(BigTreeUserForm.Permissions));
		var alerts = $('<input name="alerts" type="hidden" />').val(json_encode(BigTreeUserForm.Alerts));
		// Remove the radios / checkboxes from the permissions section as they can cause a post overrun
		$("#permission_section").find("input").remove();
		// Add the JSON versions
		$("#permission_section").append(permissions).append(alerts);
	});
	
	$(".user_permissions header a").click(function() {
		$(".user_permissions header a").removeClass("active");
		$(".user_permissions > div").hide();
		$(this).addClass("active");

		$("#" + $(this).attr("href").substr(1)).show();
		return false;
	});
	
	// Expand and collapse
	$(".user_permissions").on("click",".permission_label",function() {
		if ($(this).hasClass("disabled")) {
			return false;
		}
		
		if ($(this).hasClass("expanded")) {
			$(this).nextAll("ul").hide();
			$(this).removeClass("expanded");
		} else {
			var ul = $(this).nextAll("ul");
			// We already made this
			if (ul.length) {
				ul.show();
			// Going to pull from the JSON to create it
			} else {
				// If we aren't done loading the pages we can't do anything
				if (!BigTree.localPages) {
					return;
				}

				// Traverse our page tree
				var data = false;
				var inherited_alerts = false;
				$.fn.reverse = [].reverse;
				$(this).parentsUntil(".depth_1","li").reverse().each(function(index,el) {
					var id = $(el).find("a").attr("data-id");
					if ($(el).find("input[type=checkbox]").prop("checked")) {
						inherited_alerts = true;
					}
					if (!data) {
						data = BigTree.localPages["p" + id];
					} else {
						data = data.c["p" + id];
					}
				});

				// Build out the new level in the DOM
				var depth = (parseInt($(this).attr("data-depth")) + 1);
				ul = $('<ul class="depth_' + depth + '">');
				for (var i in data.c) {
					var page = data.c[i];
					var li = $('<li>');
					li.append('<span class="depth">');
					var a = $('<a href="#" data-id="' + page.i + '" data-depth="' + depth + '" class="permission_label<?php if ($user->Level > 0) { ?> permission_label_admin<?php } ?>">' + page.t + '</a>');
					if (!page.c) {
						a.addClass("disabled");
					}
					li.append(a);
					li.append('<span class="permission_alerts"><input type="checkbox" data-category="Alerts" data-key="' + page.i + '" name="alerts[' + page.i + ']" /></span>');
					li.append('<span class="permission_level"<?php if ($user->Level > 0) { ?> style="display: none;"<?php } ?>><input type="radio" data-category="Page" data-key="' + page.i + '" name="permissions[page][' + page.i + ']" value="p" /></span>');
					li.append('<span class="permission_level"<?php if ($user->Level > 0) { ?> style="display: none;"<?php } ?>><input type="radio" data-category="Page" data-key="' + page.i + '" name="permissions[page][' + page.i + ']" value="e" /></span>');
					li.append('<span class="permission_level"<?php if ($user->Level > 0) { ?> style="display: none;"<?php } ?>><input type="radio" data-category="Page" data-key="' + page.i + '" name="permissions[page][' + page.i + ']" value="n" /></span>');
					li.append('<span class="permission_level"<?php if ($user->Level > 0) { ?> style="display: none;"<?php } ?>><input type="radio" data-category="Page" data-key="' + page.i + '" name="permissions[page][' + page.i + ']" value="i" checked="checked" /></span>');
					if (inherited_alerts) {
						li.find("input[type=checkbox]").prop("checked",true).prop("disabled",true);
					}
					ul.append(li);
				}
				$(this).parent().append(ul);
				BigTreeCustomControls(ul);
				_localObservers(ul);
			}
			$(this).addClass("expanded");
		}
		
		return false;
	});
	
	function _localObservers(selector) {
		// Observe content alert checkboxes
		$(selector).find("input[type=checkbox]").on("click",function() {
			if ($(this).prop("checked")) {
				$(this).parent().parent().find("ul input[type=checkbox]").each(function() {
					$(this).prop("checked",true).prop("disabled",true);
					this.customControl.Link.addClass("checked").addClass("disabled");
				});
				BigTreeUserForm.Alerts[$(this).attr("data-key")] = "on";
			} else {
				$(this).parent().parent().find("ul input[type=checkbox]").each(function() {
					$(this).prop("checked",false).prop("disabled",false);
					this.customControl.Link.removeClass("checked").removeClass("disabled");
				});
				BigTreeUserForm.Alerts[$(this).attr("data-key")] = "";
			}
		});
	
		// Observe all the permission radios
		$(selector).find("input[type=radio]").on("click",function() {
			var category = $(this).attr("data-category");
			var key = $(this).attr("data-key");
			if (!BigTreeUserForm.Permissions[category]) {
				BigTreeUserForm.Permissions[category] = {};
			}
			if (category == "ModuleGBP") {
				var sub = $(this).attr("data-sub-key");
				if (!BigTreeUserForm.Permissions[category][key]) {
					BigTreeUserForm.Permissions[category][key] = {};
				}
				BigTreeUserForm.Permissions[category][key][sub] = $(this).attr("value");
			} else {
				BigTreeUserForm.Permissions[category][key] = $(this).attr("value");
			}
		});
	}
	
	$("#user_level").on("change",function(event,data) {
		if (data.value  > 0) {
			// Set the active tab to Pages, show the Pages section, hide the header.
			$(".user_permissions header").hide().find("a").removeClass("active").eq(0).addClass("active");
			$(".user_permissions > div").hide().eq(0).show();
			$(".user_permissions .permission_level").hide();
			$(".user_permissions .permission_label").addClass("permission_label_admin");
			$("#regular_user_message").hide();
			$("#admin_user_message").show();
		} else {
			$(".user_permissions header").show();
			$(".user_permissions .permission_level").show();
			$(".user_permissions .permission_label").removeClass("permission_label_admin");
			$("#regular_user_message").show();
			$("#admin_user_message").hide();
		}
	});
	
	
	$(document).ready(function() {
		$("input.email").blur(function() {
			$(this).parent("fieldset").find(".gravatar").show().find("img").attr("src", 'http://www.gravatar.com/avatar/' + md5($(this).val().trim()) + '?s=36&d=' + encodeURIComponent("<?=ADMIN_ROOT?>images/icon_default_gravatar.jpg") + '&rating=pg');
		});
		_localObservers("#permission_section");
	});
</script>