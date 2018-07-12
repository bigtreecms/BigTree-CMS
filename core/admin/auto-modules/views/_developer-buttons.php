<?php
	if ($admin->Level > 1 || $bigtree["view"]["description"]) {
?>
<div class="developer_buttons">
	<?php
		if ($bigtree["view"]["description"]) {
	?>
	<a href="#" class="js-view-description-show" <?php if (!$_COOKIE["bigtree_admin"]["ignore_view_description"][$bigtree["view"]["id"]]) { ?> style="display: none;"<?php } ?> title="Show Help Text">
		Show Help Text
		<span class="icon_small icon_small_help"></span>
	</a>
	<?php
		}

		if ($admin->Level > 1) {
	?>
	<a href="<?=ADMIN_ROOT?>developer/modules/views/edit/<?=$bigtree["view"]["id"]?>/?return=front" title="Edit View in Developer">
		Edit View in Developer
		<span class="icon_small icon_small_edit_yellow"></span>
	</a>
	<?php
		}
	?>
</div>
<?php
	}
