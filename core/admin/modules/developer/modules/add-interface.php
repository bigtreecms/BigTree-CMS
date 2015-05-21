<div class="container">
	<summary>
		<h2>Core Interfaces</h2>
	</summary>
	<section>
		<?
			foreach (BigTreeAdmin::$InterfaceTypes["core"] as $route => $interface) {
		?>
		<h3><span class="icon_small_<?=$interface["icon"]?>"></span> <?=$interface["name"]?></h3>
		<p><?=$interface["description"]?></p>
		<a href="<?=DEVELOPER_ROOT?>modules/<?=$route?>/add/?module=<?=$_GET["module"]?>" class="button shorter">Add <?=$interface["name"]?></a>
		<hr />
		<?
			}
		?>
	</section>
</div>
<?
	if (count(BigTreeAdmin::$InterfaceTypes["extension"])) {
?>
<div class="container">
	<summary>
		<h2>Extension Interfaces</h2>
	</summary>
	<section>
		<?
			foreach (BigTreeAdmin::$InterfaceTypes["extension"] as $extension => $interfaces) {
				foreach ($interfaces as $id => $interface) {
		?>
		<h3><? if ($interface["icon"]) { ?><span class="icon_small_<?=$interface["icon"]?>"></span> <? } ?><?=$interface["name"]?></h3>
		<p><?=$interface["description"]?></p>
		<a href="<?=DEVELOPER_ROOT?>modules/build-interface/<?=htmlspecialchars($extension)?>/<?=$id?>/?module=<?=$_GET["module"]?>" class="button shorter">Add <?=$interface["name"]?></a>
		<hr />
		<?
				}
			}
		?>
	</section>
</div>
<?
	}
?>