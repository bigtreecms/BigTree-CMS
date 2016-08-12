<?php
	namespace BigTree;
	
	/**
	 * @global array $callouts
	 * @global array $feeds
	 * @global array $field_types
	 * @global array $modules
	 * @global array $settings
	 * @global array $templates
	 * @global string $id
	 */
?>
<div class="container">
	<header><p><?=Text::translate("Add modules, templates, callouts, field types, feeds, and settings to your extension.")?></p></header>
	<form method="post" action="<?=DEVELOPER_ROOT?>extensions/build/save-components/" class="module">
		<section>
			<fieldset>
				<article class="package_column">
					<label for="extension_field_modules"><?=Text::translate("Modules")?></label>
					<ul>
						<?php
							foreach ((array) $modules as $module_id) {
								if (Module::exists($module_id)) {
									$module = new Module($module_id);
						?>
						<li>
							<input type="hidden" name="modules[]" value="<?=$module_id?>" />
							<a href="#" class="icon_small icon_small_delete"></a>
							<span><?=$module->Name?></span>
						</li>
						<?php
								}
							}
						?>
					</ul>
					<div class="adder">
						<a href="#"></a>
						<select id="extension_field_modules" class="custom_control" data-key="modules">
							<?php
								$groups = ModuleGroup::all("name ASC");
								$groups[] = array("id" => "0", "name" => "Ungrouped");
								
								foreach ($groups as $group_id) {
									$modules = Module::allByGroup($group_id["id"], "name ASC");
									
									if (count($modules)) {
							?>
							<optgroup label="<?=$group_id["name"]?>">
								<?php
										foreach ($modules as $module) {
											if (!$module->Extension || $module->Extension == $id) {
								?>
								<option value="<?=$module->ID?>"><?=$module->Name?></option>
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
					<label for="extension_field_templates"><?=Text::translate("Templates")?></label>
					<ul>
						<?php
							foreach ((array) $templates as $template_id) {
								if (Template::exists($template_id)) {
									$template = new Template($template_id);
						?>
						<li>
							<input type="hidden" name="templates[]" value="<?=$template_id?>" />
							<a href="#" class="icon_small icon_small_delete"></a>
							<span><?=$template->Name?></span>
						</li>
						<?php
								}
							}
						?>
					</ul>
					<div class="adder">
						<a href="#"></a>
						<select id="extension_field_templates" class="custom_control" data-key="templates">
							<optgroup label="Basic Templates">
								<?php
									$templates = Template::allByRouted("", "name ASC");
									
									foreach ($templates as $template) {
										if (!$template->Extension || $template->Extension == $id) {
								?>
								<option value="<?=$template->ID?>"><?=$template->Name?></option>
								<?php
										}
									}
								?>
							</optgroup>
							<optgroup label="Routed Templates">
								<?php
									$templates = Template::allByRouted("on", "name ASC");
									
									foreach ($templates as $template) {
										if (!$template->Extension || $template->Extension == $id) {
								?>
								<option value="<?=$template->ID?>"><?=$template->Name?></option>
								<?php
										}
									}
								?>
							</optgroup>
						</select>
					</div>
				</article>
				<article class="package_column package_column_last">
					<label for="extension_field_callouts"><?=Text::translate("Callouts")?></label>
					<ul>
						<?php
							foreach ((array) $callouts as $callout_id) {
								if (Callout::exists($callout_id)) {
									$callout = new Callout($callout_id);
						?>
						<li>
							<input type="hidden" name="callouts[]" value="<?=$callout_id?>" />
							<a href="#" class="icon_small icon_small_delete"></a>
							<span><?=$callout->Name?></span>
						</li>
						<?php
								}
							}
						?>
					</ul>
					<div class="adder">
						<a href="#"></a>
						<select id="extension_field_callouts" class="custom_control" data-key="callouts">
							<?php
								$callouts = Callout::all("name ASC");
								
								foreach ($callouts as $callout) {
									if (!$callout->Extension || $callout->Extension == $id) {
							?>
							<option value="<?=$callout->ID?>"><?=$callout->Name?></option>
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
					<label for="extension_field_settings"><?=Text::translate("Settings")?></label>
					<ul>
						<?php
							foreach ((array) $settings as $setting_id) {
								if (Setting::exists($setting_id)) {
									$setting = new Setting($setting_id);
						?>
						<li>
							<input type="hidden" name="settings[]" value="<?=$setting_id?>" />
							<a href="#" class="icon_small icon_small_delete"></a>
							<span><?=$setting->Name?></span>
						</li>
						<?php
								}
							}
						?>
					</ul>
					<div class="add_setting adder">
						<a href="#"></a>
						<select id="extension_field_settings" class="custom_control" data-key="settings">
							<optgroup label="Public">
								<?php
									$settings = Setting::allBySystem("", "name ASC");
									
									foreach ($settings as $setting) {
										if (!$setting->Extension || $setting->Extension == $id) {
								?>
								<option value="<?=$setting->ID?>"><?=$setting->Name?></option>
								<?php
										}
									}
								?>
							</optgroup>
							<optgroup label="System">
								<?php
									$settings = Setting::allBySystem("on", "name ASC");
									
									foreach ($settings as $setting) {
										if (!$setting->Extension || $setting->Extension == $id) {
								?>
								<option value="<?=$setting->ID?>"><?=$setting->Name?></option>
								<?php
										}
									}
								?>
							</optgroup>
						</select>
					</div>
				</article>
				<article class="package_column">
					<label for="extension_field_feeds"><?=Text::translate("Feeds")?></label>
					<ul>
						<?php
							foreach ((array) $feeds as $feed_id) {
								if (Feed::exists($feed_id)) {
									$feed = new Feed($feed_id);
						?>
						<li>
							<input type="hidden" name="feeds[]" value="<?=$feed_id?>" />
							<a href="#" class="icon_small icon_small_delete"></a>
							<span><?=$feed->Name?></span>
						</li>
						<?php
								}
							}
						?>
					</ul>
					<div class="add_feed adder">
						<a href="#"></a>
						<select id="extension_field_feeds" class="custom_control" data-key="feeds">
							<?php
								$feeds = Feed::all("name ASC");
								
								foreach ($feeds as $feed) {
									if (!$feed->Extension || $feed->Extension == $id) {
							?>
							<option value="<?=$feed->ID?>"><?=$feed->Name?></option>
							<?php
									}
								}
							?>
						</select>
					</div>
				</article>
				<article class="package_column package_column_last">
					<label for="extension_field_field_types"><?=Text::translate("Field Types")?></label>
					<ul>
						<?php
							foreach ((array) $field_types as $field_type_id) {
								if (FieldType::exists($field_type_id)) {
									$field_type = new FieldType($field_type_id);
						?>
						<li>
							<input type="hidden" name="field_types[]" value="<?=$field_type_id?>" />
							<a href="#" class="icon_small icon_small_delete"></a>
							<span><?=$field_type->Name?></span>
						</li>
						<?php
								}
							}
						?>
					</ul>
					<div class="add_field_type adder">
						<a href="#"></a>
						<select id="extension_field_field_types" class="custom_control" data-key="field_types">
							<?php
								$field_types = FieldType::all("name ASC");
								
								foreach ($field_types as $type) {
									if (!$type->Extension || $type->Extension == $id) {
							?>
							<option value="<?=$type->ID?>"><?=$type->Name?></option>
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
			<input type="submit" class="button blue" value="<?=Text::translate("Continue", true)?>" />
		</footer>
	</form>
</div>

<script>
	$(".adder a").click(function() {
		var select = $(this).parent().find("select");
		var el = select.get(0);
		var li = $("<li>");
		
		if (el.selectedIndex < 0) {
			return false;
		}
		
		li.html('<input type="hidden" name="' + select.attr("data-key") + '[]" value="' + select.val() + '" /><a href="#" class="icon_small icon_small_delete"></a></a>' + el.options[el.selectedIndex].text);
		$(this).parent().parent().find("ul").append(li);
		
		return false;
	});

	$(".package_column").on("click",".icon_small_delete",function() {
		$(this).parent().remove();
		
		return false;
	});
</script>