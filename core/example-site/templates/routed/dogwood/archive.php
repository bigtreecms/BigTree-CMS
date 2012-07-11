<?
	if (is_numeric(end($commands))) {
		$current_page = end($commands);
	} else {
		$current_page = 0;
	}

	$posts = $dogwood->getPageOfPosts($current_page,5);
	$dogwood_title = "Post Archive";
	
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
<div class="cell_11">
	<a class="dogwood_newer_posts" href="<?=$blog_link?>archive/<?=($current_page - 1)?>/">Newer Posts &raquo;</a>
	<?
		}
		
		if ($dogwood->getPostCount() > (count($posts) + ($current_page * 5))) {
	?>
	<a class="dogwood_older_posts" href="<?=$blog_link?>archive/<?=($current_page + 1)?>/">&laquo; Older Posts</a>
	<?
		}
	?>
</div>