<?
	/*
		Resources Available:
		$intro_title = Intro Title - Text
		$intro_content = Intro Content - HTML Area
		$intro_title_title = Intro Link Title - Text
		$intro_link = Intro Link - Text
		$intro_image = Intro Image - Upload
		$intro_image_credit = Intro Image Credit - Text
		$intro_image_link = Intro Image Link - Text
		$twitter_search = Twitter Search Phrase - Text
	*/
	
	$bigtree["layout"] = "home";
	$featuresMod = new SampleFeatures();
	$features = $featuresMod->getApproved("position DESC");
	
?>
<section id="feature">
	<div class="background" style="background-color: #<?=$features[0]["background"]?>">
		<div class="row_12 content">
			<div class="descriptions cell_5 right" style="background-color: #<?=$features[0]["background"]?>">
				<menu class="triggers">
					<? 
						for ($i = 0, $count = count($features); $i < $count; $i++) {
					?>
					<span<? if ($i == 0) { ?> class="active"<? } ?>><? echo ($i + 1) ?></span>
					<?
						}
					?>
				</menu>
				<div class="viewport">
					<?
						$i = 0; 
						foreach ($features as $feature) {
					?>
					<article class="description<? if ($i == 0) { ?>  active<? } ?>" data-background="#<?=$feature["background"]?>">
					    <h2><?=$feature["title"]?></h2>
					    <hr />
					    <p><?=BigTree::trimLength($feature["description"], 225)?></p>
					    <a href="<?=$feature["link"]?>" class="more" target="_blank">Read More</a>
					</article>
					<?
							$i++;
						}
					?>
				</div>
			</div>
			<div class="credits">
				<?
					$i = 0; 
					foreach ($features as $feature) {
				?>
				<a href="<?=$feature["image_link"]?>" target="_blank" class="credit<? if ($i == 0) { ?> active<? } ?>">
					<span class="credit_label">Photo Credit</span>
					<p class="credit_text"><?=$feature["image_credit"]?></p>
				</a>
				<?
						$i++;
					}
				?>
			</div>
		</div>
		<div class="images">
			<? 
				$i = 0;
				foreach ($features as $feature) {
					// RESPONSIVE IMAGES
					$xlrg = BigTree::prefixFile($feature["image"], "xlrg_");
					$lrg = BigTree::prefixFile($feature["image"], "lrg_");
					$med = BigTree::prefixFile($feature["image"], "med_");
					$sm = BigTree::prefixFile($feature["image"], "sm_");
			?>
			<figure class="image<? if ($i == 0) { ?> active<? } ?>">
				<img src="<?=$xlrg?>" alt="" class="responder" data-xlarge="<?=$xlrg?>" data-large="<?=$lrg?>" data-medium="<?=$med?>" data-small="<?=$sm?>" />
			</figure>
			<?
					$i++;
				}
			?>
		</div>
	</div>
</section>

<section class="intro row_12">
	<article class="intro_block">
		<div class="cell_5">
			<?
				if ($intro_image) {
					$xlrg = BigTree::prefixFile($intro_image, "xlrg_");
					$lrg = BigTree::prefixFile($intro_image, "lrg_");
					$med = BigTree::prefixFile($intro_image, "med_");
					$sm = BigTree::prefixFile($intro_image, "sm_");
			?>
			<figure>
				<img src="<?=$xlrg?>" alt=""  class="responder" data-xlarge="<?=$xlrg?>" data-large="<?=$lrg?>" data-medium="<?=$med?>" data-small="<?=$sm?>" />
				<figcaption>
					<a href="<?=$intro_image_link?>" target="_blank"><?=$intro_image_credit?></a>
				</figcaption>
			</figure>
			<?
				}
			?>
		</div>
		<div class="cell_7">
			<h2><?=$intro_title?></h2>
			<?=$intro_content?>
			<? if ($intro_link) { ?>
			<a href="<?=$intro_link?>" target="_blank" class="more"><?=($intro_link_title ? $intro_link_title : "Read More")?></a>
			<? } ?>
		</div>
	</article>
</section>

<? if ($twitter_search) { ?>
<section class="twitter_timeline loading">
	<div class="row_12">
		<header class="cell_12">
			<h3>"<?=$twitter_search?>"</h3>
			<p>Random, un-curated posts on <a href="http://www.twitter.com/" target="_blank">Twitter</a> about "<?=$twitter_search?>"</p>
			<hr />
		</header>
	</div>
	<div class="row_12 viewport">
		<div class="timeline">
			Loading
		</div>
	</div>
	<div class="triggers">
		<span class="trigger next">next</span>
		<span class="trigger previous disabled">Previous</span>
	</div>
	<script>
		var twitter_search = "<?=$twitter_search?>";
	</script>
</section>
<? } ?>