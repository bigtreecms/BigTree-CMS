<?
	// Tell the footer to draw the comment thread if we're using Disqus
	$post_detail = true;
	// We've appended the route to the URL, so we're going to grab the last route of the URL and look it up.
	$post = $dogwood->getPostByRoute(end($bigtree["commands"]));
	// Get tags related to this post
	$tags = $dogwood->getTagsForPost($post);
	// Set the page title
	$local_title = $post["title"];
?>
<article class="post post_page wysiwyg">
	<h1><?=$post["title"]?></h1>
	<div class="meta">
		By <a href="<?=$blog_link?>author/<?=$post["author"]["route"]?>/" class="author"><?=$post["author"]["name"]?></a> on <span class="date"><?=date("F j, Y",strtotime($post["date"]))?></span>
	</div>
	<?
		// If there's an image, draw it.
		if ($post["image"]) {
	?>
	<figure class="image">
		<img src="<?=$post["image"]?>" alt="Image" />
		<figcaption class="caption"><?=$post["caption"]?></figcaption>
	</figure>
	<?
		}
		
		// Echo the full blog post.
		echo $post["content"];
		
		// If we have tags on the post, draw them.
		if (count($tags)) {
			$tag_links = array();
			foreach ($tags as $tag) {
				$tag_links[] = '<a href="'.$blog_link.'tag/'.$tag["route"].'/">'.$tag["tag"].'</a>';
			}
			echo '<p>Tagged: '.implode(", ",$tag_links).'</p>';
		}
	?>
	<section class="author_info clear">
		<div class="split left">
			<a href="<?=$blog_link?>author/<?=$post["author"]["route"]?>/">
				<? 
					// If we have an author image we're going to grab the small version (prefixed with "sm_").
					if ($post["author"]["image"]) { 
				?>
				<img src="<?=BigTree::prefixFile($post["author"]["image"], "sm_")?>" alt="" />
				<? 
					}
				?>
				<strong><?=$post["author"]["name"]?></strong>
				<?=$post["author"]["title"]?>
			</a>
		</div>
		<div class="sharing split right">
			<? /* Add This Sharing Widgets */ ?>
			<div class="addthis_toolbox addthis_default_style right">
				<a class="addthis_button_facebook_like" fb:like:layout="button_count"></a>
				<a class="addthis_button_tweet"></a>
			</div>
		</div>
	</section>
	<section class="comments">
		<?
			// If we've set our Disqus short name in the admin, draw the Disqus thread container.
			if ($settings["disqus"]) {
		?>
		<div id="disqus_thread"></div>
		<?
			// If we haven't, let them know they can set it up.
			} else {
		?>
		<p class="disqus_notice">
			Enable comments by entering your <a href="http://www.disqus.com/" target="_blank">Disqus</a> shortname in the blog settings.
		</p>
		<?
			}
		?>
	</section>
</article>