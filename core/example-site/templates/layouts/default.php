<? include "_header.php" ?>
<div class="row" id="subpage">
	<? include "_breadcrumb.php" ?>
	<aside class="desktop-3 tablet-2 mobile-full sidebar">
		<?
			// Get the top level page of the one we're on, then get all navigation below it and draw it.
			$topLevel = $cms->getToplevelNavigationId();
			$nav = $cms->getNavByParent($topLevel, 2);
			if (count($nav)) {
		?>
		<nav class="subnav">
			<a href="#" class="nav_label">Navigation</a>
			<div class="nav_options">
				<? include "_subnav.php" ?>
			</div>
		</nav>
		<? 
			}
			
			// If we have callouts, draw them.
			if (count($bigtree["page"]["callouts"])) { 
		?>
		<div class="callouts clear">
			<?
				foreach ($bigtree["page"]["callouts"] as $callout) {
					include "../templates/callouts/".$callout["type"].".php";
				}
			?>
		</div>
		<? 
			}
		?>
	</aside>
	<div class="desktop-9 tablet-4 mobile-full content">
		<?=$bigtree["content"]?>
	</div>
</div>
<? include "_footer.php" ?>