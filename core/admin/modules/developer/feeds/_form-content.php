<?php $admin->drawCSRFToken() ?>
<section>
	<div class="left last">
		<fieldset>
			<label class="required">Name</label>
			<input type="text" class="required" name="name" value="<?=$name?>" />
		</fieldset>
		<fieldset>
			<label class="required">Data Table</label>
			<select name="table" id="feed_table" class="required left">
				<option></option>
				<?php BigTree::getTableSelectOptions($table); ?>
			</select>
		</fieldset>
		<fieldset>
			<label>Type</label>
			<select name="type" id="feed_type" class="left">
				<?php foreach ($feed_types as $t => $v) { ?>
				<option value="<?=$t?>"<?php if ($t == $type) { ?> selected="selected"<?php } ?>><?=$v?></option>
				<?php } ?>
			</select> &nbsp; <a href="#" class="icon_settings centered"></a>
			<input type="hidden" name="settings" id="feed_settings" value="<?=htmlspecialchars(json_encode($item["settings"]))?>" />
		</fieldset>
	</div>
	<div class="right last">
		<fieldset>
			<label>Description</label>
			<textarea name="description"><?=$description?></textarea>
		</fieldset>
	</div>
</section>
<section class="sub" id="field_area"<?php if ($type == "rss" || $type == "rss2") { ?> style="display: none;"<?php } ?>>
	<?php
		if ($item) {
			include BigTree::path("admin/ajax/developer/load-feed-fields.php");
		} else {
			echo "<p>Please choose a table to populate this area.</p>";
		}
	?>
</section>