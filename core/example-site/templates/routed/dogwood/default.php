<?
	$total_pages = ceil($dogwood->getPostCount() / 5);
	$total_pages = $total_pages ? $total_pages : 1;

	if (is_numeric(end($bigtree["commands"]))) {
		$current_page = end($bigtree["commands"]);
		// Only show the "Page X" if it's not the first page.
		if (end($bigtree["commands"])) {
			$local_title = "Page ".(end($bigtree["commands"])+1);
		}
	} else {
		$current_page = 0;
	}

	$posts = $dogwood->getPageOfPosts($current_page,5);
?>
<div class="cell_11">
	<?
		if (count($posts) > 0) {
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
	<a class="dogwood_newer_posts" href="<?=$blog_link?><?=($current_page - 1)?>/">&laquo; Newer Posts</a>
	<?
		}
		
		if ($total_pages > ($current_page + 1)) {
	?>
	<a class="dogwood_older_posts" href="<?=$blog_link?><?=($current_page + 1)?>/">Older Posts &raquo;</a>
	<?
		}
	?>
</div>