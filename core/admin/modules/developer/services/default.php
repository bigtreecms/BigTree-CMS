<?
	// Check whether our database is running the latest revision of BigTree or not.
	$current_revision = $cms->getSetting("bigtree-internal-revision");
	if ($current_revision < BIGTREE_REVISION && $admin->Level > 1) {
		BigTree::redirect(ADMIN_ROOT."dashboard/update/");
	}

	// Figure out which are connected
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
		<a class="box_select<? if ($twitter->Connected) { ?> connected<? } ?>" href="twitter/">
			<span class="twitter"></span>
			<p>Twitter</p>
		</a>
		
		<a class="box_select<? if ($instagram->Connected) { ?> connected<? } ?>" href="instagram/">
			<span class="instagram"></span>
			<p>Instagram</p>
		</a>
		
		<a class="box_select<? if ($google->Connected) { ?> connected<? } ?>" href="googleplus/">
			<span class="googleplus"></span>
			<p>Google+</p>
		</a>
		
		<a class="box_select<? if ($youtube->Connected) { ?> connected<? } ?>" href="youtube/">
			<span class="youtube"></span>
			<p>YouTube</p>
		</a>
		
		<a class="box_select<? if ($flickr->Connected) { ?> connected<? } ?>" href="flickr/">
			<span class="flickr"></span>
			<p>Flickr</p>
		</a>
		
		<a class="box_select<? if ($disqus->Connected) { ?> connected<? } ?>" href="disqus/">
			<span class="disqus"></span>
			<p>Disqus</p>
		</a>

		<a class="box_select last<? if ($salesforce->Connected) { ?> connected<? } ?>" href="salesforce/">
			<span class="cloud"></span>
			<p>Salesforce</p>
		</a>
	</section>
</div>