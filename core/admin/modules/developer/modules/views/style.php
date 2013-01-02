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
		<?	
			foreach ($actions as $action => $on) {
		?>
		<span class="view_action"><?=ucwords($action)?></span>
		<?
			}
		?>
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
	var dragging = false;
	var growing = false;
	var shrinking = false;
	var mouseStartX = false;
	var shrinkingStartWidth = false;
	var growingStartWidth = false;
	var movementDirection = false;
	
	$(".table .view_column").mousedown(function(ev) {
		growingStartWidth = $(this).width();
		objoffset = $(this).offset();
		obj_middle = Math.round(growingStartWidth / 2);
		offset = ev.clientX - objoffset.left;
		titles = $(".table .view_column");
		growing = $(this);
		gIndex = titles.index(this);
		if (offset > obj_middle) {
			if (!titles.eq(gIndex + 1).length) {
				return;
			}
			shrinking = titles.eq(gIndex + 1);
			movementDirection = "right";
			$(this).css({ cursor: "e-resize" });
		} else {
			if (gIndex == 0) {
				return;
			}
			shrinking = titles.eq(gIndex - 1);
			movementDirection = "left";
			$(this).css({ cursor: "w-resize" });
		}
		mouseStartX = ev.clientX;
		shrinkingStartWidth = shrinking.width();
		dragging = true;
		
		return false;
	}).mouseup(function() {
		dragging = false;
		growing.css({ cursor: "move" });
		$(".table .view_column").each(function() {
			name = $(this).attr("name");
			width = $(this).width();
			$("#data_" + name).val(width);
		});
	});
	
	$(window).mousemove(function(ev) {
		if (!dragging) {
			return;
		}
		difference = ev.clientX - mouseStartX;
		if (movementDirection == "left") {
			difference = difference * -1;
		}
		// The minimum width is 62 (20 pixels padding) because that's the size of an action column.  Figured it's a good minimum.
		if (shrinkingStartWidth - difference > 41 && growingStartWidth + difference > 41) {
			shrinking.css({ width: (shrinkingStartWidth - difference) + "px" });
			growing.css({ width: (growingStartWidth + difference) + "px" });
		}
	});
</script>