<?
	$tag = $cms->getTagByRoute($commands[0]);
	if (!$tag) {
		$cms->catch404();
	}
	
	if (is_numeric(end($commands))) {
		$current_page = end($commands);
	} else {
		$current_page = 0;
	}

	$posts = $dogwood->getPageOfPostsWithTag($current_page, $tag["id"], 5);
	$dogwood_title = "Tagged '" . $tag["tag"] . "'";
?>
<div class="cell_11">
	<h3>Tagged &ldquo;<?=$tag["tag"]?>&rdquo;</h3>
	<?
		if (count($posts) > 0) {
			foreach ($posts as $post) {
				include "_post.php";
			}
		} else {
	?>
	<p>Sorry, no posts found.</p>		
	<?
		}
	
		if ($current_page > 0) {
	?>
	<a class="dogwood_newer_posts" href="<?=$blog_link?>tag/<?=$tag["route"]?>/<?=($current_page - 1)?>/">Newer Posts &raquo;</a>
	<?
		}
		
		if ($dogwood->getPostCountWithTag($tag) > (count($posts) + ($current_page * 5))) {
	?>
	<a class="dogwood_older_posts" href="<?=$blog_link?>tag/<?=$tag["route"]?>/<?=($current_page + 1)?>/">&laquo; Older Posts</a>
	<?
		}
	?>
</div>