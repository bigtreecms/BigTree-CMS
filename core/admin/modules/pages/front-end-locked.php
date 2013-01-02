<?
	$user = $admin->getUser($f["user"]);
?>
<h2><strong>Warning:</strong> This page is currently locked.</h2>
<form class="bigtree_dialog_form" method="post" action="">
	<div class="overflow">
		<p>
			<strong><?=$user["name"]?></strong> currently has this page locked for editing.  It was last accessed by <strong><?=$user["name"]?></strong> on <strong><?=date("F j, Y @ g:ia",strtotime($f["last_accessed"]))?></strong>.<br />
		If you would like to edit this page anyway, please click "Unlock" below.  Otherwise, click "Cancel".
		</p>			
	</div>
	<footer>
		<a class="button cancel" href="#">Cancel</a>
		<a class="button blue" href="?force=true">Unlock</a>
	</footer>
</form>
<script>
	$("footer .cancel").click(function() {
		parent.bigtree_bar_cancel();
		
		return false;
	});
</script>