<h1>Errors Occurred</h1>
<div class="table">
	<summary>
		<p>Your submission had <?=count($fails)?> error<? if (count($fails) != 1) { ?>s<? } ?>.</p>
	</summary>
	<header>
		<span class="view_column" style="width: 250px;">Field</span>
		<span class="view_column" style="width: 668px;">Error</span>
	</header>
	<ul>
		<? foreach ($fails as $fail) { ?>
		<li>
			<section class="view_column" style="width: 250px;"><?=$fail["field"]?></section>
			<section class="view_column" style="width: 668px;"><?=$fail["error"]?></section>
		</li>
		<? } ?>
	</ul>
</div>
<a href="<?=$admin_root?>pages/edit/<?=$page?>/" class="button blue">Go Back</a>
<a href="<?=$admin_root?>pages/view-tree/<?=$_POST["parent"]?>/" class="button white">Ignore</a>