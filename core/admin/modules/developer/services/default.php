<?php
	// Route to common if we hit something in a sub directory that doesn't exist.
	if (count($bigtree["commands"])) {
		include BigTree::path("admin/modules/developer/services/_".$bigtree["commands"][0].".php");
		if ($bigtree["commands"][1]) {
			include BigTree::path("admin/modules/developer/services/common/".$bigtree["commands"][1].".php");
		} else {
			include BigTree::path("admin/modules/developer/services/common/default.php");
		}
	} else {	
		// Figure out which are connected
		$facebook = new BigTreeFacebookAPI;
		$twitter = new BigTreeTwitterAPI;
		$instagram = new BigTreeInstagramAPI;
		$google = new BigTreeGooglePlusAPI;
		$youtube = new BigTreeYouTubeAPI;
		$flickr = new BigTreeFlickrAPI;
		$disqus = new BigTreeDisqusAPI;
		$salesforce = new BigTreeSalesforceAPI;
?>
<div class="table">
	<summary><h2>Configure</h2></summary>
	<section>
		<a class="box_select<?php if ($facebook->Connected) { ?> connected<?php } ?>" href="facebook/">
			<span class="facebook"></span>
			<p>Facebook</p>
		</a>

		<a class="box_select<?php if ($twitter->Connected) { ?> connected<?php } ?>" href="twitter/">
			<span class="twitter"></span>
			<p>Twitter</p>
		</a>
		
		<a class="box_select<?php if ($instagram->Connected) { ?> connected<?php } ?>" href="instagram/">
			<span class="instagram"></span>
			<p>Instagram</p>
		</a>
		
		<a class="box_select<?php if ($google->Connected) { ?> connected<?php } ?>" href="googleplus/">
			<span class="googleplus"></span>
			<p>Google+</p>
		</a>
		
		<a class="box_select<?php if ($youtube->Connected) { ?> connected<?php } ?>" href="youtube/">
			<span class="youtube"></span>
			<p>YouTube</p>
		</a>
		
		<a class="box_select<?php if ($flickr->Connected) { ?> connected<?php } ?>" href="flickr/">
			<span class="flickr"></span>
			<p>Flickr</p>
		</a>
		
		<a class="box_select last<?php if ($disqus->Connected) { ?> connected<?php } ?>" href="disqus/">
			<span class="disqus"></span>
			<p>Disqus</p>
		</a>

		<a class="box_select second_row<?php if ($salesforce->Connected) { ?> connected<?php } ?>" href="salesforce/">
			<span class="cloud"></span>
			<p>Salesforce</p>
		</a>
	</section>
</div>
<?php
	}
?>