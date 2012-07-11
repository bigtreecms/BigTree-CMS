<?
	$month = $commands[0]."-01";
	if (is_numeric(end($commands))) {
		$current_page = end($commands);
	} else {
		$current_page = 0;
	}

	$posts = $dogwood->getPageOfPostsInMonth($current_page,$month,5);
	$dogwood_title = "Posted in " . date("F Y",strtotime($month));
?>
<div class="cell_11">
	<h3>Posted in <?=date("F Y",strtotime($month))?></h3>
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
	<a class="dogwood_newer_posts" href="<?=$blog_link?>month/<?=$month?>/<?=($current_page - 1)?>/">Newer Posts &raquo;</a>
	<?
		}
		
		if ($dogwood->getPostCountInMonth($month) > (count($posts) + ($current_page * 5))) {
	?>
	<a class="dogwood_older_posts" href="<?=$blog_link?>month/<?=$month?>/<?=($current_page + 1)?>/">&laquo; Older Posts</a>
	<?
		}
	?>
</div>