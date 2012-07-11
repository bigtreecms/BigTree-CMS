<?
	$category = $dogwood->getCategoryByRoute($commands[0]);	
	if (!$category) {
		$cms->catch404();
	}
	
	if (is_numeric(end($commands))) {
		$current_page = end($commands);
	} else {
		$current_page = 0;
	}

	$posts = $dogwood->getPageOfPostsInCategory($current_page,$category,5);

	$dogwood_title = "Posted in " . $category["title"];
?>
<div class="cell_11">
	<h3>Posted in <?=$category["title"]?></h3>
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
	
		if ($current_page) {
	?>
	<a class="dogwood_newer_posts" href="<?=$blog_link?>category/<?=$category["route"]?>/<?=($current_page - 1)?>/">Newer Posts &raquo;</a>
	<?
		}
		
		if ($dogwood->getPostCountInCategory($category) > (count($posts) + ($current_page * 5))) {
	?>
	<a class="dogwood_older_posts" href="<?=$blog_link?>category/<?=$category["route"]?>/<?=($current_page + 1)?>/">&laquo; Older Posts</a>
	<?
		}
	?>
</div>