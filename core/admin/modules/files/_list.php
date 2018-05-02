<?php
	if ($permission == "p") {
		$bigtree["custom_subnav"] = [
			["link" => "files/add/image/".$folder["id"], "title" => "Add Images", "icon" => "picture"],
			["link" => "files/add/file/".$folder["id"], "title" => "Add Files", "icon" => "file_default"],
			["link" => "files/add/video/".$folder["id"], "title" => "Add Video", "icon" => "video"],
			["link" => "files/add/folder/".$folder["id"], "title" => "Add Folder", "icon" => "add"],
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
			<span class="view_column file_manager_column_name">Name</span>
			<span class="view_column file_manager_column_type">Type</span>
			<span class="view_column file_manager_column_size">Size</span>
			<span class="view_action"></span>
		</header>

		<ul id="js-file-manager-results">
			<?php
				foreach ($contents["folders"] as $folder) {
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
			</li>
			<?php
				}

				foreach ($contents["resources"] as $resource) {
			?>
			<li<?php if ($permission == "n") { ?> class="disabled"<?php } ?>>
				<section class="view_column file_manager_column_icon">
					<?php
						if ($resource["is_image"]) {
							$thumbs = json_decode($resource["thumbs"], true);
					?>
					<img src="<?=$thumbs["bigtree_internal_list"]?>" alt="">
					<?php
						} else {
					?>
					<span class="icon_small icon_small_file icon_small_file_<?=$resource["type"]?>"></span>
					<?php
						}
					?>
				</section>
				<section class="view_column file_manager_column_name">
					<?php
						if ($permission == "n") {
							echo $resource["name"];
						} else {
					?>
					<a href="<?=ADMIN_ROOT?>files/meta/<?=$resource["id"]?>/"><?=$resource["name"]?></a>
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

				</section>
			</li>
			<?php
				}
			?>
		</ul>
	</div>
</div>