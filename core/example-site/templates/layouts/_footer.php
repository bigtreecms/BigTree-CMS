<?
	$footerAddress = $cms->getSetting("footer-address");
	$footerSocial = $cms->getSetting("footer-social-links");
	$aboutMe = $cms->getPage(3);
	
	// Trim just the address
	$start = strpos($footerAddress, "<span>") + 6;
	$end = strpos($footerAddress, "</span>");
	$geoAddress = substr($footerAddress, $start, ($end - $start));
	$geoAddress = geocodeAddress($geoAddress);
?>
		<footer id="footer">
			<div class="container_12 contain">
				<div class="grid_4 address">
					<h6>Contact</h6>
					<?=$footerAddress?>
					<img src="http://maps.googleapis.com/maps/api/staticmap?center=<?=$geoAddress["latitude"]?>,<?=$geoAddress["longitude"]?>&zoom=15&size=240x100&markers=color:red%7C<?=$geoAddress["latitude"]?>,<?=$geoAddress["longitude"]?>&sensor=false" alt="" />
				</div>
				<div class="grid_4">
					<h6><?=$aboutMe["resources"]["page_header"]?></h6>
					<?
						if ($aboutMe["resources"]["photo_file"] != "") {
							$photoFile = BigTree::prefixFile($aboutMe["resources"]["photo_file"], "tiny_");
					?>
					<img src="<?=$photoFile?>" alt="Image: <?=$aboutMe["resources"]["page_header"]?>" class="block_left" />
					<?
						}
						
						echo BigTree::trimLength($aboutMe["resources"]["page_content"], 360);
					?>
				</div>
				<div class="grid_4 social">
					<h6>Accounts</h6>
					<p>
						<?
							foreach ($footerSocial as $socialLink) {
						?>
						<a href="<?=$socialLink["link"]?>" class="<?=strtolower($socialLink["title"])?>" target="_blank"><?=$socialLink["title"]?></a>
						<?
							}
						?>
					</p>
				</div>
				<div class="grid_12 clear copyright">
					<p>
						&copy; <?=$site_title?>
					</p>
				</div>
			</div>
		</footer>
		<script src="<?=$www_root?>js/site.js"></script>
	</body>
</html>