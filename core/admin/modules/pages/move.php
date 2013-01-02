<?
	// Don't let them move the homepage.
	if ($page["id"] == 0) {
		BigTree::redirect(ADMIN_ROOT."pages/edit/0/");
	}

	// Make sure the user is an admin.
	$admin->requireLevel(1);
	
	// Get all the ancestors
	$bc = $cms->getBreadcrumbByPage($page);
	$ancestors = array();
	foreach ($bc as $item) {
		$ancestors[] = $item["id"];
	}
	
	function _local_drawNavLevel($parent,$depth,$ancestors,$children = false) {
		global $permissions,$page,$admin;
		if (!$children) {
			$children = $admin->getPageChildren($parent);
		}
		if (count($children)) {
?>
<ul class="depth_<?=$depth?>"<? if ($depth > 2 && !in_array($parent,$ancestors)) { ?> style="display: none;"<? } ?>>
	<?
			foreach ($children as $f) {
				if ($f["id"] != $page["id"]) {
					$grandchildren = $admin->getPageChildren($f["id"]);
	?>
	<li>
		<span class="depth"></span>
		<a class="title<? if (!$grandchildren) { ?> disabled<? } ?><? if ($f["id"] == $page["parent"]) { ?> active<? } ?><? if (in_array($f["id"],$ancestors)) { ?> expanded<? } ?>" href="#<?=$f["id"]?>"><?=$f["nav_title"]?></a>
		<? _local_drawNavLevel($f["id"],$depth + 1,$ancestors,$grandchildren) ?>
	</li>
	<?
				}
			}
	?>
</ul>
<?
		}
	}
?>
<div class="container">
	<form method="post" action="<?=ADMIN_ROOT?>pages/move-update/">
		<input type="hidden" name="page" value="<?=$page["id"]?>" />
		<section>
			<fieldset>
				<input type="hidden" name="parent" value="<?=$page["parent"]?>" id="page_parent" />
				<label>Select New Parent</label>
				<div class="move_page form_table">
					<div class="labels">
						<span class="page_label">Page</span>
					</div>
					<section>
						<ul class="depth_1">
							<li class="top">
								<span class="depth"></span>
								<a class="title expanded<? if ($page["parent"] == 0) { ?> active<? } ?>" href="#0">Top Level</a>
								<? _local_drawNavLevel(0,2,$ancestors) ?>
							</li>
					</section>
				</div>
			</fieldset>
		</section>
		<footer>
			<input type="submit" class="button blue" value="Move Page" />
		</footer>
	</form>
</div>

<script>
	$(".move_page .title").click(function() {		
		$(".move_page .title").removeClass("active");
		$(this).addClass("active");
		
		id = $(this).attr("href").substr(1);
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
</script>