<?php
	namespace BigTree;
	
	/**
	 * @global Page $page
	 */
	
	// Don't let them move the homepage.
	if ($page->ID === 0) {
		Router::redirect(ADMIN_ROOT."pages/edit/0/");
	}

	// Make sure the user is an admin.
	Auth::user()->requireLevel(1);
	
	// Get all the ancestors
	$ancestors = array();
	
	foreach ($page->Breadcrumb as $item) {
		$ancestors[] = $item["id"];
	}
	
	function _local_drawNavLevel($parent, $depth, $ancestors, $children = false) {
		global $page;
		
		if (!$children) {
			$children = SQL::fetchAll("SELECT * FROM bigtree_pages WHERE parent = ? AND archived != 'on' ORDER BY nav_title", $parent);
		}
		
		if (count($children)) {
?>
<ul class="depth_<?=$depth?>"<?php if ($depth > 2 && !in_array($parent, $ancestors)) { ?> style="display: none;"<?php } ?>>
	<?php
			foreach ($children as $child) {
				if ($child["id"] != $page->ID) {
					$grandchildren = SQL::fetchAll("SELECT * FROM bigtree_pages WHERE parent = ? AND archived != 'on' ORDER BY nav_title", $child["id"]);
					$classes = array("title");
					
					if (!count($grandchildren)) {
						$classes[] = "disabled";
					}
					
					if ($child["id"] == $page->Parent) {
						$classes[] = "active";
					}
					
					if (in_array($child["id"], $ancestors)) {
						$classes[] = "expanded";
					}
	?>
	<li>
		<span class="depth"></span>
		<a class="<?=implode(" ",$classes)?>" href="#<?=$child["id"]?>"><?=$child["nav_title"]?></a>
		<?php _local_drawNavLevel($child["id"], $depth + 1, $ancestors, $grandchildren) ?>
	</li>
	<?php
				}
			}
	?>
</ul>
<?php
		}
	}
?>
<div class="container">
	<form method="post" action="<?=ADMIN_ROOT?>pages/move-update/">
		<?php CSRF::drawPOSTToken(); ?>
		<input type="hidden" name="page" value="<?=$page->ID?>" />
		<section>
			<fieldset>
				<input type="hidden" name="parent" value="<?=$page->Parent?>" id="page_parent" />
				<label><?=Text::translate("Select New Parent")?></label>
				<div class="move_page form_table">
					<div class="labels">
						<span class="page_label"><?=Text::translate("Page")?></span>
					</div>
					<section>
						<ul class="depth_1">
							<li class="top">
								<span class="depth"></span>
								<a class="title expanded<?php if ($page->Parent === 0) { ?> active<?php } ?>" href="#0"><?=Text::translate("Top Level")?></a>
								<?php _local_drawNavLevel(0, 2, $ancestors) ?>
							</li>
					</section>
				</div>
			</fieldset>
		</section>
		<footer>
			<input type="submit" class="button blue" value="<?=Text::translate("Move Page", true)?>" />
		</footer>
	</form>
</div>

<script>
	(function() {
		var Blocks = $(".move_page .title");
		
		Blocks.click(function() {
			var id = $(this).attr("href").substr(1);
			
			Blocks.removeClass("active");
			$(this).addClass("active");
			$("#page_parent").val(id);
			
			if (id == 0) {
				return false;
			}
			
			if ($(this).hasClass("disabled")) {
				return false;
			}
			
			if ($(this).hasClass("expanded")) {
				if ($(this).nextAll("ul")) {
					$(this).nextAll("ul").hide();
				}
				
				$(this).removeClass("expanded");
			} else {
				if ($(this).nextAll("ul").length) {
					if ($(this).nextAll("ul")) {
						$(this).nextAll("ul").show();
					}
					
					$(this).addClass("expanded");
				}
			}
			
			return false;
		});
	})();
</script>