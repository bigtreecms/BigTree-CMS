<? include "_header.php" ?>
<div class="row_12" id="subpage">
	<aside class="cell_3 sidebar">
		<?
			$currentPage = DOMAIN.$_SERVER['REQUEST_URI'];
			$topLevel = $cms->getToplevelNavigationId();
			$nav = $cms->getNavByParent($topLevel, 2);
			if (count($nav)) {
		?>
		<div class="cell_10" style="margin: 0;">
			<nav class="subnav">
				<a href="#" class="nav_label">Navigation</a>
				<div class="nav_options">
					<?
						// Found in /custom/inc/required/utils.php
						recurseNav($nav, $currentPage);
					?>
				</div>
			</nav>
		</div>
		<? 
			}
			
			if (count($page["callouts"])) { 
		?>
		<div class="callouts clear">
			<?
				foreach ($page["callouts"] as $callout) {
					include "../templates/callouts/" . $callout["type"] . ".php";
				}
			?>
		</div>
		<? 
			} 
		?>
	</aside>
	<div class="cell_9 content">
		<?=$bigtree["content"]?>
	</div>
</div>
<? include "_footer.php" ?>