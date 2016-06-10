<?php
	namespace BigTree;
	
	$admin->Auth->requireLevel(1);
?>
<div class="container">
	<section>
		<p><?=Text::translate("The site integrity check will search your site for broken/dead links and alert you to their presence should they exist.")?></p>
		<p><?=Text::translate("Including external links will take <strong>significantly longer</strong> and the integrity check <strong>may throw false positives</strong>.")?></p>
	</section>
	<footer>
		<a href="<?=ADMIN_ROOT?>dashboard/vitals-statistics/integrity/check/?external=true" class="button"><span class="icon_small icon_small_world"></span><?=Text::translate("Include External Links")?></a>
		<a href="<?=ADMIN_ROOT?>dashboard/vitals-statistics/integrity/check/?external=false" class="button"><span class="icon_small icon_small_server"></span><?=Text::translate("Only Internal Links")?></a>
	</footer>
</div>

