<?php
	/**
	 * @global array $bigtree
	 */
?>
<script>parent.BigTreeBar.refresh("<?=base64_decode(end($bigtree["path"]))?>");</script>
<?php
	die();
?>