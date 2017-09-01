<?php
	namespace BigTree;

	Auth::user()->requireLevel(1);
	
	if (isset($_POST["clear"])) {
		CSRF::verify();
		Redirect::clearEmpty();
		Utils::growl("404 Report","Cleared 404s");
		
		Router::redirect(ADMIN_ROOT."dashboard/vitals-statistics/404/");
	} else {
?>
<form method="post" action="">
	<?php CSRF::drawPOSTToken(); ?>
	<div class="container">
		<section>
			<p><?=Text::translate("Are you sure you want to clear out all existing 404s that do not have associated 301 redirects?")?></p>
		</section>
		<footer>
			<input type="submit" class="button red" name="clear" value="<?=Text::translate("Clear 404s")?>" />
		</footer>
	</div>
</form>
<?php
	}
?>