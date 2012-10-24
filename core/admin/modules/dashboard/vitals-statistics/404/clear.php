<h1>
	<span class="page_404"></span>Clear 404s
	<? include BigTree::path("admin/modules/dashboard/vitals-statistics/_jump.php"); ?>
</h1>
<?
	include BigTree::path("admin/modules/dashboard/vitals-statistics/404/_nav.php");

	if (isset($_POST["clear"])) {
		$admin->clearDead404s();
		BigTree::redirect(ADMIN_ROOT."dashboard/vitals-statistics/404/");
	} else {
?>
<form method="post" action="">
	<div class="form_container">
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