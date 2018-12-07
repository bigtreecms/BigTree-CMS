<?php
	// Find out if we have more than one view. If so, give them an option of which one to return to.
	$available_views = $admin->getModuleViews("title", $module["id"]);

	$admin->drawCSRFToken();
?>
<section>
	<div class="contain">
		<div class="left">
			<fieldset>
				<label class="required">Item Title <small>(for example, "Question" as in "Adding Question")</small></label>
				<input type="text" name="title" value="<?=$form["title"]?>" class="required" />
			</fieldset>
	
			<fieldset>
				<label class="required">Data Table</label>
				<select name="table" id="form_table" class="required">
					<option></option>
					<?php BigTree::getTableSelectOptions($form["table"]); ?>
				</select>
			</fieldset>
		</div>
		<div class="right">
			<?php if (count($available_views) > 1) { ?>
			<fieldset>
				<label>Return View <small>(after the form is submitted, it will return to this view)</small></label>
				<select name="return_view">
					<?php foreach ($available_views as $view) { ?>
					<option value="<?=$view["id"]?>"<?php if ($form["return_view"] == $view["id"]) { ?> selected="selected"<?php } ?>><?=$view["title"]?></option>
					<?php } ?>
				</select>
			</fieldset>
			<?php } ?>
	
			<fieldset>
				<label>Return URL <small>(an optional return URL to override the default return view)</small></label>
				<input type="text" name="return_url" value="<?=htmlspecialchars($form["return_url"])?>" />
			</fieldset>
		</div>
	</div>
	<div class="triplets">
		<fieldset>
			<input type="checkbox" name="open_graph" <?php if ($form["open_graph"]) { ?>checked="checked" <?php } ?>/>
			<label class="for_checkbox">Enable Open Graph</label>
		</fieldset>
		<fieldset>
			<input type="checkbox" name="tagging" <?php if ($form["tagging"]) { ?>checked="checked" <?php } ?>/>
			<label class="for_checkbox">Enable Tagging</label>
		</fieldset>
		<fieldset>
			<a href="#" id="manage_hooks"><span class="icon_small icon_small_lightning"></span> Manage Hooks</a>
			<input name="hooks" type="hidden" id="form_hooks" value="<?=htmlspecialchars(json_encode($form["hooks"]))?>" />
		</fieldset>
	</fieldset>
</section>
<section class="sub" id="field_area">
	<?php
		if ($table) {
			include BigTree::path("admin/ajax/developer/load-form.php");
		} else {
			echo "<p>Please choose a table to populate this area.</p>";
		}
	?>
</section>