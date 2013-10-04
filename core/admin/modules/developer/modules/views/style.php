<?
	$view = BigTreeAutoModule::getView(end($bigtree["path"]));

	if ($view == "images" || $view == "images-group") {
?>
<p>The view type does not have any style settings.</p>
<?
	} else {
		$fields = $view["fields"];
		$actions = $view["actions"];
		if ($view["preview_url"]) {
			$actions["preview"] = "on";
		}
?>
<div class="table">
	<summary><p>Drag the bounds of the columns to resize them. Don't forget to save your changes.</p></summary>
	<header>
		<?
			$x = 0;
			foreach ($fields as $key => $field) {
				$x++;
		?>
		<span class="view_column" style="width: <?=$field["width"]?>px; cursor: move;" name="<?=$key?>"><?=$field["title"]?></span>
		<?
			}
		?>
		<span class="view_status">Status</span>
		<span class="view_action" style="width: <?=(count($actions) * 40)?>px;"><? if (count($view["actions"]) > 1) { ?>Actions<? } ?></span>
	</header>
</div>
<form method="post" action="<?=$developer_root?>modules/views/update-style/<?=$view["id"]?>/" class="module">
	<? foreach ($fields as $key => $field) { ?>
	<input type="hidden" name="<?=$key?>" id="data_<?=$key?>" value="<?=$field["width"]?>" />
	<? } ?>
	<a class="button" href="<?=$section_root?>clear-style/<?=$view["id"]?>/">Clear Existing Style</a>
	<input type="submit" class="button blue" value="Update" />
</form>
<?
	}
?>

<script>
	BigTree.localDragging = false;
	BigTree.localGrowing = false;
	BigTree.localShrinking = false;
	BigTree.localMouseStartX = false;
	BigTree.localShrinkingStartWidth = false;
	BigTree.localGrowingStartWidth = false;
	BigTree.localMovementDirection = false;
	
	$(".table .view_column").mousedown(function(ev) {
		BigTree.localGrowingStartWidth = $(this).width();
		objoffset = $(this).offset();
		obj_middle = Math.round(BigTree.localGrowingStartWidth / 2);
		offset = ev.clientX - objoffset.left;
		titles = $(".table .view_column");
		BigTree.localGrowing = $(this);
		gIndex = titles.index(this);
		if (offset > obj_middle) {
			if (!titles.eq(gIndex + 1).length) {
				return;
			}
			BigTree.localShrinking = titles.eq(gIndex + 1);
			BigTree.localMovementDirection = "right";
			$(this).css({ cursor: "e-resize" });
		} else {
			if (gIndex == 0) {
				return;
			}
			BigTree.localShrinking = titles.eq(gIndex - 1);
			BigTree.localMovementDirection = "left";
			$(this).css({ cursor: "w-resize" });
		}
		BigTree.localMouseStartX = ev.clientX;
		BigTree.localShrinkingStartWidth = BigTree.localShrinking.width();
		BigTree.localDragging = true;
		
		return false;
	}).mouseup(function() {
		BigTree.localDragging = false;
		BigTree.localGrowing.css({ cursor: "move" });
		$(".table .view_column").each(function() {
			name = $(this).attr("name");
			width = $(this).width();
			$("#data_" + name).val(width);
		});
	});
	
	$(window).mousemove(function(ev) {
		if (!BigTree.localDragging) {
			return;
		}
		difference = ev.clientX - BigTree.localMouseStartX;
		if (BigTree.localMovementDirection == "left") {
			difference = difference * -1;
		}
		// The minimum width is 62 (20 pixels padding) because that's the size of an action column.  Figured it's a good minimum.
		if (BigTree.localShrinkingStartWidth - difference > 41 && BigTree.localGrowingStartWidth + difference > 41) {
			BigTree.localShrinking.css({ width: (BigTree.localShrinkingStartWidth - difference) + "px" });
			BigTree.localGrowing.css({ width: (BigTree.localGrowingStartWidth + difference) + "px" });
		}
	});
</script>