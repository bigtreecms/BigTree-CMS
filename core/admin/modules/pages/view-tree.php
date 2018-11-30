<?php
	// Check to see if we're using Google Analytics.
	$ga = $cms->getSetting("bigtree-internal-google-analytics-api");
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
		<?php
			if ($class == "archived") {
		?>
		<span class="pages_title_widest">Title</span>
		<span class="pages_restore">Restore</span>
		<span class="pages_delete">Delete</span>
		<?php
			} else {
				if ($ga_on) {
		?>
		<span class="pages_title">Title</span>
		<span class="pages_views">Views</span>
		<?php
				} else {
		?>
		<span class="pages_title_wider">Title</span>		
		<?php
				}
		?>
		<span class="pages_status">Status</span>
		<span class="pages_archive">Archive</span>
		<span class="pages_edit">Edit</span>
		<?php
			}
		?>
	</header>
	<ul id="pages_<?=$class?>">
		<?php
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

				$has_children = SQL::fetchSingle("SELECT COUNT(*) FROM bigtree_pages WHERE parent = ?", $item["id"]);
		?>
		<li id="row_<?=$item["id"]?>" class="<?=$status_class?>">
			<section class="pages_title<?php if ($class == "archived") { ?>_widest<?php } elseif (!$ga_on) { ?>_wider<?php } ?>">
				<?php if ($bigtree["access_level"] == "p" && !isset($item["bigtree_pending"]) && $draggable) { ?>
				<span class="icon_sort"></span>
				<?php } ?>
				<?php if ($class != "archived" && is_numeric($item["id"])) { ?>
				<a href="<?=$proot?>view-tree/<?=$item["id"]?>/">
					<?php
						echo $item["title"];

						if ($has_children) {
					?>
					<span class="icon_small icon_small_down_arrow" title="Page has Children"></span>
					<?php
						}
					?>
				</a>
				<?php } else { ?>
				<?=$item["title"]?>				
				<?php } ?>
			</section>
			<?php
				if ($class == "archived") {
			?>
			<section class="pages_restore">
				<?php if ($perm == "p" && $admin->canModifyChildren($item)) { ?>
				<a href="<?=$proot?>restore/?id=<?=$item["id"]?><?php $admin->drawCSRFTokenGET() ?>" title="Restore Page" class="icon_restore"></a>
				<?php } else { ?>
				<span class="icon_restore disabled_icon"></span>
				<?php } ?>
			</section>
			<section class="pages_delete">
				<?php if ($perm == "p" && $admin->canModifyChildren($item)) { ?>
				<a href="<?=$proot?>delete/?id=<?=$item["id"]?><?php $admin->drawCSRFTokenGET() ?>" title="Delete Page" class="icon_delete js-delete-hook"></a>
				<?php } else { ?>
				<span class="icon_delete disabled_icon"></span>
				<?php } ?>
			</section>
			<?php
				} else {
					if ($ga_on) {
			?>
			<section class="pages_views">
				<?php if ($item["template"] && $item["template"] != "!") { ?>
				<?=number_format($item["ga_page_views"])?>
				<?php } else { ?>
				&mdash;
				<?php } ?>
			</section>
			<?php
					}
			?>
			<section class="pages_status status_<?=$status_class?>">
				<?=$status?>
			</section>
			<section class="pages_archive">
				<?php if (!isset($item["bigtree_pending"]) && $perm == "p" && ($page["id"] != 0 || $admin->Level > 1 || $class == "hidden") && $admin->canModifyChildren($item)) { ?>
				<a href="<?=$proot?>archive/?id=<?=$item["id"]?><?php $admin->drawCSRFTokenGET() ?>" title="Archive Page" class="icon_archive"></a>
				<?php } elseif ($item["bigtree_pending"] && $perm == "p") { ?>
				<a href="<?=$proot?>delete/?id=<?=$item["id"]?><?php $admin->drawCSRFTokenGET() ?>" title="Delete Pending Page" class="icon_delete"></a>
				<?php } elseif ($item["bigtree_pending"]) { ?>
				<span class="icon_delete disabled_icon"></span>
				<?php } else { ?>
				<span class="icon_archive disabled_icon"></span>
				<?php } ?>
			</section>
			<section class="pages_edit">
				<?php if ($perm) { ?>
				<a href="<?=$proot?>edit/<?=$item["id"]?>/" title="Edit Page" class="icon_edit page"></a>
				<?php } else { ?>
				<span class="icon_edit disabled_icon"></span>
				<?php } ?>
			</section>
			<?php
				}
			?>
		</li>
		<?php
			}
		?>
	</ul>
</div>
<?php
		if ($draggable && $bigtree["access_level"]) {
?>
<script>
	$("#pages_<?=$class?>").sortable({ axis: "y", containment: "parent",  handle: ".icon_sort", items: "li", placeholder: "ui-sortable-placeholder", tolerance: "pointer", update: function() {
		$.secureAjax("<?=ADMIN_ROOT?>ajax/pages/order/", { type: "POST", data: { id: "<?=$page["id"]?>", sort: $("#pages_<?=$class?>").sortable("serialize") } });
	}});
</script>
<?php
		}
	}

	include BigTree::path("admin/modules/pages/_properties.php");
?>
<h3>Subpages</h3>
<?php
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
<?php
	}
?>

<script>
	$(".table .js-delete-hook").click(function(ev) {
		ev.preventDefault();
		Current = $(this);
		BigTreeDialog({
			title: "Delete Page",
			content: '<p class="confirm">Are you sure you want to delete this page? It will be permanently deleted and unrecoverable.</p>',
			icon: "delete",
			alternateSaveText: "OK",
			callback: function() {
				document.location.href = Current.attr("href");
			}
		});
	});
</script>