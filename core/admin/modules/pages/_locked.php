<div class="container">
	<section>
		<div class="alert">
			<span></span>
			<h3>LOCKED</h3>
		</div>
		<p>
			<strong><?=$locked_by["name"]?></strong> currently has this page locked for editing.  It was last accessed by <strong><?=$locked_by["name"]?></strong> on <strong><?=$admin->convertTimestampToUser($last_accessed, $bigtree["config"]["date_format"]." @ g:i a")?></strong>.<br />
		If you would like to edit this page anyway, please click "Unlock" below.  Otherwise, click "Cancel".
		</p>
	</section>
	<footer>
		<a href="javascript:history.go(-1);" class="button white">Cancel</a>
		&nbsp;
		<a href="?force=true<?php $admin->drawCSRFTokenGET() ?>" class="button blue">Unlock</a>
	</footer>
</div>