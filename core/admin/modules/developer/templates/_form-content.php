<?
	$cached_types = $admin->getCachedFieldTypes();
	$types = $cached_types["template"];
?>
<section>
	<p class="error_message"<? if (!count($e)) { ?> style="display: none;"<? } ?>>Errors found! Please fix the highlighted fields before submitting.</p>
	
	<div class="left">
		<? if (!$template) { ?>
		<fieldset>
			<label class="required">ID</label>
			<input type="text" class="required" name="id" />
		</fieldset>
		<? } ?>
		<fieldset>
			<label class="required">Name</label>
			<input type="text" class="required" name="name" value="<?=$name?>" />
		</fieldset>
		<fieldset>
			<label>Description</label>
			<textarea name="description"><?=$description?></textarea>
		</fieldset>
		<fieldset>
			<input type="checkbox" name="callouts_enabled" <? if ($callouts_enabled) { ?>checked="checked" <? } ?>/> <label class="for_checkbox">Callouts Enabled</label>
		</fieldset>
	</div>
	<div class="right">
		<? if (!$template) { ?>
		<fieldset>
			<label>Type</label>
			<select name="routed">
				<option value="">Basic</option>
				<option value="on">Routed</option>
			</select>
		</fieldset>
		<? } ?>
		
		<fieldset>
			<label>Access Level</label>
			<select name="level">
				<option value="0">Normal User</option>
				<option value="1"<? if ($level == 1) { ?> selected="selected"<? } ?>>Administrator</option>
				<option value="2"<? if ($level == 2) { ?> selected="selected"<? } ?>>Developer</option>
			</select>
		</fieldset>
		
		<fieldset>
			<label>Related Module</label>
			<select name="module">
				<option></option>
				<?
					$modules = $admin->getModules("name ASC");
					foreach ($modules as $m) {
				?>
				<option value="<?=$m["id"]?>"<? if ($m["id"] == $module) { ?> selected="selected"<? } ?>><?=$m["name"]?></option>
				<?
					}
				?>
			</select>	
		</fieldset>
		
		<fieldset>
			<label>Image <small>(upload an image of ~32x32 pixels or a choose a default)</small></label>
			<input type="file" name="image" />
			<input type="hidden" name="existing_image" id="existing_image" />
		</fieldset>
		
		<fieldset>
			<ul class="template_image_list">
				<?
					$o = opendir($server_root."core/admin/images/templates/");
					while ($file = readdir($o)) {
						if ($file != "." && $file != "..") {
							$all[] = $file;
				?>
				<li><a href="#<?=htmlspecialchars($file)?>"<? if ($image == $file) { ?> class="active" <? } ?>><img src="<?=$admin_root?>images/templates/<?=$file?>" alt="" /></a></li>
				<?
						}
					}
					if ($image && !in_array($image,$all)) {
				?>
				<li><a href="#<?=htmlspecialchars($image)?>" class="active"><img src="<?=$admin_root?>images/templates/<?=$image?>" alt="" /></a></li>
				<?	
					}
				?>
			</ul>
		</fieldset>				
	</div>
</section>
<section class="sub">
	<label>Resources</label>
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
					<input type="text" name="resources[<?=$x?>][title]" value="<?=$resource["title"]?>" />
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