<div class="container">
	<form method="post" action="<?=DEVELOPER_ROOT?>extensions/build/save-details/" class="module">
		<section>
			<div class="contain">
				<h3>General Information</h3>
				<div class="left last">
					<fieldset<?php if (!empty($_GET['invalid'])) {
    ?> class="form_error"<?php 
} ?>>
						<label>ID <small>(i.e. com.fastspot.news &mdash; allowed characters: alphanumeric, ".", "-", and "_")</small></label>
						<input type="text" name="id" value="<?=$id?>" tabindex="1" id="extension_id" />
					</fieldset>
					<div id="extension_id_warning" class="warning_message" style="display: none;">
						<p>This ID is already in use in the official BigTree extensions database.</p>
					</div>
					<fieldset>
						<label>Title <small>(i.e. News)</small></label>
						<input type="text" name="title" value="<?=$title?>" tabindex="3" />
					</fieldset>
					<fieldset class="last">
						<label>Description</label>
						<textarea name="description" tabindex="5"><?=$description?></textarea>
					</fieldset>
				</div>
				<div class="right last">
					<fieldset>
						<label>BigTree Version Compatibility <small>(i.e. 4.2+)</small></label>
						<input type="text" name="compatibility" value="<?=$compatibility?>" tabindex="2" />
					</fieldset>
					<fieldset>
						<label>Version <small>(i.e. 1.5)</small></label>
						<input type="text" name="version" value="<?=$version?>" tabindex="4" />
					</fieldset>
					<fieldset class="last">
						<label>Keywords <small>(separate with commas)</label>
						<textarea name="keywords" tabindex="6"><?=$keywords?></textarea>
					</fieldset>
				</div>
			</div>
			<hr />
			<div class="contain">
				<div class="left last">
					<h3>Open Source Licenses</h3>
					<?php foreach ($available_licenses['Open Source'] as $name => $link) {
    ?>
					<div class="checkbox_row">
						<input type="checkbox" name="licenses[]" value="<?=$name?>" <?php if (in_array($name, (array) $licenses)) {
    ?> checked="checked"<?php 
}
    ?>/>
						<label class="for_checkbox"><?=$name?> &mdash; <a href="<?=$link?>" target="_blank">Read License</a></label>
					</div>
					<?php 
} ?>
				</div>
				<div class="right last">
					<h3>Closed Source License</h3>
					<?php foreach ($available_licenses['Closed Source'] as $name => $link) {
    ?>
					<div class="checkbox_row">
						<input type="radio" name="license" value="<?=$name?>" <?php if ($license == $name) {
    ?> checked="checked"<?php 
}
    ?>/>
						<label class="for_checkbox"><?=$name?></label>
					</div>
					<?php 
} ?>
					<br /><br />
					<h3>Custom License</h3>
					<fieldset>
						<label>Name</label>
						<input type="text" name="license_name" value="<?=$license_name?>" />
					</fieldset>
					<fieldset>
						<label>URL <small>(to full license text)</label>
						<input type="text" name="license_url" value="<?=$license_url?>" />
					</fieldset>
				</div>
			</div>
			<hr />
			<h3>Author Information</h3>
			<div class="contain">
				<fieldset class="left">
					<label>Name</label>
					<input type="text" name="author[name]" value="<?=$author['name']?>" />
				</fieldset>
				<fieldset class="right">
					<label>Email</label>
					<input type="email" name="author[email]" value="<?=$author['email']?>" />
				</fieldset>
			</div>
			<fieldset>
				<label>Website</label>
				<input type="url" name="author[url]" value="<?=$author['url']?>" />
			</fieldset>
		</section>
		<footer>
			<input type="submit" class="button blue" value="Continue" />
		</footer>
	</form>
</div>
<script>
	(function() {
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

		// Check for a unique ID
		var IDTimer = false;
		$("#extension_id").keyup(function() {
			clearTimeout(IDTimer);
			IDTimer = setTimeout(function() {
				var value = $("#extension_id").val();
				if (value && value != $("#extension_id").prop("defaultValue")) {
					$.ajax("<?=ADMIN_ROOT?>ajax/developer/extensions/exists/?id=" + escape(value), { complete: function(req) {
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