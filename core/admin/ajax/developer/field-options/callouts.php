<?
	$groups = $admin->getCalloutGroups();
	// Stop notices
	$data["groups"] = is_array($data["groups"]) ? $data["groups"] : array();
	$data["verb"] = isset($data["verb"]) ? $data["verb"] : "";
	$data["max"] = $data["max"] ? intval($data["max"]) : "";

	// Work with older group info from 4.1 and lower
	if (!array_filter($data["groups"]) && $data["group"]) {
		$data["groups"] = array($data["group"]);
	}
?>
<fieldset>
	<label>Groups <small>(if you don't choose at least one group, all callouts will be available)</small></label>
	<div class="multi_widget many_to_many" id="callout_groups">
		<section<? if (count($data["groups"])) { ?> style="display: none;"<? } ?>>
			<p>Click "Add Item" to add an item to this list.</p>
		</section>
		<ul>
			<?
				$x = 0;
				foreach ($data["groups"] as $id) {
					$group = $admin->getCalloutGroup($id);
			?>
			<li>
				<input type="hidden" name="groups[<?=$x?>]" value="<?=$group["id"]?>" />
				<p><?=$group["name"]?></p>
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
					foreach ($groups as $group) {
						if (!in_array($group["id"],$data["groups"])) {
				?>
				<option value="<?=$group["id"]?>"><?=$group["name"]?></option>
				<?
						}
					}
				?>
			</select>
			<a href="#" class="add button"><span class="icon_small icon_small_add"></span>Add Group</a>
		</footer>
	</div>
</fieldset>
<fieldset>
	<label>Noun <small>(defaults to "Callout")</small></label>
	<input type="text" name="noun" value="<?=htmlspecialchars($data["noun"])?>" />
</fieldset>
<fieldset>
	<label>Maximum Entries <small>(defaults to unlimited)</small></label>
	<input type="text" name="max" value="<?=$data["max"]?>" />
</fieldset>
<script>
	BigTreeManyToMany({
		id: "callout_groups",
		count: <?=$x?>,
		key: "groups"
	});
</script>