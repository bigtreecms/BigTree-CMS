<?
	$breadcrumb[] = array("title" => "Pending Changes", "link" => "#");

	// Get pending changes.
	$changes = $admin->getPendingChanges();
	
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
?>
<h1>
	<span class="pending"></span>Pending Changes
	<? include BigTree::path("admin/modules/dashboard/_nav.php") ?>
</h1>
<?
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
		<span class="changes_action">Preview</a></span>
		<span class="changes_action">Edit</a></span>
		<span class="changes_action">Approve</span>
		<span class="changes_action">Deny</span>
	</header>
	<ul>
		<?
			foreach ($pages as $change) {
				if ($change["item_id"]) {
					$page = $cms->getPendingPage($change["item_id"]);
					$preview_link = WWW_ROOT."_preview/".$page["path"]."/";
					$edit_link = ADMIN_ROOT."pages/edit/".$change["item_id"]."/";
				} else {
					$page = $cms->getPendingPage("p".$change["id"]);
					$preview_link = WWW_ROOT."_preview-pending/".$change["id"]."/";
					$edit_link = ADMIN_ROOT."pages/edit/p".$change["id"]."/";
				}
		?>
		<li>
			<section class="changes_author"><?=$change["user"]["name"]?></section>
			<section class="changes_page"><?=$page["nav_title"]?></section>
			<section class="changes_action"><a href="<?=$preview_link?>" target="_preview" class="icon_preview"></a></section>
			<section class="changes_action"><a href="<?=$edit_link?>" class="icon_edit"></a></section>
			<section class="changes_action"><a href="#<?=$change["id"]?>" class="icon_approve icon_approve_on"></a></section>
			<section class="changes_action"><a href="#<?=$change["id"]?>" class="icon_deny"></a></section>
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
		$edit_link = ADMIN_ROOT.$mod["route"]."/edit";
		if ($view["suffix"]) {
			$edit_link .= "-$suffix";
		}
		$edit_link .= "/";
?>
<a name="<?=$mod["id"]?>"></a>
<div class="table">
	<summary>
		<h2 class="full">
			<span class="modules"></span>
			<?=$mod["name"]?>
		</h2>
	</summary>
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
					$item = BigTreeAutoModule::getPendingItem($change["table"],$change["item_id"]);
					$item = $item["item"];
				} else {
					$item = json_decode($change["changes"],true);
					$item["id"] = "p".$change["id"];
				}
		?>
		<li>
			<section class="changes_author"><?=$change["user"]["name"]?></section>
			<?
				if (is_array($view["fields"])) {
					foreach ($view["fields"] as $field => $data) {
			?>
			<section class="view_column" style="width: <?=$data["width"]?>px">
				<?=$item[$field]?>
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
			<section class="changes_action"><a href="<?=$edit_link.$item["id"]?>/" class="icon_edit"></a></section>
			<section class="changes_action"><a href="#<?=$change["id"]?>" class="icon_approve icon_approve_on"></a></section>
			<section class="changes_action"><a href="#<?=$change["id"]?>" class="icon_deny"></a></section>
		</li>
		<?
			}
		?>
	</ul>
</div>
<?
	}
?>

<script type="text/javascript">
	$(".icon_approve").click(function() {
		$.ajax("<?=ADMIN_ROOT?>ajax/dashboard/approve-change/", { data: { id: $(this).attr("href").substr(1) }, type: "POST" });
		$(this).parents("li").remove();
		BigTree.growl("Pending Changes","Approved Change");
		return false;
	});
	
	$(".icon_deny").click(function() {
		$.ajax("<?=ADMIN_ROOT?>ajax/dashboard/reject-change/", { data: { id: $(this).attr("href").substr(1) }, type: "POST" });
		$(this).parents("li").remove();
		BigTree.growl("Pending Changes","Rejected Change");
		return false;
	});
</script>