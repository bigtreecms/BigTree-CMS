<?php
	namespace BigTree;
?>
<div class="container">
	<summary>
		<h2><?=Text::translate("Core Interfaces")?></h2>
	</summary>
	<section>
		<?php
			foreach (ModuleInterface::$CoreTypes as $route => $interface) {
		?>
		<h3><span class="icon_small_<?=$interface["icon"]?>"></span> <?=Text::translate($interface["name"])?></h3>
		<p><?=Text::translate($interface["description"])?></p>
		<a href="<?=DEVELOPER_ROOT?>modules/<?=$route?>/add/?module=<?=$_GET["module"]?>" class="button shorter"><?=Text::translate("Add ".$interface["name"])?></a>
		<hr />
		<?php
			}
		?>
	</section>
</div>
<?php
	Extension::initializeCache();
	if (count(ModuleInterface::$Plugins)) {
?>
<div class="container">
	<summary>
		<h2><?=Text::translate("Extension Interfaces")?></h2>
	</summary>
	<section>
		<?php
			foreach (ModuleInterface::$Plugins as $extension => $interfaces) {
				foreach ($interfaces as $id => $interface) {
		?>
		<h3><?php if ($interface["icon"]) { ?><span class="icon_small_<?=$interface["icon"]?>"></span> <?php } ?><?=$interface["name"]?></h3>
		<p><?=$interface["description"]?></p>
		<a href="<?=DEVELOPER_ROOT?>modules/interfaces/build/<?=htmlspecialchars($extension)?>/<?=$id?>/?module=<?=$_GET["module"]?>" class="button shorter"><?=Text::translate("Add")?> <?=$interface["name"]?></a>
		<hr />
		<?php
				}
			}
		?>
	</section>
</div>
<?php
	}
?>