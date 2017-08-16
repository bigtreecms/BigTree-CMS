<?php
	namespace BigTree;

	/**
	 * @global array $bigtree
	 */

	$view = new ModuleView(end($bigtree["path"]));
	$entries = $view->searchData(1);
	$entries = array_slice($entries["results"],0,5);

	if ($view->Type == "images" || $view->Type == "images-group") {
?>
<p><?=Text::translate("The current view type does not have any style settings.")?></p>
<?php
	} else {
		if ($view->PreviewURL) {
			$view->Actions["preview"] = "on";
		}
?>
<section class="inset_block">
	<p><?=Text::translate("Drag the bounds of the columns to resize them. Don't forget to save your changes.")?></p>
</section>
<div class="table">
	<div class="table_summary"><h2><?=Text::translate("Example View Information")?></h2></div>
	<header>
		<?php
			$x = 0;
			foreach ($view->Fields as $key => $field) {
				$x++;
		?>
		<span class="view_column" style="width: <?=$field["width"]?>px; cursor: move;" data-name="<?=$key?>"><?=$field["title"]?></span>
		<?php
			}
		?>
		<span class="view_status"><?=Text::translate("Status")?></span>
		<span class="view_action" style="width: <?=(count($view->Actions) * 40)?>px;"><?php if (count($view->Actions) > 1) { echo Text::translate("Actions"); } ?></span>
	</header>
	<ul>
		<?php
			foreach ($entries as $entry) {
		?>
		<li>
			<?php
				$x = 0;
				foreach ($view->Fields as $key => $field) {
					$x++;
			?>
			<section class="view_column" style="width: <?=$field["width"]?>px;" data-name="<?=$key?>"><?=$entry["column$x"]?></section>
			<?php
				}
			?>
			<section class="view_status status_published"><?=Text::translate("Published")?></section>
			<?php
				foreach ($view->Actions as $action => $data) {
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
<form method="post" action="<?=DEVELOPER_ROOT?>modules/views/update-style/<?=$view->ID?>/" class="module">
	<?php
		CSRF::drawPOSTToken();
		
		foreach ($view->Fields as $key => $field) {
	?>
	<input type="hidden" name="<?=$key?>" id="data_<?=$key?>" value="<?=$field["width"]?>" />
	<?php
		}
	?>
	<a class="button" href="<?=DEVELOPER_ROOT?>modules/views/clear-style/<?=$view->ID?>/?true<?php CSRF::drawGETToken(); ?>"><?=Text::translate("Clear Existing Style")?></a>
	<input type="submit" class="button blue" value="<?=Text::translate("Update", true)?>" />
</form>
<?php
	}
?>

<script>
	(function() {
		var Columns = $(".table header .view_column");
		var Dragging = false;
		var Growing = false;
		var Shrinking = false;
		var MouseStartX = false;
		var ShrinkingStartWidth = false;
		var GrowingStartWidth = false;
		var MovementDirection = false;
		var Rows = $(".table ul li");

		function mousedown(ev) {
			var objoffset = $(this).offset();
			var obj_middle = Math.round(GrowingStartWidth / 2);
			var offset = ev.clientX - objoffset.left;
			var titles = $(".table .view_column");

			GrowingStartWidth = $(this).width();
			Growing = titles.index(this);

			if (offset > obj_middle) {
				Shrinking = Growing + 1;

				// Don't allow the right column to shrink actions
				if (Shrinking == Columns.length) {
					return;
				}

				MovementDirection = "right";

				$(this).css({ cursor: "e-resize" });
			} else {
				if (Growing == 0) {
					return;
				}

				Shrinking = Growing - 1;
				MovementDirection = "left";

				$(this).css({ cursor: "w-resize" });
			}

			MouseStartX = ev.clientX;
			ShrinkingStartWidth = Columns.eq(Shrinking).width();
			Dragging = true;

			return false;
		}

		function mouseup(ev) {
			Dragging = false;
			Columns.eq(Growing).css({ cursor: "move" });

			Columns.each(function() {
				var name = $(this).attr("name");
				var width = $(this).width();

				$("#data_" + name).val(width);
			});
		}

		function mousemove(ev) {
			if (!Dragging) {
				return;
			}

			var difference = ev.clientX - MouseStartX;

			if (MovementDirection == "left") {
				difference = difference * -1;
			}

			// The minimum width is 62 (20 pixels padding) because that's the size of an action column.  Figured it's a good minimum.
			if (ShrinkingStartWidth - difference > 41 && GrowingStartWidth + difference > 41) {
				// Shrink the shrinking title
				Columns.eq(Shrinking).css({ width: (ShrinkingStartWidth - difference) + "px" });

				// Grow the growing title
				Columns.eq(Growing).css({ width: (GrowingStartWidth + difference) + "px" });

				// Shrink/Grow all the rows
				Rows.each(function() {
					sections = $(this).find("section");
					sections.eq(Shrinking).css({ width: (ShrinkingStartWidth - difference) + "px" });
					sections.eq(Growing).css({ width: (GrowingStartWidth + difference) + "px" });
				});
			}
		}

		// Init hooks
		Columns.mousedown(mousedown).mouseup(mouseup);
		$(window).mousemove(mousemove);
	})();
</script>