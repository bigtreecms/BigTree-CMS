<?php
	namespace BigTree;
	
	$module = $admin->getModule($_GET["module"]);
	$table = htmlspecialchars($_GET["table"]);

	if (!$title) {
		// Get the title from the route
		$title = $_GET["title"];
		// Add an s to the name (i.e. View Goods)
		$title = (substr($title,-1,1) != "s") ? $title."s" : $title;
		// If it ends in ys like Buddys then change it to Buddies
		if (substr($title,-2) == "ys") {
			$title = substr($title,0,-2)."ies";
		}
	}
	$title = Text::htmlEncode($title);
?>
<div class="container">
	<header>
		<p><?=Text::translate("Step 3: Creating Your View")?></p>
	</header>
	<form method="post" action="<?=DEVELOPER_ROOT?>modules/designer/view-create/" class="module">
		<input type="hidden" name="module" value="<?=$module["id"]?>" />
		<input type="hidden" name="table" value="<?=$table?>" />
		<section>
			<p class="error_message"<?php if (!count($e)) { ?> style="display: none;"<?php } ?>><?=Text::translate("Errors found! Please ensure you have entered an Item Title and one or more Fields.")?></p>
			
			<div class="left">
				<fieldset>
					<label class="required"><?=Text::translate('Item Title <small>(for example, "Questions" to make the title "Viewing Questions")</small>')?></label>
					<input type="text" class="required" name="title" value="<?=$title?>" tabindex="1" />
				</fieldset>
				
				<fieldset class="left">
					<label><?=Text::translate("View Type")?></label>
					<select name="type" id="view_type" class="left" tabindex="2">
						<option value="searchable"><?=Text::translate("Searchable List")?></option>
						<option value="draggable"><?=Text::translate("Draggable List")?></option>
					</select>
					&nbsp; <a href="#" class="options icon_settings centered"></a>
					<input type="hidden" name="options" id="view_options" />
				</fieldset>
			</div>
			
			<div class="right">
				<fieldset>
					<label><?=Text::translate("Page Description <small>(instructions for the user)</small>")?></label>
					<textarea name="description" tabindex="3"></textarea>
				</fieldset>
			</div>
		</section>
		<section id="field_area" class="sub">
			<?php
				$bigtree["module_designer_view"] = true;
				include Router::getIncludePath("admin/ajax/developer/load-view-fields.php");
			?>
		</section>
		<footer>
			<input type="submit" class="button blue" value="<?=Text::translate("Create", true)?>" />
		</footer>
	</form>
</div>

<script>	
	BigTreeFormValidator("form.module");
	
	$(".options").click(function(ev) {
		ev.preventDefault();

		// Prevent double clicks
		if (BigTree.Busy) {
			return;
		}

		BigTreeDialog({
			title: "<?=Text::translate("View Options", true)?>",
			url: "<?=ADMIN_ROOT?>ajax/developer/load-view-options/",
			post: { table: "<?=$table?>", type: $("#view_type").val(), data: $("#view_options").val() },
			icon: "edit",
			callback: function(data) {
				$("#view_options").val(JSON.stringify(data));
			}
		});
	});
</script>