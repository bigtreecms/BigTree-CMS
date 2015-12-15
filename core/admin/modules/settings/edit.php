<?php
	$admin->requireLevel(1);
	$item = $admin->getSetting(end($bigtree["path"]));
	$value = $cms->getSetting(end($bigtree["path"]));
	if ($item["encrypted"]) {
		$value = "";
	}

	if (!$item || $item["system"] || ($item["locked"] && $admin->Level < 2)) {
		$admin->stop("The setting you are trying to edit no longer exists or you do not have permission to edit it.",BigTree::path("admin/layouts/_error.php"));
	}

	// Provide developers a nice handy link for edit/return of this view
	if ($admin->Level > 1) {
		$bigtree["subnav_extras"][] = array("link" => ADMIN_ROOT."developer/settings/edit/".$item["id"]."/?return=front","icon" => "setup","title" => "Edit in Developer");
	}

	$cached_types = $admin->getCachedFieldTypes();
	$bigtree["field_types"] = $cached_types["settings"];
	$bigtree["field_namespace"] = uniqid("setting_field_");
	$bigtree["field_counter"] = 0;
?>
<div class="container">
	<summary>
		<h2><?=$item["name"]?></h2>
		<?php if ($admin->Level > 1) { ?>
		<a class="button" href="<?=ADMIN_ROOT?>developer/settings/edit/<?=$item["id"]?>/?return=front">Edit in Developer</a>
		<?php } ?>
	</summary>
	<form class="module" action="<?=ADMIN_ROOT?>settings/update/" method="post" enctype="multipart/form-data">
		<input type="hidden" name="MAX_FILE_SIZE" value="<?=BigTree::uploadMaxFileSize()?>" />
		<input type="hidden" name="_bigtree_post_check" value="success" />
		<input type="hidden" name="id" value="<?=htmlspecialchars(end($bigtree["path"]))?>" />
		<section>
			<?php
				if ($item["encrypted"]) {
			?>
			<div class="alert">
				<span></span>
				<p>This setting is encrypted. The current value cannot be shown.</p>
			</div>
			<?php
				}
		
				if ($_SESSION["bigtree_admin"]["post_max_hit"]) {
					unset($_SESSION["bigtree_admin"]["post_max_hit"]);
			?>
			<p class="warning_message">The file(s) uploaded exceeded the web server's maximum upload size. If you uploaded multiple files, try uploading one at a time.</p>
			<?php
				}

				echo $item["description"];
			?>
			<div class="form_fields">
				<?php
					$bigtree["html_fields"] = array();
					$bigtree["simple_html_fields"] = array();
					
					$field = array(
						"type" => $item["type"],
						"title" => "",
						"subtitle" => "",
						"key" => "value",
						"tabindex" => 1,
						"options" => json_decode($item["options"],true),
						"value" => $value
					);

					BigTreeAdmin::drawField($field);
				?>
			</div>
		</section>
		<footer>
			<input type="submit" class="button blue" value="Update" />		
		</footer>
	</form>
</div>
<?php
	$bigtree["html_editor_width"] = 898;
	$bigtree["html_editor_height"] = 365;
	include BigTree::path("admin/layouts/_html-field-loader.php");
?>
<script>
	BigTreeFormValidator("form.module");
</script>