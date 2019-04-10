<?php
	namespace BigTree;

	/**
	 * @global array $items
	 * @global Module $module
	 * @global ModuleView $view
	 */
	
	$module_link = ADMIN_ROOT.$module["route"]."/";
?>
<div class="table" class="image_list"<?php if ($set_index == $total_sets) { ?> style="margin: 0;"<?php } ?>>
	<div class="table_summary"><h2><?=Text::translate("Search Results")?></h2></div>
	<?php if (!empty($view->Actions["edit"])) { ?>
	<header>
		<span class="view_column"><?=Text::translate("Click an image to edit it.")?></span>
	</header>
	<?php } ?>
	<section>
		<ul id="image_list_<?=$view->ID?>" class="image_list">
			<?php
				foreach ($items as $item) {
					if ($view->Settings["preview_prefix"]) {
						$preview_image = FileSystem::getPrefixedFile($item[$view->Settings["image"]], $view->Settings["preview_prefix"]);
					} else {
						$preview_image = $item[$view->Settings["image"]];
					}
			?>
			<li id="row_<?=$item["id"]?>">
				<a class="image<?php if (empty($view->Actions["edit"])) { ?> image_disabled<?php } ?>" href="<?=$view->EditURL.$item["id"]?>/"><img src="<?=$preview_image?>" alt="" /></a>
				<?php
					foreach ($view->Actions as $action => $data) {
						if ($action != "edit") {
							$class = $view->generateActionClass($action, $item);
							$link = "#".$item["id"];
							
							if ($data != "on") {
								$data = json_decode($data,true);
								$class = $data["class"];
								$link = $module_link.$data["route"]."/".$item["id"]."/";

								if ($data["function"]) {
									$link = call_user_func($data["function"],$item);
								}
							}
				?>
				<a href="<?=$link?>" class="<?=$class?>"></a>
				<?php
						}
					}
				?>
			</li>
			<?php
				}
			?>
		</ul>
	</section>
</div>
<script>	
	$("#image_list_<?=$view->ID?> .icon_delete").click(function() {
		BigTreeDialog({
			title: "<?=Text::translate("Delete Item")?>",
			content: '<p class="confirm"><?=Text::translate("Are you sure you want to delete this item?")?></p>',
			icon: "delete",
			alternateSaveText: "<?=Text::translate("OK")?>",
			callback: $.proxy(function() {
				$.secureAjax("<?=ADMIN_ROOT?>ajax/auto-modules/views/delete/?view=<?=$view->ID?>&id=" + $(this).attr("href").substr(1));
				$(this).parents("li").remove();
			},this)
		});
		
		return false;
	});
	
	$("#image_list_<?=$view->ID?> .icon_approve").click(function() {
		$.secureAjax("<?=ADMIN_ROOT?>ajax/auto-modules/views/approve/?view=<?=$view->ID?>&id=" + $(this).attr("href").substr(1));
		$(this).toggleClass("icon_approve_on");
		return false;
	});
	
	$("#image_list_<?=$view->ID?> .icon_feature").click(function() {
		$.secureAjax("<?=ADMIN_ROOT?>ajax/auto-modules/views/feature/?view=<?=$view->ID?>&id=" + $(this).attr("href").substr(1));
		$(this).toggleClass("icon_feature_on");
		return false;
	});
	
	$("#image_list_<?=$view->ID?> .icon_archive").click(function() {
		$.secureAjax("<?=ADMIN_ROOT?>ajax/auto-modules/views/archive/?view=<?=$view->ID?>&id=" + $(this).attr("href").substr(1));
		$(this).toggleClass("icon_archive_on");
		return false;
	});
	
	$("#image_list_<?=$view->ID?> img").load(function() {
		var w = $(this).width();
		var h = $(this).height();
		var percentage;
		var style;

		if (w > h) {
			percentage = 108 / w;
			h = percentage * h;
			style = { margin: Math.floor((108 - h) / 2) + "px 0 0 0" };
		} else {
			percentage = 108 / h;
			w = percentage * w;
			style = { margin: "0 0 0 " + Math.floor((108 - w) / 2) + "px" };
		}
		
		$(this).css(style);
	});

	// Stop disabled edit action from working.
	$(".image_list a.image_disabled").click(function() {
		return false;
	});
</script>