<?	
	$settings = $cms->getSetting("btx-dogwood-settings");
?>
<div class="container">
	<form method="post" action="../update-settings/">
		<section>
			<fieldset>
				<label>Blog Title</label>
				<input type="text" name="title" value="<?=$settings["title"]?>" />
			</fieldset>
			<fieldset>
				<label>Blog Tagline</label>
				<input type="text" name="tagline" value="<?=$settings["tagline"]?>" />
			</fieldset>
			<fieldset>
				<label>Disqus Shortname <small>(to enable commenting)</small></label>
				<input type="text" name="disqus" value="<?=$settings["disqus"]?>" />
			</fieldset>
		</section>
		<footer>
			<input type="submit" class="button blue" value="Update" />
		</footer>
	</form>
</div>