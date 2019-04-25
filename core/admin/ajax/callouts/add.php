<?php
	namespace BigTree;

	if ($_POST["groups"]) {
		$callout_list = Callout::allInGroups($_POST["groups"]);
	} else {
		$callout_list = Callout::allAllowed("name ASC");
	}

	$type = $callout_list[0]->ID;
	$bigtree["resources"] = ["type" => $type];
	$bigtree["callout_count"] = intval($_POST["count"]);
	$bigtree["callout_key"] = htmlspecialchars($_POST["key"]);
?>
<div class="callout_type">
	<fieldset>
		<label for="callout_type"><?=Text::translate("Callout Type")?></label>
		<?php if (count($callout_list) > 0) { ?>
		<select id="callout_type" name="<?=$bigtree["callout_key"]?>[<?=$bigtree["callout_count"]?>][type]">
			<?php foreach ($callout_list as $item) { ?>
			<option value="<?=$item->ID?>"><?=$item->Name?></option>
			<?php } ?>
		</select>
		<?php } else { ?>
		<input id="callout_type" type="text" disabled="disabled" value="No Callouts Available" />
		<?php } ?>
	</fieldset>
</div>
<div class="callout_fields">
	<?php 
		if (count($callout_list) > 0) {
			include Router::getIncludePath("admin/ajax/callouts/resources.php");
		} else {
			echo '<p class="error_message">'.Text::translate("No callouts available.").'</p>';
		}
	?>
</div>

<script>
	(function() {
		var Window = $(".bigtree_dialog_window").last();
		var Select = Window.find(".callout_type select");
		var Fields = Window.find(".callout_fields");

		Select.change(function(event,data) {
			// TinyMCE tooltips and menus sometimes get stuck
			$(".mce-tooltip, .mce-menu").remove();

			Fields.load("<?=ADMIN_ROOT?>ajax/callouts/resources/", {
				type: data.value,
				count: <?=$bigtree["callout_count"]?>,
				key: "<?=$bigtree["callout_key"]?>",
				tab_depth: <?=intval($_POST["tab_depth"])?>
			},BigTree.formHooks).scrollTop(0);
		});
	})();
</script>