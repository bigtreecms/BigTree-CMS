<div class="container">
	<header><p>Add modules, templates, callouts, field types, feeds, and settings to your extension.</p></header>
	<form method="post" action="<?=DEVELOPER_ROOT?>extensions/build/save-components/" class="module">
		<?php $admin->drawCSRFToken() ?>
		<section>
			<fieldset>
				<article class="package_column">
					<strong>Modules</strong>
					<ul>
						<?php
							foreach ((array)$modules as $mid) {
								$module = $admin->getModule($mid);
								if ($module) {
						?>
						<li>
							<input type="hidden" name="modules[]" value="<?=$mid?>" />
							<a href="#" class="icon_small icon_small_delete"></a>
							<span><?=$module["name"]?></span>
						</li>
						<?php
								}
							}
						?>
					</ul>
					<div class="adder">
						<a href="#"></a>
						<select class="custom_control" data-key="modules">
							<?php
								$groups = $admin->getModuleGroups("name ASC");
								$groups[] = array("id" => "0", "name" => "Ungrouped");
								foreach ($groups as $g) {
									$modules = $admin->getModulesByGroup($g["id"],"name ASC");
									if (count($modules)) {
							?>
							<optgroup label="<?=$g["name"]?>">
								<?php
										foreach ($modules as $m) {
											if (!$m["extension"] || $m["extension"] == $id) {
								?>
								<option value="<?=$m["id"]?>"<?php if ($m["id"] == $module) { ?> selected="selected"<?php } ?>><?=$m["name"]?></option>
								<?php
											}
										}
								?>
							</optgroup>
							<?php
									}
								}
							?>
						</select>
					</div>
				</article>
				<article class="package_column">
					<strong>Templates</strong>
					<ul>
						<?php
							foreach ((array)$templates as $tid) {
								$template = $cms->getTemplate($tid);
								if ($template) {
						?>
						<li>
							<input type="hidden" name="templates[]" value="<?=$tid?>" />
							<a href="#" class="icon_small icon_small_delete"></a>
							<span><?=$template["name"]?></span>
						</li>
						<?php
								}
							}
						?>
					</ul>
					<div class="adder">
						<a href="#"></a>
						<select class="custom_control" data-key="templates">
							<optgroup label="Basic Templates">
								<?php
									$templates = $admin->getBasicTemplates("name ASC");
									foreach ($templates as $template) {
										if (!$template["extension"] || $template["extension"] == $id) {
								?>
								<option value="<?=$template["id"]?>"><?=$template["name"]?></option>
								<?php
										}
									}
								?>
							</optgroup>
							<optgroup label="Routed Templates">
								<?php
									$templates = $admin->getRoutedTemplates("name ASC");
									foreach ($templates as $template) {
										if (!$template["extension"] || $template["extension"] == $id) {
								?>
								<option value="<?=$template["id"]?>"><?=$template["name"]?></option>
								<?php
										}
									}
								?>
							</optgroup>
						</select>
					</div>
				</article>
				<article class="package_column package_column_last">
					<strong>Callouts</strong>
					<ul>
						<?php
							foreach ((array)$callouts as $cid) {
								$callout = $admin->getCallout($cid);
								if ($callout) {
						?>
						<li>
							<input type="hidden" name="callouts[]" value="<?=$cid?>" />
							<a href="#" class="icon_small icon_small_delete"></a>
							<span><?=$callout["name"]?></span>
						</li>
						<?php
								}
							}
						?>
					</ul>
					<div class="adder">
						<a href="#"></a>
						<select class="custom_control" data-key="callouts">
							<?php
								$callouts = $admin->getCallouts("name ASC");
								foreach ($callouts as $callout) {
									if (!$callout["extension"] || $callout["extension"] == $id) {
							?>
							<option value="<?=$callout["id"]?>"><?=$callout["name"]?></option>
							<?php
									}
								}
							?>
						</select>
					</div>
				</article>
			</fieldset>
			<fieldset>
				<article class="package_column">
					<strong>Settings</strong>
					<ul>
						<?php
							foreach ((array)$settings as $sid) {
								$setting = $admin->getSetting($sid);
								if ($setting) {
						?>
						<li>
							<input type="hidden" name="settings[]" value="<?=$sid?>" />
							<a href="#" class="icon_small icon_small_delete"></a>
							<span><?=$setting["name"]?></span>
						</li>
						<?php
								}
							}
						?>
					</ul>
					<div class="add_setting adder">
						<a href="#"></a>
						<select class="custom_control" data-key="settings">
							<?php
								$settings = $admin->getSettings();
								
								foreach ($settings as $setting) {
									if (!$setting["extension"] || $setting["extension"] == $id) {
							?>
							<option value="<?=$setting["id"]?>"><?=$setting["name"]?></option>
							<?php
									}
								}
							?>
						</select>
					</div>
				</article>
				<article class="package_column">
					<strong>Feeds</strong>
					<ul>
						<?php
							foreach ((array)$feeds as $fid) {
								$feed = $cms->getFeed($fid);
								if ($feed) {
						?>
						<li>
							<input type="hidden" name="feeds[]" value="<?=$fid?>" />
							<a href="#" class="icon_small icon_small_delete"></a>
							<span><?=$feed["name"]?></span>
						</li>
						<?php
								}
							}
						?>
					</ul>
					<div class="add_feed adder">
						<a href="#"></a>
						<select class="custom_control" data-key="feeds">
							<?php
								$feeds = $admin->getFeeds();
								foreach ($feeds as $feed) {
									if (!$feed["extension"] || $feed["extension"] == $id) {
							?>
							<option value="<?=$feed["id"]?>"><?=$feed["name"]?></option>
							<?php
									}
								}
							?>
						</select>
					</div>
				</article>
				<article class="package_column package_column_last">
					<strong>Field Types</strong>
					<ul>
						<?php
							foreach ((array)$field_types as $fid) {
								$field_type = $admin->getFieldType($fid);
								if ($field_type) {
						?>
						<li>
							<input type="hidden" name="field_types[]" value="<?=$fid?>" />
							<a href="#" class="icon_small icon_small_delete"></a>
							<span><?=$field_type["name"]?></span>
						</li>
						<?php
								}
							}
						?>
					</ul>
					<div class="add_field_type adder">
						<a  href="#"></a>
						<select class="custom_control" data-key="field_types">
							<?php
								$field_types = $admin->getFieldTypes();
								foreach ($field_types as $type) {
									if (!$type["extension"] || $type["extension"] == $id) {
							?>
							<option value="<?=$type["id"]?>"><?=$type["name"]?></option>
							<?php
									}
								}
							?>
						</select>
					</div>
				</article>
			</fieldset>
		</section>
		<footer>
			<input type="submit" class="button blue" value="Continue" />
		</footer>
	</form>
</div>

<script>
	$(".adder a").click(function(ev) {
		var select = $(this).parent().find("select");
		var el = select.get(0);
		if (el.selectedIndex < 0) {
			return false;
		}
		var li = $("<li>");
		li.html('<input type="hidden" name="' + select.attr("data-key") + '[]" value="' + select.val() + '" /><a href="#" class="icon_small icon_small_delete"></a></a>' + el.options[el.selectedIndex].text);
		$(this).parent().parent().find("ul").append(li);
		return false;
	});

	$(".package_column").on("click",".icon_small_delete",function() {
		$(this).parent().remove();
		return false;
	});
</script>