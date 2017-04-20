<?php
	namespace BigTree;
	
	$tags = Tag::allSimilar($_POST["tag"]);

	foreach ($tags as $tag) {
?>
<li><a href="#"><?php if ($tag->Name == $_POST["tag"]) { ?><span><?=htmlspecialchars($tag->Name)?></span><?php } else { ?><?=htmlspecialchars($tag->Name)?><?php } ?></a></li>
<?php
	}
	