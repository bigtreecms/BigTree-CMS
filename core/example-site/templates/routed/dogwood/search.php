<?
	if ($_POST["query"]) {
		BigTree::redirect($blog_link."search/".urlencode($_POST["query"])."/0/");
	}

	$query = $commands[0];
	$current_page = $commands[1];

	$posts = $dogwood->getSearchPageOfPosts($query,$current_page,5);
	$dogwood_title = "Results for '" . htmlspecialchars($query) . "'";
?>
<div class="cell_11">
	<h3>Results for &ldquo;<?=htmlspecialchars($query)?>&rdquo;</h3>
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
	<a class="dogwood_newer_posts" href="<?=$blog_link?>search/<?=urlencode($query)?>/<?=($current_page - 1)?>/">Newer Posts &raquo;</a>
	<?
		}
		
		if ($dogwood->getSearchPostCount($query) > (count($posts) + ($current_page * 5))) {
	?>
	<a class="dogwood_older_posts" href="<?=$blog_link?>search/<?=urlencode($query)?>/<?=($current_page + 1)?>/">&laquo; Older Posts</a>
	<?
		}
	?>
</div>