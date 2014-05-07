<?
	// Setup the preview action if we have a preview URL and field.
	if ($bigtree["view"]["preview_url"]) {
		$bigtree["view"]["actions"]["preview"] = "on";
	}
?>
<div class="table auto_modules">
	<summary>
		<h2>Filtered Data</h2>
	</summary>
	<header>
		<?
			$x = 0;
			foreach ($bigtree["view"]["fields"] as $key => $field) {
				$x++;
		?>
		<span class="view_column" style="width: <?=$field["width"]?>px;"><?=$field["title"]?></span>
		<?
			}
		?>
		<span class="view_status">Status</span>		
		<span class="view_action" style="width: <?=(count($bigtree["view"]["actions"]) * 40)?>px;"><? if (count($bigtree["view"]["actions"]) > 1) { ?>Actions<? } ?></span>
	</header>
	<ul id="sort_table">
		<?
			foreach ($items as $item) {
				// Get the status
				if (BigTreeAutoModule::changeExists($bigtree["view"]["table"],$item["id"])) {
					$status_class = "pending";
					$status = "Changed";
				} else {
					$status_class = "published";
					$status = "Published";
				}
				$item_permission = $admin->getAccessLevel($bigtree["module"],$item,$bigtree["form"]["table"]);
				if ($item_permission && $item_permission != "n") {
		?>
		<li id="row_<?=$item["id"]?>" class="<?=$status_class?>">
			<? foreach ($bigtree["view"]["fields"] as $key => $field) { ?>
			<section class="view_column" style="width: <?=$field["width"]?>px;"><?=$item[$key]?></section>
			<? } ?>
			<section class="view_status status_<?=$status_class?>"><?=$status?></section>
			<?
				foreach ($bigtree["view"]["actions"] as $action => $data) {
					if ($data == "on") {
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
						} elseif ($action == "edit") {
							$link = $bigtree["view"]["edit_url"].$item["id"]."/";
						} else {
							$link = "#".$item["id"];
						}
			?>
			<section class="view_action action_<?=$action?>"><a href="<?=$link?>" class="<?=$class?>" title="<?=ucwords($action)?>"></a></section>
			<?
					} else {
						$data = json_decode($data,true);
						$link = MODULE_ROOT.$data["route"]."/".$item["id"]."/";
						if ($data["function"]) {
							$link = call_user_func($data["function"],$item);
						}
			?>
			<section class="view_action"><a href="<?=$link?>" class="<?=$data["class"]?>" title="<?=BigTree::safeEncode($data["name"])?>"></a></section>
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
</div>
<? include BigTree::path("admin/auto-modules/views/_common-js.php") ?>