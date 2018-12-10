<?php
	$cached_types = $admin->getCachedFieldTypes(true);
	$field_types = $cached_types["settings"];
	$metadata = BigTreeJSONDB::get("config", "file-metadata");
	$count = 0;

	$draw_field = function($key, $field) {
		global $count, $field_types;

		$count++;
?>
<li>
	<section class="developer_resource_id">
		<span class="icon_sort"></span>
		<input type="text" name="<?=$key?>[ids][<?=$count?>]" value="<?=$field["id"]?>" class="required" />
	</section>
	<section class="developer_resource_title">
		<input type="text" name="<?=$key?>[titles][<?=$count?>]" value="<?=$field["title"]?>" class="required" />
	</section>
	<section class="developer_resource_subtitle">
		<input type="text" name="<?=$key?>[subtitles][<?=$count?>]" value="<?=$field["subtitle"]?>" />
	</section>
	<section class="developer_resource_type">
		<select name="<?=$key?>[types][<?=$count?>]" id="type_<?=$count?>">
			<optgroup label="Default">
				<?php foreach ($field_types["default"] as $k => $v) { ?>
				<option value="<?=$k?>"<?php if ($k == $field["type"]) { ?> selected="selected"<?php } ?>><?=$v["name"]?></option>
				<?php } ?>
			</optgroup>
			<?php if (count($field_types["custom"])) { ?>
			<optgroup label="Custom">
				<?php foreach ($field_types["custom"] as $k => $v) { ?>
				<option value="<?=$k?>"<?php if ($k == $field["type"]) { ?> selected="selected"<?php } ?>><?=$v["name"]?></option>
				<?php } ?>
			</optgroup>
			<?php } ?>
		</select>
		<a href="#" class="icon_settings" name="<?=$count?>"></a>
		<input type="hidden" name="<?=$key?>[settings][<?=$count?>]" value="<?=htmlspecialchars(json_encode($field["settings"]))?>" id="settings_<?=$count?>" />
	</section>
	<section class="developer_resource_action right">
		<a href="#" class="icon_delete"></a>
	</section>
</li>
<?php
	}
?>
<form method="post" action="<?=DEVELOPER_ROOT?>files/update-metadata/" class="js-metadata-form">
	<div class="container">
		<summary>
			<h2>Metadata</h2>
		</summary>
	
		<section>
			<h3>Generic Files Metadata</h3>
			<div class="form_table" data-name="file">
				<header>
					<a class="add add_field" href="#"><span></span>Field</a>
				</header>
				<div class="labels">
					<span class="developer_resource_id">ID</span>
					<span class="developer_resource_title">Title</span>
					<span class="developer_resource_subtitle">Subtitle</span>
					<span class="developer_resource_type">Type</span>
					<span class="developer_resource_action right">Delete</span>
				</div>
				<ul id="file_metadata_table">
					<?php
						if (is_array($metadata["file"])) {
							foreach ($metadata["file"] as $field) {
								$draw_field("file", $field);
							}
						}
					?>
				</ul>
			</div>
	
			<hr>
	
			<h3>Image Metadata</h3>
			<div class="form_table" data-name="image">
				<header>
					<a class="add add_field" href="#"><span></span>Field</a>
				</header>
				<div class="labels">
					<span class="developer_resource_id">ID</span>
					<span class="developer_resource_title">Title</span>
					<span class="developer_resource_subtitle">Subtitle</span>
					<span class="developer_resource_type">Type</span>
					<span class="developer_resource_action right">Delete</span>
				</div>
				<ul id="image_metadata_table">
					<?php
						if (is_array($metadata["image"])) {
							foreach ($metadata["image"] as $field) {
								$draw_field("image", $field);
							}
						}
					?>
				</ul>
			</div>
	
			<hr>
	
			<h3>Video Metadata</h3>
			<div class="form_table" data-name="video">
				<header>
					<a class="add add_field" href="#"><span></span>Field</a>
				</header>
				<div class="labels">
					<span class="developer_resource_id">ID</span>
					<span class="developer_resource_title">Title</span>
					<span class="developer_resource_subtitle">Subtitle</span>
					<span class="developer_resource_type">Type</span>
					<span class="developer_resource_action right">Delete</span>
				</div>
				<ul id="video_metadata_table">
					<?php
						if (is_array($metadata["video"])) {
							foreach ($metadata["video"] as $field) {
								$draw_field("video", $field);
							}
						}
					?>
				</ul>
			</div>
		</section>
	
		<footer>
			<input type="submit" value="Update Metadata Fields" class="button blue">
		</footer>
	</div>
