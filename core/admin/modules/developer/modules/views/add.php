<?php
	namespace BigTree;
	
	$module = new Module($_GET["module"]);
	$table = isset($_GET["table"]) ? $_GET["table"] : "";
	$title = isset($_GET["title"]) ? htmlspecialchars($_GET["title"]) : "";

	// See if we can default to positioned
	$is_positioned = false;

	if ($table) {
		$table_description = SQL::describeTable($table);

		if (isset($table_description["columns"]["position"]) && $table_description["columns"]["position"]["type"] == "int") {
			$is_positioned = true;
		}
	}

	if (isset($_SESSION["bigtree_admin"]["developer"]["saved_view"])) {
		Globalize::arrayObject($_SESSION["bigtree_admin"]["developer"]["saved_view"], array("htmlspecialchars"));
		unset($_SESSION["bigtree_admin"]["developer"]["saved_view"]);
	} else {
		// Stop notices
		$description = $type = $preview_url = "";
		$settings = array();

		if ($is_positioned) {
			$type = "draggable";
		}
	}
?>
<div class="container">

	<form method="post" action="<?=DEVELOPER_ROOT?>modules/views/create/<?=$module->ID?>/" class="module">
		<?php
			if (isset($_GET["new_module"]) || isset($new_module)) {
		?>
		<input type="hidden" name="new_module" value="true" />
		<?php
			}
			
			include Router::getIncludePath("admin/modules/developer/modules/views/_form.php");
		?>
		<footer>
			<input type="submit" class="button blue" value="<?=Text::translate("Create", true)?>" />
		</footer>
	</form>
</div>

<?php include Router::getIncludePath("admin/modules/developer/modules/views/_js.php") ?>