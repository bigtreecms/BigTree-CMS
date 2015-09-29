<?php
	$mpage = ADMIN_ROOT.$module['route'].'/';
	BigTree::globalizeArray($view);

	// Figure out the column width
	$awidth = count($actions) * 40;
	$available = 896 - $awidth;
	$percol = floor($available / count($fields));

	foreach ($fields as $key => $field) {
	    $fields[$key]['width'] = $percol - 20;
	}

	$items = BigTreeAutoModule::parseViewData($view, $items);
?>
<div class="table" style="margin: 0;">
	<summary><h2>Search Results</h2></summary>
	<header>
		<?php
			$x = 0;
			foreach ($fields as $key => $field) {
			    ++$x;
			    ?>
		<span class="view_column" style="width: <?=$field['width']?>px;"><?=$field['title']?></span>
		<?php

			}
		?>
		<span class="view_action" style="width: <?=(count($actions) * 40)?>px;">Actions</span>
	</header>
	<ul id="results_table_<?=$view['id']?>">
		<?php foreach ($items as $item) {
    ?>
		<li id="row_<?=$item['id']?>"<?php if ($item['bigtree_pending']) {
    ?> class="pending"<?php 
}
    ?><?php if ($item['bigtree_changes']) {
    ?> class="changes"<?php 
}
    ?>>
		<?php
			$x = 0;
    foreach ($fields as $key => $field) {
        ++$x;
        $value = strip_tags($item[$key]);
        ?>
		<section class="view_column" style="width: <?=$field['width']?>px;">
			<?=$value?>
		</section>
		<?php

    }

    foreach ($actions as $action => $data) {
        $class = $admin->getActionClass($action, $item);
        if ($data == 'on') {
            ?>
		<section class="view_action action_<?=$action?>"><a href="#<?=$item['id']?>" class="<?=$class?>"></a></section>
		<?php

        } else {
            $data = json_decode($data, true);
            $link = $mpage.$data['route'].'/'.$item['id'].'/';
            if ($data['function']) {
                $link = call_user_func($data['function'], $item);
            }
            ?>
		<section class="view_action"><a href="<?=$link?>" class="<?=$data['class']?>"></a></section>
		<?php

        }
    }
    ?>
	</li>
	<?php 
} ?>
	</ul>
</div>

<script>
	$("#results_table_<?=$view['id']?> .icon_edit").click(function() {
		document.location.href = "<?=$view['edit_url']?>" + $(this).attr("href").substr(1) + "/";
		return false;
	});
			
	$("#results_table_<?=$view['id']?> .icon_delete").click(function() {
		BigTreeDialog({
			title: "Delete Item",
			content: '<p class="confirm">Are you sure you want to delete this item?',
			icon: "delete",
			alternateSaveText: "OK",
			callback: $.proxy(function() {
				$.ajax("<?=ADMIN_ROOT?>ajax/auto-modules/views/delete/?view=<?=$view['id']?>&id=" + $(this).attr("href").substr(1));
				$(this).parents("li").remove();
			},this)
		});
		
		return false;
	});
	$("#results_table_<?=$view['id']?> .icon_approve").click(function() {
		$.ajax("<?=ADMIN_ROOT?>ajax/auto-modules/views/approve/?view=<?=$view['id']?>&id=" + $(this).attr("href").substr(1));
		$(this).toggleClass("icon_approve_on");
		return false;
	});
	$("#results_table_<?=$view['id']?> .icon_feature").click(function() {
		$.ajax("<?=ADMIN_ROOT?>ajax/auto-modules/views/feature/?view=<?=$view['id']?>&id=" + $(this).attr("href").substr(1));
		$(this).toggleClass("icon_feature_on");
		return false;
	});
	$("#results_table_<?=$view['id']?> .icon_archive").click(function() {
		$.ajax("<?=ADMIN_ROOT?>ajax/auto-modules/views/archive/?view=<?=$view['id']?>&id=" + $(this).attr("href").substr(1));
		$(this).toggleClass("icon_archive_on");
		return false;
	});
</script>