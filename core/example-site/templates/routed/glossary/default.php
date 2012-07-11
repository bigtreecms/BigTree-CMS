<?
	/*
		Resources Available:
		$page_header = Page Header - Text
		$page_content = Page Content - HTML Area
	*/
	
	$glossaryMod = new SampleGlossary;
	
	$term = false;
	if (isset($commands[0])) {
		$term = $glossaryMod->getByRoute($commands[0]);
	}
	
	if (!$term) {
		$terms = $glossaryMod->getApproved();
		header("Location: ".$cms->getLink($page["id"]).$terms[0]["route"]."/");
		die();
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