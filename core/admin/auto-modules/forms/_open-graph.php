<p class="error_message" style="display: none;">Errors found! Please fix the highlighted fields before submitting.</p>
<fieldset>
	<label for="open_graph_field_title">Open Graph Title <small>(defaults to the page title if left empty)</small></label>
	<input id="open_graph_field_title" type="text" name="_open_graph_[title]" value="<?=$og_data["title"]?>">
</fieldset>
<fieldset>
	<label for="open_graph_field_description">Open Graph Description <small>(defaults to the page's meta description if left empty)</small></label>
	<input id="open_graph_field_description" type="text" name="_open_graph_[description]" value="<?=$og_data["description"]?>">
</fieldset>
<fieldset>
	<label for="open_graph_field_type">Open Graph Type</label>
	<select id="open_graph_field_type" name="_open_graph_[type]">
		<option></option>
		<?php
			foreach (BigTreeAdmin::$OpenGraphTypes as $og_type) {
				?>
				<option<?php if ($og_type == $og_data["type"]) { ?> selected<?php } ?>><?=$og_type?></option>
				<?php
			}
		?>
	</select>
</fieldset>
<fieldset>
	<label for="open_graph_field_image">Open Graph Image <small>(min 1200x630)</small></label>
	<input id="open_graph_field_image" type="file" name="_open_graph_[image]"  data-min-width="1200" data-min-height="630" accept="image/*">
	
	<div class="currently" <?php if (!$og_data["image"]) { ?> style="display: none;"<?php } ?>>
		<a href="#" class="remove_resource"></a>
		<div class="currently_wrapper">
			<?php if ($og_data["image"]) { ?>
				<a href="<?=$og_data["image"]?>" target="_blank"><img src="<?=$og_data["image"]?>" alt="" /></a>
			<?php } ?>
		</div>
		<label>CURRENT</label>
		<input type="hidden" name="_open_graph_[image]" value="<?=$og_data["image"]?>" />
	</div>
</fieldset>