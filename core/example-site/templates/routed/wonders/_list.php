<div class="container_12 contain">
	<div class="grid_12">
		<h1>Past Wonders</h1>
	</div>
</div>
<section class="wonder_list">
	<div class="container_12 contain">
		<?
			$current = array_shift($wonders);
			foreach ($wonders as $wonder) {
				$image = BigTree::prefixFile($wonder["image"], "lrg_");
		?>
		<article>
			<a href="<?=$wonderLink?><?=$wonder["route"]?>/">
				<div class="grid_9">
					<img src="<?=$image?>" alt="<?=$wonder["title"]?>" />
				</div>
				<div class="grid_3">
					<h2><?=$wonder["title"]?></h2>
				</div>
			</a>
		</article>
		<?
			}
		?>
	</div>
</section>