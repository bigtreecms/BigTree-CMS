<?php
	$permission = $admin->getResourceFolderPermission($bigtree["commands"][0]);

	if ($permission != "p") {
		$admin->stop("You do not have permission to create content in this folder.");
	}

	$dir = opendir(SITE_ROOT."files/temporary/".$admin->ID."/");
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

		$file_name = SITE_ROOT."files/temporary/".$admin->ID."/".$file;

		$storage = new BigTreeStorage;
		$output = $storage->store($file_name, $file, "files/resources/");

		if ($output) {
			$total_files++;
			$resource_id = $admin->createResource($bigtree["commands"][0], $output, $file, "file");
		} elseif ($storage->DisabledFileError) {
			$bigtree["errors"][] = [
				"field" => pathinfo($file, PATHINFO_BASENAME),
				"error" => "This file was rejected due to a file type that could cause security issues."
			];
		} elseif (!empty($storage->Cloud->Errors[0])) {
			$bigtree["errors"][] = [
				"field" => pathinfo($file, PATHINFO_BASENAME),
				"error" => $storage->Cloud->Errors[0]
			];
		} else {
			$bigtree["errors"][] = [
				"field" => pathinfo($file, PATHINFO_BASENAME),
				"error" => "Failed to store file."
			];
		}
	}

	$admin->growl("File Manager", "Uploaded Files");
	
	if (count($bigtree["errors"])) {
?>
<div class="container">
	<section>
		<div class="alert">
			<span></span>
			<p>Some files uploaded caused <?=count($bigtree["errors"])?> error<?php if (count($bigtree["errors"]) != 1) { ?>s<?php } ?>.</p>
		</div>
		<div class="table error_table">
			<header>
				<span class="view_column" style="width: calc(25% - 40px)">File</span>
				<span class="view_column" style="width: calc(75% - 40px)">Error</span>
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
		<a href="<?=ADMIN_ROOT?>files/add/file/<?=intval($bigtree["commands"][0])?>/" class="button blue">Return</a>
	</footer>
</div>
<?php
	} elseif ($total_files == 1) {
		BigTree::redirect(ADMIN_ROOT."files/edit/file/$resource_id/");
	} else {
		BigTree::redirect(ADMIN_ROOT."files/folder/".intval($bigtree["commands"][0])."/");
	}
	