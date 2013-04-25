<?
	/*
		Resources Available:
		$page_header = Page Header - Text
		$page_content = Page Content - HTML Area
	*/
	
	$glossaryMod = new SampleGlossary;
	$term = false;
	
	// If we have a URL route, grab a term for it.
	if (isset($bigtree["commands"][0])) {
		$term = $glossaryMod->getByRoute($bigtree["commands"][0]);
	}
	
	// If we don't have a term, redirect with a 301 to the first approved term.
	if (!$term) {
		$terms = $glossaryMod->getApproved();
		BigTree::redirect(WWW_ROOT.$bigtree["page"]["path"]."/".$terms[0]["route"]."/",301);
	}
?>
<article>
	<h1><?=$page_header?></h1>
	<hr />
	<br />
	<h2><?=$term["term"]?></h2>
	<p><?=$term["description"]?></p>
	<br /><br />
	<?=$page_content?>
</article>