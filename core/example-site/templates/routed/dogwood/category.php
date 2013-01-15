<?
	$category = $dogwood->getCategoryByRoute($bigtree["commands"][0]);
	// If this category doesn't exist, throw a 404 error.
	if (!$category) {
		$cms->catch404();
	}
	
	if (is_numeric(end($bigtree["commands"]))) {
		$current_page = end($bigtree["commands"]);
	} else {
		$current_page = 0;
	}

	$posts = $dogwood->getPageOfPostsInCategory($current_page,$category,5);
	
	if ($current_page) {
		$local_title = "Page ".($current_page + 1)." of stories posted in " . $category["title"];
	} else {
		$local_title = "Posted in " . $category["title"];		
	}
?>
<div class="cell_11">
	<h3>Posted in <?=$category["title"]?></h3>
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
	
		if ($current_page) {
	?>
	<a class="dogwood_newer_posts" href="<?=$blog_link?>category/<?=$category["route"]?>/<?=($current_page - 1)?>/">&laquo; Newer Posts</a>
	<?
		}
		
		if ($dogwood->getPostCountInCategory($category) > (count($posts) + ($current_page * 5))) {
	?>
	<a class="dogwood_older_posts" href="<?=$blog_link?>category/<?=$category["route"]?>/<?=($current_page + 1)?>/">Older Posts &raquo;</a>
	<?
		}
	?>
</div>