<?
	// Stop notices
	$id = $name = $pages = $modules = $callouts = $settings = "";
	if (isset($_SESSION["bigtree_admin"]["admin_saved"])) {
		BigTree::globalizeArray($_SESSION["bigtree_admin"]["admin_saved"],array("htmlspecialchars"));
		unset($_SESSION["bigtree_admin"]["admin_saved"]);
	}
	
	if (isset($_SESSION["bigtree_admin"]["admin_error"])) {
		$e = $_SESSION["bigtree_admin"]["admin_error"];
		unset($_SESSION["bigtree_admin"]["admin_error"]);
	} else {
		$e = false;
	}
?>
<div class="container">
	<form method="post" action="<?=$developer_root?>field-types/create/" enctype="multipart/form-data" class="module">
		<section>
			<p class="error_message"<? if (!$e) { ?> style="display: none;"<? } ?>>Errors found! Please fix the highlighted fields before submitting.</p>
			<div class="left">
				<fieldset<? if ($e) { ?> class="form_error"<? } ?>>
					<label class="required">ID <small>(must be unique among all field types)</small><? if ($e) { ?><span class="form_error_reason">ID Already In Use</span><? } ?></label>
					<input type="text" class="required" name="id" value="<?=$id?>" />
				</fieldset>
				<fieldset>
					<label class="required">Name</label>
					<input type="text" class="required" name="name" value="<?=$name?>" />
				</fieldset>
			</div>
			<div class="right">
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