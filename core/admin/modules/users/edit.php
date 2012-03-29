<?
	$breadcrumb[] = array("link" => "#", "title" => "Edit User");
	
	$user = $admin->getUser($commands[0]);
	BigTree::globalizeArray($user,array("htmlspecialchars"));
	
	if (!$permissions) {
		$permissions = array(
			"page" => array(),
			"module" => array()
		);
	}
	
	function _local_userDrawNavLevel($parent,$depth,$alert_above = false,$children = false) {
		global $permissions,$alerts,$admin;
		if (!$children) {
			$children = $admin->getPageChildren($parent);
		}
		if (count($children)) {
?>
<ul class="depth_<?=$depth?>"<? if ($depth > 2) { ?> style="display: none;"<? } ?>>
	<?
			foreach ($children as $f) {
				$grandchildren = $admin->getPageChildren($f["id"]);
				$alert_below = ($alert_above || $alerts[$f["id"]]) ? true : false;
	?>
	<li>
		<span class="depth"></span>
		<a class="permission_label<? if (!$grandchildren) { ?> disabled<? } ?>" href="#"><?=$f["nav_title"]?></a>
		<span class="permission_alerts"><input type="checkbox" name="alerts[<?=$f["id"]?>]"<? if ($alerts[$f["id"]] == "on" || $alert_above) { ?> checked="checked"<? } ?><? if ($alert_above) { ?> disabled="disabled"<? } ?>/></span>
		<span class="permission_level"><input type="radio" name="permissions[page][<?=$f["id"]?>]" value="p" <? if ($permissions["page"][$f["id"]] == "p") { ?>checked="checked" <? } ?>/></span>
		<span class="permission_level"><input type="radio" name="permissions[page][<?=$f["id"]?>]" value="e" <? if ($permissions["page"][$f["id"]] == "e") { ?>checked="checked" <? } ?>/></span>
		<span class="permission_level"><input type="radio" name="permissions[page][<?=$f["id"]?>]" value="n" <? if ($permissions["page"][$f["id"]] == "n") { ?>checked="checked" <? } ?>/></span>
		<span class="permission_level"><input type="radio" name="permissions[page][<?=$f["id"]?>]" value="i" <? if (!$permissions["page"][$f["id"]] || $permissions["page"][$f["id"]] == "i") { ?>checked="checked" <? } ?>/></span>
		<? _local_userDrawNavLevel($f["id"],$depth + 1,$alert_below,$grandchildren) ?>
	</li>
	<?
			}
	?>
</ul>
<?
		}
	}
	
	function _local_userDrawFolderLevel($parent,$depth,$children = false) {
		global $permissions,$alerts,$admin;
		if (!$children) {
			$children = $admin->getResourceFolderChildren($parent);
		}
		if (count($children)) {
?>
<ul class="depth_<?=$depth?>"<? if ($depth > 2) { ?> style="display: none;"<? } ?>>
	<?
			foreach ($children as $f) {
				$grandchildren = $admin->getResourceFolderChildren($f["id"]);
	?>
	<li>
		<span class="depth"></span>
		<a class="permission_label folder_label<? if (!count($grandchildren)) { ?> disabled<? } ?>" href="#"><?=$f["name"]?></a>
		<span class="permission_level"><input type="radio" name="permissions[resources][<?=$f["id"]?>]" value="p" <? if ($permissions["resources"][$f["id"]] == "p") { ?>checked="checked" <? } ?>/></span>
		<span class="permission_level"><input type="radio" name="permissions[resources][<?=$f["id"]?>]" value="e" <? if ($permissions["resources"][$f["id"]] == "e") { ?>checked="checked" <? } ?>/></span>
		<span class="permission_level"><input type="radio" name="permissions[resources][<?=$f["id"]?>]" value="n" <? if ($permissions["resources"][$f["id"]] == "n") { ?>checked="checked" <? } ?>/></span>
		<span class="permission_level"><input type="radio" name="permissions[resources][<?=$f["id"]?>]" value="i" <? if (!$permissions["resources"][$f["id"]] || $permissions["resources"][$f["id"]] == "i") { ?>checked="checked" <? } ?>/></span>
		<? _local_userDrawFolderLevel($f["id"],$depth + 1,$grandchildren) ?>
	</li>
	<?
			}
	?>
</ul>
<?
		}
	}
	
	$e = false;

	if (isset($_SESSION["bigtree"]["update_user"])) {
		BigTree::globalizeArray($_SESSION["bigtree"]["update_user"],array("htmlspecialchars"));
		$e = true;
		unset($_SESSION["bigtree"]["update_user"]);
	}
	
	$modules = $admin->getModules("name ASC");
