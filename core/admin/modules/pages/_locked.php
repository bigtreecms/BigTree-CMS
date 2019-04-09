<?php
	namespace BigTree;
	
	/**
	 * @global array $bigtree
	 * @global string $last_accessed
	 * @global array $locked_by
	 */
	
	$lock_message = Text::translate('<strong>:user:</strong> currently has this page locked for editing.'.
									'It was last accessed by <strong>:user</strong> on <strong>:last_accessed:</strong>.'.
									'<br>'.
									'If you would like to edit this page anyway, please click "Unlock" below.'.
									'Otherwise, click "Cancel".',
									false,
									[
										":user:" => $locked_by["name"],
										":last_accessed:" => Auth::user()->convertTimestampTo($last_accessed)
									]);
?>
<div class="container">
	<section>
		<div class="alert">
			<span></span>
			<h3><?=Text::translate("Locked")?></h3>
		</div>
		<p><?=$lock_message?></p>
	</section>
	<footer>
		<a href="javascript:history.go(-1);" class="button white"><?=Text::translate("Cancel")?></a>
		&nbsp;
		<a href="?force=true<?php CSRF::drawGETToken(); ?>" class="button blue"><?=Text::translate("Unlock")?></a>
	</footer>
</div>