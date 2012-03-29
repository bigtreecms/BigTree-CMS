<?
	$breadcrumb[] = array("title" => "Edit Callout", "link" => "#");
	$callout = $cms->getCallout(end($path));
	
	BigTree::globalizeArray($callout);
	
	$resources = json_decode($callout["resources"],true);
?>
<h1><span class="icon_developer_callouts"></span>Edit Callout</h1>
<? include BigTree::path("admin/modules/developer/callouts/_nav.php") ?>

<div class="form_container">
	<form method="post" action="<?=$section_root?>update/" enctype="multipart/form-data" class="module">
		<input type="hidden" name="id" value="<?=$callout["id"]?>" />
		<? include BigTree::path("admin/modules/developer/callouts/_form-content.php") ?>
		<footer>
			<input type="submit" class="button blue" value="Update" />
		</footer>
	</form>
</div>

<? include BigTree::path("admin/modules/developer/callouts/_common-js.php") ?>
<script type="text/javascript">
	var resource_count = <?=$x?>;
</script>