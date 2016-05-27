<?php
	namespace BigTree;

	/**
	 * @global ModuleForm $form
	 */
?>
<div class="container">
	<form method="post" action="<?=$form->Root?>process/<?php if (!empty($form->Embedded)) { ?>?hash=<?=$form->Hash?><?php } ?>" enctype="multipart/form-data" class="module" id="auto_module_form">
		<?php if (!empty($form->Embedded)) { ?>
		<fieldset>
			<label><?=Text::translate("This is a field that shouldn't be filled out.")?></label>
			<input type="text" name="_bigtree_email" />
			<input type="text" name="_bigtree_hashcash" id="bigtree_hashcash_field" />
		</fieldset>
		<?php } ?>
		<input type="hidden" id="preview_field" name="_bigtree_preview" />
		<input type="hidden" name="MAX_FILE_SIZE" value="<?=Storage::getUploadMaxFileSize()?>" id="bigtree_max_file_size" />
		<input type="hidden" name="_bigtree_post_check" value="success" />
		<?php
			if (isset($bigtree["entry"])) {
		?>
		<input type="hidden" name="id" value="<?=htmlspecialchars($bigtree["edit_id"])?>" />
		<?php
			}	
			if (isset($_GET["view_data"])) {
		?>
		<input type="hidden" name="_bigtree_return_view_data" value="<?=htmlspecialchars($_GET["view_data"])?>" />
		<?php
			}
		?>
		<section>
			<p class="error_message" style="display: none;"><?=Text::translate("Errors found! Please fix the highlighted fields before submitting.")?></p>
			<?php
				if ($_SESSION["bigtree_admin"]["post_max_hit"]) {
					unset($_SESSION["bigtree_admin"]["post_max_hit"]);
			?>
			<p class="warning_message"><?=Text::translate("The file(s) uploaded exceeded the web server's maximum upload size. If you uploaded multiple files, try uploading one at a time.")?></p>
			<?php
				} elseif ($_SESSION["bigtree_admin"]["post_hash_failed"]) {
					unset($_SESSION["bigtree_admin"]["post_hash_failed"]);
			?>
			<p class="warning_message"><?=Text::translate("The form submission failed to pass our automated submission test. If you have JavaScript turned off, please turn it on.")?></p>
			<?php
				}
			?>
			<div class="form_fields">
				<?php
					$bigtree["html_fields"] = array();
					$bigtree["simple_html_fields"] = array();
					$bigtree["tabindex"] = 1;
					$bigtree["field_types"] = FieldType::reference(false,"modules");

					Field::$Namespace = uniqid("form_field_");
	
					foreach ($form->Fields as $resource) {
						if (is_array($resource)) {
							$field = array(
								"type" => $resource["type"],
								"title" => $resource["title"],
								"subtitle" => $resource["subtitle"],
								"key" => $resource["column"],
								"value" => isset($bigtree["entry"][$resource["column"]]) ? $bigtree["entry"][$resource["column"]] : "",
								"tabindex" => $bigtree["tabindex"],
								"options" => $resource["options"]
							);
	
							// Give many to many its information
							if ($resource["type"] == "many-to-many") {
								$field["value"] = isset($bigtree["many-to-many"][$resource["column"]]) ? $bigtree["many-to-many"][$resource["column"]]["data"] : false;
							}
		
							$field = new Field($field);
							$field->draw();
						}
					}
				?>
			</div>
			<?php if (!empty($form->Tagging)) { ?>
			<div class="tags" id="bigtree_tag_browser">
				<fieldset>
					<label><?=Text::translate("Tags")?><span></span></label>
					<ul id="tag_list">
						<?php foreach ($bigtree["tags"] as $tag) { ?>
						<li><input type="hidden" name="_tags[]" value="<?=$tag["id"]?>" /><a href="#"><?=$tag["tag"]?><span>x</span></a></li>
						<?php } ?>
					</ul>
					<input type="text" name="tag_entry" id="tag_entry" />
					<ul id="tag_results" style="display: none;"></ul>
				</fieldset>
			</div>
			<script>
				BigTreeTagAdder.init();
			</script>
			<?php } ?>
		</section>
		<footer>
			<?php
				if (!empty($form->Embedded)) {
			?>
			<input type="submit" class="button" tabindex="<?=$bigtree["tabindex"]?>" value="Submit" />
			<?php
				} else {
					if (isset($bigtree["related_view"]) && $bigtree["related_view"]["preview_url"]) {
			?>
			<a class="button save_and_preview" href="#">
				<span class="icon_small icon_small_computer"></span>
				<?=Text::translate("Save & Preview", true)?>
			</a>
			<?php
					}
			?>
			<input type="submit" class="button<?php if ($bigtree["access_level"] != "p") { ?> blue<?php } ?>" tabindex="<?=$bigtree["tabindex"]?>" value="<?=Text::translate("Save", true)?>" name="save" />
			<input type="submit" class="button blue" tabindex="<?=($bigtree["tabindex"] + 1)?>" value="<?=Text::translate("Save & Publish", true)?>" name="save_and_publish" <?php if ($bigtree["access_level"] != "p") { ?>style="display: none;" <?php } ?>/>
			<?php
				}
			?>
		</footer>
	</form>
</div>
<?php include Router::getIncludePath("admin/layouts/_html-field-loader.php") ?>
<script>
	BigTreeFormValidator("#auto_module_form",false<?php if (!empty($form->Embedded)) { ?>,true<?php } ?>);
	
	$(".save_and_preview").click(function() {
		$("#preview_field").val("true");
		$(this).parents("form").submit();

		return false;
	});

	<?php if ($bigtree["access_level"] == "p" || !$bigtree["edit_id"]) { ?>
	$(".gbp_select").change(function() {
		var access_level = $(this).find("option").eq($(this).get(0).selectedIndex).attr("data-access-level");
		if (access_level == "p") {
			$("input[name=save]").removeClass("blue");
			$("input[name=save_and_publish]").show();
		} else {
			$("input[name=save]").addClass("blue");
			$("input[name=save_and_publish]").hide();
		}
	}).trigger("change");
	<?php } ?>
</script>