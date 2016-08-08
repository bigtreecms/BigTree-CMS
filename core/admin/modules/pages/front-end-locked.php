<?php
	namespace BigTree;
	
	/**
	 * @global array $bigtree
	 * @global string $last_accessed
	 * @global string $locked_by
	 */
	
	if (!empty($bigtree["config"]["date_format"])) {
		$last_accessed = date($bigtree["config"]["date_format"]." @ g:ia", strtotime($last_accessed));
	} else {
		$last_accessed = date("F j, Y @ g:ia", strtotime($last_accessed));
	}
?>
<h2><strong><?=Text::translate("Warning")?>:</strong> <?=Text::translate("This page is currently locked.")?></h2>
<form class="bigtree_dialog_form" method="post" action="">
	<div class="overflow">
		<p>
			<?=Text::translate("<strong>:user:</strong> currently has this page locked for editing.  It was last accessed by <strong>:user</strong> on <strong>:last_accessed:</strong>.", false, array(":user:" => $locked_by, ":last_accessed:" => $last_accessed))?><br />
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