<?php
	namespace BigTree;
	
	/**
	 * @global Page $page
	 */
	
	// Check to see if we're using Google Analytics.
	$ga = Setting::value("bigtree-internal-google-analytics-api");
	$ga_on = isset($ga["profile"]) ? $ga["profile"] : false;
	
	// Handy function to show the trees without repeating so much code.
	$draw_page_tree = function($nav, $title, $class, $draggable = false) {
		global $ga_on, $bigtree, $page;
?>
<div class="table">
	<div class="table_summary">
		<h2><span class="icon_medium_<?=$class?>"></span><?=Text::translate($title)?></h2>
	</div>
	<header>
		<?php
			if ($class == "archived") {
		?>
		<span class="pages_title_widest"><?=Text::translate("Title")?></span>
		<span class="pages_restore"><?=Text::translate("Restore")?></span>
		<span class="pages_delete"><?=Text::translate("Delete")?></span>
		<?php
			} else {
				if ($ga_on) {
		?>
		<span class="pages_title"><?=Text::translate("Title")?></span>
		<span class="pages_views"><?=Text::translate("Views")?></span>
		<?php
				} else {
		?>
		<span class="pages_title_wider"><?=Text::translate("Title")?></span>		
		<?php
				}
		?>
		<span class="pages_status"><?=Text::translate("Status")?></span>
		<span class="pages_archive"><?=Text::translate("Archive")?></span>
		<span class="pages_edit"><?=Text::translate("Edit")?></span>
		<?php
			}
		?>
	</header>
	<ul id="pages_<?=$class?>">
		<?php
			foreach ($nav as $item) {
				$page_data = new Page($item, false);
				$access_level = $page_data->UserAccessLevel;
				
				if (isset($item["bigtree_pending"])) {
					$status = '<a href="'.WWW_ROOT.'_preview-pending/'.$item["id"].'/" target="_blank">'.Text::translate("Pending").'</a>';
					$status_class = "pending";
				} elseif (SQL::exists("bigtree_pending_changes",array("table" => "bigtree_pages", "item_id" => $item["id"]))) {
					$status = '<a href="'.WWW_ROOT.'_preview/'.$item["path"].'/" target="_blank">'.Text::translate("Changed").'</a>';
					$status_class = "pending";
				} elseif (strtotime($item["publish_at"]) > time()) {
					$status = Text::translate("Scheduled");
					$status_class = "scheduled";
				} elseif ($item["expire_at"] != "" && strtotime($item["expire_at"]) < time()) {
					$status = Text::translate("Expired");
					$status_class = "expired";
				} else {
					$status = Text::translate("Published");
					$status_class = "published";
				}
		?>
		<li id="row_<?=$item["id"]?>" class="<?=$status_class?>">
			<section class="pages_title<?php if ($class == "archived") { ?>_widest<?php } elseif (!$ga_on) { ?>_wider<?php } ?>">
				<?php if ($bigtree["access_level"] == "p" && !isset($item["bigtree_pending"]) && $draggable) { ?>
				<span class="icon_sort"></span>
				<?php } ?>
				<?php if ($class != "archived" && is_numeric($item["id"])) { ?>
				<a href="<?=ADMIN_ROOT?>pages/view-tree/<?=$item["id"]?>/"><?=$item["title"]?></a>
				<?php } else { ?>
				<?=$item["title"]?>				
				<?php } ?>
			</section>
			<?php
				if ($class == "archived") {
			?>
			<section class="pages_restore">
				<?php if ($access_level == "p" && $page_data->UserCanModifyChildren) { ?>
				<a href="<?=ADMIN_ROOT?>pages/restore/<?=$item["id"]?>/" title="<?=Text::translate("Restore Page")?>" class="icon_restore"></a>
				<?php } else { ?>
				<span class="icon_restore disabled_icon"></span>
				<?php } ?>
			</section>
			<section class="pages_delete">
				<?php if ($access_level == "p" && $page_data->UserCanModifyChildren) { ?>
				<a href="<?=ADMIN_ROOT?>pages/delete/<?=$item["id"]?>/" title="<?=Text::translate("Delete Page")?>" class="icon_delete"></a>
				<?php } else { ?>
				<span class="icon_delete disabled_icon"></span>
				<?php } ?>
			</section>
			<?php
				} else {
					if ($ga_on) {
			?>
			<section class="pages_views">
				<?php
					if ($item["template"] && $item["template"] != "!") {
						echo number_format($item["ga_page_views"]);
					} else {
						echo "&mdash;";
					}
				?>
			</section>
			<?php
					}
			?>
			<section class="pages_status status_<?=$status_class?>">
				<?=$status?>
			</section>
			<section class="pages_archive">
				<?php if (!isset($item["bigtree_pending"]) && $access_level == "p" && ($page->ID !== 0 || Auth::user()->Level > 1 || $class == "hidden") && $page_data->UserCanModifyChildren) { ?>
				<a href="<?=ADMIN_ROOT?>pages/archive/<?=$item["id"]?>/" title="<?=Text::translate("Archive Page")?>" class="icon_archive"></a>
				<?php } elseif ($item["bigtree_pending"] && $access_level == "p") { ?>
				<a href="<?=ADMIN_ROOT?>pages/delete/<?=$item["id"]?>/" title="<?=Text::translate("Delete Pending Page")?>" class="icon_delete"></a>
				<?php } elseif ($item["bigtree_pending"]) { ?>
				<span class="icon_delete disabled_icon"></span>
				<?php } else { ?>
				<span class="icon_archive disabled_icon"></span>
				<?php } ?>
			</section>
			<section class="pages_edit">
				<?php if ($access_level) { ?>
				<a href="<?=ADMIN_ROOT?>pages/edit/<?=$item["id"]?>/" title="<?=Text::translate("Edit Page")?>" class="icon_edit page"></a>
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
		$.ajax("<?=ADMIN_ROOT?>ajax/pages/order/", { type: "POST", data: { id: "<?=$page->ID?>", sort: $("#pages_<?=$class?>").sortable("serialize") } });
	}});
</script>
<?php
		}
	};

	include Router::getIncludePath("admin/modules/pages/_properties.php");
?>
<h3><?=Text::translate("Subpages")?></h3>
<?php
	$nav_visible = array_merge($page->getVisibleChildren(true), $page->getPendingChildren(true));
	$nav_hidden = array_merge($page->getHiddenChildren(true), $page->getPendingChildren(false));
	$nav_archived = $page->getArchivedChildren(true);
	
	if (count($nav_visible) || count($nav_hidden) || count($nav_archived)) {
		// Drag Visible Pages
		if (count($nav_visible)) {
			$draw_page_tree($nav_visible, "Visible", "pages", true);
		}
		
		// Draw Hidden Pages
		if (count($nav_hidden)) {
			$draw_page_tree($nav_hidden, "Hidden", "hidden", false);
		}
		
		// Draw Archived Pages
		if (count($nav_archived)) {
			$draw_page_tree($nav_archived, "Archived", "archived", false);
		}
	} else {
?>
<p><?=Text::translate("Create new subpages by clicking the \"Add Subpage\" button above.")?></p>
<?php
	}
?>