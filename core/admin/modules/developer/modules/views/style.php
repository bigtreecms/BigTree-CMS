<?php
	namespace BigTree;

	$view = \BigTreeAutoModule::getView(end($bigtree["path"]));
	$entries = \BigTreeAutoModule::getSearchResults($view,1);
	$entries = array_slice($entries["results"],0,5);

	if ($view == "images" || $view == "images-group") {
?>
<p><?=Text::translate("The current view type does not have any style settings.")?></p>
<?php
	} else {
		$fields = $view["fields"];
		$actions = $view["actions"];
		if ($view["preview_url"]) {
			$actions["preview"] = "on";
		}
?>
<section class="inset_block">
	<p><?=Text::translate("Drag the bounds of the columns to resize them. Don't forget to save your changes.")?></p>
</section>
<div class="table">
	<summary><h2><?=Text::translate("Example View Information")?></h2></summary>
	<header>
		<?php
			$x = 0;
			foreach ($fields as $key => $field) {
				$x++;
		?>
		<span class="view_column" style="width: <?=$field["width"]?>px; cursor: move;" name="<?=$key?>"><?=$field["title"]?></span>
		<?php
			}
		?>
		<span class="view_status"><?=Text::translate("Status")?></span>
		<span class="view_action" style="width: <?=(count($actions) * 40)?>px;"><?php if (count($view["actions"]) > 1) { echo Text::translate("Actions"); } ?></span>
	</header>
	<ul>
		<?php
			foreach ($entries as $entry) {
		?>
		<li>
			<?php
				$x = 0;
				foreach ($fields as $key => $field) {
					$x++;
			?>
			<section class="view_column" style="width: <?=$field["width"]?>px;" name="<?=$key?>"><?=$entry["column$x"]?></section>
			<?php
				}
			?>
			<section class="view_status status_published"><?=Text::translate("Published")?></section>
			<?php
				foreach ($actions as $action => $data) {
					if ($data != "on") {
						$data = json_decode($data,true);
						$class = $data["class"];
					} else {
						$class = "icon_$action";
					}
			?>
			<section class="view_action"><a href="#" class="<?=$class?>"></a></section>
			<?php
				}
			?>
		</li>
		<?php
			}
		?>
	</ul>
</div>
<form method="post" action="<?=DEVELOPER_ROOT?>modules/views/update-style/<?=$view["id"]?>/" class="module">
	<?php foreach ($fields as $key => $field) { ?>
	<input type="hidden" name="<?=$key?>" id="data_<?=$key?>" value="<?=$field["width"]?>" />
	<?php } ?>
	<a class="button" href="<?=DEVELOPER_ROOT?>modules/views/clear-style/<?=$view["id"]?>/"><?=Text::translate("Clear Existing Style")?></a>
	<input type="submit" class="button blue" value="<?=Text::translate("Update", true)?>" />
</form>
<?php
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
		var objoffset = $(this).offset();
		var obj_middle = Math.round(BigTree.localGrowingStartWidth / 2);
		var offset = ev.clientX - objoffset.left;
		var titles = $(".table .view_column");
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
			var name = $(this).attr("name");
			width = $(this).width();
			$("#data_" + name).val(width);
		});
	});
	
	$(window).mousemove(function(ev) {
		if (!BigTree.localDragging) {
			return;
		}
		var difference = ev.clientX - BigTree.localMouseStartX;
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
				var sections = $(this).find("section");
				sections.eq(BigTree.localShrinking).css({ width: (BigTree.localShrinkingStartWidth - difference) + "px" });
				sections.eq(BigTree.localGrowing).css({ width: (BigTree.localGrowingStartWidth + difference) + "px" });
			});
		}
	});
</script>