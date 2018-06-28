<?php
	if ($admin->Level > 1) {
?>
<div class="developer_buttons">
	<a href="<?=ADMIN_ROOT?>developer/modules/views/edit/<?=$bigtree["view"]["id"]?>/?return=front" title="Edit View in Developer">
		Edit View in Developer
		<span class="icon_small icon_small_edit_yellow"></span>
	</a>
</div>
<?php
	}
