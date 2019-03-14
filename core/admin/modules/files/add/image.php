<?php
	$bigtree["js"][] = "dropzone.js";
	$bigtree["breadcrumb"][] = ["link" => "#", "title" => "Add Images"];
	$folder = intval($bigtree["commands"][0]);
	$permission = $admin->getResourceFolderPermission($folder);

	if ($permission != "p") {
		$admin->stop("You do not have permission to create content in this folder.");
	}

	// Clean out the temp directory
	BigTree::deleteDirectory(SITE_ROOT."files/temporary/".$admin->ID."/");
?>
<div class="container file_manager_wrapper">	
	<section>
		<form action="<?=ADMIN_ROOT?>ajax/files/dropzone-upload-image/" class="dropzone" id="file_manager_dropzone">
			<?php $admin->drawCSRFToken(); ?>
			<input type="hidden" name="MAX_FILE_SIZE" value="<?=BigTree::uploadMaxFileSize()?>">
			<input type="hidden" name="folder" value="<?=$folder?>">
			<p>Drag and drop files into this zone or click to manually upload.</p>
		</form>
	</section>

	<footer>
		<form method="post" action="<?=ADMIN_ROOT?>files/process/image/<?=$folder?>/" id="files_form">
			<input type="hidden" name="folder" value="<?=$folder?>">
			<input type="submit" class="blue button js-continue-button disabled" value="Continue">
		</form>
	</footer>
</div>

<script>
	(function() {
		var ContinueButton = $(".js-continue-button");
		var Form = $("#files_form");
		var Processed = 0;
		var Total = 0;

		Dropzone.options.fileManagerDropzone = {
			accept: function(file, done) {
				if (file.type != "image/png" && file.type != "image/gif" && file.type != "image/jpeg") {
					done("This form only accepts png, jpg, and gif images.");
				} else {
					done();
				}
			},
			init: function() {
				this.on("addedfile", function(ev) {
					Total++;
					$(ev.previewElement).find(".dz-details").append('<span class="button_loader"></span>');
				});

				this.on("success", function(ev, response) {
					Processed++;
					Form.append($('<input name="files[]" type="hidden">').val(response));

					$(ev.previewElement).removeClass("dz-processing").find(".button_loader").remove();

					if (Processed == Total) {
						ContinueButton.removeClass("disabled");
					}
				});

				this.on("error", function(ev, response) {
					$(ev.previewElement).removeClass("dz-processing").find(".button_loader").remove();
					Processed++;
				});
			}
		};
	
		ContinueButton.click(function() {
			if ($(this).hasClass("disabled")) {
				return false;
			}
	
			$(this).addClass("disabled");
			$(this).after('<span class="button_loader"></span>');
		});
	})();
</script>