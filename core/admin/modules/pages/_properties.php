<?php
	namespace BigTree;
	
	/**
	 * @global Page $page
	 */

	$live_url = false;
	$preview_url = false;
	$age = floor((time() - strtotime($page_id->UpdatedAt)) / (60 * 60 * 24));
	$seo = $page_id->SEORating;
	
	if (is_numeric($page_id->ID)) {
		if ($page_id->ID == 0) {
			$live_url = WWW_ROOT;
		} else {
			$live_url = WWW_ROOT.$page_id->Path."/";
		}
		if (isset($page_id->ChangesApplied)) {
			$status = Text::translate("Changes Pending");
			if ($page_id->ID == 0) {
				$preview_url = WWW_ROOT."_preview/";
			} else {
				$preview_url = WWW_ROOT."_preview/".$page_id->Path."/";
			}
		} else {
			$status = Text::translate("Published");
		}
	} else {
		$preview_url = WWW_ROOT."_preview-pending/".$page_id->ID."/";
		$status = Text::translate("Unpublished");
	}
	
	$open = (isset($_COOKIE["bigtree_admin"]["page_properties_open"]) && $_COOKIE["bigtree_admin"]["page_properties_open"]) ? true : false;
	
	$seo_recs = "<ul>";

	foreach ($seo["recommendations"] as $rec) {
		$seo_recs .= "<li>$rec</li>";
	}

	if (!count($seo["recommendations"])) {
		$seo_recs .= "<li>".Text::translate("Keep up the good work!")."</li>";
	}

	$seo_recs .= "</ul>";
?>
<h3 class="properties"><span><?=Text::translate("Properties")?></span><span class="icon_small icon_small_caret_<?php if ($open) { ?>down<?php } else { ?>right<?php } ?>"></span></h3>
<section class="inset_block property_block"<?php if (!$open) { ?> style="display: none;"<?php } ?>>
	<article>
		<label><?=Text::translate("Status")?></label>
		<p class="<?=str_replace(" ","_",strtolower($status))?>"><?=$status?></p>
	</article>
	<article class="seo">
		<label><?=Text::translate("SEO Rating")?></label>
		<p style="color: <?=$seo["color"]?>"><span><?=$seo["score"]?>%</span><a href="#" class="icon_small icon_small_help"></a></p>
	</article>
	<article>
		<label><?=Text::translate("Content Age")?></label>
		<p><?=$age?> <?=Text::translate("Days")?></p>
	</article>
	<article class="page_id">
		<label><?=Text::translate("Page ID")?></label>
		<p><?=$page_id->ID?></p>
	</article>
	<?php
		if ($live_url) {
	?>
	<article class="link">
		<label><?=Text::translate("Live URL")?></label>
		<p><a href="<?=$live_url?>" target="_blank"><?=$live_url?></a></p>
	</article>
	<?php
		}
		
		if ($preview_url) {
	?>
	<article class="link">
		<label><?=Text::translate("Preview URL")?></label>
		<p><a href="<?=$preview_url?>" target="_blank"><?=$preview_url?></a></p>
	</article>
	<?php
		}
	?>
</section>
<hr <?php if ($open) { ?>style="display: none;" <?php } ?>/>

<script>
	$(document).ready(function() {
		BigTreeToolTip({
			selector: ".seo .icon_small_help",
			content: "<p><strong><?=Text::translate("SEO Goals")?></strong></p><?=$seo_recs?>",
			position: "below",
			icon: "seo"
		});
	});
</script>