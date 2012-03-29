<?
	if (class_exists("BTXWikipediaAPI")) {
		$btxInstagramAPI = new BTXInstagramAPI;
		$instagramImages = $btxInstagramAPI->searchTag($currentWonder["title"], 20);
		$instagramCount = floor(count($instagramImages["data"]) / 4) * 4;
		$instagramImages = array_slice($instagramImages["data"], 0, $instagramCount);
	}
?>
<div class="inner">
	<figure class="grid_8 large">
		<img src="<?=$instagramImages[0]["images"]["standard_resolution"]["url"]?>" height="600" width="600" alt="" />
		<figcaption>
			<p><?=$instagramImages[0]["caption"]["text"]?></p>
			<strong><?=$instagramImages[0]["user"]["username"]?></strong>
		</figcaption>
	</figure>
	<div class="grid_4 thumbnails">
		<h3>The Sights</h3>
		<div class="contain">
			<?
				$i = 1;
				foreach ($instagramImages as $item) {
			?>
			<a href="<?=$item["link"]?>" target="_blank" class="pic<? if ($i == 1) { echo ' active'; } ?><? if ($i % 4 == 0) { echo ' end'; } ?>" data-large="<?=$item["images"]["standard_resolution"]["url"]?>" data-caption="<?=$item["caption"]["text"]?>" data-user="<?=$item["user"]["username"]?>"><span style="background-image: url(<?=$item["images"]["thumbnail"]["url"]?>);"><?=$i?></span></a>
			<?
					$i++;
				}
			?>
		</div>
		<p>
			<a href="http://www.instagram.com" target="_blank" class="more">Find More On Instagram</a>
		</p>
	</div>
</div>