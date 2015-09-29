<?php
	$quotesMod = new DemoQuotes();
	$quotes = $quotesMod->getApproved('position DESC, id ASC');
?>
<div class="page">
	<article class="row">
		<div class="mobile-full tablet-4 tablet-push-1 desktop-6 desktop-push-3">
			<h1><?=$page_header?></h1>
			<hr />
			<?php foreach ($quotes as $quote) {
    ?>
			<blockquote>
				<p><?=$quote['quote']?></p>
				<span class="author"><?=$quote['author']?><?php if ($quote['source']) {
    ?>, <em><?=$quote['source']?></em><?php 
}
    ?></span>
			</blockquote>
			<?php 
} ?>
		</div>
		<?php include '../templates/layouts/_callouts.php' ?>
	</article>
</div>