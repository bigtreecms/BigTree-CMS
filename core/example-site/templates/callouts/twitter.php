<?
	/*
		Resources Available:
		"timeline" = Twitter Username - Text
		"query" = Twitter Search - Text
	*/	
	
	if ($callout["timeline"] || $callout["query"]) {
?>
<section class="twitter_timeline loading">
	<div class="row_12">
		<header class="cell_12">
			<? if ($callout["query"]) { ?>
			<h3>"<?=$callout["query"]?>"</h3>
			<p>Random, un-curated posts on <a href="http://www.twitter.com/" target="_blank">Twitter</a> about "<?=$callout["query"]?>"</p>
			<? } else { ?>
			<h3><?=$callout["timeline"]?></h3>
			<p>The latest posts on <a href="http://www.twitter.com/" target="_blank">Twitter</a> from the ever-wonderful <a href="http://www.twitter.com/<?=$callout["timeline"]?>"><?=$callout["timeline"]?></a>.</p>
			<? } ?>
			<hr />
		</header>
	</div>
	<div class="row_12 viewport">
		<div class="timeline">
			Loading...
		</div>
	</div>
	<div class="triggers">
		<span class="trigger next">next</span>
		<span class="trigger previous disabled">Previous</span>
	</div>
	<script>
		<? if ($callout["query"]) { ?>
		var twitter_search = "<?=$callout["query"]?>";
		<? } else { ?>
		var twitter_timeline = "<?=$callout["timeline"]?>";
		<? } ?>
		var twitter_in_sidebar = <? if ($bigtree["page"]["id"]) { ?>true<? } else { ?>false<? } ?>;
	</script>
</section>
<?
	}
?>