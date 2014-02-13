<?
	// Stop notices
	$id = $name = $pages = $modules = $callouts = $settings = "";
	if ($_SESSION["bigtree_admin"]["error"]) {
		BigTree::globalizeArray($_SESSION["bigtree_admin"]["saved"]);
		$show_error = $_SESSION["bigtree_admin"]["error"];
		unset($_SESSION["bigtree_admin"]["error"]);
		unset($_SESSION["bigtree_admin"]["saved"]);
	}
?>
<div class="container">
	<form method="post" action="<?=DEVELOPER_ROOT?>field-types/create/" enctype="multipart/form-data" class="module">
		<section>
			<div class="left last">
				<fieldset<? if ($show_error) { ?> class="form_error"<? } ?>>
					<label class="required">ID <small>(used for file name, alphanumeric, "-" and "_" only)</small><? if ($show_error) { ?> <span class="form_error_reason"><?=$show_error?></span><? } ?></label>
					<input type="text" class="required" name="id" value="<?=$id?>" />
				</fieldset>
				<fieldset>
					<label class="required">Name</label>
					<input type="text" class="required" name="name" value="<?=$name?>" />
				</fieldset>
			</div>
			<div class="right last">
				<fieldset>
					<label class="required">Use Cases</label>
					<ul class="developer_field_types_usage">
						<li><input type="checkbox" name="pages"<? if ($pages) { ?> checked="checked"<? } ?> /> <label class="for_checkbox">Pages</label></li>
						<li><input type="checkbox" name="modules"<? if ($modules) { ?> checked="checked"<? } ?> /> <label class="for_checkbox">Modules</label></li>
						<li><input type="checkbox" name="callouts"<? if ($callouts) { ?> checked="checked"<? } ?> /> <label class="for_checkbox">Callouts</label></li>
						<li><input type="checkbox" name="settings"<? if ($settings) { ?> checked="checked"<? } ?> /> <label class="for_checkbox">Settings</label></li>
					</ul>
				</fieldset>
			</div>
		</section>
		<footer>
			<input type="submit" class="button blue" value="Create" />
		</footer>
	</form>
</div>

<script>
	new BigTreeFormValidator("form.module");
</script>