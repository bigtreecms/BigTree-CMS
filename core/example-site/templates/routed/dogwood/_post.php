<? 
	$tags = $dogwood->getTagsForPost($post);
?>
<article class="post<? if ($last) { ?> last_post<? } ?>">
	<h2><a href="<?=$blog_link?>post/<?=$post["route"]?>/"><?=$post["title"]?></a></h2>
	<div class="meta">
		By <a href="<?=$blog_link?>author/<?=$post["author"]["route"]?>/" class="author"><?=$post["author"]["name"]?></a> on <span class="date"><?=date("F j, Y",strtotime($post["date"]))?></span>
	</div>
	<?=$post["blurb"]?>
	<div class="links">
		<a href="<?=$blog_link?>post/<?=$post["route"]?>/" class="more">Continue Reading</a>
		<?
			if ($settings["disqus"]) {
		?>
		<a href="<?=$blog_link?>post/<?=$post["route"]?>/#disqus_thread" class="more">Comments</a>
		<?
			}
			
			if (count($tags)) {
				echo "&nbsp;|&nbsp;&nbsp;Tagged:&nbsp;&nbsp;";
				$tag_links = array();
				foreach ($tags as $tag) {
					$tag_links[] = '<a href="'.$blog_link.'tag/'.$tag["route"].'/">'.$tag["tag"].'</a>';
				}
				echo implode(", ",$tag_links);
			}
		?>
	</div>
</article>