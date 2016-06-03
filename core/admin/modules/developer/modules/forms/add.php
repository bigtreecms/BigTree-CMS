<?php
	namespace BigTree;

	// Try to make sense of a plural title into singular
	if (isset($_GET["title"])) {
		$title = $_GET["title"];
		if (substr($title,-3,3) == "ies") {
			$title = substr($title,0,-3)."y";
		} else {
			$title = rtrim($title,"s");
		}
		if (strtolower($_GET["title"]) == "news") {
			$title = $_GET["title"];
		}
	} else {
		$title = "";
	}

	$form = new ModuleForm(array(
		"table" => isset($_GET["table"]) ? $_GET["table"] : "",
		"title" => $title
	));
	$module = new Module($_GET["module"]);

?>
<div class="container">
	<form method="post" action="<?=DEVELOPER_ROOT?>modules/forms/create/<?=$module->ID?>/" class="module">
		<?php include Router::getIncludePath("admin/modules/developer/modules/forms/_form.php") ?>
		<footer>
			<input type="submit" class="button blue" value="<?=Text::translate("Create", true)?>" />
		</footer>
	</form>
</div>