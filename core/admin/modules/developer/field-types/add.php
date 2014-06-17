<?
	// Stop notices
	$id = $name = $self_draw = "";
	$use_cases = array("templates" => "on", "modules" => "on","callouts" => "on","settings" => "on");
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
			<div class="contain">
				<div class="left">
					<fieldset<? if ($show_error) { ?> class="form_error"<? } ?>>
						<label class="required">ID <small>(used for file name, alphanumeric, "-" and "_" only)</small><? if ($show_error) { ?> <span class="form_error_reason"><?=$show_error?></span><? } ?></label>
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
							<li><input type="checkbox" name="use_cases[templates]"<? if ($use_cases["templates"]) { ?> checked="checked"<? } ?> /> <label class="for_checkbox">Templates</label></li>
							<li><input type="checkbox" name="use_cases[modules]"<? if ($use_cases["modules"]) { ?> checked="checked"<? } ?> /> <label class="for_checkbox">Modules</label></li>
							<li><input type="checkbox" name="use_cases[callouts]"<? if ($use_cases["callouts"]) { ?> checked="checked"<? } ?> /> <label class="for_checkbox">Callouts</label></li>
							<li><input type="checkbox" name="use_cases[settings]"<? if ($use_cases["settings"]) { ?> checked="checked"<? } ?> /> <label class="for_checkbox">Settings</label></li>
						</ul>
					</fieldset>
				</div>
			</div>
			<hr />
			<fieldset>
				<input type="checkbox" name="self_draw"<? if ($self_draw) { ?> checked="checked"<? } ?> />
				<label class="for_checkbox">Self Draw <small>(if checked, you will need to draw your &lt;fieldset&gt; and &lt;label&gt; manually)</small></label>
			</fieldset>
		</section>
		<footer>
			<input type="submit" class="button blue" value="Create" />
		</footer>
	</form>
</div>

<script>
	new BigTreeFormValidator("form.module");
</script>