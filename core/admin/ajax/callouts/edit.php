<?php
	if ($_POST["groups"]) {
		$items = $admin->getCalloutsInGroups($_POST["groups"]);
	} else {
		$items = $admin->getCalloutsAllowed("name ASC");
	}

	$bigtree["callout_count"] = intval($_POST["count"]);
	$bigtree["callout_key"] = htmlspecialchars($_POST["key"]);
	$bigtree["resources"] = json_decode(base64_decode($_POST["data"]),true);

	if (!empty($_POST["front_end_editor"]) && $_POST["front_end_editor"] != "false") {
		define("BIGTREE_FRONT_END_EDITOR", true);
	}
?>
<div class="callout_type">
	<fieldset>
		<label>Callout Type</label>
		<select name="<?=$bigtree["callout_key"]?>[<?=$bigtree["callout_count"]?>][type]">
			<?php foreach ($items as $item) { ?>
			<option value="<?=$item["id"]?>"<?php if ($item["id"] == $bigtree["resources"]["type"]) { ?> selected="selected"<?php } ?>><?=$item["name"]?></option>
			<?php } ?>
		</select>
	</fieldset>
</div>
<div class="callout_fields">
	<?php include BigTree::path("admin/ajax/callouts/resources.php"); ?>
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
				count: <?=$bigtree["callout_count"]?>,
				key: "<?=$bigtree["callout_key"]?>",
				resources: "<?=htmlspecialchars($_POST["data"])?>",
				type: data.value,
				tab_depth: <?=intval($_POST["tab_depth"])?>,
				front_end_editor: <?=(defined("BIGTREE_FRONT_END_EDITOR") ? "true" : "false")?>,
				original_type: "<?=BigTree::safeEncode($_POST["original_type"])?>"
			}, BigTreeCustomControls).scrollTop(0);
		});
	})();
</script>