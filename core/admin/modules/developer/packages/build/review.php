<?php
	namespace BigTree;
	
	/**
	 * @global array $author
	 * @global array $callouts
	 * @global array $extension_settings
	 * @global array $feeds
	 * @global array $field_types
	 * @global array $files
	 * @global array $modules
	 * @global array $tables
	 * @global array $templates
	 * @global string $compatibility
	 * @global string $description
	 * @global string $id
	 * @global string $keywords
	 * @global string $title
	 * @global string $version
	 */
	
	// Need to get the names for everything we're including
	$module_string = $template_string = $callout_string = $setting_string = $feed_string = $field_string = $table_string = $file_string = array();
	
	foreach (array_filter((array) $modules) as $module_id) {
		$module = new Module($module_id);
		
		if ($module->Name) {
			$module_string[] = $module->Name;
		}
	}
	
	foreach (array_filter((array) $templates) as $template_id) {
		$template = new Template($template_id);
		
		if ($template->Name) {
			$template_string[] = $template->Name;
		}
	}
	
	foreach (array_filter((array) $callouts) as $callout_id) {
		$callout = new Callout($callout_id);
		
		if ($callout->Name) {
			$callout_string[] = $callout->Name;
		}
	}
	
	foreach (array_filter((array) $extension_settings) as $setting_id) {
		$setting = new Setting($setting_id);
		
		if ($setting->Name) {
			$setting_string[] = $setting->Name;
		}
	}
	
	foreach (array_filter((array) $feeds) as $feed_id) {
		$feed = new Feed($feed_id);
		
		if ($feed->Name) {
			$feed_string[] = $feed->Name;
		}
	}
	
	foreach (array_filter((array) $field_types) as $field_id) {
		$field_type = new FieldType($field_id);
		
		if ($field_type->Name) {
			$field_string[] = $field_type->Name;
		}
	}
	
	foreach (array_filter((array) $tables) as $table) {
		list($table) = explode("#", $table);
		
		if ($table) {
			$table_string[] = $table;
		}
	}
	
	foreach (array_filter((array) $files) as $file) {
		$file = Text::replaceServerRoot($file);
		
		if ($file) {
			$file_string[] = $file;
		}
	}
?>
<div class="container package_review">
	<summary>
		<h2><?=Text::translate("Review Your Package")?></h2>
	</summary>
	<section>
		<fieldset>
			<h3><?=Text::translate("Package Information")?></h3>
			<label>
				<small><?=Text::translate("id")?></small>
				<?=$id?>
			</label>
			<label>
				<small><?=Text::translate("bigtree version compatibility")?></small>
				<?=$compatibility?>
			</label>
			<label>
				<small><?=Text::translate("title")?></small>
				<?=$title?>
			</label>
			<label>
				<small><?=Text::translate("version")?></small>
				<?=$version?>
			</label>
			<label>
				<small><?=Text::translate("description")?></small>
				<?=$description?>
			</label>
			<label>
				<small><?=Text::translate("keywords")?></small>
				<?=$keywords?>
			</label>
		</fieldset>
		<fieldset>
			<h3><?=Text::translate("Author Information")?></h3>
			<label>
				<small><?=Text::translate("name")?></small>
				<?=$author["name"]?>
			</label>
			<label>
				<small><?=Text::translate("email")?></small>
				<?=$author["email"]?>
			</label>
			<label>
				<small><?=Text::translate("website")?></small>
				<?=$author["url"]?>
			</label>
		</fieldset>
		<fieldset>
			<h3><?=Text::translate("Components")?></h3>
			<?php
				if (count($module_string)) {
			?>
			<label>
				<small><?=Text::translate("modules")?></small>
				<?=implode(", ",$module_string)?>
			</label>
			<?php
				}
				if (count($template_string)) {
			?>
			<label>
				<small><?=Text::translate("templates")?></small>
				<?=implode(", ",$template_string)?>
			</label>
			<?php
				}
				if (count($callout_string)) {
			?>
			<label>
				<small><?=Text::translate("callouts")?></small>
				<?=implode(", ",$callout_string)?>
			</label>
			<?php
				}
				if (count($setting_string)) {
			?>
			<label>
				<small><?=Text::translate("settings")?></small>
				<?=implode(", ",$setting_string)?>
			</label>
			<?php
				}
				if (count($feed_string)) {
			?>
			<label>
				<small><?=Text::translate("feeds")?></small>
				<?=implode(", ",$feed_string)?>
			</label>
			<?php
				}
				if (count($field_string)) {
			?>
			<label>
				<small><?=Text::translate("field types")?></small>
				<?=implode(", ",$field_string)?>
			</label>
			<?php
				}
				if (count($table_string)) {
			?>
			<label>
				<small><?=Text::translate("tables")?></small>
				<?=implode(", ",$table_string)?>
			</label>
			<?php
				}
			?>
		</fieldset>
		<?php
			if (count($file_string)) {
		?>
		<h3><?=Text::translate("Files")?></h3>
		<ul>
			<li><?=implode("</li><li>",$file_string)?></li>
		</ul>
		<?php
			}
		?>
	</section>
	<footer>
		<a class="button blue" href="<?=DEVELOPER_ROOT?>packages/build/create/"><?=Text::translate("Create")?></a>
		<a class="button" href="<?=DEVELOPER_ROOT?>packages/build/details/"><?=Text::translate("Edit")?></a>
	</footer>
</div>