<?
	$mod = $commands[0];
	$table = $commands[1];
	if (!$title) {
		$title = (substr($commands[2],-1,1) != "s") ? $commands[2]."s" : $commands[2];
	}
	
	$mdata = $admin->getModule($mod);
?>
<h1><span class="icon_developer_modules"></span>Module Designer</h1>
<? include BigTree::path("admin/modules/developer/modules/_nav.php"); ?>
<div class="form_container">
	<header>
		<p>Step 3: Creating Your View</p>
	</header>
	<form method="post" action="<?=$developer_root?>modules/designer/view-create/" class="module">
		<input type="hidden" name="module" value="<?=$mod?>" />
		<input type="hidden" name="table" value="<?=$table?>" />
		<section>
			<p class="error_message"<? if (!count($e)) { ?> style="display: none;"<? } ?>>Errors found! Please fix the highlighted fields before submitting.</p>
			
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
					&nbsp; <a href="#" class="options button_edit" style="margin-top: -2px;"></a>
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
				$allow_all_actions = true;
				include BigTree::path("admin/ajax/developer/load-view-fields.php");
			?>
		</section>
		<footer>
			<input type="submit" class="button blue" value="Create" />
		</footer>
	</form>
</div>

<script type="text/javascript">	
	new BigTreeFormValidator("form.module");
	
	$(".options").click(function() {
		$.ajax("<?=$admin_root?>ajax/developer/load-view-options/", { type: "POST", data: { table: "<?=$table?>", type: $("#view_type").val(), data: $("#view_options").val() }, complete: function(response) {
			new BigTreeDialog("View Options",response.responseText,function(data) {
				$.ajax("<?=$admin_root?>ajax/developer/save-view-options/", { type: "POST", data: data });
			});
		}});
		
		return false;
	});
</script>