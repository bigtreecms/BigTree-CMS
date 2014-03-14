<?
	$id = $_GET["module"];
	$table = isset($_GET["table"]) ? $_GET["table"] : "";
	$form = array("title" => "","table" => "","tagging" => "","return_view" => "","return_url" => "","hooks" => array());

	$module = $admin->getModule($id);

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

	$title = htmlspecialchars(urldecode($title));
?>
<div class="container">
	<form method="post" action="<?=DEVELOPER_ROOT?>modules/forms/create/<?=$module["id"]?>/" class="module">
		<? include BigTree::path("admin/modules/developer/modules/forms/_form.php") ?>
		<footer>
			<input type="submit" class="button blue" value="Create" />
		</footer>
	</form>
</div>