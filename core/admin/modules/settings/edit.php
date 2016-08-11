<?php
	namespace BigTree;
	
	/**
	 * @global array $bigtree
	 */
	
	Auth::user()->requireLevel(1);
	
	$setting = new Setting(end($bigtree["path"]));
	$value = $setting->Encrypted ? "" : $setting->Value;

	if (!Setting::exists(end($bigtree["path"])) || $setting->System || ($setting->Locked && Auth::user()->Level < 2)) {
		Auth::stop("The setting you are trying to edit no longer exists or you do not have permission to edit it.",
					Router::getIncludePath("admin/layouts/_error.php"));
	}

	// Provide developers a nice handy link for edit/return of this view
	if (Auth::user()->Level > 1) {
		$bigtree["subnav_extras"][] = array("link" => ADMIN_ROOT."developer/settings/edit/".$setting->ID."/?return=front",
											"icon" => "setup",
											"title" => "Edit in Developer"
		);
	}

	$bigtree["field_types"] = FieldType::reference(false,"settings");

	Field::$Namespace = uniqid("setting_field_");
?>
<div class="container">
	<div class="container_summary">
		<h2><?=$setting->Name?></h2>
		<?php if (Auth::user()->Level > 1) { ?>
		<a class="button" href="<?=ADMIN_ROOT?>developer/settings/edit/<?=$setting->ID?>/?return=front"><?=Text::translate("Edit in Developer")?></a>
		<?php } ?>
	</div>
	<form class="module" action="<?=ADMIN_ROOT?>settings/update/" method="post" enctype="multipart/form-data">
		<input type="hidden" name="MAX_FILE_SIZE" value="<?=Storage::getUploadMaxFileSize()?>" />
		<input type="hidden" name="_bigtree_post_check" value="success" />
		<input type="hidden" name="id" value="<?=htmlspecialchars(end($bigtree["path"]))?>" />
		<section>
			<?php
				if ($setting->Encrypted) {
			?>
			<div class="alert">
				<span></span>
				<p><?=Text::translate("This setting is encrypted. The current value cannot be shown.")?></p>
			</div>
			<?php
				}
		
				if ($_SESSION["bigtree_admin"]["post_max_hit"]) {
					unset($_SESSION["bigtree_admin"]["post_max_hit"]);
			?>
			<p class="warning_message"><?=Text::translate("The file(s) uploaded exceeded the web server's maximum upload size. If you uploaded multiple files, try uploading one at a time.")?></p>
			<?php
				}

				echo $setting->Description;
			?>
			<div class="form_fields">
				<?php
					$bigtree["html_fields"] = array();
					$bigtree["simple_html_fields"] = array();
					
					$field = new Field(array(
						"type" => $setting->Type,
						"title" => "",
						"subtitle" => "",
						"key" => "value",
						"tabindex" => 1,
						"options" => $setting->Settings,
						"value" => $value
					));

					$field->draw();
				?>
			</div>
		</section>
		<footer>
			<input type="submit" class="button blue" value="<?=Text::translate("Update", true)?>" />		
		</footer>
	</form>
</div>
<?php
	$bigtree["html_editor_width"] = 898;
	$bigtree["html_editor_height"] = 365;
	include Router::getIncludePath("admin/layouts/_html-field-loader.php");
?>
<script>
	BigTreeFormValidator("form.module");
</script>