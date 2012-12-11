<?
	$module = $admin->getModule($_GET["module"]);
?>
<div class="container">
	<header>
		<h2>Module Complete</h2>
	</header>
	<section>
		<p>Your module is created.  You may access it <a href="<?=ADMIN_ROOT.$module["route"]?>/">by clicking here</a>.</p>
	</section>
</div>