<?
	$module = $admin->getModule($_GET["module"]);
?>
<div class="container">
	<summary>
		<h2>Module Complete</h2>
	</summary>
	<section>
		<p>Your module is created.  You may access it <a href="<?=ADMIN_ROOT.$module["route"]?>/">by clicking here</a>.</p>
	</section>
</div>