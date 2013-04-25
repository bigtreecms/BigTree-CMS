<?
?>
<div class="container">
	<section>
		<p>Currently connected to your account:</p>
		<p>
			<img src="<?=$instagramAPI->settings["user_image"]?>" class="gravatar" style="width: 50px;" />
			<strong style="font-size:16px; padding: 8px 0 5px; display: block;"><?=$instagramAPI->settings["user_name"]?></strong>
			[<?=$instagramAPI->settings["user_id"]?>]
		</p>
	</section>
</div>