<?php
	namespace BigTree;
	
	/**
	 * @global ModuleView $view
	 */
	
	if (Auth::user()->Level > 1) {
?>
<div class="developer_buttons">
	<a href="<?=ADMIN_ROOT?>developer/modules/views/edit/<?=$view->ID?>/?return=front" title="<?=Text::translate("Edit View in Developer", true)?>">
		<?=Text::translate("Edit View in Developer")?>
		<span class="icon_small icon_small_edit_yellow"></span>
	</a>
</div>
<?php
	}
