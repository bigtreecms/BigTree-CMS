<?php
	namespace BigTree;
	
	/**
	 * @global array $bigtree
	 */
	
	$folder_id = intval($bigtree["commands"][0]);
	
	if (!ResourceFolder::exists($folder_id)) {
		Auth::stop("Folder does not exist.");
	}
	
	$folder = new ResourceFolder($folder_id);

	if ($folder->UserAccessLevel != "p") {
		Auth::stop("You do not have permission to create content in this folder.");
	}

	$dir = opendir(SITE_ROOT."files/temporary/".Auth::user()->ID."/");
	$total_files = 0;
	$bigtree["errors"] = [];

	while ($file = readdir($dir)) {
		if ($file == "." || $file == "..") {
			continue;
		}

		$extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

		if ($extension == "jpg" || $extension == "jpeg" || $extension == "png" || $extension == "gif") {
			continue;
		}

		$file_name = SITE_ROOT."files/temporary/".Auth::user()->ID."/".$file;

		$storage = new Storage;
		$output = $storage->store($file_name, $file, "files/resources/");

		if ($output) {
			$total_files++;
			$resource = Resource::create($folder_id, $output, $file, "file");
		} elseif ($storage->DisabledFileError) {
			$bigtree["errors"][] = [
				"field" => pathinfo($file, PATHINFO_BASENAME),
				"error" => Text::translate("This file was rejected due to a file type that could cause security issues.")
			];
		} elseif (!empty($storage->Cloud->Errors[0])) {
			$bigtree["errors"][] = [
				"field" => pathinfo($file, PATHINFO_BASENAME),
				"error" => $storage->Cloud->Errors[0]
			];
		} else {
			$bigtree["errors"][] = [
				"field" => pathinfo($file, PATHINFO_BASENAME),
				"error" => Text::translate("Failed to store file.")
			];
		}
	}

	Utils::growl("File Manager", "Uploaded Files");
	
	if (count($bigtree["errors"])) {
?>
<div class="container">
	<section>
		<div class="alert">
			<span></span>
			<p><?=Text::translate("The following errors ocurred:")?></p>
		</div>
		<div class="table error_table">
			<header>
				<span class="view_column" style="width: calc(25% - 40px)"><?=Text::translate("File")?></span>
				<span class="view_column" style="width: calc(75% - 40px)"><?=Text::translate("Error")?></span>
			</header>
			<ul>
				<?php foreach ($bigtree["errors"] as $error) { ?>
					<li>
						<section class="view_column" style="width: calc(25% - 40px)"><?=$error["field"]?></section>
						<section class="view_column" style="width: calc(75% - 40px)"><?=$error["error"]?></section>
					</li>
				<?php } ?>
			</ul>
		</div>
	</section>
	<footer>
		<a href="<?=ADMIN_ROOT?>files/add/file/<?=$folder->ID?>/" class="button blue"><?=Text::translate("Return")?></a>
	</footer>
</div>
<?php
	} elseif ($total_files == 1) {
		Router::redirect(ADMIN_ROOT."files/edit/file/".$resource->ID."/");
	} else {
		Router::redirect(ADMIN_ROOT."files/folder/".$folder_id."/");
	}
	