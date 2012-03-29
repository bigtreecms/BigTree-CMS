<?
	$cached_types = $admin->getCachedFieldTypes();
	$types = $cached_types["callout"];
?>
<section>
	<div class="left">
		<? if (!$callout) { ?>
		<fieldset>
			<label class="required">ID</label>
			<input type="text" class="required" name="id" value="<?=$id?>" />
		</fieldset>
		<? } ?>
		<fieldset>
			<label class="required">Name</label>
			<input type="text" class="required" name="name" value="<?=$name?>" />
		</fieldset>
		<fieldset>
			<label>Access Level</label>
			<select name="level">
				<option value="0">Normal User</option>
				<option value="1"<? if ($level == 1) { ?> selected="selected"<? } ?>>Administrator</option>
				<option value="2"<? if ($level == 2) { ?> selected="selected"<? } ?>>Developer</option>
			</select>
		</fieldset>
	</div>
	<div class="right">
		<fieldset>
			<label>Description</label>
			<textarea name="description"><?=$description?></textarea>
		</fieldset>	
	</div>
</section>
<section class="sub">
	<label>Resources <small>(please note that "type" is a reserved ID &mdash; any resource with that ID will be removed)</small></label>
	<div class="form_table">
		<header>
			<a href="#" class="add_resource add">Add Resource</a>
		</header>
		<div class="labels">
			<span class="developer_resource_id">ID</span>
			<span class="developer_resource_title">Title</span>
			<span class="developer_resource_subtitle">Subtitle</span>
			<span class="developer_resource_type">Type</span>
			<span class="developer_resource_action">Edit</span>
			<span class="developer_resource_action">Delete</span>
		</div>
		<ul id="resource_table">
			<?
				$x = 0;
				foreach ($resources as $resource) {
					$x++;
			?>
			<li>
				<section class="developer_resource_id">
					<span class="icon_sort"></span>
					<input type="text" name="resources[<?=$x?>][id]" value="<?=$resource["id"]?>" />
				</section>
				<section class="developer_resource_title">
					<input type="text" name="resources[<?=$x?>][name]" value="<?=$resource["name"]?>" />
				</section>
				<section class="developer_resource_subtitle">
					<input type="text" name="resources[<?=$x?>][subtitle]" value="<?=$resource["subtitle"]?>" />
				</section>
				<section class="developer_resource_type">
					<select name="resources[<?=$x?>][type]" id="type_<?=$x?>">
						<? foreach ($types as $k => $v) { ?>
						<option value="<?=$k?>"<? if ($k == $resource["type"]) { ?> selected="selected"<? } ?>><?=htmlspecialchars($v)?></option>
						<? } ?>
					</select>
				</section>
				<section class="developer_resource_action">
					<a href="#" class="icon_edit" name="<?=$x?>"></a>
					<input type="hidden" name="resources[<?=$x?>][options]" value="<?=htmlspecialchars(json_encode($resource))?>" id="options_<?=$x?>" />
				</section>
				<section class="developer_resource_action">
					<a href="#" class="icon_delete"></a>
				</section>
			</li>
			<?
				}
			?>
		</ul>
	</div>
</section>