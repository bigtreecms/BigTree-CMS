<?
	/*
		Resources Available:
		"title" = Title - Text
		"image" = Image - Text
		"description" = Description - HTML Area
		"link" = Link - Text
	*/
?>
<div class="callout grey_block">
	<h4><?=$callout["title"]?></h4>
	<? 
		if ($callout["image"]) { 
			$lrg = BigTree::prefixFile($callout["image"], "lrg_");
			$med = BigTree::prefixFile($callout["image"], "med_");
			$sm = BigTree::prefixFile($callout["image"], "sm_");
	?>
	<figure>
		<img src="<?=$lrg?>" alt="Flexible Callout: <?=$callout["title"]?>"  class="responder" data-xlarge="<?=$lrg?>" data-large="<?=$med?>" data-medium="<?=$sm?>" data-small="<?=$sm?>" />
	</figure>
	<? 
		}
		
		echo $callout["description"];
	
		if ($callout["link"]) { 
	?>
	<a href="<?=$callout["link"]?>" class="more">Learn More</a>
	<? 
		} 
	?>
</div>