?>
<h1><span class="users"></span>Edit User</h1>
<? include BigTree::path("admin/modules/users/_nav.php"); ?>
<div class="form_container">
	<form class="module" action="<?=$admin_root?>users/update/<?=$path[3]?>/" method="post">
		<section>
			<p class="error_message"<? if (!$e) { ?> style="display: none;"<? } ?>>Errors found! Please fix the highlighted fields before submitting.</p>
			<div class="left">
				<fieldset<? if ($e) { ?> class="form_error"<? } ?>>
					<label class="required">Email<? if ($e) { ?><span class="form_error_reason">Already In Use By Another User</span><? } ?></label>
					<input type="text" class="required email" name="email" value="<?=$email?>" tabindex="1" />
				</fieldset>
				
				<fieldset>
					<label>Password <small>(leave blank to remain unchanged)</small></label>
					<input type="password" name="password" value="" tabindex="3" />
				</fieldset>
				<? if ($user["id"] != $admin->ID) { ?>
				<fieldset>
					<label class="required">User Level</label>
					<select name="level" tabindex="5" id="user_level">
						<option value="0"<? if ($user["level"] == "0") { ?> selected="selected"<? } ?>>Normal User</option>
						<option value="1"<? if ($user["level"] == "1") { ?> selected="selected"<? } ?>>Administrator</option>
						<? if ($admin->Level > 1) { ?><option value="2"<? if ($user["level"] == "2") { ?> selected="selected"<? } ?>>Developer</option><? } ?>
					</select>
				</fieldset>
				<? } ?>
			</div>
			<div class="right">
				<fieldset>
					<label>Name</label>
					<input type="text" name="name" value="<?=$name?>" tabindex="2" />
				</fieldset>
				
				<fieldset>
					<label>Company</label>
					<input type="text" name="company" value="<?=$company?>" tabindex="4" />
				</fieldset>
				
				<br /><br />
				
				<fieldset>
					<input type="checkbox" name="daily_digest" tabindex="4" <? if ($daily_digest) { ?> checked="checked"<? } ?> />
					<label class="for_checkbox">Daily Digest Email</label>
				</fieldset>
			</div>			
		</section>
		<section class="sub" id="permission_section">
			<fieldset>
				<label>Permissions
					<small id="admin_user_message"<? if ($user["level"] < 1) { ?> style="display: none;"<? } ?>>(this user is an <strong>administrator</strong> and is a publisher of the entire site &mdash; permissions below are ignored)</small>
					<small id="regular_user_message"<? if ($user["level"] > 0) { ?> style="display: none;"<? } ?>>(for module sub-permissions "No Access" inherits from the main permission level)</small>
				</label>
			
				<div class="user_permissions form_table">
					<header>
						<nav>
							<ul>
								<li><a href="#page_permissions" class="active">Pages</a></li>
								<li><a href="#module_permissions">Modules</a></li>
								<li><a href="#resource_permissions">Resources</a></li>
							</ul>
						</nav>
					</header>
					<div id="page_permissions">
						<div class="labels">
							<span class="permission_label">Page</span>
							<span class="permission_alerts">Content Alerts</span>
							<span class="permission_level">Publisher</span>
							<span class="permission_level">Editor</span>
							<span class="permission_level">No Access</span>
							<span class="permission_level">Inherit</span>
						</div>
						<section>
							<ul class="depth_1">
								<li class="top">
									<span class="depth"></span>
									<a class="permission_label expanded" href="#">All Pages</a>
									<span class="permission_alerts"><input type="checkbox" name="alerts[0]"<? if ($alerts[0] == "on") { ?> checked="checked"<? } ?>/></span>
									<span class="permission_level"><input type="radio" name="permissions[page][<?=$f["id"]?>]" value="p" <? if ($permissions["page"][0] == "p") { ?>checked="checked" <? } ?>/></span>
									<span class="permission_level"><input type="radio" name="permissions[page][<?=$f["id"]?>]" value="e" <? if ($permissions["page"][0] == "e") { ?>checked="checked" <? } ?>/></span>
									<span class="permission_level"><input type="radio" name="permissions[page][<?=$f["id"]?>]" value="n" <? if ($permissions["page"][0] == "n" || !$permissions["page"][0]) { ?>checked="checked" <? } ?>/></span>
									<span class="permission_level">&nbsp;</span>
									<? _local_userDrawNavLevel(0,2,$alerts[0]) ?>
								</li>
							</ul>
						</section>
					</div>
					
					<div id="module_permissions" style="display: none;">
						<div class="labels">
							<span class="permission_label permission_label_wider">Module</span>
							<span class="permission_level">Publisher</span>
							<span class="permission_level">Editor</span>
							<span class="permission_level">No Access</span>
						</div>
						<section>
							<ul class="depth_1">
								<?
									$x = 0;
									foreach ($modules as $m) {
										$x++;
										$gbp = json_decode($m["gbp"],true);
										if (!is_array($gbp)) {
											$gbp = array();
										}
								?>
								<li<? if ($x == 1) { ?> class="top"<? } ?>>
									<span class="depth"></span>
									<a class="permission_label permission_label_wider<? if (!count($gbp)) { ?> disabled<? } ?>" href="#"><?=$m["name"]?></a>
									<span class="permission_level"><input type="radio" name="permissions[module][<?=$m["id"]?>]" value="p" <? if ($permissions["module"][$m["id"]] == "p") { ?>checked="checked" <? } ?>/></span>
									<span class="permission_level"><input type="radio" name="permissions[module][<?=$m["id"]?>]" value="e" <? if ($permissions["module"][$m["id"]] == "e") { ?>checked="checked" <? } ?>/></span>
									<span class="permission_level"><input type="radio" name="permissions[module][<?=$m["id"]?>]" value="n" <? if (!$permissions["module"][$m["id"]] || $permissions["module"][$m["id"]] == "n") { ?>checked="checked" <? } ?>/></span>
									<?
										if ($gbp["enabled"]) {
											$categories = array();
											$ot = mysql_real_escape_string($gbp["other_table"]);
											$tf = mysql_real_escape_string($gbp["title_field"]);
											if ($tf && $ot) {
												$q = sqlquery("SELECT id,`$tf` FROM `$ot` ORDER BY `$tf` ASC");
									?>
									<ul class="depth_2" style="display: none;">
										<? while ($c = sqlfetch($q)) { ?>
										<li>
											<span class="depth"></span>
											<a class="permission_label permission_label_wider disabled" href="#"><?=$gbp["name"]?>: <?=$c[$tf]?></a>
											<span class="permission_level"><input type="radio" name="permissions[module_gbp][<?=$m["id"]?>][<?=$c["id"]?>]" value="p" <? if ($permissions["module_gbp"][$m["id"]][$c["id"]] == "p") { ?>checked="checked" <? } ?>/></span>
											<span class="permission_level"><input type="radio" name="permissions[module_gbp][<?=$m["id"]?>][<?=$c["id"]?>]" value="e" <? if ($permissions["module_gbp"][$m["id"]][$c["id"]] == "e") { ?>checked="checked" <? } ?>/></span>
											<span class="permission_level"><input type="radio" name="permissions[module_gbp][<?=$m["id"]?>][<?=$c["id"]?>]" value="n" <? if (!$permissions["module_gbp"][$m["id"]][$c["id"]] || $permissions["module_gbp"][$m["id"]][$c["id"]] == "n") { ?>checked="checked" <? } ?>/></span>
										</li>
										<? } ?>
									</ul>
									<?
											}
										}
									?>
								</li>
								<?
									}
								?>	
							</ul>
						</section>
					</div>
					
					<div id="resource_permissions" style="display: none;">
						<div class="labels">
							<span class="permission_label folder_label">Folder</span>
							<span class="permission_level">Creator</span>
							<span class="permission_level">Consumer</span>
							<span class="permission_level">No Access</span>
							<span class="permission_level">Inherit</span>
						</div>
						<section>
							<ul class="depth_1">
								<li class="top">
									<span class="depth"></span>
									<a class="permission_label folder_label expanded" href="#">Home Folder</a>
									<span class="permission_level"><input type="radio" name="permissions[resources][<?=$f["id"]?>]" value="p" <? if ($permissions["resources"][0] == "p") { ?>checked="checked" <? } ?>/></span>
									<span class="permission_level"><input type="radio" name="permissions[resources][<?=$f["id"]?>]" value="e" <? if ($permissions["resources"][0] == "e" || !$permissions["resources"][0]) { ?>checked="checked" <? } ?>/></span>
									<span class="permission_level"><input type="radio" name="permissions[resources][<?=$f["id"]?>]" value="n" <? if ($permissions["resources"][0] == "n") { ?>checked="checked" <? } ?>/></span>
									<span class="permission_level">&nbsp;</span>
									<? _local_userDrawFolderLevel(0,2) ?>
								</li>
							</ul>
						</section>
					</div>
					
				</div>
			</fieldset>
		</section>
		<footer>
			<input type="submit" class="blue" value="Update" />
		</footer>
	</form>
