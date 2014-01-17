<?
	BigTree::globalizePOSTVars();

	$module = $admin->getModule(end($bigtree["path"]));
	$id = $admin->createModuleReport($title,$table,$type,$filters,$fields,$parser,$view);
	$report_route = $admin->createModuleAction($module["id"],$title,$admin->uniqueModuleActionRoute($module["id"],"report"),"on","export",false,false,$id);
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