<?
	$module = $admin->getModule($_GET["module"]);
?>
<div class="container">
	<form method="post" action="<?=DEVELOPER_ROOT?>modules/embeds/create/<?=$module["id"]?>/" class="module">
		<? include BigTree::path("admin/modules/developer/modules/embeds/_form.php") ?>
		<section class="sub" id="field_area">
			<p>Please choose a table to populate this area.</p>
		</section>
		<footer>
			<input type="submit" class="button blue" value="Create" />
		</footer>
	</form>
</div>

<? include BigTree::path("admin/modules/developer/modules/forms/_js.php") ?>