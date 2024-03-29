<?php
	// Need to get the names for everything we're including
	$module_string = [];
	foreach ((array) $modules as $m) {
		$module = $admin->getModule($m);
		if ($module) {
			$module_string[] = $module["name"];
		}
	}
	$template_string = [];
	foreach ((array) $templates as $t) {
		$template = $cms->getTemplate($t);
		if ($template) {
			$template_string[] = $template["name"];
		}
	}
	$callout_string = [];
	foreach ((array) $callouts as $c) {
		$callout = $admin->getCallout($c);
		if ($callout) {
			$callout_string[] = $callout["name"];
		}
	}
	$setting_string = [];
	foreach ((array) $settings as $s) {
		$setting = $admin->getSetting($s);
		if ($setting) {
			$setting_string[] = $setting["name"];
		}
	}
	$feed_string = [];
	foreach ((array) $feeds as $f) {
		$feed = $cms->getFeed($f);
		if ($feed) {
			$feed_string[] = $feed["name"];
		}
	}
	$field_string = [];
	foreach ((array) $field_types as $f) {
		$field_type = $admin->getFieldType($f);
		if ($field_type) {
			$field_string[] = $field_type["name"];
		}
	}
	$table_string = [];
	foreach ((array) $tables as $t) {
		$table_pieces = explode("#", $t);
		$table = $table_pieces[0] ?? "";
		$data = $table_pieces[1] ?? "";
		
		if ($table) {
			$table_string[] = $table;
		}
	}
	$file_string = [];
	foreach ((array) $files as $f) {
		$file = str_replace(SERVER_ROOT, "", $f);
		if ($file) {
			$file_string[] = $file;
		}
	}
?>
<div class="container package_review">
	<summary>
		<h2>Review Your Extension</h2>
	</summary>
	<section>
		<fieldset>
			<h3>Extension Information</h3>
			<label>
				<small>id</small>
				<?=$id?>
			</label>
			<label>
				<small>bigtree version compatibility</small>
				<?=$compatibility?>
			</label>
			<label>
				<small>title</small>
				<?=$title?>
			</label>
			<label>
				<small>version</small>
				<?=$version?>
			</label>
			<label>
				<small>description</small>
				<?=$description?>
			</label>
			<label>
				<small>keywords</small>
				<?=$keywords?>
			</label>
		</fieldset>
		<fieldset>
			<h3>Author Information</h3>
			<label>
				<small>name</small>
				<?=$author["name"]?>
			</label>
			<label>
				<small>email</small>
				<?=$author["email"]?>
			</label>
			<label>
				<small>website</small>
				<?=$author["url"]?>
			</label>
		</fieldset>
		<fieldset>
			<h3>Components</h3>
			<?php
				if (count($module_string)) {
			?>
			<label>
				<small>modules</small>
				<?=implode(", ", $module_string)?>
			</label>
			<?php
				}
				if (count($template_string)) {
			?>
			<label>
				<small>templates</small>
				<?=implode(", ", $template_string)?>
			</label>
			<?php
				}
				if (count($callout_string)) {
			?>
			<label>
				<small>callouts</small>
				<?=implode(", ", $callout_string)?>
			</label>
			<?php
				}
				if (count($setting_string)) {
			?>
			<label>
				<small>settings</small>
				<?=implode(", ", $setting_string)?>
			</label>
			<?php
				}
				if (count($feed_string)) {
			?>
			<label>
				<small>feeds</small>
				<?=implode(", ", $feed_string)?>
			</label>
			<?php
				}
				if (count($field_string)) {
			?>
			<label>
				<small>field types</small>
				<?=implode(", ", $field_string)?>
			</label>
			<?php
				}
				if (count($table_string)) {
			?>
			<label>
				<small>tables</small>
				<?=implode(", ", $table_string)?>
			</label>
			<?php
				}
			?>
		</fieldset>
		<?php
			if (count($file_string)) {
		?>
		<h3>Files</h3>
		<ul>
			<li><?=implode("</li><li>", $file_string)?></li>
		</ul>
		<?php
			}
		?>
	</section>
	<footer>
		<a class="button blue" href="<?=DEVELOPER_ROOT?>extensions/build/create/">Create</a>
		<a class="button" href="<?=DEVELOPER_ROOT?>extensions/build/details/">Edit</a>
	</footer>
</div>