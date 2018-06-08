<div class="file_browser_images">
	<?php
		if ($_POST["query"]) {
			$items = $admin->searchResources($_POST["query"]);
			$perm = "e";
			$bc = array(array("name" => "Clear Results","id" => ""));
		} else {
			$perm = $admin->getResourceFolderPermission($_POST["folder"]);
			$items = $admin->getContentsOfResourceFolder($_POST["folder"]);
			$bc = $admin->getResourceFolderBreadcrumb($_POST["folder"]);
		}

		if (!$_POST["query"] && $_POST["folder"] > 0) {
			$folder = $admin->getResourceFolder($_POST["folder"]);
	?>
	<a href="#<?=$folder["parent"]?>" class="file folder"><span class="file_type file_type_folder file_type_folder_back"></span> Back</a>
	<?php
		}

		$minWidth = $_POST["minWidth"];
		$minHeight = $_POST["minHeight"];

		foreach ($items["folders"] as $folder) {
	?>
	<a href="#<?=$folder["id"]?>" class="file folder">
		<span class="file_type file_type_folder"></span>
		<?=$folder["name"]?>
	</a>
	<?php
		}

		foreach ($items["resources"] as $resource) {
			if ($resource["is_image"]) {
				$disabled = (($minWidth && $minWidth !== "false" && $resource["width"] < $minWidth) || ($minHeight && $minHeight !== "false" && $resource["height"] < $minHeight)) ? " disabled" : "";

				$data = htmlspecialchars(json_encode(array(
					"file" => $resource["file"],
					"thumbs" => $resource["thumbs"],
					"crops" => $resource["crops"]
				)));
	?>
	<a href="<?=$data?>" class="image<?=$disabled?>"><img src="<?=BigTree::prefixFile($resource["file"], "list-preview/").$thumb.($_COOKIE["bigtree_admin"]["recently_replaced_file"] ? "?".uniqid() : "")?>" alt="" /></a>
	<?php
			}
		}

		// Make sure the breadcrumb is at most 5 pieces
		$cut_breadcrumb = array_slice($bc,-5,5);

		if (count($cut_breadcrumb) < count($bc)) {
			$cut_breadcrumb = array_merge(array(array("id" => 0,"name" => "&hellip;")),$cut_breadcrumb);
		}

		$crumb_contents = "";

		foreach ($cut_breadcrumb as $crumb) {
			$crumb_contents .= '<li><a href="#'.$crumb["id"].'" title="'.$crumb["name"].'">'.$crumb["name"].'</a></li>';
		}
	?>
</div>
<script>
	<?php if ($_POST["query"]) { ?>
	BigTreeFileManager.setTitleSuffix(": Search Results");
	<?php } else { ?>
	BigTreeFileManager.setTitleSuffix("");
	<?php } ?>
	BigTreeFileManager.setBreadcrumb("<?=str_replace('"','\"',$crumb_contents)?>");
</script>