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
	
	// Switch to loading the content of this page into the /templates/layouts/home.php file.
	$bigtree["layout"] = "home";
	
	// Get all approved features in positioned order.
	$featuresMod = new SampleFeatures;
	$features = $featuresMod->getApproved("position DESC, id ASC");	
?>
<section id="feature">
	<div class="background" style="background-color: #<?=$features[0]["background"]?>">
		<div class="row_12 content">
			<div class="descriptions cell_5 right" style="background-color: #<?=$features[0]["background"]?>">
				<menu class="triggers">
					<? for ($i = 0, $count = count($features); $i < $count; $i++) { ?>
					<span<? if ($i == 0) { ?> class="active"<? } ?>><? echo ($i + 1) ?></span>
					<? } ?>
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
			?>
			<figure class="image<? if ($i == 0) { ?> active<? } ?>">
				<img src="<?=$feature["image"]?>" alt="" />
			</figure>
			<?
					$i++;
				}
			?>
		</div>
	</div>
</section>

<section class="home_callouts">
	<?
		foreach ($bigtree["page"]["callouts"] as $callout) {
			include "../templates/callouts/".$callout["type"].".php";
		}
	?>
</section>