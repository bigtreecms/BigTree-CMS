<?php
	namespace BigTree;
	
	/**
	 * @global array $bigtree
	 */
	
	// Route to common if we hit something in a sub directory that doesn't exist.
	if (count(Router::$Commands)) {
		include Router::getIncludePath("admin/modules/developer/services/_".Router::$Commands[0].".php");
		
		if (Router::$Commands[1]) {
			include Router::getIncludePath("admin/modules/developer/services/common/".Router::$Commands[1].".php");
		} else {
			include Router::getIncludePath("admin/modules/developer/services/common/default.php");
		}
	} else {	
		// Figure out which are connected
		$facebook = new Facebook\API;
		$twitter = new Twitter\API;
		$instagram = new Instagram\API;
		$youtube = new YouTube\API;
		$flickr = new Flickr\API;
		$disqus = new Disqus\API;
		$salesforce = new Salesforce\API;
?>
<div class="container">
	<div class="container_summary"><h2><?=Text::translate("Configure")?></h2></div>
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