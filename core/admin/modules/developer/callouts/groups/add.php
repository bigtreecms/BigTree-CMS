<?
	$callouts = $admin->getCallouts("name ASC");
?>
<div class="container">
	<form method="post" action="<?=DEVELOPER_ROOT?>callouts/groups/create/" class="module">
		<? $admin->drawCSRFToken() ?>
		<section>
			<fieldset>
				<label class="required">Name</label>
				<input type="text" name="name" value="" class="required" />
			</fieldset>
			<fieldset>
				<label>Callouts</label>
				<div class="multi_widget many_to_many" id="group_callouts">
					<section<? if (count($entries)) { ?> style="display: none;"<? } ?>>
						<p>Click "Add Item" to add an item to this list.</p>
					</section>
					<ul></ul>
					<footer>
						<select>
							<? foreach ($callouts as $callout) { ?>
							<option value="<?=BigTree::safeEncode($callout["id"])?>"><?=BigTree::safeEncode(BigTree::trimLength(strip_tags($callout["name"]),100))?></option>
							<? } ?>
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