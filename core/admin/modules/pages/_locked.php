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
<div class="container">
	<section>
		<div class="alert">
			<span></span>
			<h3><?=Text::translate("Locked")?></h3>
		</div>
		<p>
			<?=Text::translate("<strong>:user:</strong> currently has this page locked for editing.  It was last accessed by <strong>:user</strong> on <strong>:last_accessed:</strong>.", false, array(":user:" => $locked_by["name"], ":last_accessed:" => $last_accessed))?><br />
			<?=Text::translate("If you would like to edit this page anyway, please click \"Unlock\" below.  Otherwise, click \"Cancel\".")?>
		</p>
	</section>
	<footer>
		<a href="javascript:history.go(-1);" class="button white"><?=Text::translate("Cancel")?></a>
		&nbsp;
		<a href="?force=true<?php CSRF::drawGETToken(); ?>" class="button blue"><?=Text::translate("Unlock")?></a>
	</footer>
</div>