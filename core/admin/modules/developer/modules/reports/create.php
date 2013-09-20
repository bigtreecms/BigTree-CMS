<?
	BigTree::globalizePOSTVars();

	$module = $admin->getModule(end($bigtree["path"]));
	$report_route = $admin->createModuleReport($module["id"],$title,$table,$type,$filters,$fields,$parser,$view);
?>
<div class="container">
	<section>
		<h3><?=$title?></h3>
		<p>Your report has been created.</p>
	</section>
	<footer>
		<a href="<?=ADMIN_ROOT?><?=$module["route"]?>/" class="button white">View Module</a>
		<a href="<?=ADMIN_ROOT?><?=$module["route"]?>/<?=$report_route?>/" class="button blue">View Report</a>
	</footer>
</div>