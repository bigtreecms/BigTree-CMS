<?php
	namespace BigTree;
	
	/**
	 * @global array $author
	 * @global array $available_licenses
	 * @global array $licenses
	 * @global string $compatibility
	 * @global string $description
	 * @global string $id
	 * @global string $keywords
	 * @global string $license
	 * @global string $license_name
	 * @global string $license_url
	 * @global string $title
	 * @global string $version
	 */
?>
<div class="container">
	<form method="post" action="<?=DEVELOPER_ROOT?>extensions/build/save-details/" class="module">
	  <?php CSRF::drawPOSTToken(); ?>
		<section>
			<div class="contain">
				<h3><?=Text::translate("General Information")?></h3>
				<div class="left last">
					<fieldset<?php if (!empty($_GET["invalid"])) { ?> class="form_error"<?php } ?>>
						<label for="extension_field_id"><?=Text::translate('ID <small>(i.e. com.fastspot.news &mdash; allowed characters: alphanumeric, ".", "-", and "_")</small>')?></label>
						<input id="extension_field_id" type="text" name="id" value="<?=$id?>" tabindex="1" id="extension_id" />
					</fieldset>
					<div id="extension_id_warning" class="warning_message" style="display: none;">
						<p><?=Text::translate("This ID is already in use in the official BigTree extensions database.")?></p>
					</div>
					<fieldset>
						<label for="extension_field_title"><?=Text::translate('Title <small>(i.e. News)</small>')?></label>
						<input id="extension_field_title" type="text" name="title" value="<?=$title?>" tabindex="3" />
					</fieldset>
					<fieldset class="last">
						<label for="extension_field_description"><?=Text::translate("Description")?></label>
						<textarea id="extension_field_description" name="description" tabindex="5"><?=$description?></textarea>
					</fieldset>
				</div>
				<div class="right last">
					<fieldset>
						<label for="extension_field_compatibility"><?=Text::translate('BigTree Version Compatibility <small>(i.e. 4.2+)</small>')?></label>
						<input id="extension_field_compatibility" type="text" name="compatibility" value="<?=$compatibility?>" tabindex="2" />
					</fieldset>
					<fieldset>
						<label for="extension_field_version"><?=Text::translate('Version <small>(i.e. 1.5)</small>')?></label>
						<input id="extension_field_version" type="text" name="version" value="<?=$version?>" tabindex="4" />
					</fieldset>
					<fieldset class="last">
						<label for="extension_field_keywords"><?=Text::translate('Keywords <small>(separate with commas)</small>')?></label>
						<textarea id="extension_field_keywords" name="keywords" tabindex="6"><?=$keywords?></textarea>
					</fieldset>
				</div>
			</div>
			<hr />
			<div class="contain">
				<div class="left last">
					<h3><?=Text::translate("Open Source Licenses")?></h3>
					<?php
						$x = 0;
						
						foreach ($available_licenses["Open Source"] as $name => $link) {
							$x++;
					?>
					<div class="checkbox_row">
						<input id="extension_field_license_<?=$x?>" type="checkbox" name="licenses[]" value="<?=$name?>" <?php if (in_array($name,(array)$licenses)) { ?> checked="checked"<?php } ?>/>
						<label for="extension_field_license_<?=$x?>" class="for_checkbox"><?=$name?> &mdash; <a href="<?=$link?>" target="_blank"><?=Text::translate("Read License")?></a></label>
					</div>
					<?php
						}
					?>
				</div>
				<div class="right last">
					<h3><?=Text::translate("Closed Source License")?></h3>
					<?php
						foreach ($available_licenses["Closed Source"] as $name => $link) {
							$x++;
					?>
					<div class="checkbox_row">
						<input id="extension_field_license_<?=$x?>" type="radio" name="license" value="<?=$name?>" <?php if ($license == $name) { ?> checked="checked"<?php } ?>/>
						<label for="extension_field_license_<?=$x?>" class="for_checkbox"><?=Text::translate($name)?></label>
					</div>
					<?php
						}
					?>
					<br /><br />
					<h3><?=Text::translate("Custom License")?></h3>
					<fieldset>
						<label for="extension_field_license_name"><?=Text::translate("Name")?></label>
						<input id="extension_field_license_name" type="text" name="license_name" value="<?=$license_name?>" />
					</fieldset>
					<fieldset>
						<label for="extension_field_license_url"><?=Text::translate('URL <small>(to full license text)</small>')?></label>
						<input id="extension_field_license_url" type="text" name="license_url" value="<?=$license_url?>" />
					</fieldset>
				</div>
			</div>
			<hr />
			<h3><?=Text::translate("Author Information")?></h3>
			<div class="contain">
				<fieldset class="left">
					<label for="extension_field_author_name"><?=Text::translate("Name")?></label>
					<input id="extension_field_author_name" type="text" name="author[name]" value="<?=$author["name"]?>" />
				</fieldset>
				<fieldset class="right">
					<label for="extension_field_author_email"><?=Text::translate("Email")?></label>
					<input id="extension_field_author_email" type="email" name="author[email]" value="<?=$author["email"]?>" />
				</fieldset>
			</div>
			<fieldset>
				<label for="extension_field_author_url"><?=Text::translate("Website")?></label>
				<input id="extension_field_author_url" type="url" name="author[url]" value="<?=$author["url"]?>" />
			</fieldset>
		</section>
		<footer>
			<input type="submit" class="button blue" value="<?=Text::translate("Continue", true)?>" />
		</footer>
	</form>
</div>
<script>
	(function() {
		// Check for a unique ID
		var IDTimer = false;

		// Any time someone chooses an open source license, clear the proprietary ones
		$("input[type=checkbox]").click(function() {
			$("input[type=radio]").each(function() {
				this.customControl.clear();
			});
		});
		// And vice versa
		$("input[type=radio]").click(function() {
			$("input[type=checkbox]").each(function() {
				this.customControl.clear();
			});
		});
		
		$("#extension_id").keyup(function() {
			clearTimeout(IDTimer);
			
			IDTimer = setTimeout(function() {
				var value = $("#extension_id").val();
				
				if (value && value != $("#extension_id").prop("defaultValue")) {
					$.ajax("<?=ADMIN_ROOT?>ajax/developer/extensions/exists/?id=" + encodeURIComponent(value), { complete: function(req) {
						if (parseInt(req.responseText)) {
							$("#extension_id_warning").show();
						} else {
							$("#extension_id_warning").hide();
						}
					}});
				} else {
					$("#extension_id_warning").hide();
				}
			},300);
		});
	})();

</script>