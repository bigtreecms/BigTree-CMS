<?
	$view = BigTreeAutoModule::getView(end($bigtree["path"]));
	$entries = BigTreeAutoModule::getSearchResults($view,1);
	$entries = array_slice($entries["results"],0,5);

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
<section class="inset_block">
	<p>Drag the bounds of the columns to resize them. Don't forget to save your changes.</p>
</section>
<div class="table">
	<summary><h2>Example View Information</h2></summary>
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
	<ul>
		<?
			foreach ($entries as $entry) {
		?>
		<li>
			<?
				$x = 0;
				foreach ($fields as $key => $field) {
					$x++;
			?>
			<section class="view_column" style="width: <?=$field["width"]?>px;" name="<?=$key?>"><?=$entry["column$x"]?></section>
			<?
				}
			?>
			<section class="view_status status_published">Published</section>
			<?	
				foreach ($actions as $action => $data) {
					if ($data != "on") {
						$data = json_decode($data,true);
						$class = $data["class"];
					} else {
						$class = "icon_$action";
					}
			?>
			<section class="view_action"><a href="#" class="<?=$class?>"></a></section>
			<?
				}
			?>
		</li>
		<?
			}
		?>
	</ul>
</div>
<form method="post" action="<?=DEVELOPER_ROOT?>modules/views/update-style/<?=$view["id"]?>/" class="module">
	<? foreach ($fields as $key => $field) { ?>
	<input type="hidden" name="<?=$key?>" id="data_<?=$key?>" value="<?=$field["width"]?>" />
	<? } ?>
	<a class="button" href="<?=DEVELOPER_ROOT?>modules/views/clear-style/<?=$view["id"]?>/">Clear Existing Style</a>
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
	BigTree.localViewTitles = $(".table header .view_column");
	BigTree.localViewRows = $(".table ul li");
	
	$(".table .view_column").mousedown(function(ev) {
		BigTree.localGrowingStartWidth = $(this).width();
		objoffset = $(this).offset();
		obj_middle = Math.round(BigTree.localGrowingStartWidth / 2);
		offset = ev.clientX - objoffset.left;
		titles = $(".table .view_column");
		BigTree.localGrowing = titles.index(this);
		if (offset > obj_middle) {
			BigTree.localShrinking = BigTree.localGrowing + 1;
			BigTree.localMovementDirection = "right";
			$(this).css({ cursor: "e-resize" });
		} else {
			if (BigTree.localGrowing == 0) {
				return;
			}
			BigTree.localShrinking = BigTree.localGrowing - 1;
			BigTree.localMovementDirection = "left";
			$(this).css({ cursor: "w-resize" });
		}
		BigTree.localMouseStartX = ev.clientX;
		BigTree.localShrinkingStartWidth = BigTree.localViewTitles.eq(BigTree.localShrinking).width();
		BigTree.localDragging = true;
		
		return false;
	}).mouseup(function() {
		BigTree.localDragging = false;
		BigTree.localViewTitles.eq(BigTree.localGrowing).css({ cursor: "move" });
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
			// Shrink the shrinking title
			BigTree.localViewTitles.eq(BigTree.localShrinking).css({ width: (BigTree.localShrinkingStartWidth - difference) + "px" });
			// Grow the growing title
			BigTree.localViewTitles.eq(BigTree.localGrowing).css({ width: (BigTree.localGrowingStartWidth + difference) + "px" });
			// Shrink/Grow all the rows
			BigTree.localViewRows.each(function() {
				sections = $(this).find("section");
				sections.eq(BigTree.localShrinking).css({ width: (BigTree.localShrinkingStartWidth - difference) + "px" });
				sections.eq(BigTree.localGrowing).css({ width: (BigTree.localGrowingStartWidth + difference) + "px" });
			});
		}
	});
</script>