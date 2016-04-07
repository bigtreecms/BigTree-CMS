<?php
	namespace BigTree;
	
	$callouts = Callout::all("name ASC",true);
	$group = new CalloutGroup(end($bigtree["path"]));
?>
<div class="container">
	<form method="post" action="<?=DEVELOPER_ROOT?>callouts/groups/update/<?=$group->ID?>/" class="module">
		<section>
			<fieldset>
			    <label class="required">Name</label>
			    <input type="text" name="name" value="<?=$group->Name?>" class="required" />
			</fieldset>
			<fieldset>
				<label>Callouts</label>
				<div class="multi_widget many_to_many" id="group_callouts">
					<section<?php if (count($group->Callouts)) { ?> style="display: none;"<?php } ?>>
						<p>Click "Add Item" to add an item to this list.</p>
					</section>
					<ul>
						<?php
							$x = 0;
							foreach ($group->Callouts as $id) {
								$callout = new Callout($id);
						?>
						<li>
							<input type="hidden" name="callouts[<?=$x?>]" value="<?=Text::htmlEncode($id)?>" />
							<p><?=Text::trimLength($callout->Name,100)?></p>
							<a href="#" class="icon_delete"></a>
						</li>
						<?php
								$x++;
							}
						?>
					</ul>
					<footer>
						<select>
							<?php
								foreach ($callouts as $callout) {
									if (!in_array($callout["id"],$group->Callouts)) {
							?>
							<option value="<?=Text::htmlEncode($callout["id"])?>"><?=Text::trimLength($callout["name"],100)?></option>
							<?php
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