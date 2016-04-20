<?php
	$_POST["live"] = true;
	$_POST["resources"]["page_content"] = $_POST["content"];
	$seo = $admin->getPageSEORating($_POST,$_POST["resources"]);
	$seo_rating = $seo["score"];
	$seo_recommendations = $seo["recommendations"];
	$seo_color = $seo["color"];
	
	$goal_text = "<strong>".Text::translate("SEO Goals")."</strong>";
	if (count($seo_recommendations)) {
		$goal_text .= "<ul>";
		foreach ($seo_recommendations as $rec) {
			$goal_text .= "<li>".Text::translate($rec, true)."</li>";
		}
		$goal_text .= "</ul>";
	} else {
		$goal_text .= "<p>".Text::translate("You currently meet all recommended SEO goals.")."</p>";
	}
	
	echo $goal_text;
?>
<script>
	$("li.seo_info p").html('<strong style="color: <?=$seo_color?>"><?=$seo_rating?>%</strong> <?=Text::translate("SEO Rating")?>');
	$(".seo_goals").html("<?=str_replace('"','\"',$goal_text)?>");
</script>