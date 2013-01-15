<?
	$month = $bigtree["commands"][0];
	if (is_numeric(end($bigtree["commands"]))) {
		$current_page = end($bigtree["commands"]);
	} else {
		$current_page = 0;
	}

	$posts = $dogwood->getPageOfPostsInMonth($current_page,$month,5);
	if ($current_page) {
		$local_title = "Page ".($current_page + 1)." of stories posted in " . date("F Y",strtotime($month));	
	} else {
		$local_title = "Posted in ".date("F Y",strtotime($month));
	}
?>
<div class="cell_11">
	<h3>Posted in <?=date("F Y",strtotime($month))?></h3>
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
	<a class="dogwood_newer_posts" href="<?=$blog_link?>month/<?=$month?>/<?=($current_page - 1)?>/">&laquo; Newer Posts</a>
	<?
		}
		
		if ($dogwood->getPostCountInMonth($month) > (count($posts) + ($current_page * 5))) {
	?>
	<a class="dogwood_older_posts" href="<?=$blog_link?>month/<?=$month?>/<?=($current_page + 1)?>/">Older Posts &raquo;</a>
	<?
		}
	?>
</div>