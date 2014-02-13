<?
	$quotesMod = new DemoQuotes;
	$quotes = $quotesMod->getApproved("position DESC, id ASC");
?>
<div class="page">
	<article class="row">
		<div class="mobile-full tablet-4 tablet-push-1 desktop-6 desktop-push-3">
			<h1><?=$page_header?></h1>
			<hr />
			<? foreach ($quotes as $quote) { ?>
			<blockquote>
				<p><?=$quote["quote"]?></p>
				<span class="author"><?=$quote["author"]?><? if ($quote["source"]) { ?>, <em><?=$quote["source"]?></em><? } ?></span>
			</blockquote>
			<? } ?>
		</div>
		<? include "../templates/layouts/_callouts.php" ?>
	</article>
</div>