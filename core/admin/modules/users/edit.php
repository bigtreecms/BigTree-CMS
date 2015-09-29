<?php
	// We set this header so that when the user reloads the page form element changes don't stick (since we're only tracking explicit changes back to the JSON objects for Alerts and Permissions)
	header('Cache-Control: no-store');
	$user = $admin->getUser(end($bigtree['commands']));

	// Stop if this is a 404 or the user is editing someone higher than them.
	if (!$user || $user['level'] > $admin->Level) {
	    ?>
<div class="container">
	<section>
		<h3>Error</h3>
		<p>The user you are trying to edit no longer exists or you are not allowed to edit this user.</p>
	</section>
</div>
<?php
		$admin->stop();
	}

	$bigtree['gravatar'] = $user['email'];
	BigTree::globalizeArray($user);

	if (!$permissions) {
	    $permissions = array(
			'page' => array(),
			'module' => array(),
			'resources' => array(),
			'module_gbp' => array(),
		);
	} else {
	    if (!is_array($permissions['module_gbp'])) {
	        $permissions['module_gbp'] = array();
	    }
	}

	// We need to gather all the page levels that should be expanded (anything that isn't "inherit" should have its parents pre-opened)
	$page_ids = array();
	if (is_array($permissions['page'])) {
	    foreach ($permissions['page'] as $id => $permission) {
	        if ($permission != 'i') {
	            $page_ids[] = $id;
	        }
	    }
	}
	if (is_array($alerts)) {
	    foreach ($alerts as $id => $on) {
	        $page_ids[] = $id;
	    }
	}

	$page_ids = array_unique($page_ids);

	$pre_opened_parents = array();
	foreach ($page_ids as $id) {
	    $pre_opened_parents = array_merge($pre_opened_parents, $admin->getPageLineage($id));
	}

	// Gather up the parents for resource folders that should be open by default.
	$pre_opened_folders = array();
	if (is_array($permissions['resources'])) {
	    foreach ($permissions['resources'] as $id => $permission) {
	        if ($permission != 'i') {
	            $folder = $admin->getResourceFolder($id);
	            $pre_opened_folders[] = $folder['parent'];
	        }
	    }
	}

	function _local_userDrawNavLevel($parent, $depth, $alert_above = false, $children = false)
	{
	    global $permissions,$alerts,$admin,$user,$pre_opened_parents;
	    if (!$children) {
	        $children = $admin->getPageChildren($parent);
	    }
	    if (count($children)) {
	        ?>
<ul class="depth_<?=$depth?>">
	<?php
			foreach ($children as $f) {
			    $grandchildren = $admin->getPageChildren($f['id']);
			    $alert_below = ($alert_above || (isset($alerts[$f['id']]) && $alerts[$f['id']])) ? true : false;
			    ?>
	<li>
		<span class="depth"></span>
		<a class="permission_label<?php if (!$grandchildren) {
    ?> disabled<?php 
}
			    ?><?php if ($user['level'] > 0) {
    ?> permission_label_admin<?php 
}
			    ?><?php if (in_array($f['id'], $pre_opened_parents)) {
    ?> expanded<?php 
}
			    ?>" href="#" data-id="<?=$f['id']?>" data-depth="<?=$depth?>"><?=$f['nav_title']?></a>
		<span class="permission_alerts"><input type="checkbox" data-category="Alerts" data-key="<?=$f['id']?>" name="alerts[<?=$f['id']?>]"<?php if ((isset($alerts[$f['id']]) && $alerts[$f['id']] == 'on') || $alert_above) {
    ?> checked="checked"<?php 
}
			    ?><?php if ($alert_above) {
    ?> disabled="disabled"<?php 
}
			    ?>/></span>
		<span class="permission_level"<?php if ($user['level'] > 0) {
    ?> style="display: none;"<?php 
}
			    ?>>
			<input type="radio" data-category="Page" data-key="<?=$f['id']?>" name="permissions[page][<?=$f['id']?>]" value="p" <?php if ($permissions['page'][$f['id']] == 'p') {
    ?>checked="checked" <?php 
}
			    ?>/>
		</span>
		<span class="permission_level"<?php if ($user['level'] > 0) {
    ?> style="display: none;"<?php 
}
			    ?>>
			<input type="radio" data-category="Page" data-key="<?=$f['id']?>" name="permissions[page][<?=$f['id']?>]" value="e" <?php if ($permissions['page'][$f['id']] == 'e') {
    ?>checked="checked" <?php 
}
			    ?>/>
		</span>
		<span class="permission_level"<?php if ($user['level'] > 0) {
    ?> style="display: none;"<?php 
}
			    ?>>
			<input type="radio" data-category="Page" data-key="<?=$f['id']?>" name="permissions[page][<?=$f['id']?>]" value="n" <?php if ($permissions['page'][$f['id']] == 'n') {
    ?>checked="checked" <?php 
}
			    ?>/>
		</span>
		<span class="permission_level"<?php if ($user['level'] > 0) {
    ?> style="display: none;"<?php 
}
			    ?>>
			<input type="radio" data-category="Page" data-key="<?=$f['id']?>" name="permissions[page][<?=$f['id']?>]" value="i" <?php if (!$permissions['page'][$f['id']] || $permissions['page'][$f['id']] == 'i') {
    ?>checked="checked" <?php 
}
			    ?>/>
		</span>
		<?php
				if (in_array($f['id'], $pre_opened_parents)) {
				    _local_userDrawNavLevel($f['id'], $depth + 1, $alert_below, $grandchildren);
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

	function _local_userDrawFolderLevel($parent, $depth, $children = false)
	{
	    global $permissions,$alerts,$admin,$pre_opened_folders;
	    if (!$children) {
	        $children = $admin->getResourceFolderChildren($parent);
	    }
	    if (count($children)) {
	        ?>
<ul class="depth_<?=$depth?>"<?php if ($depth > 2 && !in_array($parent, $pre_opened_folders)) {
    ?> style="display: none;"<?php 
}
	        ?>>
	<?php
			foreach ($children as $f) {
			    $grandchildren = $admin->getResourceFolderChildren($f['id']);
			    ?>
	<li>
		<span class="depth"></span>
		<a class="permission_label folder_label<?php if (!count($grandchildren)) {
    ?> disabled<?php 
}
			    ?><?php if (in_array($f['id'], $pre_opened_folders)) {
    ?> expanded<?php 
}
			    ?>" href="#"><?=$f['name']?></a>
		<span class="permission_level"><input type="radio" data-category="Resource" data-key="<?=$f['id']?>" name="permissions[resources][<?=$f['id']?>]" value="p" <?php if ($permissions['resources'][$f['id']] == 'p') {
    ?>checked="checked" <?php 
}
			    ?>/></span>
		<span class="permission_level"><input type="radio" data-category="Resource" data-key="<?=$f['id']?>" name="permissions[resources][<?=$f['id']?>]" value="e" <?php if ($permissions['resources'][$f['id']] == 'e') {
    ?>checked="checked" <?php 
}
			    ?>/></span>
		<span class="permission_level"><input type="radio" data-category="Resource" data-key="<?=$f['id']?>" name="permissions[resources][<?=$f['id']?>]" value="n" <?php if ($permissions['resources'][$f['id']] == 'n') {
    ?>checked="checked" <?php 
}
			    ?>/></span>
		<span class="permission_level"><input type="radio" data-category="Resource" data-key="<?=$f['id']?>" name="permissions[resources][<?=$f['id']?>]" value="i" <?php if (!$permissions['resources'][$f['id']] || $permissions['resources'][$f['id']] == 'i') {
    ?>checked="checked" <?php 
}
			    ?>/></span>
		<?php _local_userDrawFolderLevel($f['id'], $depth + 1, $grandchildren) ?>
	</li>
	<?php

			}
	        ?>
</ul>
<?php

	    }
	}

	$error = '';
	if (isset($_SESSION['bigtree_admin']['update_user'])) {
	    BigTree::globalizeArray($_SESSION['bigtree_admin']['update_user'], array('htmlspecialchars'));
	    unset($_SESSION['bigtree_admin']['update_user']);
	}

	// Prevent a notice on alerts
	if (!is_array($alerts)) {
	    $alerts = array(array());
	}

	$groups = $admin->getModuleGroups('name ASC');
?>
<div class="container">
	<form class="module" action="<?=ADMIN_ROOT?>users/update/" method="post">
		<input type="hidden" name="id" value="<?=$user['id']?>" />
		<section>
			<p class="error_message"<?php if (!$error) {
    ?> style="display: none;"<?php 
} ?>>Errors found! Please fix the highlighted fields before submitting.</p>
			<div class="left">
				<fieldset<?php if ($error == 'email') {
    ?> class="form_error"<?php 
} ?> style="position: relative;">
					<label class="required">Email <small>(Profile images from <a href="http://www.gravatar.com/" target="_blank">Gravatar</a>)</small> <?php if ($error == 'email') {
    ?><span class="form_error_reason">Already In Use By Another User</span><?php 
} ?></label>
					<input type="text" class="required email" name="email" autocomplete="off" value="<?=htmlspecialchars($email)?>" tabindex="1" />
					<span class="gravatar"<?php if ($email) {
    ?> style="display: block;"<?php 
} ?>><img src="<?=BigTree::gravatar($email, 36)?>" alt="" /></span>
				</fieldset>
				
				<fieldset<?php if ($error == 'password') {
    ?> class="form_error"<?php 
} ?> >
					<label>Password <small>(Leave blank to remain unchanged)</small> <?php if ($error == 'password') {
    ?><span class="form_error_reason">Did Not Meet Requirements</span><?php 
} ?></label>
					<input type="password" name="password" value="" tabindex="3" autocomplete="off" id="password_field"<?php if ($policy) {
    ?> class="has_tooltip" data-tooltip="<?=htmlspecialchars($policy_text)?>"<?php 
} ?> />
					<?php if ($policy) {
    ?>
					<p class="password_policy">Password Policy In Effect</p>
					<?php 
} ?>
				</fieldset>

				<?php if ($user['id'] != $admin->ID) {
    ?>
				<fieldset>
					<label class="required">User Level</label>
					<select name="level" tabindex="5" id="user_level">
						<option value="0"<?php if ($user['level'] == '0') {
    ?> selected="selected"<?php 
}
    ?>>Normal User</option>
						<option value="1"<?php if ($user['level'] == '1') {
    ?> selected="selected"<?php 
}
    ?>>Administrator</option>
						<?php if ($admin->Level > 1) {
    ?><option value="2"<?php if ($user['level'] == '2') {
    ?> selected="selected"<?php 
}
    ?>>Developer</option><?php 
}
    ?>
					</select>
				</fieldset>
				<?php 
} ?>
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
				
				<br />
				
				<fieldset>
					<input type="checkbox" name="daily_digest" tabindex="4" <?php if ($daily_digest) {
    ?> checked="checked"<?php 
} ?> />
					<label class="for_checkbox">Daily Digest Email</label>
				</fieldset>
			</div>			
		</section>
		<section class="sub" id="permission_section">
			<fieldset class="last">
				<label>Permissions
					<small id="admin_user_message"<?php if ($user['level'] < 1) {
    ?> style="display: none;"<?php 
} ?>>(this user is an <strong>administrator</strong> and is a publisher of the entire site)</small>
					<small id="regular_user_message"<?php if ($user['level'] > 0) {
    ?> style="display: none;"<?php 
} ?>>(for module sub-permissions "No Access" inherits from the main permission level)</small>
				</label>
			
				<div class="user_permissions form_table">
					<header<?php if ($user['level'] > 0) {
    ?> style="display: none;"<?php 
} ?>>
						<nav>
							<a href="#page_permissions" class="active">Pages</a>
							<a href="#module_permissions">Modules</a>
							<a href="#resource_permissions">Resources</a>
						</nav>
					</header>
					<div id="page_permissions">
						<div class="labels sticky_controls">
							<span class="permission_label<?php if ($user['level'] > 0) {
    ?> permission_label_admin<?php 
} ?>">Page</span>
							<span class="permission_alerts">Content Alerts</span>
							<span class="permission_level"<?php if ($user['level'] > 0) {
    ?> style="display: none;"<?php 
} ?>>Publisher</span>
							<span class="permission_level"<?php if ($user['level'] > 0) {
    ?> style="display: none;"<?php 
} ?>>Editor</span>
							<span class="permission_level"<?php if ($user['level'] > 0) {
    ?> style="display: none;"<?php 
} ?>>No Access</span>
							<span class="permission_level"<?php if ($user['level'] > 0) {
    ?> style="display: none;"<?php 
} ?>>Inherit</span>
						</div>
						<section>
							<ul class="depth_1">
								<li class="top">
									<span class="depth"></span>
									<a class="permission_label expanded<?php if ($user['level'] > 0) {
    ?> permission_label_admin<?php 
} ?>" href="#">All Pages</a>
									<span class="permission_alerts"><input type="checkbox" name="alerts[0]"<?php if ($alerts[0] == 'on') {
    ?> checked="checked"<?php 
} ?>/></span>
									<span class="permission_level"<?php if ($user['level'] > 0) {
    ?> style="display: none;"<?php 
} ?>>
										<input type="radio" data-category="Page" data-key="0" name="permissions[page][0]" value="p" <?php if ($permissions['page'][0] == 'p') {
    ?>checked="checked" <?php 
} ?>/>
									</span>
									<span class="permission_level"<?php if ($user['level'] > 0) {
    ?> style="display: none;"<?php 
} ?>>
										<input type="radio" data-category="Page" data-key="0" name="permissions[page][0]" value="e" <?php if ($permissions['page'][0] == 'e') {
    ?>checked="checked" <?php 
} ?>/>
									</span>
									<span class="permission_level"<?php if ($user['level'] > 0) {
    ?> style="display: none;"<?php 
} ?>>
										<input type="radio" data-category="Page" data-key="0" name="permissions[page][0]" value="n" <?php if ($permissions['page'][0] == 'n' || !$permissions['page'][0]) {
    ?>checked="checked" <?php 
} ?>/>
									</span>
									<span class="permission_level"<?php if ($user['level'] > 0) {
    ?> style="display: none;"<?php 
} ?>>&nbsp;</span>
									<?php _local_userDrawNavLevel(0, 2, $alerts[0]) ?>
								</li>
							</ul>
						</section>
					</div>
					
					<div id="module_permissions" style="display: none;">
						<div class="labels sticky_controls">
							<span class="permission_label permission_label_wider">Module</span>
							<span class="permission_level">Publisher</span>
							<span class="permission_level">Editor</span>
							<span class="permission_level">No Access</span>
						</div>
						<section>
							<ul class="depth_1">
								<?php
									$groups[] = array('id' => 0, 'name' => '- Ungrouped -');
									foreach ($groups as $group) {
									    $modules = $admin->getModulesByGroup($group, 'name ASC');
									    if (count($modules)) {
									        ?>
								<li class="module_group">
									<span class="module_group_name"><?=$group['name']?></span>
								</li>
								<?php
											foreach ($modules as $m) {
											    $gbp = json_decode($m['gbp'], true);
											    if (!is_array($gbp)) {
											        $gbp = array();
											    }

												// Determine whether we have access to anything in this section (default to open) or not (default to closed)
												$closed = true;
											    if (is_array($permissions['module_gbp'][$m['id']])) {
											        foreach ($permissions['module_gbp'][$m['id']] as $id => $permission) {
											            if ($permission != 'n') {
											                $closed = false;
											            }
											        }
											    }
											    ?>
								<li>
									<span class="depth"></span>
									<a class="permission_label permission_label_wider<?php if (!isset($gbp['enabled']) || !$gbp['enabled']) {
    ?> disabled<?php 
}
											    ?><?php if (!$closed) {
    ?>  expanded<?php 
}
											    ?>" href="#"><?=$m['name']?></a>
									<span class="permission_level"><input type="radio" data-category="Module" data-key="<?=$m['id']?>" name="permissions[module][<?=$m['id']?>]" value="p" <?php if ($permissions['module'][$m['id']] == 'p') {
    ?>checked="checked" <?php 
}
											    ?>/></span>
									<span class="permission_level"><input type="radio" data-category="Module" data-key="<?=$m['id']?>" name="permissions[module][<?=$m['id']?>]" value="e" <?php if ($permissions['module'][$m['id']] == 'e') {
    ?>checked="checked" <?php 
}
											    ?>/></span>
									<span class="permission_level"><input type="radio" data-category="Module" data-key="<?=$m['id']?>" name="permissions[module][<?=$m['id']?>]" value="n" <?php if (!$permissions['module'][$m['id']] || $permissions['module'][$m['id']] == 'n') {
    ?>checked="checked" <?php 
}
											    ?>/></span>
									<?php
												if (isset($gbp['enabled']) && $gbp['enabled']) {
												    if (BigTree::tableExists($gbp['other_table'])) {
												        $categories = array();
												        $ot = sqlescape($gbp['other_table']);
												        $tf = sqlescape($gbp['title_field']);
												        if ($tf && $ot) {
												            $q = sqlquery("SELECT id,`$tf` FROM `$ot` ORDER BY `$tf` ASC");
												            ?>
									<ul class="depth_2"<?php if ($closed) {
    ?> style="display: none;"<?php 
}
												            ?>>
										<?php
															while ($c = sqlfetch($q)) {
															    ?>
										<li>
											<span class="depth"></span>
											<a class="permission_label permission_label_wider disabled" href="#"><?=$gbp['name']?>: <?=$c[$tf]?></a>
											<span class="permission_level"><input type="radio" data-category="ModuleGBP" data-key="<?=$m['id']?>" data-sub-key="<?=$c['id']?>" name="permissions[module_gbp][<?=$m['id']?>][<?=$c['id']?>]" value="p" <?php if ($permissions['module_gbp'][$m['id']][$c['id']] == 'p') {
    ?>checked="checked" <?php 
}
															    ?>/></span>
											<span class="permission_level"><input type="radio" data-category="ModuleGBP" data-key="<?=$m['id']?>" data-sub-key="<?=$c['id']?>" name="permissions[module_gbp][<?=$m['id']?>][<?=$c['id']?>]" value="e" <?php if ($permissions['module_gbp'][$m['id']][$c['id']] == 'e') {
    ?>checked="checked" <?php 
}
															    ?>/></span>
											<span class="permission_level"><input type="radio" data-category="ModuleGBP" data-key="<?=$m['id']?>" data-sub-key="<?=$c['id']?>" name="permissions[module_gbp][<?=$m['id']?>][<?=$c['id']?>]" value="n" <?php if (!$permissions['module_gbp'][$m['id']][$c['id']] || $permissions['module_gbp'][$m['id']][$c['id']] == 'n') {
    ?>checked="checked" <?php 
}
															    ?>/></span>
										</li>
										<?php

															}
												            ?>
									</ul>
									<?php

												        }
												    }
												}
											}
									        ?>
								</li>
								<?php

									    }
									}
								?>	
							</ul>
						</section>
					</div>
					
					<div id="resource_permissions" style="display: none;">
						<div class="labels sticky_controls">
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
									<span class="permission_level"><input type="radio" data-category="Resource" data-key="0" name="permissions[resources][0]" value="p" <?php if ($permissions['resources'][0] == 'p') {
    ?>checked="checked" <?php 
} ?>/></span>
									<span class="permission_level"><input type="radio" data-category="Resource" data-key="0" name="permissions[resources][0]" value="e" <?php if ($permissions['resources'][0] == 'e' || !$permissions['resources'][0]) {
    ?>checked="checked" <?php 
} ?>/></span>
									<span class="permission_level"><input type="radio" data-category="Resource" data-key="0" name="permissions[resources][0]" value="n" <?php if ($permissions['resources'][0] == 'n') {
    ?>checked="checked" <?php 
} ?>/></span>
									<span class="permission_level">&nbsp;</span>
									<?php _local_userDrawFolderLevel(0, 2) ?>
								</li>
							</ul>
						</section>
					</div>
				</div>
			</fieldset>
		</section>
		<footer>
			<input id="edit_user_submit" type="submit" class="blue" value="Update" />
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
		if (strnatcmp(phpversion(), '5.3') >= 0) {
		    ?>
	var BigTreeUserForm = {
		Alerts: <?=json_encode($alerts, JSON_FORCE_OBJECT)?>,
		Permissions: {
			Page: <?=json_encode($permissions['page'], JSON_FORCE_OBJECT)?>,
			Module: <?=json_encode($permissions['module'], JSON_FORCE_OBJECT)?>,
			ModuleGBP: <?=json_encode($permissions['module_gbp'], JSON_FORCE_OBJECT)?>,
			Resource: <?=json_encode($permissions['resources'], JSON_FORCE_OBJECT)?>
		}
	};
	<?php

		} else {
		    ?>
	var BigTreeUserForm = {
		Alerts: <?=json_encode($alerts)?>,
		Permissions: {
			Page: <?=json_encode($permissions['page'])?>,
			Module: <?=json_encode($permissions['module'])?>,
			ModuleGBP: <?=json_encode($permissions['module_gbp'])?>,
			Resource: <?=json_encode($permissions['resources'])?>
		}
	};
	<?php

		}
	?>

	BigTreeFormValidator("form.module");
	BigTreePasswordInput("input[type=password]");
	
	$("form.module").submit(function(ev) {
		$("#edit_user_submit").val("Saving Permisions...").prop("disabled",true);
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
						data = BigTree.localPages[id];
					} else {
						data = data.c[id];
					}
				});

				// Build out the new level in the DOM
				var depth = (parseInt($(this).attr("data-depth")) + 1);
				ul = $('<ul class="depth_' + depth + '">');
				for (var i in data.c) {
					var page = data.c[i];
					var li = $('<li>');
					li.append('<span class="depth">');
					var a = $('<a href="#" data-id="' + page.i + '" data-depth="' + depth + '" class="permission_label<?php if ($user['level'] > 0) {
    ?> permission_label_admin<?php 
} ?>">' + page.t + '</a>');
					if (!page.c) {
						a.addClass("disabled");
					}
					li.append(a);
					li.append('<span class="permission_alerts"><input type="checkbox" data-category="Alerts" data-key="' + page.i + '" name="alerts[' + page.i + ']" /></span>');
					li.append('<span class="permission_level"<?php if ($user['level'] > 0) {
    ?> style="display: none;"<?php 
} ?>><input type="radio" data-category="Page" data-key="' + page.i + '" name="permissions[page][' + page.i + ']" value="p" /></span>');
					li.append('<span class="permission_level"<?php if ($user['level'] > 0) {
    ?> style="display: none;"<?php 
} ?>><input type="radio" data-category="Page" data-key="' + page.i + '" name="permissions[page][' + page.i + ']" value="e" /></span>');
					li.append('<span class="permission_level"<?php if ($user['level'] > 0) {
    ?> style="display: none;"<?php 
} ?>><input type="radio" data-category="Page" data-key="' + page.i + '" name="permissions[page][' + page.i + ']" value="n" /></span>');
					li.append('<span class="permission_level"<?php if ($user['level'] > 0) {
    ?> style="display: none;"<?php 
} ?>><input type="radio" data-category="Page" data-key="' + page.i + '" name="permissions[page][' + page.i + ']" value="i" checked="checked" /></span>');
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