</div>

<script type="text/javascript">
	new BigTreeFormValidator("form.module");
	
	$(".user_permissions header a").click(function() {		
		$(".user_permissions header a").removeClass("active");
		$(".user_permissions > div").hide();
		$(this).addClass("active");

		$("#" + $(this).attr("href").substr(1)).show();
		return false;
	});
	
	$(".permission_label").click(function() {
		if ($(this).hasClass("disabled")) {
			return false;
		}
			
		if ($(this).hasClass("expanded")) {
			if ($(this).nextAll("ul")) {
				$(this).nextAll("ul").hide();
			}
			$(this).removeClass("expanded");
		} else {
			if ($(this).nextAll("ul")) {
				$(this).nextAll("ul").show();
			}
			$(this).addClass("expanded");
		}
		
		return false;
	});
	
	$("input[type=checkbox]").on("checked:click",function() {
		if ($(this).attr("checked")) {
			$(this).parent().parent().find("ul input[type=checkbox]").each(function() {
				$(this).attr("checked","checked").attr("disabled","disabled");
				this.customControl.Link.addClass("checked").addClass("disabled");
			});
		} else {
			$(this).parent().parent().find("ul input[type=checkbox]").each(function() {
				$(this).attr("checked",false).attr("disabled",false);
				this.customControl.Link.removeClass("checked").removeClass("disabled");
			});
		}
	});
	
	$("#user_level").on("select:changed",function(event,data) {
		if (data.value  > 0) {
			$("#regular_user_message").hide();
			$("#admin_user_message").show();
		} else {
			$("#regular_user_message").show();
			$("#admin_user_message").hide();
		}
	});
</script>