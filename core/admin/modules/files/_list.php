<?php
	namespace BigTree;
	
	/**
	 * @global ResourceFolder $folder
	 * @global array $permission
	 * @global array $contents
	 */
	
	$text_name = Text::translate("Name");
	$text_type = Text::translate("Type");
	$text_size = Text::translate("Size");
	$text_folder = Text::translate("folder");
	
	if ($permission == "p") {
		$bigtree["custom_subnav"] = [
			["link" => "files/add/image/".$folder->ID, "title" => "Add Images", "icon" => "picture"],
			["link" => "files/add/file/".$folder->ID, "title" => "Add Files", "icon" => "file_default"],
			["link" => "files/add/video/".$folder->ID, "title" => "Add Video", "icon" => "video"],
			["link" => "files/add/folder/".$folder->ID, "title" => "Add Folder", "icon" => "add"],
		];
	}
?>
<div class="file_manager_wrapper">
	<div class="table">
		<summary>
			<input class="form_search" id="js-file-manager-search" placeholder="Search" value="" type="search">
			<span class="form_search_icon"></span>
		</summary>

		<header>
			<span class="view_column file_manager_column_icon"></span>
			<span class="view_column file_manager_column_name"><?=$text_name?></span>
			<span class="view_column file_manager_column_type"><?=$text_type?></span>
			<span class="view_column file_manager_column_size"><?=$text_size?></span>
			<span class="view_action"></span>
			<span class="view_action"></span>
		</header>

		<ul id="js-file-manager-results">
			<?php
				foreach ($contents["folders"] as $sub_folder) {
					$folder_obj = new ResourceFolder($sub_folder["id"]);
			?>
			<li>
				<section class="view_column file_manager_column_icon">
					<span class="icon_folder"></span>
				</section>
				<section class="view_column file_manager_column_name">
					<a href="<?=ADMIN_ROOT?>files/folder/<?=$sub_folder["id"]?>/"><?=$sub_folder["name"]?></a>
				</section>
				<section class="view_column file_manager_column_type">
					<?=$text_folder?>
				</section>
				<section class="view_column file_manager_column_size">
					&mdash;
				</section>
				<section class="view_action">
					<a href="<?=ADMIN_ROOT?>files/edit/folder/<?=$sub_folder["id"]?>/" class="icon_edit<?php if ($folder_obj->UserAccessLevel != "p") { ?> disabled_icon<?php } ?>"></a>
				</section>
				<section class="view_action">
					<a href="<?=ADMIN_ROOT?>files/delete/folder/<?=$sub_folder["id"]?>/" class="icon_delete<?php if (!Auth::user()->Level) { ?> disabled_icon<?php } ?>"></a>
				</section>
			</li>
			<?php
				}

				foreach ($contents["resources"] as $resource) {
			?>
			<li<?php if (!$permission || $permission == "n") { ?> class="disabled"<?php } ?>>
				<section class="view_column file_manager_column_icon">
					<?php
						if ($resource["is_image"]) {
					?>
					<img src="<?=FileSystem::getPrefixedFile(Link::detokenize($resource["file"]), "list-preview/")."?".strtotime($resource["file_last_updated"])?>" alt="">
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
						if (!$permission || $permission == "n") {
							echo $resource["name"];
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
							echo Storage::formatBytes($resource["size"]);
						} else {
							echo "&mdash;";
						}
					?>
				</section>
				<section class="view_action">
					<a href="<?=ADMIN_ROOT?>files/edit/file/<?=$resource["id"]?>/" class="icon_edit<?php if ($permission != "p") { ?> disabled_icon<?php } ?>"></a>
				</section>
				<section class="view_action">
					<a href="<?=ADMIN_ROOT?>files/delete/file/<?=$resource["id"]?>/" class="icon_delete<?php if ($permission != "p") { ?> disabled_icon<?php } ?>"></a>
				</section>
			</li>
			<?php
				}
			?>
		</ul>
	</div>
</div>

<script>
	(function() {
		var DefaultContent;
		var SearchTimer;
		var QueryField = $("#js-file-manager-search");

		QueryField.keyup(function() {
			clearTimeout(SearchTimer);
			SearchTimer = setTimeout(search, 300);
		});

		function search() {
			if (!DefaultContent) {
				DefaultContent = $("#js-file-manager-results").html();
			}

			if (QueryField.val()) {
				$("#js-file-manager-results").load("<?=ADMIN_ROOT?>ajax/files/search/", { query: QueryField.val() });
			} else {
				$("#js-file-manager-results").html(DefaultContent);
			}
		}
	})();
</script>