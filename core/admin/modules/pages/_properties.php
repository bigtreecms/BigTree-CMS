<?
	$live_url = false;
	$preview_url = false;
	$age = floor((time() - strtotime($page["updated_at"])) / (60 * 60 * 24));
	$seo = $admin->getPageSEORating($page,$page["resources"]);
	if (isset($page["id"]) && is_numeric($page["id"])) {
		if ($page["id"] == 0) {
			$live_url = WWW_ROOT;
		} else {
			$live_url = WWW_ROOT.$page["path"]."/";
		}
		if (isset($page["changes_applied"])) {
			$status = "Changes Pending";
			if ($page["id"] == 0) {
				$preview_url = WWW_ROOT."_preview/";
			} else {
				$preview_url = WWW_ROOT."_preview/".$page["path"]."/";
			}
		} else {
			$status = "Published";
		}
	} else {
		$preview_url = WWW_ROOT."_preview-pending/".$page["id"]."/";
		$status = "Unpublished";
	}
	
	$open = (isset($_COOKIE["bigtree_admin"]["page_properties_open"]) && $_COOKIE["bigtree_admin"]["page_properties_open"]) ? true : false;
	
	$seo_recs = "<ul>";
	foreach ($seo["recommendations"] as $rec) {
		$seo_recs .= "<li>$rec</li>";
	}
	if (!count($seo["recommendations"])) {
		$seo_recs .= "<li>Keep up the good work!</li>";
	}
	$seo_recs .= "</ul>";
?>
<h3 class="properties"><span>Properties</span><span class="icon_small icon_small_caret_<? if ($open) { ?>down<? } else { ?>right<? } ?>"></span></h3>
<section class="inset_block property_block"<? if (!$open) { ?> style="display: none;"<? } ?>>
	<article>
		<label>Status</label>
		<p class="<?=str_replace(" ","_",strtolower($status))?>"><?=$status?></p>
	</article>
	<article class="seo">
		<label>SEO Rating</label>
		<p style="color: <?=$seo["color"]?>"><span><?=$seo["score"]?>%</span><a href="#" class="icon_small icon_small_help"></a></p>
	</article>
	<article>
		<label>Content Age</label>
		<p><?=$age?> Days</p>
	</article>
	<article class="page_id">
		<label>Page ID</label>
		<p><?=$page["id"]?></p>
	</article>
	<?
		if ($live_url) {
	?>
	<article class="link">
		<label>Live URL</label>
		<p><a href="<?=$live_url?>" target="_blank"><?=$live_url?></a></p>
	</article>
	<?
		}
		
		if ($preview_url) {
	?>
	<article class="link">
		<label>Preview URL</label>
		<p><a href="<?=$preview_url?>" target="_blank"><?=$preview_url?></a></p>
	</article>
	<?
		}
	?>
</section>
<hr <? if ($open) { ?>style="display: none;" <? } ?>/>

<script>
	$(document).ready(function() {
		new BigTreeToolTip(".seo .icon_small_help","<p><strong>SEO Goals</strong></p><?=$seo_recs?>","below","seo",true);
	});
</script>