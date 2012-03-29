<nav class="sub">
	<ul>
		<? foreach ($actions as $a) { ?>
		<li><a href="<?=$admin_root?><?=$module["route"]?>/<? if ($a["route"]) { echo $a["route"]."/"; } ?>"<? if (end($path) == $a["route"]) { ?> class="active"<? } ?>><span class="icon_small icon_small_<?=$a["class"]?>"></span><?=$a["name"]?></a></li>
		<? } ?>
	</ul>
</nav>