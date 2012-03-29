<div class="form_container">
	<form method="post" action="process/" enctype="multipart/form-data" class="module" id="auto_module_form">
		<input type="hidden" name="MAX_FILE_SIZE" value="<?=BigTree::uploadMaxFileSize()?>" />
		<? if ($item) { ?>
		<input type="hidden" name="id" value="<?=htmlspecialchars($item_id)?>" />
		<? } ?>
		<section>
			<p class="error_message" style="display: none;">Errors found! Please fix the highlighted fields before submitting.</p>
			<?
				$htmls = array();
				$simplehtmls = array();
				$dates = array();
				$validators = array();
				$tabindex = 1;

				$fields = $form["fields"];
			
				foreach ($fields as $key => $options) {
					if (is_array($options)) {
						$type = $options["type"];
						$title = $options["title"];
						$subtitle = $options["subtitle"];
						$value = $item[$key];
						$currently_key = "currently_$key";
						
						// Setup Validation Classes
						$label_validation_class = "";
						$input_validation_class = "";
						if ($options["validation"]) {
							if (strpos($options["validation"],"required") !== false) {
								$label_validation_class = ' class="required"';
							}
							$input_validation_class = ' class="'.$options["validation"].'"';
						}
						
						if ($options["wrapper"]) {
							echo $options["wrapper"];
						}
						
						$path = BigTree::path("admin/form-field-types/draw/$type.php");
						if (file_exists($path)) {
							include $path;
						} else {
							include BigTree::path("admin/form-field-types/draw/text.php");
						}
						
						if ($options["wrapper"]) {
							$parts = explode(" ",$options["wrapper"]);
							echo "</".substr($parts[0],1).">";
						}
						
						$tabindex++;
					}
				}
			?>
			<div class="tags" id="bigtree_tag_browser">
				<fieldset>
					<label>Tags <img src="<?=$admin_root?>images/tag.png" alt="" /></label>
					<ul id="tag_list">
						<? foreach ($tags as $tag) { ?>
						<li><input type="hidden" name="_tags[]" value="<?=$tag["id"]?>" /><a href="#"><?=$tag["tag"]?><span>x</span></a></li>
						<? } ?>
					</ul>
					<input type="text" name="tag_entry" id="tag_entry" />
					<ul id="tag_results" style="display: none;"></ul>
				</fieldset>
			</div>
			<script type="text/javascript">
				BigTreeTagAdder.init(<?=$module["id"]?>,<? if ($item["id"]) { echo $item["id"]; } else { echo "false"; } ?>,"bigtree_tag_browser");
			</script>
		</section>
		<footer>
			<? if ($view["preview_url"]) { ?>
			<a class="button save_and_preview" href="#">
				<span></span>
				Save &amp; Preview
			</a>
			<? } ?>
			<input type="submit" class="button<? if ($permission_level == "e") { ?> blue<? } ?>" tabindex="<?=$tabindex?>" value="Save" name="save" />
			<? if ($permission_level == "p") { ?>
			<input type="submit" class="button blue" tabindex="<?=($tabindex + 1)?>" value="Save & Publish" name="save_and_publish" />
			<? } ?>
		</footer>
	</form>
</div>
<?
	if (count($htmls) || count($small_htmls) || count ($simplehtmls)) {
		include BigTree::path("admin/layouts/_tinymce.php"); 

		if (count($htmls))
			include BigTree::path("admin/layouts/_tinymce_specific.php");
		if (count($small_htmls))
			include BigTree::path("admin/layouts/_tinymce_block_small.php");
		if (count($simplehtmls))
			include BigTree::path("admin/layouts/_tinymce_specific_simple.php");
	}
?>
<script type="text/javascript">
	<?
		if (is_array($dates)) {
			foreach ($dates as $id) {
	?>
	$("#<?=$id?>").datepicker({ durration: 200, showAnim: "slideDown" });
	<?
			}
		}
		if (is_array($times)) {
			foreach ($times as $id) {
	?>
	$("#<?=$id?>").timepicker({ durration: 200, showAnim: "slideDown", ampm: true, hourGrid: 6,	minuteGrid: 10 });
	<?
			}
		}
	?>
	
	new BigTreeFormValidator("#auto_module_form");
	
	$(".save_and_preview").click(function() {
		$(this).parents("form").attr("action","process/preview/").submit();

		return false;
	});
</script>