<?
	$updates = @json_decode(BigTree::cURL("http://www.bigtreecms.org/ajax/version-check/?current_version=".BIGTREE_VERSION),true);
	$update = $updates[$_GET["type"]];
	if (!$update) {
		$admin->growl("Developer","Couldn't Get Download Information","error");
		BigTree::redirect(DEVELOPER_ROOT);
	}
?>
<div class="container">
	<summary><h2>Upgrade BigTree</h2></summary>
	<section>
		<p>Please wait while we download the update...</p>
	</section>
</div>
<script>
	$.ajax("<?=ADMIN_ROOT?>ajax/developer/upgrade/download/", { type: "POST", data: { file: "<?=$update["file"]?>" }, complete: function() {
		window.location.href = "<?=DEVELOPER_ROOT?>upgrade/check-file/?type=<?=htmlspecialchars($_GET["type"])?>";
	} });
</script>