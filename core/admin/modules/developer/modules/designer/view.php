<?
	$module = $admin->getModule($_GET["module"]);
	$table = $_GET["table"];
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
	$title = htmlspecialchars($title);
?>
<div class="container">
	<header>
		<p>Step 3: Creating Your View</p>
	</header>
	<form method="post" action="<?=DEVELOPER_ROOT?>modules/designer/view-create/" class="module">
		<input type="hidden" name="module" value="<?=$module["id"]?>" />
		<input type="hidden" name="table" value="<?=$table?>" />
		<section>
			<p class="error_message"<? if (!count($e)) { ?> style="display: none;"<? } ?>>Errors found! Please ensure you have entered an Item Title and one or more Fields.</p>
			
			<div class="left">
				<fieldset>
					<label class="required">Item Title <small>(for example, "Questions" to make the title "Viewing Questions")</small></label>
					<input type="text" class="required" name="title" value="<?=$title?>" tabindex="1" />
				</fieldset>
				
				<fieldset class="left">
					<label>View Type</label>
					<select name="type" id="view_type" class="left" tabindex="2">
						<option value="searchable">Searchable List</option>
						<option value="draggable">Draggable List</option>
					</select>
					&nbsp; <a href="#" class="options icon_settings centered"></a>
					<input type="hidden" name="options" id="view_options" value="<?=htmlspecialchars($view["options"])?>" />
				</fieldset>
			</div>
			
			<div class="right">
				<fieldset>
					<label>Page Description <small>(instructions for the user)</small></label>
					<textarea name="description" tabindex="3"><?=$view["description"]?></textarea>
				</fieldset>
			</div>
		</section>
		<section id="field_area" class="sub">
			<?
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
	new BigTreeFormValidator("form.module");
	
	$(".options").click(function() {
		$.ajax("<?=ADMIN_ROOT?>ajax/developer/load-view-options/", { type: "POST", data: { table: "<?=$table?>", type: $("#view_type").val(), data: $("#view_options").val() }, complete: function(response) {
			new BigTreeDialog("View Options",response.responseText,function(data) {
				$("#view_options").val(JSON.stringify(data));
			});
		}});
		
		return false;
	});
</script>