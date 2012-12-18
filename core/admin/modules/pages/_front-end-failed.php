<h2>Errors Occurred</h2>
<div class="bigtree_dialog_form">
	<div class="overflow">
		<div class="table">
			<summary>
				<p>Your submission had <?=count($fails)?> error<? if (count($fails) != 1) { ?>s<? } ?>.</p>
			</summary>
			<header>
				<span class="view_column" style="padding: 0 0 0 20px; width: 250px;">Field</span>
				<span class="view_column" style="width: 506px;">Error</span>
			</header>
			<ul>
				<? foreach ($fails as $fail) { ?>
				<li>
					<section class="view_column" style="padding: 0 0 0 20px; width: 250px;"><?=$fail["field"]?></section>
					<section class="view_column" style="width: 506px;"><?=$fail["error"]?></section>
				</li>
				<? } ?>
			</ul>
		</div>
	</div>
	<footer>
		<a href="<?=ADMIN_ROOT?>pages/front-end-return/<?=base64_encode($refresh_link)?>/" class="button white">Ignore</a>				
		<a href="<?=ADMIN_ROOT?>pages/front-end-edit/<?=$page?>/" class="button blue">Go Back</a>
	</footer>
</div>