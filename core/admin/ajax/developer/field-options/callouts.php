<?php
	namespace BigTree;
	
	/**
	 * @global array $options
	 */

	$groups = CalloutGroup::all();

	// Stop notices
	$options["groups"] = is_array($options["groups"]) ? $options["groups"] : array();
	$options["verb"] = isset($options["verb"]) ? $options["verb"] : "";
	$options["max"] = $options["max"] ? intval($options["max"]) : "";

	// Work with older group info from 4.1 and lower
	if (!array_filter($options["groups"]) && $options["group"]) {
		$options["groups"] = array($options["group"]);
	}
?>
<fieldset>
	<label for="options_field_groups"><?=Text::translate("Groups <small>(if you don't choose at least one group, all callouts will be available)</small>")?></label>
	<div class="multi_widget many_to_many" id="callout_groups">
		<section<?php if (count($options["groups"])) { ?> style="display: none;"<?php } ?>>
			<p><?=Text::translate('Click "Add Item" to add an item to this list.')?></p>
		</section>
		<ul>
			<?php
				$x = 0;
				foreach ($options["groups"] as $id) {
					$group = new CalloutGroup($id);
			?>
			<li>
				<input type="hidden" name="groups[<?=$x?>]" value="<?=$group->ID?>" />
				<p><?=$group->Name?></p>
				<a href="#" class="icon_delete"></a>
			</li>
			<?php
					$x++;
				}
			?>
		</ul>
		<footer>
			<select id="options_field_groups">
				<?php
					foreach ($groups as $group) {
						if (!in_array($group->ID,$options["groups"])) {
				?>
				<option value="<?=$group->ID?>"><?=$group->Name?></option>
				<?php
						}
					}
				?>
			</select>
			<a href="#" class="add button"><span class="icon_small icon_small_add"></span><?=Text::translate("Add Group")?></a>
		</footer>
	</div>
</fieldset>

<fieldset>
	<label for="options_field_noun"><?=Text::translate('Noun <small>(defaults to "Callout")</small>')?></label>
	<input id="options_field_noun" type="text" name="noun" value="<?=htmlspecialchars($options["noun"])?>" />
</fieldset>

<fieldset>
	<label for="options_field_max"><?=Text::translate("Maximum Entries <small>(defaults to unlimited)</small>")?></label>
	<input id="options_field_max" type="text" name="max" value="<?=$options["max"]?>" />
</fieldset>

<script>
	BigTreeManyToMany({
		id: "callout_groups",
		count: <?=$x?>,
		key: "groups"
	});
</script>