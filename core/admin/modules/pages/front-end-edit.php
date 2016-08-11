<?php
	namespace BigTree;
	
	/**
	 * @global Page $page
	 */
	
	define("BIGTREE_FRONT_END_EDITOR",true);
	$bigtree["layout"] = "front-end";
	
	// Check for a page lock
	$force = isset($_GET["force"]) ? $_GET["force"] : false;
	Lock::enforce("bigtree_pages", $bigtree["current_page"]["id"], "admin/modules/pages/front-end-locked.php", $force);

	$template = new Template($bigtree["current_page"]["template"]);
	
	$bigtree["resources"] = $page->Resources;
	$bigtree["html_fields"] = array();
	$bigtree["simple_html_fields"] = array();
	$bigtree["tabindex"] = 1;
	$bigtree["template"] = $template->Array;
?>
<h2><?=Text::translate("Edit Page Content")?></h2>
<form class="bigtree_dialog_form" method="post" action="<?=ADMIN_ROOT?>pages/front-end-update/" enctype="multipart/form-data">
	<input type="hidden" name="page" value="<?=$bigtree["current_page"]["id"]?>" />
	<input type="hidden" name="_bigtree_post_check" value="success" />
	<div class="overflow">
		<?php
			if ($_SESSION["bigtree_admin"]["post_max_hit"]) {
				unset($_SESSION["bigtree_admin"]["post_max_hit"]);
		?>
		<p class="warning_message"><?=Text::translate("The file(s) uploaded exceeded the web server's maximum upload size. If you uploaded multiple files, try uploading one at a time.")?></p>
		<?php
			}
		?>
		<p class="error_message" style="display: none;"><?=Text::translate("Errors found! Please fix the highlighted fields before submitting.")?></p>
		<div class="form_fields">
			<?php
				if (count($template->Fields)) {

					// Get field types for knowing self drawing ones
					$bigtree["field_types"] = FieldType::reference(false,"templates");

					Field::$Namespace = uniqid("template_field_");

					foreach ($template->Fields as $resource) {
						$field = new Field(array(
							"type" => $resource["type"],
							"title" => $resource["title"],
							"subtitle" => $resource["subtitle"],
							"key" => "resources[".$resource["id"]."]",
							"value" => isset($bigtree["resources"][$resource["id"]]) ? $bigtree["resources"][$resource["id"]] : "",
							"tabindex" => $bigtree["tabindex"],
							"options" => $resource["options"]
						));

						$field->draw();
					}
				} else {
					echo '<p>'.Text::translate("There are no resources for the selected template.").'</p>';
				}
			?>
		</div>
	</div>
	<footer>
		<a class="button bigtree_dialog_close" href="#"><?=Text::translate("Cancel")?></a>
		<input type="submit" class="button<?php if ($bigtree["access_level"] != "p") { ?> blue<?php } ?>" name="ptype" value="<?=Text::translate("Save & Preview", true)?>" />
		<?php if ($bigtree["access_level"] == "p") { ?>
		<input type="submit" class="button blue" name="ptype" value="<?=Text::translate("Save & Publish", true)?>" />
		<?php } ?>
	</footer>
</form>
<?php
	$bigtree["html_editor_width"] = 760;
	$bigtree["html_editor_height"] = 365;			
	include Router::getIncludePath("admin/layouts/_html-field-loader.php");
?>
<script>
	BigTreeFormValidator(".bigtree_dialog_form");
	
	$(".bigtree_dialog_close").click(function() {
		parent.BigTreeBar.cancel();
		
		return false;
	});
	
	BigTree.localLockTimer = setInterval("$.ajax('<?=ADMIN_ROOT?>ajax/refresh-lock/', { type: 'POST', data: { table: 'bigtree_pages', id: '<?=$bigtree["current_page"]["id"]?>' } });",60000);
</script>