<?php
	$breadcrumb = $cms->getBreadcrumb(true);
?>
<nav class="breadcrumb_nav clearfix">
	<h2 class="nav_heading">Breadcrumb Navigation</h2>
	<div class="breadcrumb_nav_item home">
		<a href="<?=WWW_ROOT?>" class="breadcrumb_nav_link home">Home</a>
	</div>
	<?php
		$i = 0;
		$count = count($breadcrumb);
		
		foreach ($breadcrumb as $item) {
			$i++;
	?>
	<div class="breadcrumb_nav_item">
		<?php
			if ($i == $count) {
		?>
		<span class="breadcrumb_nav_label"><?=$item["title"]?></span>
		<?php
			} else {
		?>
		<a href="<?=$item["link"]?>" class="breadcrumb_nav_link"><?=$item["title"]?></a>
		<?php
			}
		?>
	</div>
	<?php
		}
	?>
</nav>