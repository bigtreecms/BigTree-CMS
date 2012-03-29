<?
	$breadcrumb[] = array("link" => $mroot . "activity/", "title" => "Recent Activity");
	
	if ($btxTwitterAPI->active_username) {
		$feed = $btxTwitterAPI->timeline();
	} else {
		$feed = false;
	}
	
	$view["title"] = "Recent Activity";
		
	include "_heading.php";
	include BigTree::path("admin/auto-modules/_nav.php"); 
?>
<div class="form_container" id="mailchimp_api">
	<section>
		<? 
			if (!$feed) { 
		?>
		<p>
			Please set and active username.
			<br />
		</p>
		<? 
			} else {
				foreach ($feed as $tweet) {
		?>
		<p>
			<?=$tweet["text"]?>
			<br />
			<small><?=$tweet["created"]?></small>
		</p>
		<hr style="margin: 0 0 15px; width: 100%;" />
		<?
				}
			}
		?>
	</section>
</div>