<?
	$tag = $cms->getTagByRoute($bigtree["commands"][0]);
	// If this isn't a valid tag, throw a 404.
	if (!$tag) {
		$cms->catch404();
	}
	
	if (is_numeric(end($bigtree["commands"]))) {
		$current_page = end($bigtree["commands"]);
	} else {
		$current_page = 0;
	}

	$posts = $dogwood->getPageOfPostsWithTag($current_page, $tag["id"], 5);
	if ($current_page) {
		$local_title = "Page ".($current_page + 1)." of posts tagged &quot;".$tag["tag"]."&quot;";		
	} else {	
		$local_title = "Tagged &quot;".$tag["tag"]."&quot;";
	}
?>
<div class="cell_11">
	<h3>Tagged &ldquo;<?=$tag["tag"]?>&rdquo;</h3>
	<?
		if (count($posts)) {
			$x = 0;
			foreach ($posts as $post) {
				$x++;
				if ($x == count($posts)) {
					$last = true;
				} else {
					$last = false;
				}
				include "_post.php";
			}
		} else {
	?>
	<p>Sorry, no posts found.</p>		
	<?
		}
	
		if ($current_page > 0) {
	?>
	<a class="dogwood_newer_posts" href="<?=$blog_link?>tag/<?=$tag["route"]?>/<?=($current_page - 1)?>/">&laquo; Newer Posts</a>
	<?
		}
		
		if ($dogwood->getPostCountWithTag($tag) > (count($posts) + ($current_page * 5))) {
	?>
	<a class="dogwood_older_posts" href="<?=$blog_link?>tag/<?=$tag["route"]?>/<?=($current_page + 1)?>/">Older Posts &raquo;</a>
	<?
		}
	?>
</div>