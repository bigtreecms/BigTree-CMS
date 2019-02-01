<?php
	$results = $admin->searchResources($_POST["query"]);

	foreach ($results["folders"] as $folder) {
?>
<li>
	<section class="view_column file_manager_column_icon">
		<span class="icon_folder"></span>
	</section>
	<section class="view_column file_manager_column_name">
		<a href="<?=ADMIN_ROOT?>files/folder/<?=$folder["id"]?>/"><?=$folder["name"]?></a>
	</section>
	<section class="view_column file_manager_column_type">
		Folder
	</section>
	<section class="view_column file_manager_column_size">
		&mdash;
	</section>
	<section class="view_action">
		<a href="<?=ADMIN_ROOT?>files/edit/folder/<?=$folder["id"]?>/" class="icon_edit"></a>
	</section>
	<section class="view_action">
		<a href="<?=ADMIN_ROOT?>files/delete/folder/<?=$folder["id"]?>/" class="icon_delete<?php if (!$admin->Level) { ?> disabled_icon<?php } ?>"></a>
	</section>
</li>
<?php
	}

	foreach ($results["resources"] as $resource) {
		if ($resource["permission"] == "n") {
			continue;
		}
?>
<li<?php if ($resource["permission"] == "n") { ?> class="disabled"<?php } ?>>
	<section class="view_column file_manager_column_icon">
		<?php
			if ($resource["is_image"]) {
		?>
		<img src="<?=BigTree::prefixFile(BigTreeCMS::replaceRelativeRoots($resource["file"]), "list-preview/")?>" alt="">
		<?php
			} elseif ($resource["is_video"]) {
		?>
		<span class="icon_large icon_large_<?=strtolower($resource["location"])?>"></span>
		<?php
			} else {
		?>
		<span class="icon_file_default icon_file_<?=$resource["type"]?>"></span>
		<?php
			}
		?>
	</section>
	<section class="view_column file_manager_column_name">
		<?php
			if ($resource["permission"] != "p") {
		?>
		<a href="<?=$resource["file"]?>" target="_blank"><?=$resource["name"]?></a>
		<?php
			} else {
		?>
		<a href="<?=ADMIN_ROOT?>files/edit/file/<?=$resource["id"]?>/"><?=$resource["name"]?></a>
		<?php
			}
		?>
	</section>
	<section class="view_column file_manager_column_type">
		<?=($resource["mimetype"] ?: $resource["type"])?>
	</section>
	<section class="view_column file_manager_column_size">
		<?php
			if ($resource["width"] && $resource["height"]) {
				echo $resource["width"]." x ".$resource["height"];
			} elseif ($resource["size"]) {
				echo BigTree::formatBytes($resource["size"]);
			} else {
				echo "&mdash;";
			}
		?>
	</section>
	<section class="view_action">
		<a href="<?=ADMIN_ROOT?>files/edit/file/<?=$resource["id"]?>/" class="icon_edit<?php if ($resource["permission"] != "p") { ?> disabled_icon<?php } ?>"></a>
	</section>
	<section class="view_action">
		<a href="<?=ADMIN_ROOT?>files/delete/file/<?=$resource["id"]?>/" class="icon_delete<?php if ($resource["permission"] != "p") { ?> disabled_icon<?php } ?>"></a>
	</section>
</li>
<?php
	}
