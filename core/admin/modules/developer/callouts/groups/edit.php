<?
	$id = end($bigtree["path"]);
	$callouts = $admin->getCallouts("name ASC");
	$group = $admin->getCalloutGroup($id);
	$group["callouts"] = is_array($group["callouts"]) ? $group["callouts"] : array();
?>
<div class="container">
	<form method="post" action="<?=DEVELOPER_ROOT?>callouts/groups/update/<?=$id?>/" class="module">
		<? $admin->drawCSRFToken() ?>
		<section>
			<fieldset>
			    <label class="required">Name</label>
			    <input type="text" name="name" value="<?=$group["name"]?>" class="required" />
			</fieldset>
			<fieldset>
				<label>Callouts</label>
				<div class="multi_widget many_to_many" id="group_callouts">
					<section<? if (count($group["callouts"])) { ?> style="display: none;"<? } ?>>
						<p>Click "Add Item" to add an item to this list.</p>
					</section>
					<ul>
						<?
							$x = 0;
							foreach ($group["callouts"] as $id) {
								$callout = $admin->getCallout($id);
						?>
						<li>
							<input type="hidden" name="callouts[<?=$x?>]" value="<?=BigTree::safeEncode($id)?>" />
							<p><?=BigTree::safeEncode(BigTree::trimLength(strip_tags($callout["name"]),100))?></p>
							<a href="#" class="icon_delete"></a>
						</li>
						<?
								$x++;
							}
						?>
					</ul>
					<footer>
						<select>
							<?
								foreach ($callouts as $callout) {
									if (!in_array($callout["id"],$group["callouts"])) {
							?>
							<option value="<?=BigTree::safeEncode($callout["id"])?>"><?=BigTree::safeEncode(BigTree::trimLength(strip_tags($callout["name"]),100))?></option>
							<?
									}
								}
							?>
						</select>
						<a href="#" class="add button"><span class="icon_small icon_small_add"></span>Add Callout</a>
					</footer>
				</div>
			</fieldset>
		</section>
		<footer>
			<input type="submit" class="button blue" value="Update" />
		</footer>
	</form>
</div>
<script>
	BigTreeFormValidator("form.module");
	BigTreeManyToMany({
		id: "group_callouts",
		count: <?=$x?>,
		key: "callouts"
	});
</script>