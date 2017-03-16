<?
	define("BIGTREE_FRONT_END_EDITOR",true);
	$bigtree["layout"] = "front-end";
	// Check for a page lock
	$force = isset($_GET["force"]) ? $_GET["force"] : false;
	$admin->lockCheck("bigtree_pages",$bigtree["current_page"]["id"],"admin/modules/pages/front-end-locked.php",$force);
	
	$bigtree["template"] = $cms->getTemplate($bigtree["current_page"]["template"]);
	$bigtree["resources"] = $bigtree["current_page"]["resources"];
	$bigtree["html_fields"] = array();
	$bigtree["simple_html_fields"] = array();
	$bigtree["tabindex"] = 1;
	$bigtree["field_namespace"] = uniqid("template_field_");
	$bigtree["field_counter"] = 0;
?>
<h2>Edit Page Content</h2>
<form class="bigtree_dialog_form" method="post" action="<?=ADMIN_ROOT?>pages/front-end-update/" enctype="multipart/form-data">
	<? $admin->drawCSRFToken() ?>
	<input type="hidden" name="page" value="<?=$bigtree["current_page"]["id"]?>" />
	<input type="hidden" name="_bigtree_post_check" value="success" />
	<div class="overflow">
		<?
			if ($_SESSION["bigtree_admin"]["post_max_hit"]) {
				unset($_SESSION["bigtree_admin"]["post_max_hit"]);
		?>
		<p class="warning_message">The file(s) uploaded exceeded the web server's maximum upload size. If you uploaded multiple files, try uploading one at a time.</p>
		<?
			}
		?>
		<p class="error_message" style="display: none;">Errors found! Please fix the highlighted fields before submitting.</p>
		<div class="form_fields">
			<?
				if (is_array($bigtree["template"]["resources"]) && count($bigtree["template"]["resources"])) {

					// Get field types for knowing self drawing ones
					$cached_types = $admin->getCachedFieldTypes();
					$bigtree["field_types"] = $cached_types["templates"];

					foreach ($bigtree["template"]["resources"] as $resource) {
						$field = array(
							"type" => $resource["type"],
							"title" => $resource["title"],
							"subtitle" => $resource["subtitle"],
							"key" => "resources[".$resource["id"]."]",
							"value" => isset($bigtree["resources"][$resource["id"]]) ? $bigtree["resources"][$resource["id"]] : "",
							"tabindex" => $bigtree["tabindex"],
							"options" => $resource["options"]
						);

						BigTreeAdmin::drawField($field);
					}
				} else {
					echo '<p>There are no resources for the selected template.</p>';
				}
			?>
		</div>
	</div>
	<footer>
		<a class="button bigtree_dialog_close" href="#">Cancel</a>
		<input type="submit" class="button<? if ($bigtree["access_level"] != "p") { ?> blue<? } ?>" name="ptype" value="Save &amp; Preview" />
		<? if ($bigtree["access_level"] == "p") { ?>
		<input type="submit" class="button blue" name="ptype" value="Save &amp; Publish" />
		<? } ?>
	</footer>
</form>
<?
	$bigtree["html_editor_width"] = 760;
	$bigtree["html_editor_height"] = 365;			
	include BigTree::path("admin/layouts/_html-field-loader.php");
?>
<script>
	BigTreeFormValidator(".bigtree_dialog_form");
	
	$(".bigtree_dialog_close").click(function() {
		parent.BigTreeBar.cancel();
		
		return false;
	});
	
	BigTree.localLockTimer = setInterval("$.secureAjax('<?=ADMIN_ROOT?>ajax/refresh-lock/', { type: 'POST', data: { table: 'bigtree_pages', id: '<?=$bigtree["current_page"]["id"]?>' } });",60000);
</script>