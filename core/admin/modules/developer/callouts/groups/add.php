<?php
	namespace BigTree;
	
	$callouts = Callout::all("name ASC",true);
?>
<div class="container">
	<form method="post" action="<?=DEVELOPER_ROOT?>callouts/groups/create/" class="module">
		<?php CSRF::drawPOSTToken(); ?>
		<section>
			<fieldset>
				<label for="group_field_name" class="required"><?=Text::translate("Name")?></label>
				<input id="group_field_name" type="text" name="name" value="" class="required" />
			</fieldset>
			<fieldset>
				<label for="group_field_callouts"><?=Text::translate("Callouts")?></label>
				<div class="multi_widget many_to_many" id="group_callouts">
					<section>
						<p><?=Text::translate('Click "Add Item" to add an item to this list.')?></p>
					</section>
					<ul></ul>
					<footer>
						<select id="group_field_callouts">
							<?php foreach ($callouts as $callout) { ?>
							<option value="<?=Text::htmlEncode($callout["id"])?>"><?=Text::trimLength($callout["name"],100)?></option>
							<?php } ?>
						</select>
						<a href="#" class="add button"><span class="icon_small icon_small_add"></span><?=Text::translate("Add Callout")?></a>
					</footer>
				</div>
			</fieldset>
		</section>
		<footer>
			<input type="submit" class="button blue" value="<?=Text::translate("Create", true)?>" />
		</footer>
	</form>
</div>
<script>
	BigTreeFormValidator("form.module");
	BigTreeManyToMany({
		id: "group_callouts",
		count: 0,
		key: "callouts"
	});
</script>