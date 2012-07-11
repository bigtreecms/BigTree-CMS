<?
	$author = $dogwood->getAuthorByRoute($commands[0]);	
	if (!$author) {
		$cms->catch404();
	}

	if (is_numeric(end($commands))) {
		$current_page = end($commands);
	} else {
		$current_page = 0;
	}

	$posts = $dogwood->getPageOfPostsByAuthor($current_page,$author,5);
	$dogwood_title = "Posted by " . $author["name"];
?>
<div class="cell_11">
	<h3>Posted by <?=$author["name"]?></h3>
	<?
		if ($current_page == 0) {
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
	<a class="dogwood_newer_posts" href="<?=$blog_link?>author/<?=$author["route"]?>/<?=($current_page - 1)?>/">Newer Posts &raquo;</a>
	<?
		}
		
		if ($dogwood->getPostCountForAuthor($author) > (count($posts) + ($current_page * 5))) {
	?>
	<a class="dogwood_older_posts" href="<?=$blog_link?>author/<?=$author["route"]?>/<?=($current_page + 1)?>/">&laquo; Older Posts</a>
	<?
		}
	?>
</div>