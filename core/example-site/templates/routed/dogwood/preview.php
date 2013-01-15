<?
	// We've appended either the pending ID (p1234) or actual ID (1234) to the first command.
	$details = $dogwood->getPendingPostAndTags(end($bigtree["commands"]));
	$post = $details["post"];
	$tags = $details["tags"];

	// Set the page title
	$local_title = "PREVIEW OF: ".$post["title"];
?>
<article class="row post post_page wysiwyg">
	<div class="cell_11">
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
	</div>
	<div class="cell_12 author_info clear">
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
	</div>
</article>