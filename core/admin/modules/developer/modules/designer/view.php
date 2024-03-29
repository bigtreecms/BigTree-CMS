<?php
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
	$title = BigTree::safeEncode($title);
?>
<div class="container">
	<header>
		<p>Step 3: Creating Your View</p>
	</header>
	<form method="post" action="<?=DEVELOPER_ROOT?>modules/designer/view-create/" class="module">
		<?php $admin->drawCSRFToken() ?>
		<input type="hidden" name="module" value="<?=$module["id"]?>" />
		<input type="hidden" name="table" value="<?=$table?>" />
		<section>
			<p class="error_message"<?php if (!count($e)) { ?> style="display: none;"<?php } ?>>Errors found! Please ensure you have entered an Item Title and one or more Fields.</p>
			
			<div class="left">
				<fieldset>
					<label class="required">Item Title <small>(for example, "Questions" to make the title "Viewing Questions")</small></label>
					<input type="text" class="required" name="title" value="<?=$title?>" tabindex="1" />
				</fieldset>
				
				<fieldset class="left">
					<label>View Type</label>
					<select name="type" id="view_type" tabindex="2">
						<option value="searchable">Searchable List</option>
						<option value="draggable">Draggable List</option>
					</select>
					&nbsp; <a href="#" class="js-view-settings icon_settings centered"></a>
					<input type="hidden" name="settings" id="view_settings" />
				</fieldset>
			</div>
			
			<div class="right">
				<fieldset>
					<label>Page Description <small>(instructions for the user)</small></label>
					<textarea name="description" tabindex="3"></textarea>
				</fieldset>
			</div>
		</section>
		<section id="field_area" class="sub">
			<?php
				$bigtree["module_designer_view"] = true;
				include BigTree::path("admin/ajax/developer/load-view-fields.php");
			?>
		</section>
		<footer>
			<input type="submit" class="button blue" value="Create" />
		</footer>
	</form>
</div>

<script>	
	BigTreeFormValidator("form.module");
	
	$(".js-view-settings").click(function(ev) {
		ev.preventDefault();

		// Prevent double clicks
		if (BigTree.Busy) {
			return;
		}

		$.ajax("<?=ADMIN_ROOT?>ajax/developer/load-view-settings/", { type: "POST", data: { table: "<?=$table?>", type: $("#view_type").val(), data: $("#view_settings").val() }, complete: function(response) {
			BigTreeDialog({
				title: "View Settings",
				content: response.responseText,
				icon: "edit",
				callback: function(data) {
					$("#view_settings").val(JSON.stringify(data));
				}
			});
		}});
	});
</script>