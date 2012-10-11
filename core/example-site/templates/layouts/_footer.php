<?
	// Get the footer address that's stored in Settings
	$footerAddress = $cms->getSetting("footer-address");
	
	// Get social media links from Settings.
	$footerSocial = $cms->getSetting("footer-social-links");
	
	// Get information from the about page to display in the footer.
	$aboutMe = $cms->getPage(4);
	
	// Trim just the address
	$start = strpos($footerAddress, "<span>") + 6;
	$end = strpos($footerAddress, "</span>");
	$geoAddress = BigTree::geocodeAddress(substr($footerAddress, $start, ($end - $start)));
?>
		<footer id="footer">
			<div class="row_12 contain">
				<div class="cell_3 address">
					<h6>Contact</h6>
					<?=$footerAddress?>
					<img src="http://maps.googleapis.com/maps/api/staticmap?center=<?=$geoAddress["latitude"]?>,<?=$geoAddress["longitude"]?>&amp;zoom=15&amp;size=240x100&amp;markers=color:red%7C<?=$geoAddress["latitude"]?>,<?=$geoAddress["longitude"]?>&amp;sensor=false" alt="" />
				</div>
				<div class="cell_3 push_1 right social">
					<h6>Accounts</h6>
					<p>
						<? foreach ($footerSocial as $socialLink) { ?>
						<a href="<?=$socialLink["link"]?>" class="<?=strtolower($socialLink["title"])?>" target="_blank"><?=$socialLink["title"]?><small><?=$socialLink["subtitle"]?></small></a>
						<? } ?>
					</p>
				</div>
				<div class="cell_4 push_1 about">
					<h6><?=$aboutMe["resources"]["page_header"]?></h6>
					<?
						if ($aboutMe["resources"]["photo_file"]) {
							$photoFile = BigTree::prefixFile($aboutMe["resources"]["photo_file"], "tiny_");
					?>
					<img src="<?=$photoFile?>" alt="Image: <?=$aboutMe["resources"]["page_header"]?>" class="block_left" />
					<?
						}
						
						echo BigTree::trimLength($aboutMe["resources"]["page_content"], 360);
					?>
				</div>
				<div class="cell_12 clear copyright">
					<p>&copy; <?=$site_title?></p>
				</div>
			</div>
		</footer>
	</body>
</html>