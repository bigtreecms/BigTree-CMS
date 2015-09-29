<?php
	$type = $admin->getFieldType(end($bigtree['commands']));
	BigTree::globalizeArray($type, array('htmlspecialchars'));
?>
<div class="container">
	<form method="post" action="<?=DEVELOPER_ROOT?>field-types/update/" enctype="multipart/form-data" class="module">
		<input type="hidden" name="id" value="<?=$id?>" />
		<section>
			<fieldset>
				<label class="required">Name</label>
				<input type="text" class="required" name="name" value="<?=$name?>" />
			</fieldset>
			<fieldset>
				<label class="required">Use Cases</label>
				<ul class="developer_field_types_usage">
					<li><input type="checkbox" name="use_cases[templates]"<?php if ($use_cases['templates']) {
    ?> checked="checked"<?php 
} ?> /> <label class="for_checkbox">Templates</label></li>
					<li><input type="checkbox" name="use_cases[modules]"<?php if ($use_cases['modules']) {
    ?> checked="checked"<?php 
} ?> /> <label class="for_checkbox">Modules</label></li>
					<li><input type="checkbox" name="use_cases[callouts]"<?php if ($use_cases['callouts']) {
    ?> checked="checked"<?php 
} ?> /> <label class="for_checkbox">Callouts</label></li>
					<li><input type="checkbox" name="use_cases[settings]"<?php if ($use_cases['settings']) {
    ?> checked="checked"<?php 
} ?> /> <label class="for_checkbox">Settings</label></li>
				</ul>
			</fieldset>
			<hr />
			<fieldset>
				<input type="checkbox" name="self_draw"<?php if ($self_draw) {
    ?> checked="checked"<?php 
} ?> />
				<label class="for_checkbox">Self Draw <small>(if checked, you will need to draw your &lt;fieldset&gt; and &lt;label&gt; manually)</small></label>
			</fieldset>
		</section>
		<footer>
			<input type="submit" class="button blue" value="Update" />
		</footer>
	</form>
</div>

<script>
	BigTreeFormValidator("form.module");
</script>