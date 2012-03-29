<?
	include BigTree::path("admin/auto-modules/_setup.php");
	$view = BigTreeAutoModule::getView($action["view"]);
	
	// Setup the preview action if we have a preview URL and field.
	if ($view["preview_url"]) {
		$view["actions"]["preview"] = "on";
	}
?>
<h1>
	<span class="modules"></span><?=$view["title"]?>
	<? if (count($subnav)) { ?>
	<div class="jump_group">
		<span class="icon"></span>
		<div class="dropdown">
			<strong><?=$mgroup["name"]?></strong>
			<? foreach ($subnav as $link) { ?>
			<a href="<?=$admin_root?><?=$link["link"]?>"><?=$link["title"]?></a>
			<? } ?>
		</div>
	</div>
	<? } ?>
</h1>
<?
	include BigTree::path("admin/auto-modules/_nav.php");

	if ($view["description"]) {
		echo "<p>".$view["description"]."</p>";
	}

	$maction = $action;

	$action_names = array(
		"approve" => "Approve/Deny",
		"edit" => "Edit",
		"delete" => "Delete",
		"archive" => "Archive/Unarchive",
		"featured" => "Featured"
	);
	
	include BigTree::path("admin/auto-modules/views/".$view["type"].".php");

	$action = $maction;
?>
