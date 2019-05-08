<?php
	namespace BigTree;

	if ($_POST["groups"]) {
		$callout_list = Callout::allInGroups($_POST["groups"]);
	} else {
		$callout_list = Callout::allAllowed("name ASC");
	}

	$callout_count = intval($_POST["count"]);
	$callout_key = htmlspecialchars($_POST["key"]);
	$content = json_decode(base64_decode($_POST["data"]),true);
?>
<div class="callout_type">
	<fieldset>
		<label for="callout_type"><?=Text::translate("Callout Type")?></label>
		<select id="callout_type" name="<?=$callout_key?>[<?=$callout_count?>][type]">
			<?php foreach ($callout_list as $item) { ?>
			<option value="<?=$item->ID?>"<?php if ($item->ID == $content["type"]) { ?> selected="selected"<?php } ?>><?=$item->Name?></option>
			<?php } ?>
		</select>
	</fieldset>
</div>
<div class="callout_fields">
	<?php include Router::getIncludePath("admin/ajax/callouts/resources.php") ?>
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
				count: <?=$callout_count?>,
				key: "<?=$callout_key?>",
				resources: "<?=htmlspecialchars($_POST["data"])?>",
				type: data.value,
				original_type: "<?=Text::htmlEncode($_POST["original_type"])?>",
				tab_depth: <?=intval($_POST["tab_depth"])?>
			}, BigTreeCustomControls).scrollTop(0);
		});
	})();
</script>