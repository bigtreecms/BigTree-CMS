<?
	$live_url = false;
	$preview_url = false;
	$page_data = $admin->getPendingPage(is_array($page) ? $page["id"] : $page);
	$age = floor((time() - strtotime($page_data["updated_at"])) / (60 * 60 * 24));
	$seo = $admin->getPageSEORating($page_data,$page_data["resources"]);
	if (isset($page_data["id"]) && is_numeric($page_data["id"])) {
		if ($page_data["id"] == 0) {
			$live_url = $www_root;
		} else {
			$live_url = $www_root.$page_data["path"]."/";
		}
		if ($page_data["changes_applied"]) {
			$status = "Changes Pending";
			$preview_url = $www_root."_preview/".$page_data["path"]."/";
		} else {
			$status = "Published";
		}
	} else {
		$preview_url = $www_root."_preview-pending/".$page."/";
		$status = "Unpublished";
	}
	
	$open = $_COOKIE["bigtree_default_properties_open"] ? true : false;
	
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
<section class="property_block"<? if (!$open) { ?> style="display: none;"<? } ?>>
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

<script type="text/javascript">
	$(document).ready(function() {
		new BigTreeToolTip(".seo .icon_small_help","<p><strong>SEO Goals</strong></p><?=$seo_recs?>","below","seo",true);
	});
</script>