<?
	// Check whether our database is running the latest revision of BigTree or not.
	$current_revision = $cms->getSetting("bigtree-internal-revision");
	if ($current_revision < BIGTREE_REVISION && $admin->Level > 1) {
		BigTree::redirect(ADMIN_ROOT."dashboard/update/");
	}
?>
<div class="table">
	<summary><h2>Configure</h2></summary>
	<section>
		<a class="box_select" href="twitter/">
			<span class="twitter"></span>
			<p>Twitter</p>
		</a>
		
		<a class="box_select" href="instagram/">
			<span class="instagram"></span>
			<p>Instagram</p>
		</a>
		
		<a class="box_select" href="googleplus/">
			<span class="googleplus"></span>
			<p>Google+</p>
		</a>
		
		<a class="box_select" href="youtube/">
			<span class="youtube"></span>
			<p>YouTube</p>
		</a>
		
		<a class="box_select" href="flickr/">
			<span class="flickr"></span>
			<p>Flickr</p>
		</a>
		
	</section>
</div>