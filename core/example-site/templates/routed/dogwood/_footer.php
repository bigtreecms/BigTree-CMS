<?
	// If we've enabled Disqus in the admin for commenting we're either going to load the comment counts for a post list or show the comment thread on the detail page.
	if ($settings["disqus"]) {
?>
<script>
	var disqus_shortname = '<?=$settings["disqus"]?>';
	<?
		// Draw the comment thread if we're on the detail page
		if ($post_detail) {
	?>
	(function() {
		var dsq = document.createElement('script'); dsq.type = 'text/javascript'; dsq.async = true;
		dsq.src = 'http://' + disqus_shortname + '.disqus.com/embed.js';
		(document.getElementsByTagName('head')[0] || document.getElementsByTagName('body')[0]).appendChild(dsq);
	})();
	<?
		// Draw comment counts if we're on a list view
		} else {
	?>
	(function () {
		var s = document.createElement('script'); s.async = true;
		s.type = 'text/javascript';
		s.src = 'http://' + disqus_shortname + '.disqus.com/count.js';
		(document.getElementsByTagName('HEAD')[0] || document.getElementsByTagName('BODY')[0]).appendChild(s);
	}());
	<?
		}
	?>
</script>
<?
	}
?>