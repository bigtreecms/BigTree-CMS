<?
	// Setup the preview action if we have a preview URL and field.
	if ($bigtree["view"]["preview_url"]) {
		$bigtree["view"]["actions"]["preview"] = "on";
	}
?>
<div class="table auto_modules image_list">
	<summary>
		<h2>Filtered Data</h2>
	</summary>
	<section>
		<ul id="image_list">
			<?
				foreach ($items as $item) {
					if ($prefix) {
						$preview_image = BigTree::prefixFile($item[$bigtree["view"]["options"]["image"]],$prefix);
					} else {
						$preview_image = $item[$bigtree["view"]["options"]["image"]];
					}
					$item_permission = $admin->getAccessLevel($bigtree["module"],$item,$bigtree["form"]["table"]);

					if ($item_permission && $item_permission != "n") {
			?>
			<li id="row_<?=$item["id"]?>" class="non_draggable">
				<a class="image<? if (!isset($bigtree["view"]["actions"]["edit"])) { ?> image_disabled<? } ?>" href="<?=$bigtree["view"]["edit_url"].$item["id"]?>/"><img src="<?=$preview_image?>" alt="" /></a>
				<?
					foreach ($bigtree["view"]["actions"] as $action => $data) {
						if ($action != "edit") {
							if (($action == "delete" || $action == "approve" || $action == "feature" || $action == "archive") && $item_permission != "p") {
								if ($action == "delete") {
									$class = "icon_delete";
								} else {
									$class = "icon_disabled";
								}
							} else {
								$class = $admin->getActionClass($action,$item);
							}
							
							if ($action == "preview") {
								$link = rtrim($bigtree["view"]["preview_url"],"/")."/".$item["id"].'/" target="_preview';
							} else {
								$link = "#".$item["id"];
							}
							
							$action = ucwords($action);
							if ($data != "on") {
								$data = json_decode($data,true);
								$class = $data["class"];
								$link = MODULE_ROOT.$data["route"]."/".$item["id"]."/";
								if ($data["function"]) {
									$link = call_user_func($data["function"],$item);
								}
								$action = BigTree::safeEncode($data["name"]);
							}
				?>
				<a href="<?=$link?>" class="<?=$class?>" title="<?=$action?>"></a>
				<?
						}
					}
				?>
			</li>
			<?
					}
				}
			?>
		</ul>
	</section>
</div>
<? include BigTree::path("admin/auto-modules/views/_common-js.php") ?>