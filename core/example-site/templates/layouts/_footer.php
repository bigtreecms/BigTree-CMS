<?php
	$social_nav = $cms->getSetting("navigation-social");

	$navigation_options = [
		"type"    => "reveal",
		"gravity" => "right"
	];
?>
			<footer class="footer">
				<div class="fs-row">
					<div class="fs-cell fs-md-half fs-lg-half">
						<a href="<?=WWW_ROOT?>" class="footer_logo"><?=$home_page["title"]?></a>
						<p class="footer_content">
							<strong>Disclaimer:</strong> We are not actual lumberjacks. <br>
							&copy; <?=date("Y")?> Timber Lumberjack Co
						</p>
					</div>

					<div class="fs-cell-right fs-md-2 fs-lg-4 fs-xl-3">
						<nav class="social_nav">
							<h2 class="social_nav_heading">Stay Connected</h2>
							<?php
								foreach ($social_nav as $item) {
							?>
							<div class="social_nav_item">
								<a href="<?=$item["link"]?>" class="social_nav_link"><?=$item["title"]?></a>
							</div>
							<?php
								}
							?>
						</nav>
					</div>
				</div>
			</footer>
		</div>

		<div class="mobile_sidebar js-navigation" data-navigation-options="<?=htmlspecialchars(json_encode($navigation_options))?>" data-navigation-handle=".js-navigation_handle" data-navigation-content=".js-navigation_content">
			<nav class="main_nav main_nav_mobile">
				<h2 class="nav_heading">Main Navigation</h2>
				<?php
					foreach ($primary_nav as $item) {
				?>
				<div class="main_nav_item">
					<a href="<?=$item["link"]?>" class="main_nav_link<?php if (strpos($current_url, $item["link"]) !== false) { ?> active<?php } ?>"<?php if ($item["new_window"]) { ?> target="_blank"<?php } ?>><?=$item["title"]?></a>
				</div>
				<?php
					}
				?>
			</nav>

			<nav class="secondary_nav secondary_nav_mobile">
				<h2 class="nav_heading">Secondary Navigation</h2>
				<?php
					foreach ($secondary_nav as $item) {
				?>
				<div class="secondary_nav_item">
					<a href="<?=$item["link"]?>" class="secondary_nav_link"><?=$item["title"]?></a>
				</div>
				<?php
					}
				?>
			</nav>
		</div>

		<script src="<?=STATIC_ROOT?>js/site.js?<?=filemtime(SITE_ROOT."js/main.js")?>"></script>
	</body>
</html>