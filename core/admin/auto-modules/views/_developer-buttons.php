<?php
	namespace BigTree;
	
	/**
	 * @global ModuleView $view
	 */
	
	if (Auth::user()->Level > 1 || $view->Description) {
?>
<div class="developer_buttons">
	<?php
		if ($view->Description) {
	?>
	<a href="#" class="js-view-description-show" <?php if (!$_COOKIE["bigtree_admin"]["ignore_view_description"][$view->ID]) { ?> style="display: none;"<?php } ?> title="Show Help Text">
		<?=Text::translate("Show Help Text")?>
		<span class="icon_small icon_small_help"></span>
	</a>
	<?php
		}
		
		if (Auth::user()->Level > 1) {
	?>
	<a href="<?=ADMIN_ROOT?>developer/modules/views/edit/<?=$view->ID?>/?return=front" title="<?=Text::translate("Edit View in Developer", true)?>">
		<?=Text::translate("Edit View in Developer")?>
		<span class="icon_small icon_small_edit_yellow"></span>
	</a>
	<?php
		}
	?>
</div>
<?php
	}
