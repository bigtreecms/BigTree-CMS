<?php
	$admin->drawCSRFToken();
	
	$cached_types = $admin->getCachedFieldTypes(true);
	$types = $cached_types["templates"];
	$hooks = is_array($hooks) ? $hooks : [];

	if (isset($_GET["return"])) {
?>
<input type="hidden" name="return_to_front" value="<?=htmlspecialchars($_GET["return"])?>" />
<?php
	}
?>
<section class="developer_template_form">
	<p class="error_message"<?php if (!$show_error) { ?> style="display: none;"<?php } ?>>Errors found! Please fix the highlighted fields before submitting.</p>
	
	<div class="contain">
		<?php if (!isset($template)) { ?>
		<div class="left">
			<fieldset<?php if ($show_error) { ?> class="form_error"<?php } ?>>
				<label class="required">ID <small>(used for file/directory name, alphanumeric, "-" and "_" only)</small><?php if ($show_error) { ?> <span class="form_error_reason"><?=$show_error?></span><?php } ?></label>
				<input type="text" class="required" name="id" value="<?=$id?>" />
			</fieldset>
		</div>
		<?php } ?>
		<div class="<?php if (isset($template)) { ?>left<?php } else { ?>right<?php } ?>">
			<fieldset>
				<label class="required">Name</label>
				<input type="text" class="required" name="name" value="<?=$name?>" />
			</fieldset>
		</div>
	</div>
	<?php
		if (!isset($template)) {
	?>
	<fieldset class="float_margin">
		<label>Type</label>
		<select name="routed">
			<option value="">Basic</option>
			<option value="on">Routed</option>
		</select>
	</fieldset>
	<?php
		}
		if (!isset($template) || $routed) {
	?>
	<fieldset class="float_margin">
		<label>Related Module</label>
		<select name="module">
			<option></option>
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
				?>
				<option value="<?=$m["id"]?>"<?php if ($m["id"] == $module) { ?> selected="selected"<?php } ?>><?=$m["name"]?></option>
				<?php
						}
				?>
			</optgroup>
			<?php
					}
				}
			?>
		</select>	
	</fieldset>
	<?php
		}
	?>
	<fieldset class="float_margin">
		<label>Access Level</label>
		<select name="level">
			<option value="0">Normal User</option>
			<option value="1"<?php if ($level == 1) { ?> selected="selected"<?php } ?>>Administrator</option>
			<option value="2"<?php if ($level == 2) { ?> selected="selected"<?php } ?>>Developer</option>
		</select>
	</fieldset>
	<fieldset class="float_margin">
		<a href="#" id="manage_hooks"><span class="icon_small icon_small_lightning"></span> Manage Hooks</a>
		<input name="hooks" type="hidden" id="form_hooks" value="<?=htmlspecialchars(json_encode($hooks))?>" />
	</fieldset>
</section>
<section class="sub">
	<label>Resources</label>
	<div class="form_table">
		<header>
			<a href="#" class="add_resource add"><span></span>Add Resource</a>
		</header>
		<div class="labels">
			<span class="developer_resource_id">ID</span>
			<span class="developer_resource_title">Title</span>
			<span class="developer_resource_subtitle">Subtitle</span>
			<span class="developer_resource_type">Type</span>
			<span class="developer_resource_action right">Delete</span>
		</div>
		<ul id="resource_table">
			<?php
				$x = 0;
				$resources = is_array($resources) ? $resources : array();
				foreach ($resources as $resource) {
					$x++;
			?>
			<li>
				<section class="developer_resource_id">
					<span class="icon_sort"></span>
					<input type="text" name="resources[<?=$x?>][id]" value="<?=$resource["id"]?>" />
				</section>
				<section class="developer_resource_title">
					<input type="text" name="resources[<?=$x?>][title]" value="<?=$resource["title"]?>" />
				</section>
				<section class="developer_resource_subtitle">
					<input type="text" name="resources[<?=$x?>][subtitle]" value="<?=$resource["subtitle"]?>" />
				</section>
				<section class="developer_resource_type">
					<select name="resources[<?=$x?>][type]" id="type_<?=$x?>">
						<optgroup label="Default">
							<?php foreach ($types["default"] as $k => $v) { ?>
							<option value="<?=$k?>"<?php if ($k == $resource["type"]) { ?> selected="selected"<?php } ?>><?=$v["name"]?></option>
							<?php } ?>
						</optgroup>
						<?php if (count($types["custom"])) { ?>
						<optgroup label="Custom">
							<?php foreach ($types["custom"] as $k => $v) { ?>
							<option value="<?=$k?>"<?php if ($k == $resource["type"]) { ?> selected="selected"<?php } ?>><?=$v["name"]?></option>
							<?php } ?>
						</optgroup>
						<?php } ?>
					</select>
					<a href="#" class="icon_settings" name="<?=$x?>"></a>
					<input type="hidden" name="resources[<?=$x?>][settings]" value="<?=htmlspecialchars(json_encode($resource["settings"] ?: $resource["options"]))?>" id="settings_<?=$x?>" />
				</section>
				<section class="developer_resource_action right">
					<a href="#" class="icon_delete"></a>
				</section>
			</li>
			<?php
				}
			?>
		</ul>
	</div>
</section>

<script>
	$("#manage_hooks").click(function() {
		var data = $.parseJSON($("#form_hooks").val());
		var html = '<fieldset><label>Editing Hook</label><input type="text" name="edit" value="' + htmlspecialchars(data.edit ? data.edit : "") + '" /></fieldset>';
		html += '<fieldset><label>Pre-processing Hook</label><input type="text" name="pre" value="' + htmlspecialchars(data.pre ? data.pre : "") + '" /></fieldset>';
		html += '<fieldset><label>Post-processing Hook</label><input type="text" name="post" value="' + htmlspecialchars(data.post ? data.post : "") + '" /></fieldset>';
		html += '<fieldset><label>Publishing Hook</label><input type="text" name="publish" value="' + htmlspecialchars(data.publish ? data.publish : "") + '" /></fieldset>';
		
		BigTreeDialog({
			title: "Manage Hooks",
			content: html,
			helpLink: "http://www.bigtreecms.org/docs/dev-guide/modules/advanced-techniques/form-hooks/",
			icon: "edit",
			callback: function(data) {
				$("#form_hooks").val(JSON.stringify(data));
			}
		});
		
		return false;
	});
</script>