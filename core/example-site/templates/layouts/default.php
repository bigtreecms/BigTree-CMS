<? include "_header.php" ?>
<div class="row_12" id="subpage">
	<aside class="cell_3 sidebar">
		<?
			// Get the top level page of the one we're on, then get all navigation below it and draw it.
			$topLevel = $cms->getToplevelNavigationId();
			$nav = $cms->getNavByParent($topLevel, 2);
			if (count($nav)) {
		?>
		<div class="cell_10" style="margin: 0;">
			<nav class="subnav">
				<a href="#" class="nav_label">Navigation</a>
				<div class="nav_options">
					<? include "_subnav.php" ?>
				</div>
			</nav>
		</div>
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
	<div class="cell_9 content">
		<?=$bigtree["content"]?>
	</div>
</div>
<? include "_footer.php" ?>