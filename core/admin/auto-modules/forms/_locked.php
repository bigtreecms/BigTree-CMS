<?php
	namespace BigTree;

	/**
	 * @global string $last_accessed
	 * @global array $locked_by
	 */
	
	$view_data = isset($_GET["view_data"]) ? "&view_data=".htmlspecialchars($_GET["view_data"]) : "";
	$lock_message = Text::translate('<strong>:locked_by:</strong> currently has this entry locked for editing. '.
									'It was last accessed by <strong>:locked_by:</strong> on </strong>:datetime:</strong>.'.
									'If you would like to edit it anyway, please click "Unlock" below. '.
									'Otherwise, click "Cancel".', false,
									[
										":locked_by:" => $locked_by["name"],
										":datetime:" => Auth::user()->convertTimestampTo($last_accessed, "F j, Y @ g:ia")
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
		<a href="?force=true<?=$view_data?><?php CSRF::drawGETToken(); ?>" class="button blue"><?=Text::translate("Unlock")?></a>
		&nbsp;
		<a href="javascript:history.go(-1);" class="button white"><?=Text::translate("Cancel")?></a>
	</footer>
</div>