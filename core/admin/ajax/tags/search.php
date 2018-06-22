<?php
	$tags = $admin->searchTags($_POST["tag"], true);

	foreach ($tags as $tag) {
?>
<li>
	<a href="#" data-tag="<?=BigTree::safeEncode($tag["tag"])?>"<?php if ($tag["tag"] == strtolower($_POST["tag"])) { ?> class="match"<?php } ?>>
		<?=BigTree::safeEncode($tag["tag"])?>
		<span class="tag_usage_count">(<?=$tag["usage_count"]?>)</span>
	</a>
</li>
<?php
	}
?>