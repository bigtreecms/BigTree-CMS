<?
	$ga_on = $cms->getSetting("bigtree-internal-google-analytics-profile");
	
	$parent = is_array($commands) ? end($commands) : 0;
	$page = $cms->getPage($parent,false);
	$parent_access = $admin->getPageAccessLevel($parent);
	
	// Setup the page breadcrumb
	if ($parent && $page) {

	} else {
		$breadcrumb = array(
			array("link" => "pages/", "title" => "Pages"),
			array("link" => "pages/view-tree/0/", "title" => "Home")
		);
	}
	
	function local_drawPageTree($nav,$title,$subtitle,$class,$draggable = false) {
		global $admin_root,$proot,$admin,$cms,$www_root,$ga_on,$parent_access,$parent;
?>
<div class="table">
	<summary>
		<h2><span class="<?=$class?>"></span><?=$title?><small><?=$subtitle?></small></h2>
	</summary>
	<header>
		<?
			if ($class == "archived") {
		?>
		<span class="pages_title_widest">Title</span>
		<span class="pages_restore">Restore</span>
		<span class="pages_delete">Delete</span>
		<?
			} else {
				if ($ga_on) {
		?>
		<span class="pages_title">Title</span>
		<span class="pages_views">Views</span>
		<?
				} else {
		?>
		<span class="pages_title_wider">Title</span>		
		<?
				}
		?>
		<span class="pages_status">Status</span>
		<span class="pages_archive">Archive</span>
		<span class="pages_edit">Edit</span>
		<?
			}
		?>
	</header>
	<ul id="pages_<?=$class?>">
		<?
			foreach ($nav as $item) {
				$perm = $admin->getPageAccessLevel($item["id"]);
				
				if ($item["bigtree_pending"]) {
					$status = '<a href="'.$www_root.'_preview-pending/'.$item["id"].'/" target="_blank">Pending</a>';
					$status_class = "pending";
				} elseif ($admin->getPageChanges($item["id"])) {
					$status = '<a href="'.$www_root.'_preview/'.$item["path"].'/" target="_blank">Changed</a>';
					$status_class = "pending";
				} elseif (strtotime($item["publish_at"]) > time()) {
					$status = "Scheduled";
					$status_class = "scheduled";
				} elseif ($item["expire_at"] != "" && strtotime($item["expire_at"]) < time()) {
					$status = "Expired";
					$status_class = "expired";
				} else {
					$status = "Published";
					$status_class = "published";
				}
		?>
		<li id="row_<?=$item["id"]?>" class="<?=$status_class?>">
			<section class="pages_title<? if ($class == "archived") { ?>_widest<? } elseif (!$ga_on) { ?>_wider<? } ?>">
				<? if ($parent_access == "p" && !$item["bigtree_pending"] && $draggable) { ?>
				<span class="icon_sort"></span>
				<? } ?>
				<? if ($class != "archived") { ?>
				<a href="<?=$proot?>view-tree/<?=$item["id"]?>/"><?=$item["title"]?></a>
				<? } else { ?>
				<?=$item["title"]?>				
				<? } ?>
			</section>
			<?
				if ($class == "archived") {
			?>
			<section class="pages_restore">
				<? if ($perm == "p" && $admin->canModifyChildren($item)) { ?>
				<a href="<?=$proot?>restore/<?=$item["id"]?>/" title="Restore Page" class="icon_restore"></a>
				<? } else { ?>
				<span class="icon_restore disabled_icon"></span>
				<? } ?>
			</section>
			<section class="pages_delete">
				<? if ($perm == "p" && $admin->canModifyChildren($item)) { ?>
				<a href="<?=$proot?>delete/<?=$item["id"]?>/" title="Delete Page" class="icon_delete"></a>
				<? } else { ?>
				<span class="icon_delete disabled_icon"></span>
				<? } ?>
			</section>
			<?	
				} else {
					if ($ga_on) {
			?>
			<section class="pages_views">
				<?=number_format($item["ga_page_views"])?>
			</section>
			<?
					}
			?>
			<section class="pages_status status_<?=$status_class?>">
				<?=$status?>
			</section>
			<section class="pages_archive">
				<? if (!$item["bigtree_pending"] && $perm == "p" && ($parent != 0 || $admin->Level > 1) && $admin->canModifyChildren($item)) { ?>
				<a href="<?=$proot?>archive/<?=$item["id"]?>/" title="Archive Page" class="icon_archive"></a>
				<? } elseif ($item["bigtree_pending"] && $perm == "p") { ?>
				<a href="<?=$proot?>delete/<?=$item["id"]?>/" title="Delete Pending Page" class="icon_delete"></a>
				<? } elseif ($item["bigtree_pending"]) { ?>
				<span class="icon_delete disabled_icon"></span>
				<? } else { ?>
				<span class="icon_archive disabled_icon"></span>
				<? } ?>
			</section>
			<section class="pages_edit">
				<? if ($perm) { ?>
				<a href="<?=$proot?>edit/<?=$item["id"]?>/" title="Edit Page" class="icon_edit page"></a>
				<? } else { ?>
				<span class="icon_edit disabled_icon"></span>
				<? } ?>
			</section>
			<?
				}
			?>
		</li>
		<?
			}
		?>
	</ul>
</div>
<?
		if ($draggable && $parent_access) {
?>
<script type="text/javascript">
	$("#pages_<?=$class?>").sortable({ axis: "y", containment: "parent",  handle: ".icon_sort", items: "li", placeholder: "ui-sortable-placeholder", tolerance: "pointer", update: function() {
		$.ajax("<?=$admin_root?>ajax/pages/order/?id=<?=$parent?>&sort=" + escape($("#pages_<?=$class?>").sortable("serialize")));
	}});
</script>
<?
		}
	}

	if (!$page) {
?>
<h1><span class="error"></span>Error</h1>
<p class="error">The page you are trying to view no longer exists.</p>
<?
		$admin->stop();
	}
?>
<h1>
	<? if (!$parent) { ?>
	<span class="home"></span>Home
	<? } else { ?>
	<span class="page"></span><?=$page["nav_title"]?>
	<? } ?>
</h1>
<?
	include BigTree::path("admin/modules/pages/_nav.php");
	include BigTree::path("admin/modules/pages/_properties.php");
?>
<h3>Subpages</h3>
<?
	$nav_visible = array_merge($admin->getNaturalNavigationByParent($parent,1),$admin->getPendingNavigationByParent($parent));
	$nav_hidden = array_merge($admin->getHiddenNavigationByParent($parent),$admin->getPendingNavigationByParent($parent,""));	
	$nav_archived = $admin->getArchivedNavigationByParent($parent);
	
	if (count($nav_visible) || count($nav_hidden) || count($nav_archived)) {
		// Drag Visible Pages
		if (count($nav_visible)) {
			local_drawPageTree($nav_visible,"Visible","","visible",true);
		}
		
		// Draw Hidden Pages
		if (count($nav_hidden)) {
			local_drawPageTree($nav_hidden,"Hidden","Not Appearing In Navigation","hidden",false);
		}
		
		// Draw Archived Pages
		if (count($nav_archived)) {
			local_drawPageTree($nav_archived,"Archived","Not Accessible By Users","archived",false);
		}
	} else {
?>
	<p>Create new subpages by clicking the "Add Subpage" button above.</p>
<?	
	}
?>