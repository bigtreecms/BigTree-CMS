<?php
	$admin->requireLevel(1);
	
	$existing_internal_session = BigTreeCMS::cacheGet("org.bigtreecms.integritycheck", "session.internal");
	$existing_external_session = BigTreeCMS::cacheGet("org.bigtreecms.integritycheck", "session.external");
?>
<div class="container">
	<?php
		if ($existing_internal_session || $existing_external_session) {
	?>
	<section>
		<p>
			<strong>An existing integrity check session is in progress.</strong><br>
			Click "Resume Session" below to resume the session and continue where the scanner left off.<br>
			Click "Reset" to begin a new session.
		</p>
	</section>
	<footer>
		<a href="<?=ADMIN_ROOT?>dashboard/vitals-statistics/integrity/reset/" class="button red">Reset</a>
		<a href="<?=ADMIN_ROOT?>dashboard/vitals-statistics/integrity/check/?external=<?=($existing_external_session ? "true" : "false")?>" class="button blue">Resume Session</a>
	</footer>
	<?php
		} else {
	?>
	<section>
		<p>The site integrity check will search your site for broken/dead links and alert you to their presence should they exist.</p>
		<p>Including external links will take <strong>significantly longer</strong> and the integrity check <strong>may throw false positives</strong>.</p>
	</section>
	<footer>
		<a href="<?=ADMIN_ROOT?>dashboard/vitals-statistics/integrity/check/?external=true" class="button"><span class="icon_small icon_small_world"></span>Include External Links</a>
		<a href="<?=ADMIN_ROOT?>dashboard/vitals-statistics/integrity/check/?external=false" class="button"><span class="icon_small icon_small_server"></span>Only Internal Links</a>
	</footer>
	<?php
		}
	?>
</div>