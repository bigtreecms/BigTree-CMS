<?
	$cached_types = $admin->getCachedFieldTypes();
	$types = $cached_types["templates"];
?>
<section>
	<p class="error_message"<? if (!$show_error) { ?> style="display: none;"<? } ?>>Errors found! Please fix the highlighted fields before submitting.</p>
	
	<div class="contain">
		<? if (!isset($template)) { ?>
		<div class="left">
			<fieldset<? if ($show_error) { ?> class="form_error"<? } ?>>
				<label class="required">ID <small>(used for file/directory name, alphanumeric, "-" and "_" only)</small><? if ($show_error) { ?> <span class="form_error_reason"><?=$show_error?></span><? } ?></label>
				<input type="text" class="required" name="id" value="<?=$id?>" />
			</fieldset>
		</div>
		<? } ?>
		<div class="<? if (isset($template)) { ?>left<? } else { ?>right<? } ?>">
			<fieldset>
				<label class="required">Name</label>
				<input type="text" class="required" name="name" value="<?=$name?>" />
			</fieldset>
		</div>
	</div>
	<? if (!isset($template)) { ?>
	<fieldset class="float_margin">
		<label>Type</label>
		<select name="routed">
			<option value="">Basic</option>
			<option value="on">Routed</option>
		</select>
	</fieldset>
	<? } ?>
	<fieldset class="float_margin">
		<label>Related Module</label>
		<select name="module">
			<option></option>
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
	</fieldset>
	<fieldset class="float_margin">
		<label>Access Level</label>
		<select name="level">
			<option value="0">Normal User</option>
			<option value="1"<? if ($level == 1) { ?> selected="selected"<? } ?>>Administrator</option>
			<option value="2"<? if ($level == 2) { ?> selected="selected"<? } ?>>Developer</option>
		</select>
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
			<?
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
						<? foreach ($types as $k => $v) { ?>
						<option value="<?=$k?>"<? if ($k == $resource["type"]) { ?> selected="selected"<? } ?>><?=$v["name"]?></option>
						<? } ?>
					</select>
					<a href="#" class="icon_settings" name="<?=$x?>"></a>
					<input type="hidden" name="resources[<?=$x?>][options]" value="<?=htmlspecialchars(json_encode($resource))?>" id="options_<?=$x?>" />
				</section>
				<section class="developer_resource_action right">
					<a href="#" class="icon_delete"></a>
				</section>
			</li>
			<?
				}
			?>
		</ul>
	</div>
</section>