</form>

<script>
	(function() {
		var CurrentFieldKey = false;
		var KeyCount = <?=$count?>;

		BigTreeFormValidator(".js-metadata-form");

		function hooks() {
			$("#video_metadata_table, #image_metadata_table, #file_metadata_table").sortable({ axis: "y", containment: "parent", handle: ".icon_sort", items: "li", placeholder: "ui-sortable-placeholder", tolerance: "pointer" });
			BigTreeCustomControls();
		}

		$(".form_table").on("click",".icon_settings",function(ev) {
			ev.preventDefault();
	
			// Prevent double clicks
			if (BigTree.Busy) {
				return;
			}
	
			CurrentFieldKey = $(this).attr("name");
			
			$.ajax("<?=ADMIN_ROOT?>ajax/developer/load-field-settings/", { type: "POST", data: { type: $("#type_" + CurrentFieldKey).val(), data: $("#settings_" + CurrentFieldKey).val() }, complete: function(response) {
				BigTreeDialog({
					title: "Field Settings",
					content: response.responseText,
					icon: "edit",
					callback: function(data) {
						$("#settings_" + CurrentFieldKey).val(JSON.stringify(data));
					}
				});
			}});
			
		}).on("click",".icon_delete",function(ev) {
			ev.preventDefault();

			$(this).parents("li").remove();
		});
		
		$(".add_field").click(function(ev) {
			ev.preventDefault();

			KeyCount++;
	
			var li = $('<li id="row_' + KeyCount + '">');
			var parent = $(this).parents(".form_table");
			var name = parent.data("name");
	
			li.html('<section class="developer_resource_id">' +
						'<span class="icon_sort"></span>' +
						'<input type="text" name="' + name + '[ids][' + KeyCount + ']" value="" class="required" />' +
					'</section>' +
					'<section class="developer_resource_title">' +
						'<input type="text" name="' + name + '[titles][' + KeyCount + ']" value="" class="required" />' +
					'</section>' +
					'<section class="developer_resource_subtitle">' +
						'<input type="text" name="' + name + '[subtitles][' + KeyCount + ']" value="" />' +
					'</section>' +
					'<section class="developer_resource_type">' +
						'<select name="' + name + '[types][' + KeyCount + ']" id="type_' + KeyCount + '">' +
							'<optgroup label="Default">' +
								<?php foreach ($field_types["default"] as $k => $v) { ?>
								'<option value="<?=$k?>"><?=$v["name"]?></option>' +
								<?php } ?>
							'</optgroup>' +
							<?php if (count($field_types["custom"])) { ?>
							'<optgroup label="Custom">' +
								<?php foreach ($field_types["custom"] as $k => $v) { ?>
								'<option value="<?=$k?>"><?=$v["name"]?></option>' +
								<?php } ?>
							'</optgroup>' +
							<?php } ?>
						'</select>' +
						'<a href="#" class="icon_settings" name="' + KeyCount + '"></a>' +
						'<input type="hidden" name="' + name + '[settings][' + KeyCount + ']" value="" id="settings_' + KeyCount + '" />' +
					'</section>' +
					'<section class="developer_resource_action right">' +
						'<a href="#" class="icon_delete" name="' + KeyCount + '"></a>' +
					'</section>');


			parent.find("ul").append(li);
			hooks();
		});
		
		hooks();
	})();
</script>