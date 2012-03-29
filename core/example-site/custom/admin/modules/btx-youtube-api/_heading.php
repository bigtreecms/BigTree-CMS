<h1>
	<span class="<?=$view["icon"]?>"></span><?=$view["title"]?>
	<? if (count($subnav)) { ?>
	<div class="jump_group">
		<span class="icon"></span>
		<div class="dropdown">
			<strong><?=$mgroup["name"]?></strong>
			<? foreach ($subnav as $link) { ?>
			<a href="<?=$admin_root?><?=$link["link"]?>"><?=$link["title"]?></a>
			<? } ?>
		</div>
	</div>
	<? } ?>
</h1>