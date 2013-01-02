<?
	$_POST["live"] = true;
	$_POST["resources"]["page_content"] = $_POST["content"];
	$seo = $admin->getPageSEORating($_POST,$_POST["resources"]);
	$seo_rating = $seo["score"];
	$seo_recommendations = $seo["recommendations"];
	$seo_color = $seo["color"];
	
	$goal_text = "<strong>SEO Goals</strong>";
	if (count($seo_recommendations)) {
		$goal_text .= "<ul>";
		foreach ($seo_recommendations as $rec) {
			$goal_text .= "<li>".htmlspecialchars($rec)."</li>";
		}
		$goal_text .= "</ul>";
	} else {
		$goal_text .= "<p>You currently meet all recommended SEO goals.</p>";
	}
	$goal_text .= '<p><a href="'.$wiki.'SEO" target="wiki">Learn More About SEO Goals</a></p>';
?>
<?=$goal_text?>
<script>
	$("li.seo_info p").html('<strong style="color: <?=$seo_color?>"><?=$seo_rating?>%</strong> SEO Rating');
	$(".seo_goals").html("<?=str_replace('"','\"',$goal_text)?>");
</script>