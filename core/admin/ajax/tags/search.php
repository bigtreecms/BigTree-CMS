<?
	$tags = $admin->searchTags($_POST["tag"]);
	foreach ($tags as $tag) {
?>
<li><a href="#"><? if ($tag == $_POST["tag"]) { ?><span><?=htmlspecialchars($tag)?></span><? } else { ?><?=htmlspecialchars($tag)?><? } ?></a></li>
<?
	}
?>