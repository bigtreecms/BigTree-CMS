<?php
	namespace BigTree;
	
	/**
	 * @global array $bigtree
	 */
	
	Auth::user()->requireLevel(1);
	$folder_id = intval(Router::$Commands[0]);
	
	if (!ResourceFolder::exists($folder_id)) {
		Auth::stop("This folder does not exist.");
	} elseif ($folder_id === 0) {
		Auth::stop("You may not delete the root folder.");
	}
	
	$folder = new ResourceFolder($folder_id);
	$stats = $folder->getStatistics();
?>
<form class="container" method="post" action="<?=ADMIN_ROOT?>files/process/delete-folder/">
	<?php CSRF::drawPOSTToken(); ?>
	<input type="hidden" name="id" value="<?=$folder->ID?>">

	<section>
		<p>
			<?=Text::translate("Are you sure you want to delete: :folder_name:?", false, [":folder_name:" => $folder->Name])?>
			<br>
			<strong>
				<?=Text::translate(":sub_folder_count: sub-folder(s) and :file_count: file(s) will also be deleted.", false, [":sub_folder_count:" => $stats["folders"], ":file_count:" => $stats["resources"]])?>
			</strong>
		</p>
		<?php
			if ($stats["allocations"]) {
		?>
		<hr>
		<p>
			<?=Text::translate("One or more files that will be deleted are currently being used :count: times.", false, [":count:" => $stats["allocations"]])?>
		</p>
		<?php
			}
		?>
	</section>

	<footer>
		<input type="submit" class="button red" value="<?=Text::translate("Delete Folder", true)?>">
	</footer>
</form>