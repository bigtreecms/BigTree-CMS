<?php
	$admin->requireLevel(1);
	$item = $admin->getSetting(end($bigtree["path"]));
	$value = $cms->getSetting(end($bigtree["path"]));
	
	if ($item["encrypted"]) {
		$value = "";
	}

	if (!$item || $item["system"] || ($item["locked"] && $admin->Level < 2)) {
?>
<div class="container">
	<section>
		<h3>Error</h3>
		<p>The setting you are trying to edit no longer exists or you do not have permission to edit it.</p>
	</section>
</div>
<?php
		$admin->stop();
	}

	$cached_types = $admin->getCachedFieldTypes();
	$bigtree["field_types"] = $cached_types["settings"];
	$bigtree["field_namespace"] = uniqid("setting_field_");
	$bigtree["field_counter"] = 0;
?>
<div class="container">
	<?php
		if ($admin->Level > 1) {
	?>
	<div class="developer_buttons">
		<a href="<?=ADMIN_ROOT?>developer/settings/edit/<?=$item["id"]?>/" title="Edit Setting in Developer">
			Edit Setting in Developer
			<span class="icon_small icon_small_edit_yellow"></span>
		</a>
	</div>
	<?php
		}
	?>
	<summary>
		<h2><?=$item["name"]?></h2>
	</summary>
	<form class="module" action="<?=ADMIN_ROOT?>settings/update/" method="post" enctype="multipart/form-data">
		<?php $admin->drawCSRFToken() ?>
		<input type="hidden" name="MAX_FILE_SIZE" value="<?=BigTree::uploadMaxFileSize()?>" />
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
		
				$admin->drawPOSTErrorMessage();

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
						"settings" => $item["settings"],
						"has_value" => !is_null($value),
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