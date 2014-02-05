<div class="container">
	<header><p>Build out the manifest details for your package.</p></header>
	<form method="post" action="<?=DEVELOPER_ROOT?>packages/build/save-details/" class="module">
		<section>
			<div class="contain">
				<h3>Package Information</h3>
				<div class="left last">
					<fieldset<? if (!empty($_GET["invalid"])) { ?> class="form_error"<? } ?>>
						<label>ID <small>(i.e. com.fastspot.news &mdash; allowed characters: alphanumeric, ".", "-", and "_")</small></label>
						<input type="text" name="id" value="<?=$id?>" tabindex="1" />
					</fieldset>
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
						<label>BigTree Version Compatibility <small>(i.e. 4.0+)</small></label>
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
					<? foreach ($available_licenses["Open Source"] as $name => $link) { ?>
					<div class="checkbox_row">
						<input type="checkbox" name="licenses[]" value="<?=$name?>" <? if (in_array($name,(array)$licenses)) { ?> checked="checked"<? } ?>/>
						<label class="for_checkbox"><?=$name?> &mdash; <a href="<?=$link?>" target="_blank">Read License</a></label>
					</div>
					<? } ?>
				</div>
				<div class="right last">
					<h3>Closed Source License</h3>
					<? foreach ($available_licenses["Closed Source"] as $name => $link) { ?>
					<div class="checkbox_row">
						<input type="radio" name="license" value="<?=$name?>" <? if ($license == $name) { ?> checked="checked"<? } ?>/>
						<label class="for_checkbox"><?=$name?></label>
					</div>
					<? } ?>
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
					<input type="text" name="author[name]" value="<?=$author["name"]?>" />
				</fieldset>
				<fieldset class="right">
					<label>Email</label>
					<input type="email" name="author[email]" value="<?=$author["email"]?>" />
				</fieldset>
			</div>
			<fieldset>
				<label>Website</label>
				<input type="url" name="author[url]" value="<?=$author["url"]?>" />
			</fieldset>
		</section>
		<footer>
			<input type="submit" class="button blue" value="Continue" />
		</footer>
	</form>
</div>
<script>
	$("input[type=checkbox]").click(function() {
		$("input[type=radio]").each(function() {
			this.customControl.clear();
		});
	});
	$("input[type=radio]").click(function() {
		$("input[type=checkbox]").each(function() {
			this.customControl.clear();
		});
	});
</script>