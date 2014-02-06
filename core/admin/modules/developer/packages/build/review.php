<?
	// Need to get the names for everything we're including
	$module_string = array();
	foreach ((array)$modules as $m) {
		$module = $admin->getModule($m);
		if ($module) {
			$module_string[] = $module["name"];
		}
	}
	$template_string = array();
	foreach ((array)$templates as $t) {
		$template = $cms->getTemplate($t);
		if ($template) {
			$template_string[] = $template["name"];
		}
	}
	$callout_string = array();
	foreach ((array)$callouts as $c) {
		$callout = $admin->getCallout($c);
		if ($callout) {
			$callout_string[] = $callout["name"];
		}
	}
	$setting_string = array();
	foreach ((array)$settings as $s) {
		$setting = $admin->getSetting($s);
		if ($setting) {
			$setting_string[] = $setting["name"];
		}
	}
	$feed_string = array();
	foreach ((array)$feeds as $f) {
		$feed = $cms->getFeed($f);
		if ($feed) {
			$feed_string[] = $feed["name"];
		}
	}
	$field_string = array();
	foreach ((array)$field_types as $f) {
		$field_type = $admin->getFieldType($f);
		if ($field_type) {
			$field_string[] = $field_type["name"];
		}
	}
	$table_string = array();
	foreach ((array)$tables as $t) {
		list($table,$data) = explode("#",$t);
		if ($table) {
			$table_string[] = $table;
		}
	}
	$file_string = array();
	foreach ((array)$files as $f) {
		$file = str_replace(SERVER_ROOT,"",$f);
		if ($file) {
			$file_string[] = $file;
		}
	}
?>
<div class="container package_review">
	<summary>
		<h2>Review Your Package</h2>
	</summary>
	<section>
		<fieldset>
			<h3>Package Information</h3>
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
			<?
				if (count($module_string)) {
			?>
			<label>
				<small>modules</small>
				<?=implode(", ",$module_string)?>
			</label>
			<?
				}
				if (count($template_string)) {
			?>
			<label>
				<small>templates</small>
				<?=implode(", ",$template_string)?>
			</label>
			<?	
				}
				if (count($callout_string)) {
			?>
			<label>
				<small>callouts</small>
				<?=implode(", ",$callout_string)?>
			</label>
			<?	
				}
				if (count($setting_string)) {
			?>
			<label>
				<small>settings</small>
				<?=implode(", ",$setting_string)?>
			</label>
			<?	
				}
				if (count($feed_string)) {
			?>
			<label>
				<small>feeds</small>
				<?=implode(", ",$feed_string)?>
			</label>
			<?	
				}
				if (count($field_string)) {
			?>
			<label>
				<small>field types</small>
				<?=implode(", ",$field_string)?>
			</label>
			<?	
				}
				if (count($table_string)) {
			?>
			<label>
				<small>tables</small>
				<?=implode(", ",$table_string)?>
			</label>
			<?	
				}
			?>
		</fieldset>
		<?
			if (count($file_string)) {
		?>
		<h3>Files</h3>
		<ul>
			<li><?=implode("</li><li>",$file_string)?></li>
		</ul>
		<?
			}
		?>
	</section>
	<footer>
		<a class="button blue" href="<?=DEVELOPER_ROOT?>packages/build/create/">Create</a>
		<a class="button" href="<?=DEVELOPER_ROOT?>packages/build/details/">Edit</a>
	</footer>
</div>