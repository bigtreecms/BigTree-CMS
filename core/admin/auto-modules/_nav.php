<nav class="sub">
	<ul>
		<?
			foreach ($actions as $a) {
				// If no level is set, it's 0.
				$a["level"] = isset($a["level"]) ? $a["level"] : 0;
				
				if ($a["level"] <= $admin->Level) {
		?>
		<li><a href="<?=ADMIN_ROOT?><?=$module["route"]?>/<? if ($a["route"]) { echo $a["route"]."/"; } ?>"<? if (end($bigtree["path"]) == $a["route"]) { ?> class="active"<? } ?>><span class="icon_small icon_small_<?=$a["class"]?>"></span><?=$a["name"]?></a></li>
		<?
				}
			}
		?>
	</ul>
</nav>