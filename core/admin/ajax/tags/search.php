<?php
	$tags = $admin->searchTags($_POST["tag"]);
	foreach ($tags as $tag) {
?>
<li><a href="#"><?php if ($tag == $_POST["tag"]) { ?><span><?=htmlspecialchars($tag)?></span><?php } else { ?><?=htmlspecialchars($tag)?><?php } ?></a></li>
<?php
	}
?>