<?php
	$file = $admin->getResource($bigtree["commands"][0]);

	if (!$file) {
		$admin->stop("Invalid resource.");
	}

	$permission = $admin->getResourceFolderPermission($file["folder"]);

	if ($permission != "p") {
		$admin->stop("Access denied.");
	}

	$allocation = $admin->getResourceAllocation($file["id"]);
	$count = count($allocation);
?>
<form class="container" method="post" action="<?=ADMIN_ROOT?>files/process/delete-file/">
	<?php $admin->drawCSRFToken(); ?>
	<input type="hidden" name="id" value="<?=$file["id"]?>">

	<section>
		<p>Are you sure you want to delete &rdquo;<?=$file["name"]?>&rdquo;?</p>
		<?php
			if ($count) {
		?>
		<hr>
		<p>This file is presently being used in <strong><?=$count?></strong> location<?php if ($count != 1) { echo "s"; } ?>.</p>
		<?php
			}
		?>
	</section>

	<footer>
		<input type="submit" class="button red" value="Delete File">
	</footer>
</form>