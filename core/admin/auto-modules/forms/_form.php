<div class="container">
	<form method="post" action="<?=$bigtree["form_root"]?>process/" enctype="multipart/form-data" class="module" id="auto_module_form">
		<input type="hidden" id="preview_field" name="_bigtree_preview" />
		<input type="hidden" name="MAX_FILE_SIZE" value="<?=BigTree::uploadMaxFileSize()?>" />
		<?
			if (isset($item)) {
		?>
		<input type="hidden" name="id" value="<?=htmlspecialchars($edit_id)?>" />
		<?
			}	
			if (isset($_GET["view_data"])) {
		?>
		<input type="hidden" name="_bigtree_return_view_data" value="<?=htmlspecialchars($_GET["view_data"])?>" />
		<?	
			}
		?>
		<section>
			<p class="error_message" style="display: none;">Errors found! Please fix the highlighted fields before submitting.</p>
			<?
				$bigtree["datepickers"] = array();
				$bigtree["timepickers"] = array();
				$bigtree["datetimepickers"] = array();
				$bigtree["html_fields"] = array();
				$bigtree["simple_html_fields"] = array();
				
				$tabindex = 1;
				foreach ($form["fields"] as $key => $options) {
					if (is_array($options)) {
						$type = $options["type"];
						$title = $options["title"];
						$subtitle = $options["subtitle"];
						$value = isset($item[$key]) ? $item[$key] : "";
						$currently_key = "currently_$key";
						
						// Setup Validation Classes
						$label_validation_class = "";
						$input_validation_class = "";
						if (isset($options["validation"]) && $options["validation"]) {
							if (strpos($options["validation"],"required") !== false) {
								$label_validation_class = ' class="required"';
							}
							$input_validation_class = ' class="'.$options["validation"].'"';
						}
						
						$path = BigTree::path("admin/form-field-types/draw/$type.php");
						if (file_exists($path)) {
							include $path;
						} else {
							include BigTree::path("admin/form-field-types/draw/text.php");
						}
						
						$tabindex++;
					}
				}

				if ($form["tagging"]) {
			?>
			<div class="tags" id="bigtree_tag_browser">
				<fieldset>
					<label>Tags<span></span></label>
					<ul id="tag_list">
						<? foreach ($tags as $tag) { ?>
						<li><input type="hidden" name="_tags[]" value="<?=$tag["id"]?>" /><a href="#"><?=$tag["tag"]?><span>x</span></a></li>
						<? } ?>
					</ul>
					<input type="text" name="tag_entry" id="tag_entry" />
					<ul id="tag_results" style="display: none;"></ul>
				</fieldset>
			</div>
			<script>
				BigTreeTagAdder.init(<?=$module["id"]?>,<? if (isset($item)) { echo '"'.$edit_id.'"'; } else { echo "false"; } ?>,"bigtree_tag_browser");
			</script>
			<?
				}
			?>
		</section>
		<footer>
			<? if (isset($view) && $view["preview_url"]) { ?>
			<a class="button save_and_preview" href="#">
				<span class="icon_small icon_small_computer"></span>
				Save &amp; Preview
			</a>
			<? } ?>
			<input type="submit" class="button<? if ($permission_level != "p") { ?> blue<? } ?>" tabindex="<?=$tabindex?>" value="Save" name="save" />
			<input type="submit" class="button blue" tabindex="<?=($tabindex + 1)?>" value="Save & Publish" name="save_and_publish" <? if ($permission_level != "p") { ?>style="display: none;" <? } ?>/>
		</footer>
	</form>
</div>
<?
	if (count($bigtree["html_fields"]) || count($bigtree["simple_html_fields"])) {
		$bigtree["js"][] = "tiny_mce/tiny_mce.js";

		if (count($bigtree["html_fields"])) {
			include BigTree::path("admin/layouts/_tinymce_specific.php");
		}
		if (count($bigtree["simple_html_fields"])) {
			include BigTree::path("admin/layouts/_tinymce_specific_simple.php");
		}
	}
?>
<script>
	<?
		foreach ($bigtree["datepickers"] as $id) {
	?>
	$("#<?=$id?>").datepicker({ duration: 200, showAnim: "slideDown" });
	<?
		}

		foreach ($bigtree["timepickers"] as $id) {
	?>
	$("#<?=$id?>").timepicker({ duration: 200, showAnim: "slideDown", ampm: true, hourGrid: 6, minuteGrid: 10 });
	<?
		}
		
		foreach ($bigtree["datetimepickers"] as $id) {
	?>
	$("#<?=$id?>").datetimepicker({ duration: 200, showAnim: "slideDown", ampm: true, hourGrid: 6, minuteGrid: 10 });
	<?
		}
	?>
	
	new BigTreeFormValidator("#auto_module_form");
	
	$(".save_and_preview").click(function() {
		$("#preview_field").val("true");
		$(this).parents("form").submit();

		return false;
	});

	<? if ($permission_level == "p" || !$edit_id) { ?>
	$(".gbp_select").change(function() {
		access_level = $(this).find("option").eq($(this).get(0).selectedIndex).attr("data-access-level");
		if (access_level == "p") {
			$("input[name=save]").removeClass("blue");
			$("input[name=save_and_publish]").show();
		} else {
			$("input[name=save]").addClass("blue");
			$("input[name=save_and_publish]").hide();
		}
	});
	$(window).load(function() {
		$(".gbp_select").trigger("change");
	});
	<? } ?>
</script>