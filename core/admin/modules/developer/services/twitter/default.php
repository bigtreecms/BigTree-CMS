<?
?>
<div class="container">
	<section>
		<p>Currently connected to your account:</p>
		<p>
			<img src="<?=$twitterAPI->settings["user_image"]?>" class="gravatar" style="width: 50px;" />
			<strong style="font-size:16px; padding: 8px 0 5px; display: block;"><?=$twitterAPI->settings["user_name"]?></strong>
			[<?=$twitterAPI->settings["user_id"]?>]
		</p>
	</section>
</div>