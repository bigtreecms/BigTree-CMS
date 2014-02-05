<div class="container">
	<header><p>Add modules, templates, callouts, field types, feeds, and settings to your package.</p></header>
	<form method="post" action="<?=DEVELOPER_ROOT?>packages/build/save-components/" class="module">
		<section>
			<fieldset>
				<article class="package_column">
					<strong>Modules</strong>
					<ul>
						<?
							foreach ((array)$modules as $mid) {
								$module = $admin->getModule($mid);
								if ($module) {
						?>
						<li>
							<input type="hidden" name="modules[]" value="<?=$mid?>" />
							<a href="#" class="icon_small icon_small_delete"></a>
							<span><?=$module["name"]?></span>
						</li>
						<?
								}
							}
						?>
					</ul>
					<div class="adder">
						<a href="#"></a>
						<select class="custom_control" data-key="modules">
							<?
								$groups = $admin->getModuleGroups("name ASC");
								$groups[] = array("id" => "0", "name" => "Ungrouped");
								foreach ($groups as $g) {
									$modules = $admin->getModulesByGroup($g["id"],"name ASC");
									if (count($modules)) {
							?>
							<optgroup label="<?=$g["name"]?>">
								<?
										foreach ($modules as $m) {
								?>
								<option value="<?=$m["id"]?>"<? if ($m["id"] == $module) { ?> selected="selected"<? } ?>><?=$m["name"]?></option>
								<?
										}
								?>
							</optgroup>
							<?
									}
								}
							?>
						</select>
					</div>
				</article>
				<article class="package_column">
					<strong>Templates</strong>
					<ul>
						<?
							foreach ((array)$templates as $tid) {
								$template = $cms->getTemplate($tid);
								if ($template) {
						?>
						<li>
							<input type="hidden" name="templates[]" value="<?=$tid?>" />
							<a href="#" class="icon_small icon_small_delete"></a>
							<span><?=$template["name"]?></span>
						</li>
						<?
								}
							}
						?>
					</ul>
					<div class="adder">
						<a href="#"></a>
						<select class="custom_control" data-key="templates">
							<optgroup label="Basic Templates">
								<?
									$templates = $admin->getBasicTemplates("name ASC");
									foreach ($templates as $template) {
								?>
								<option value="<?=$template["id"]?>"><?=$template["name"]?></option>
								<?
									}
								?>
							</optgroup>
							<optgroup label="Routed Templates">
								<?
									$templates = $admin->getRoutedTemplates("name ASC");
									foreach ($templates as $template) {
								?>
								<option value="<?=$template["id"]?>"><?=$template["name"]?></option>
								<?
									}
								?>
							</optgroup>
						</select>
					</div>
				</article>
				<article class="package_column package_column_last">
					<strong>Callouts</strong>
					<ul>
						<?
							foreach ((array)$callouts as $cid) {
								$callout = $admin->getCallout($cid);
								if ($callout) {
						?>
						<li>
							<input type="hidden" name="callouts[]" value="<?=$cid?>" />
							<a href="#" class="icon_small icon_small_delete"></a>
							<span><?=$callout["name"]?></span>
						</li>
						<?
								}
							}
						?>
					</ul>
					<div class="adder">
						<a href="#"></a>
						<select class="custom_control" data-key="callouts">
							<?
								$callouts = $admin->getCallouts("name ASC");
								foreach ($callouts as $callout) {
							?>
							<option value="<?=$callout["id"]?>"><?=$callout["name"]?></option>
							<?
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
						<?
							foreach ((array)$settings as $sid) {
								$setting = $admin->getSetting($sid);
								if ($setting) {
						?>
						<li>
							<input type="hidden" name="settings[]" value="<?=$sid?>" />
							<a href="#" class="icon_small icon_small_delete"></a>
							<span><?=$setting["name"]?></span>
						</li>
						<?
								}
							}
						?>
					</ul>
					<div class="add_setting adder">
						<a href="#"></a>
						<select class="custom_control" data-key="settings">
							<optgroup label="Public">
								<?
									$settings = $admin->getSettings();
									foreach ($settings as $setting) {
								?>
								<option value="<?=$setting["id"]?>"><?=$setting["name"]?></option>
								<?
									}
								?>
							</optgroup>
							<optgroup label="System">
								<?
									$settings = $admin->getSystemSettings();
									foreach ($settings as $setting) {
								?>
								<option value="<?=$setting["id"]?>"><?=$setting["name"]?></option>
								<?
									}
								?>
							</optgroup>
						</select>
					</div>
				</article>
				<article class="package_column">
					<strong>Feeds</strong>
					<ul>
						<?
							foreach ((array)$feeds as $fid) {
								$feed = $cms->getFeed($fid);
								if ($feed) {
						?>
						<li>
							<input type="hidden" name="feeds[]" value="<?=$fid?>" />
							<a href="#" class="icon_small icon_small_delete"></a>
							<span><?=$feed["name"]?></span>
						</li>
						<?
								}
							}
						?>
					</ul>
					<div class="add_feed adder">
						<a href="#"></a>
						<select class="custom_control" data-key="feeds">
							<?
								$feeds = $admin->getFeeds();
								foreach ($feeds as $feed) {
							?>
							<option value="<?=$feed["id"]?>"><?=$feed["name"]?></option>
							<?
								}
							?>
						</select>
					</div>
				</article>
				<article class="package_column package_column_last">
					<strong>Field Types</strong>
					<ul>
						<?
							foreach ((array)$field_types as $fid) {
								$field_type = $admin->getFieldType($fid);
								if ($field_type) {
						?>
						<li>
							<input type="hidden" name="field_types[]" value="<?=$fid?>" />
							<a href="#" class="icon_small icon_small_delete"></a>
							<span><?=$field_type["name"]?></span>
						</li>
						<?
								}
							}
						?>
					</ul>
					<div class="add_field_type adder">
						<a  href="#"></a>
						<select class="custom_control" data-key="field_types">
							<?
								$field_types = $admin->getFieldTypes();
								foreach ($field_types as $type) {
							?>
							<option value="<?=$type["id"]?>"><?=$type["name"]?></option>
							<?
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
		li = $("<li>");
		li.html('<input type="hidden" name="' + select.attr("data-key") + '[]" value="' + select.val() + '" /><a href="#" class="icon_small icon_small_delete"></a></a>' + el.options[el.selectedIndex].text);
		$(this).parent().parent().find("ul").append(li);
		return false;
	});

	$(".package_column").on("click",".icon_small_delete",function() {
		$(this).parent().remove();
		return false;
	});
</script>