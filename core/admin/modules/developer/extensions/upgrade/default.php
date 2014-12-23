<?
	// If we can't do a local, FTP, or SFTP update then we give instructions on how to manually update
	if (!$updater->Method) {
		BigTree::redirect($page_link."failed/".$page_vars);
	}
?>
<div class="container">
	<summary><h2>Upgrade Extension</h2></summary>
	<section>
		<p>Please wait while we download the update...</p>
	</section>
</div>
<script>
	$.ajax("<?=ADMIN_ROOT?>ajax/developer/upgrade/download/", { type: "POST", data: { file: "<?=rtrim($_GET["url"],"/")?>/archive/master.zip" }, complete: function() {
		window.location.href = "<?=$page_link?>check-file/<?=$page_vars?>";
	} });
</script>