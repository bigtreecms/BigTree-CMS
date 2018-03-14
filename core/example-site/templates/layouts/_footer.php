<?php
	$main_nav = $cms->getNavByParent(0,2);
	$social_nav = $cms->getSetting("nav-social");
?>
			<footer id="footer" class="row">
				<p>
					&copy; <?=date("Y")?> <?=$home_page["nav_title"]?>
				</p>
			</footer>
		</div>
		<div class="shifter-navigation">
			<nav class="navigation">
				<div class="item home">
					<a href="<?=WWW_ROOT?>">Home</a>
				</div>
				<?php
					foreach ($main_nav as $navItem) {
						$active = (strpos($current_url,$navItem["link"]) !== false);
				?>
				<div class="item">
					<a href="<?=$navItem["link"]?>"<?php if ($active) { ?> class="active"<?php } ?><?=targetBlank($navItem["link"])?>><?=$navItem["title"]?></a>
					<?php
						if ($active && count($navItem["children"])) {
							foreach ($navItem["children"] as $child) {
					?>
					<a href="<?=$child["link"]?>" class="secondary<?php if (strpos($current_url,$child["link"]) !== false) { ?> active<?php } ?>"<?=targetBlank($child["link"])?>><?=$child["title"]?></a>
					<?php
							}
						}
					?>
				</div>
				<?php
					}
				?>
				<div class="social">
					<?php foreach ($social_nav as $navItem) { ?>
					<a href="<?=$navItem["link"]?>" class="<?=$navItem["class"]?>"<?=targetBlank($navItem["link"])?>><?=$navItem["title"]?></a>
					<?php } ?>
				</div>
				<div class="colophon">
					<?=$cms->getSetting("colophon")?>
				</div>
			</nav>
		</div>
	</body>
</html>