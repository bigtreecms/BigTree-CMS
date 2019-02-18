<?php
	$admin->requireLevel(1);
	$folder = $admin->getResourceFolder($bigtree["commands"][0]);
	$counts = $admin->getResourceFolderAllocationCounts($folder["id"]);
?>
<form class="container" method="post" action="<?=ADMIN_ROOT?>files/process/delete-folder/">
	<?php $admin->drawCSRFToken(); ?>
	<input type="hidden" name="id" value="<?=$folder["id"]?>">

	<section>
		<p>
			Are you sure you want to delete &rdquo;<?=$folder["name"]?>&rdquo;?<br>
			<strong><?=$counts["folders"]?> sub-folder<?php if ($counts["folders"] != 1) { echo "s"; } ?> and <?=$counts["resources"]?> file<?php if ($counts["resources"] != 1) { echo "s"; } ?> will also be deleted.</strong>
		</p>
		<?php
			if ($counts["allocations"]) {
		?>
		<hr>
		<p><?php if ($counts["resources"] != 1) { echo "These files are"; } else { echo "The file is"; } ?> presently being used in <strong><?=$counts["allocations"]?></strong> location<?php if ($counts["allocations"] != 1) { echo "s"; } ?>.</p>
		<?php
			}
		?>
	</section>

	<footer>
		<input type="submit" class="button red" value="Delete Folder">
	</footer>
</form>