<?
	$author = $dogwood->getAuthorByRoute($bigtree["commands"][0]);
	// If the author wasn't found throw a 404.	
	if (!$author) {
		$cms->catch404();
	}

	if (is_numeric(end($bigtree["commands"]))) {
		$current_page = end($bigtree["commands"]);
	} else {
		$current_page = 0;
	}

	$posts = $dogwood->getPageOfPostsByAuthor($current_page,$author,5);
	if ($current_page) {
		$local_title = "Page ".($current_page + 1)." of stories posted by " . $author["name"];
	} else {
		$local_title = "Posted by " . $author["name"];
	}
?>
<div class="cell_11">
	<h3>Posted by <?=$author["name"]?></h3>
	<?
		// Show the author's bio if this is the first page.
		if (!$current_page) {
	?>
	<div class="author_bio">
		<? if ($author["image"]) { ?>
		<div class="image">
			<img src="<?=$author["image"]?>" alt="" class="block_left" />
		</div>
		<? } ?>
		<div class="contain">
			<h4><?=$author["name"]?></h4>
			<?=$author["biography"]?>
		</div>
	</div>
	<?
		}
		
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
	<a class="dogwood_newer_posts" href="<?=$blog_link?>author/<?=$author["route"]?>/<?=($current_page - 1)?>/">&laquo; Newer Posts</a>
	<?
		}
		
		if ($dogwood->getPostCountForAuthor($author) > (count($posts) + ($current_page * 5))) {
	?>
	<a class="dogwood_older_posts" href="<?=$blog_link?>author/<?=$author["route"]?>/<?=($current_page + 1)?>/">Older Posts &raquo;</a>
	<?
		}
	?>
</div>