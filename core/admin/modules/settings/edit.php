<?php
	namespace BigTree;
	
	/**
	 * @global array $bigtree
	 */
	
	Auth::user()->requireLevel(1);
	
	$setting = new Setting(end(Router::$Path));
	$value = $setting->Encrypted ? "" : $setting->Value;

	if (!Setting::exists(end(Router::$Path)) || $setting->System || ($setting->Locked && Auth::user()->Level < 2)) {
		Auth::stop("The setting you are trying to edit no longer exists or you do not have permission to edit it.",
					Router::getIncludePath("admin/layouts/_error.php"));
	}
	
	$bigtree["field_types"] = FieldType::reference(false,"settings");

	Field::$Namespace = uniqid("setting_field_");
?>
<div class="container">
	<?php
		if (Auth::user()->Level > 1) {
	?>
	<div class="developer_buttons">
		<a href="<?=ADMIN_ROOT?>developer/settings/edit/<?=$setting->ID?>/" title="<?=Text::translate("Edit Setting in Developer", true)?>">
			<?=Text::translate("Edit Setting in Developer")?>
			<span class="icon_small icon_small_edit_yellow"></span>
		</a>
	</div>
	<?php
		}
	?>
	<div class="container_summary">
		<h2><?=$setting->Name?></h2>
	</div>
	<form class="module" action="<?=ADMIN_ROOT?>settings/update/" method="post" enctype="multipart/form-data">
		<?php CSRF::drawPOSTToken(); ?>
		<input type="hidden" name="MAX_FILE_SIZE" value="<?=Storage::getUploadMaxFileSize()?>" />
		<input type="hidden" name="_bigtree_post_check" value="success" />
		<input type="hidden" name="id" value="<?=htmlspecialchars(end(Router::$Path))?>" />
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
		
				Utils::drawPOSTErrorMessage();

				echo $setting->Description;
			?>
			<div class="form_fields">
				<?php
					$bigtree["html_fields"] = [];
					$bigtree["simple_html_fields"] = [];
					
					$field = new Field([
						"type" => $setting->Type,
						"title" => "",
						"subtitle" => "",
						"key" => "value",
						"tabindex" => 1,
						"settings" => $setting->Settings,
						"has_value" => !is_null($value),
						"value" => $value
					]);

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