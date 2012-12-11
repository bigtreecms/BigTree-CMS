<?
	$admin->requireLevel(1);
?>
<div class="container">
	<section>
		<p>The site integrity check will search your site for broken/dead links and alert you to their presence should they exist.</p>
		<p>Including external links will take <strong>significantly longer</strong> the integrity check and <strong>may throw false positives</strong>.</p>
	</section>
	<footer>
		<a href="<?=ADMIN_ROOT?>dashboard/vitals-statistics/integrity/check/?external=true" class="button"><span class="icon_small icon_small_world"></span>Include External Links</a>
		<a href="<?=ADMIN_ROOT?>dashboard/vitals-statistics/integrity/check/?external=false" class="button"><span class="icon_small icon_small_server"></span>Only Internal Links</a>
	</footer>
</div>

