<?php
	namespace BigTree;
	
	$root = ADMIN_ROOT."dashboard/vitals-statistics/";
?>
<div class="developer">
	<div class="container">
		<section>
			<a class="box_select" href="<?=$root?>analytics/">
				<span class="analytics"></span>
				<p><?=Text::translate("Analytics")?></p>
			</a>
			
			<a class="box_select" href="<?=$root?>404/">
				<span class="page_404"></span>
				<p><?=Text::translate("404 Report")?></p>
			</a>
			
			<a class="box_select" href="<?=$root?>integrity/">
				<span class="integrity"></span>
				<p><?=Text::translate("Integrity Check")?></p>
			</a>
		</section>
	</div>
</div>