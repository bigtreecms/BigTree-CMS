<div class="fs-row page_row">
	<div class="fs-cell fs-lg-8">
		<div class="typography">
			<h1><?=$page_header?></h1>
			<?php
				if ($page_intro) {
			?>
			<p class="page_intro"><?=$page_intro?></p>
			<?php
				}
			?>
		</div>
	</div>
	<div class="fs-cell-right fs-lg-4 fs-xl-3">
		<?php include SERVER_ROOT."templates/layouts/_subnav.php"; ?>
	</div>
	<div class="fs-cell fs-lg-8 page_content">
		<div class="typography">
			<?=$page_content?>
		</div>
		<?php include SERVER_ROOT."templates/layouts/_callouts-content.php"; ?>
	</div>
	<div class="fs-cell-right fs-lg-4 fs-xl-3">
		<?php include SERVER_ROOT."templates/layouts/_callouts-sidebar.php"; ?>
	</div>
</div>
<?php include SERVER_ROOT."templates/layouts/_callouts-full.php"; ?>