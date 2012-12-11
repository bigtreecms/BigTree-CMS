<?
	$admin->requireLevel(1);
	if (isset($_POST["clear"])) {
		$admin->clearDead404s();
		BigTree::redirect(ADMIN_ROOT."dashboard/vitals-statistics/404/");
	} else {
?>
<form method="post" action="">
	<div class="container">
		<section>
			<p>Are you sure you want to clear out all existing 404s that do not have associated 301 redirects?</p>
		</section>
		<footer>
			<input type="submit" class="button red" name="clear" value="Clear 404s" />
		</footer>
	</div>
</form>
<?
	}
?>