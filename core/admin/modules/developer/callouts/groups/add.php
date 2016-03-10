<?php
	$callouts = BigTree\Callout::all("name ASC",true);
?>
<div class="container">
	<form method="post" action="<?=DEVELOPER_ROOT?>callouts/groups/create/" class="module">
		<section>
			<fieldset>
				<label class="required">Name</label>
				<input type="text" name="name" value="" class="required" />
			</fieldset>
			<fieldset>
				<label>Callouts</label>
				<div class="multi_widget many_to_many" id="group_callouts">
					<section<?php if (count($entries)) { ?> style="display: none;"<?php } ?>>
						<p>Click "Add Item" to add an item to this list.</p>
					</section>
					<ul></ul>
					<footer>
						<select>
							<?php foreach ($callouts as $callout) { ?>
							<option value="<?=BigTree::safeEncode($callout["id"])?>"><?=BigTree::trimLength($callout["name"],100)?></option>
							<?php } ?>
						</select>
						<a href="#" class="add button"><span class="icon_small icon_small_add"></span>Add Callout</a>
					</footer>
				</div>
			</fieldset>
		</section>
		<footer>
			<input type="submit" class="button blue" value="Create" />
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