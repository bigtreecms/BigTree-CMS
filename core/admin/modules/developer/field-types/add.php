<?php
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
		<?php $admin->drawCSRFToken() ?>
		<section>
			<div class="contain">
				<div class="left">
					<fieldset<?php if ($show_error) { ?> class="form_error"<?php } ?>>
						<label class="required">ID <small>(used for file name, alphanumeric, "-" and "_" only)</small><?php if ($show_error) { ?> <span class="form_error_reason"><?=$show_error?></span><?php } ?></label>
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
							<li><input type="checkbox" name="use_cases[templates]"<?php if ($use_cases["templates"]) { ?> checked="checked"<?php } ?> /> <label class="for_checkbox">Templates</label></li>
							<li><input type="checkbox" name="use_cases[modules]"<?php if ($use_cases["modules"]) { ?> checked="checked"<?php } ?> /> <label class="for_checkbox">Modules</label></li>
							<li><input type="checkbox" name="use_cases[callouts]"<?php if ($use_cases["callouts"]) { ?> checked="checked"<?php } ?> /> <label class="for_checkbox">Callouts</label></li>
							<li><input type="checkbox" name="use_cases[settings]"<?php if ($use_cases["settings"]) { ?> checked="checked"<?php } ?> /> <label class="for_checkbox">Settings</label></li>
						</ul>
					</fieldset>
				</div>
			</div>
			<hr />
			<fieldset>
				<input type="checkbox" name="self_draw"<?php if ($self_draw) { ?> checked="checked"<?php } ?> />
				<label class="for_checkbox">Self Draw <small>(if checked, you will need to draw your &lt;fieldset&gt; and &lt;label&gt; manually)</small></label>
			</fieldset>
		</section>
		<footer>
			<input type="submit" class="button blue" value="Create" />
		</footer>
	</form>
</div>

<script>
	BigTreeFormValidator("form.module");
</script>