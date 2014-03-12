<?
	// Get pending changes awaiting this user's approval.
	$changes = $admin->getPublishableChanges();
	
	// Go through and get all the modules and pages, separate them out.
	$modules = array();
	$pages = array();
	
	foreach ($changes as $change) {
		$mid = $change["mod"]["id"];
		if ($change["table"] == "bigtree_pages") {
			$pages[] = $change;
		} else {
			if (isset($modules[$mid])) {
				$modules[$mid]["changes"][] = $change;
			} else {
				$modules[$mid] = $change["mod"];
				$modules[$mid]["table"] = $change["table"];
				$modules[$mid]["changes"] = array($change);		
			}
		}
	}

	if (!count($changes)) {
?>
<div class="container">
	<section>
		<p>You have no changes awaiting your approval.</p>
	</section>
</div>
<?
	}

	if (count($pages)) {
?>
<a name="0"></a>
<div class="table">
	<summary>
		<h2 class="full">
			<span class="pages"></span>
			Pages
		</h2>
	</summary>
	<header>
		<span class="changes_author">Author</span>
		<span class="changes_page">Page</span>
		<span class="changes_type">Type</span>
		<span class="changes_time">Updated</span>
		<span class="changes_action">Preview</a></span>
		<span class="changes_action">Edit</a></span>
		<span class="changes_action">Approve</span>
		<span class="changes_action">Deny</span>
	</header>
	<ul>
		<?
			foreach ($pages as $change) {
				if (is_numeric($change["item_id"])) {
					$page = $cms->getPendingPage($change["item_id"]);
					$preview_link = WWW_ROOT."_preview/".$page["path"]."/";
					$edit_link = ADMIN_ROOT."pages/edit/".$change["item_id"]."/";
					if (!$change["item_id"]) {
						$page["nav_title"] = "Home";
					}
				} else {
					$page = $cms->getPendingPage("p".$change["id"]);
					$preview_link = WWW_ROOT."_preview-pending/p".$change["id"]."/";
					$edit_link = ADMIN_ROOT."pages/edit/p".$change["id"]."/";
				}
		?>
		<li>
			<section class="changes_author"><?=$change["user"]["name"]?></section>
			<section class="changes_page"><?=$page["nav_title"]?></section>
			<section class="changes_type"><? if (is_numeric($change["item_id"])) { ?>EDIT<? } else { ?><span class="new">NEW</span><? } ?></section>
			<section class="changes_time"><?=BigTree::relativeTime($change["date"])?></section>
			<section class="changes_action"><a href="<?=$preview_link?>" target="_preview" class="icon_preview"></a></section>
			<section class="changes_action"><a href="<?=$edit_link?>" class="icon_edit"></a></section>
			<section class="changes_action"><a href="#<?=$change["id"]?>" data-module="Pages" class="icon_approve icon_approve_on"></a></section>
			<section class="changes_action"><a href="#<?=$change["id"]?>" data-module="Pages" class="icon_deny"></a></section>
		</li>
		<?		
			}
		?>
	</ul>
</div>
<?
	}
	
	foreach ($modules as $mod) {
		$view = BigTreeAutoModule::getViewForTable($mod["table"]);
		if ($view) {
			$view_data = BigTreeAutoModule::getViewData($view);
		} else {
			$view_data = false;
		}
?>
<a name="<?=$mod["id"]?>"></a>
<div class="table">
	<summary>
		<h2 class="full">
			<span class="modules"></span>
			<?=$mod["name"]?>
		</h2>
	</summary>
	<? if ($view["type"] == "images" || $view["type"] == "images-grouped") { ?>
	<section>
		<ul class="image_list">
			<?
				foreach ($mod["changes"] as $change) {
					if ($view_data) {
						if ($change["item_id"]) {
							$item = $view_data[$change["item_id"]];
						} else {
							$item = $view_data["p".$change["id"]];
						}
					} else {
						$item = array("id" => $change["item_id"] ? $change["item_id"] : "p".$change["id"]);
					}
					$image = str_replace(array("{staticroot}","{wwwroot}"),array(STATIC_ROOT,WWW_ROOT),$item["column1"]);
					if ($view["options"]["prefix"]) {
						$image = BigTree::prefixFile($image,$view["options"]["prefix"]);
					}
			?>
			<li class="non_draggable">
				<p><?=$change["user"]["name"]?></p>
				<a class="image" href="<?=$view["edit_url"].$item["id"]?>/"><img src="<?=$image?>" alt="" /></a>
				<? if ($view["preview_url"]) { ?>
				<a href="<?=rtrim($view["preview_url"],"/")."/".$item["id"]."/"?>" target="_preview" class="icon_preview"></a>
				<? } ?>
				<a href="#<?=$change["id"]?>" data-module="<?=$mod["name"]?>" class="icon_approve icon_approve_on"></a>
				<a href="#<?=$change["id"]?>" data-module="<?=$mod["name"]?>" class="icon_deny"></a>
			</li>
			<?
				}
			?>
		</ul>
	</section>
	<? } else { ?>
	<header>
		<span class="changes_author">Author</span>
		<?
			if (is_array($view["fields"])) {
				foreach ($view["fields"] as $field) {
		?>
		<span class="view_column" style="width: <?=$field["width"]?>px;"><?=$field["title"]?></span>
		<?
				}
			}
			
			if ($view["preview_url"]) {
		?>
		<span class="changes_action">Preview</a></span>
		<?
			}
		?>
		<span class="changes_action">Edit</a></span>
		<span class="changes_action">Approve</span>
		<span class="changes_action">Deny</span>
	</header>
	<ul>
		<?
			foreach ($mod["changes"] as $change) {
				if ($change["item_id"]) {
					$item = $view_data[$change["item_id"]];
				} else {
					$item = $view_data["p".$change["id"]];
				}
		?>
		<li>
			<section class="changes_author"><?=$change["user"]["name"]?></section>
			<?
				if (is_array($view["fields"])) {
					$x = 0;
					foreach ($view["fields"] as $field => $data) {
						$x++;
			?>
			<section class="view_column" style="width: <?=$data["width"]?>px;">
				<?=$item["column$x"]?>
			</section>
			<?
					}
				}
					
				if ($view["preview_url"]) {
			?>
			<section class="changes_action"><a href="<?=rtrim($view["preview_url"],"/")."/".$item["id"]."/"?>" target="_preview" class="icon_preview"></a></section>
			<?
				}
			?>
			<section class="changes_action"><a href="<?=$view["edit_url"].$item["id"]?>/" class="icon_edit"></a></section>
			<section class="changes_action"><a href="#<?=$change["id"]?>" data-module="<?=$mod["name"]?>" class="icon_approve icon_approve_on"></a></section>
			<section class="changes_action"><a href="#<?=$change["id"]?>" data-module="<?=$mod["name"]?>" class="icon_deny"></a></section>
		</li>
		<?
			}
		?>
	</ul>
	<? } ?>
</div>
<?
	}
?>

<script>
	$(".icon_approve").click(function() {
		$.ajax("<?=ADMIN_ROOT?>ajax/dashboard/approve-change/", { data: { id: $(this).attr("href").substr(1) }, type: "POST" });
		$(this).parents("li").remove();
		BigTree.Growl($(this).attr("data-module"),"Approved Change");
		return false;
	});
	
	$(".icon_deny").click(function() {
		$.ajax("<?=ADMIN_ROOT?>ajax/dashboard/reject-change/", { data: { id: $(this).attr("href").substr(1) }, type: "POST" });
		$(this).parents("li").remove();
		BigTree.Growl($(this).attr("data-module"),"Rejected Change");
		return false;
	});
</script>