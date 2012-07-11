<?
	$post_detail = true;
	$post = $dogwood->getPostByRoute(end($commands));
	$tags = $dogwood->getTagsForPost($post);
?>
<article class="row post post_page wysiwyg">
	<div class="cell_11">
		<h1><?=$post["title"]?></h1>
		<div class="meta">
			By <a href="<?=$blog_link?>author/<?=$post["author"]["route"]?>/" class="author"><?=$post["author"]["name"]?></a> on <span class="date"><?=date("F j, Y",strtotime($post["date"]))?></span>
		</div>
		<?
			if ($post["image"]) {
			?>
		<figure class="image">
			<img src="<?=$post["image"]?>" alt="Image" />
			<figcaption class="caption"><?=$post["caption"]?></figcaption>
		</figure>
		<?
		    }
		    echo $post["content"];
		    
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
					if ($post["author"]["image"] != "") { 
						$authorImage = BigTree::prefixFile($post["author"]["image"], "sm_");
				?>
				<img src="<?=$authorImage?>" alt="Author" />
				<? 
					} 
				?>
				<strong><?=$post["author"]["name"]?></strong>
				<?=$post["author"]["title"]?>
			</a>
		</div>
		<div class="sharing split right">
			<!-- AddThis Button BEGIN -->
			<div class="addthis_toolbox addthis_default_style right">
				<a class="addthis_button_facebook_like" fb:like:layout="button_count"></a>
				<a class="addthis_button_tweet"></a>
			</div>
			<!-- AddThis Button END -->
		</div>
	</div>
	<div class="cell_12 comments">
		<?
			if ($settings["disqus"]) {
		?>
		<div id="disqus_thread"></div>
		<?
			} else {
		?>
		<p class="disqus_notice">
			Enable comments by entering your <a href="http://www.disqus.com/" target="_blank">Disqus</a> shortname in the blog settings.
		</p>
		<?
			}
		?>
	</div>
</article>