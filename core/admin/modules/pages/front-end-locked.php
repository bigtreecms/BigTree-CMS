<?php
	$user = $admin->getUser($f["user"]);
?>
<h2><strong><?=Text::translate("Warning")?>:</strong> <?=Text::translate("This page is currently locked.")?></h2>
<form class="bigtree_dialog_form" method="post" action="">
	<div class="overflow">
		<p>
			<strong><?=$user["name"]?></strong> <?=Text::translate("currently has this page locked for editing.  It was last accessed by")?> <strong><?=$user["name"]?></strong> <?=Text::translate("on")?> <strong><?=date("F j, Y @ g:ia",strtotime($f["last_accessed"]))?></strong>.<br />
			<?=Text::translate("If you would like to edit this page anyway, please click \"Unlock\" below.  Otherwise, click \"Cancel\".")?>
		</p>			
	</div>
	<footer>
		<a class="button cancel" href="#"><?=Text::translate("Cancel")?></a>
		<a class="button blue" href="?force=true"><?=Text::translate("Unlock")?></a>
	</footer>
</form>
<script>
	$("footer .cancel").click(function() {
		parent.bigtree_bar_cancel();
		
		return false;
	});
</script>