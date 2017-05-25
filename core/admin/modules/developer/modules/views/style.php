<?
	$view = BigTreeAutoModule::getView(end($bigtree["path"]));
	$entries = BigTreeAutoModule::getSearchResults($view,1);
	$entries = array_slice($entries["results"],0,5);

	if ($view["type"] == "images" || $view["type"] == "images-group") {
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
	<?
		$admin->drawCSRFToken();

		foreach ($fields as $key => $field) {
	?>
	<input type="hidden" name="<?=$key?>" id="data_<?=$key?>" value="<?=$field["width"]?>" />
	<?
		}
	?>
	<a class="button" href="<?=DEVELOPER_ROOT?>modules/views/clear-style/?id=<?=$view["id"]?><? $admin->drawCSRFTokenGET() ?>">Clear Existing Style</a>
	<input type="submit" class="button blue" value="Update" />
</form>
<?
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