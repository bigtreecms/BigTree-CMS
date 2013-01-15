<?
	// Once a user posts their query we redirect to a bookmarkable link (that is safe to go back to)
	if ($_POST["query"]) {
		BigTree::redirect($blog_link."search/".urlencode($_POST["query"])."/0/");
	}

	// Pull the query and current page
	$query = $bigtree["commands"][0];
	$current_page = isset($bigtree["commands"][1]) ? $bigtree["commands"][1] : 0;

	// Grab a page of results
	$posts = $dogwood->getSearchPageOfPosts($query,$current_page,5);
	if ($current_page) {
		$local_title = "Page ".($current_page + 1)." of search results for &quot;" . htmlspecialchars($query) . "&quot;";	
	} else {
		$local_title = "Search results for &quot;" . htmlspecialchars($query) . "&quot;";
	}
?>
<div class="cell_11">
	<h3>Search results for &ldquo;<?=htmlspecialchars($query)?>&rdquo;</h3>
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
	<a class="dogwood_newer_posts" href="<?=$blog_link?>search/<?=urlencode($query)?>/<?=($current_page - 1)?>/">&laquo; Newer Posts</a>
	<?
		}
		
		if ($dogwood->getSearchPostCount($query) > (count($posts) + ($current_page * 5))) {
	?>
	<a class="dogwood_older_posts" href="<?=$blog_link?>search/<?=urlencode($query)?>/<?=($current_page + 1)?>/">Older Posts &raquo;</a>
	<?
		}
	?>
</div>