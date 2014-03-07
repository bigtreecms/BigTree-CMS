<?
	// Check to see if we're using Google Analytics.
	$ga = $cms->getSetting("bigtree-internal-google-analytics");
	$ga_on = isset($ga["profile"]) ? $ga["profile"] : false;
	
	// Handy function to show the trees without repeating so much code.
	function local_drawPageTree($nav,$title,$subtitle,$class,$draggable = false) {
		global $proot,$admin,$cms,$ga_on,$bigtree,$page;
?>
<div class="table">
	<summary>
		<h2><span class="<?=$class?>"></span><?=$title?></h2>
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
				
				if (isset($item["bigtree_pending"])) {
					$status = '<a href="'.WWW_ROOT.'_preview-pending/'.$item["id"].'/" target="_blank">Pending</a>';
					$status_class = "pending";
				} elseif ($admin->pageChangeExists($item["id"])) {
					$status = '<a href="'.WWW_ROOT.'_preview/'.$item["path"].'/" target="_blank">Changed</a>';
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
				<? if ($bigtree["access_level"] == "p" && !isset($item["bigtree_pending"]) && $draggable) { ?>
				<span class="icon_sort"></span>
				<? } ?>
				<? if ($class != "archived" && is_numeric($item["id"])) { ?>
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
				<? if ($item["template"]) { ?>
				<?=number_format($item["ga_page_views"])?>
				<? } else { ?>
				&mdash;
				<? } ?>
			</section>
			<?
					}
			?>
			<section class="pages_status status_<?=$status_class?>">
				<?=$status?>
			</section>
			<section class="pages_archive">
				<? if (!isset($item["bigtree_pending"]) && $perm == "p" && ($page["id"] != 0 || $admin->Level > 1 || $class == "hidden") && $admin->canModifyChildren($item)) { ?>
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
		if ($draggable && $bigtree["access_level"]) {
?>
<script>
	$("#pages_<?=$class?>").sortable({ axis: "y", containment: "parent",  handle: ".icon_sort", items: "li", placeholder: "ui-sortable-placeholder", tolerance: "pointer", update: function() {
		$.ajax("<?=ADMIN_ROOT?>ajax/pages/order/", { type: "POST", data: { id: "<?=$page["id"]?>", sort: $("#pages_<?=$class?>").sortable("serialize") } });
	}});
</script>
<?
		}
	}

	include BigTree::path("admin/modules/pages/_properties.php");
?>
<h3>Subpages</h3>
<?
	$nav_visible = array_merge($admin->getNaturalNavigationByParent($page["id"],1),$admin->getPendingNavigationByParent($page["id"]));
	$nav_hidden = array_merge($admin->getHiddenNavigationByParent($page["id"]),$admin->getPendingNavigationByParent($page["id"],""));
	$nav_archived = $admin->getArchivedNavigationByParent($page["id"]);
	
	if (count($nav_visible) || count($nav_hidden) || count($nav_archived)) {
		// Drag Visible Pages
		if (count($nav_visible)) {
			local_drawPageTree($nav_visible,"Visible","","pages",true);
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