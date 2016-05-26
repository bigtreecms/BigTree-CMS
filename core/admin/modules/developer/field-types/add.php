<?php
	namespace BigTree;

	// Stop notices
	$id = $name = $self_draw = "";
	$use_cases = array("templates" => "on", "modules" => "on","callouts" => "on","settings" => "on");
	if ($_SESSION["bigtree_admin"]["error"]) {
		Globalize::arrayObject($_SESSION["bigtree_admin"]["saved"]);
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
					<fieldset<?php if ($show_error) { ?> class="form_error"<?php } ?>>
						<label class="required"><?=Text::translate('ID <small>(used for file name, alphanumeric, "-" and "_" only)</small>')?><?php if ($show_error) { ?> <span class="form_error_reason"><?=Text::translate($show_error)?></span><?php } ?></label>
						<input type="text" class="required" name="id" value="<?=$id?>" />
					</fieldset>
					<fieldset>
						<label class="required"><?=Text::translate("Name")?></label>
						<input type="text" class="required" name="name" value="<?=$name?>" />
					</fieldset>
				</div>
				<div class="right">
					<fieldset>
						<label class="required"><?=Text::translate("Use Cases")?></label>
						<ul class="developer_field_types_usage">
							<li><input type="checkbox" name="use_cases[templates]"<?php if ($use_cases["templates"]) { ?> checked="checked"<?php } ?> /> <label class="for_checkbox"><?=Text::translate("Templates")?></label></li>
							<li><input type="checkbox" name="use_cases[modules]"<?php if ($use_cases["modules"]) { ?> checked="checked"<?php } ?> /> <label class="for_checkbox"><?=Text::translate("Modules")?></label></li>
							<li><input type="checkbox" name="use_cases[callouts]"<?php if ($use_cases["callouts"]) { ?> checked="checked"<?php } ?> /> <label class="for_checkbox"><?=Text::translate("Callouts")?></label></li>
							<li><input type="checkbox" name="use_cases[settings]"<?php if ($use_cases["settings"]) { ?> checked="checked"<?php } ?> /> <label class="for_checkbox"><?=Text::translate("Settings")?></label></li>
						</ul>
					</fieldset>
				</div>
			</div>
			<hr />
			<fieldset>
				<input type="checkbox" name="self_draw"<?php if ($self_draw) { ?> checked="checked"<?php } ?> />
				<label class="for_checkbox"><?=Text::translate('Self Draw <small>(if checked, you will need to draw your &lt;fieldset&gt; and &lt;label&gt; manually)</small>')?></label>
			</fieldset>
		</section>
		<footer>
			<input type="submit" class="button blue" value="<?=Text::translate("Create", true)?>" />
		</footer>
	</form>
</div>

<script>
	BigTreeFormValidator("form.module");
</script>