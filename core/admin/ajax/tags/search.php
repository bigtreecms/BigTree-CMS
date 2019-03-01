<?php
	namespace BigTree;
	
	$tags = Tag::allSimilar($_POST["tag"]);

	foreach ($tags as $tag) {
?>
<li>
	<a href="#" data-tag="<?=Text::htmlEncode($tag)?>"<?php if ($tag == strtolower($_POST["tag"])) { ?> class="match"<?php } ?>>
		<?=Text::htmlEncode($tag)?>
		<span class="tag_usage_count">(<?=$tag->UsageCount?>)</span>
	</a>
</li>
<?php
	}
	