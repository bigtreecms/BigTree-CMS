<?	
	// Stop notices
	$id = $name = $description = $display_default = $level = "";
	$resources = array();
?>
<div class="container">
	<form method="post" action="<?=$section_root?>create/" enctype="multipart/form-data" class="module">
		<? include BigTree::path("admin/modules/developer/callouts/_form-content.php") ?>
		<footer>
			<input type="submit" class="button blue" value="Create" />
		</footer>
	</form>
</div>

<? include BigTree::path("admin/modules/developer/callouts/_common-js.php") ?>
<script>
	var resource_count = <?=$x?>;
</script>