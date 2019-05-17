<?php
	namespace BigTree;
	
	// Pre-translate strings
	$text_or = Text::translate("OR");
	$text_browse = Text::translate("Browse");
	$text_current = Text::translate("CURRENT");
	$text_choose_new = Text::translate("Choose New Crops");
	$text_show_existing = Text::translate("Show Existing Crops", true);
	$text_currently_using_existing = str_replace('"', '&quot;', Text::translate("<strong>Currently:</strong> Using existing crops"));
	$text_currently_generating = str_replace('"', '&quot;', Text::translate("<strong>Currently:</strong> Generating new crops after saving"));
	$text_use_existing = Text::translate("Use Existing Crops", true);
	$text_hide_existing = Text::translate("Hide Existing Crops", true);
	
	// If we're using a preset, the prefix may be there
	if (!empty($this->Settings["preset"])) {
		$media_settings = DB::get("config", "media-settings");
		$preset = $media_settings["presets"][$this->Settings["preset"]];
		
		if (!empty($preset["preview_prefix"])) {
			$this->Settings["preview_prefix"] = $preset["preview_prefix"];
		}
		
		if (!empty($preset["min_width"])) {
			$this->Settings["min_width"] = $preset["min_width"];
		}
		
		if (!empty($preset["min_height"])) {
			$this->Settings["min_height"] = $preset["min_height"];
		}
	}

	// Get min width/height designations
	$min_width = $this->Settings["min_width"] ? intval($this->Settings["min_width"]) : 0;
	$min_height = $this->Settings["min_height"] ? intval($this->Settings["min_height"]) : 0;
	$filtered_crops = [];
	$image = null;

	// Get preview data and existing crops
	if ($this->Value) {
		if ($this->Settings["preview_prefix"]) {
			$preview_image = FileSystem::getPrefixedFile($this->Value, $this->Settings["preview_prefix"]);
		} else {
			$preview_image = $this->Value;
		}
		
		if (!empty($this->Settings["preview_cache_suffix"])) {
			$preview_image .= $this->Settings["preview_cache_suffix"];
		}

		$image = new Image(str_replace(STATIC_ROOT, SITE_ROOT, $this->Value), $this->Settings);
		$image->filterGeneratableCrops();
		$filtered_crops = $image->Settings["crops"];

		foreach ($filtered_crops as $index => $crop) {
			if ($crop["width"] == $image->Width && $crop["height"] == $image->Height) {
				unset($filtered_crops[$index]);
			}
		}
	} else {
		$preview_image = false;
	}

	// Generate the file manager restrictions
	$button_options = htmlspecialchars(json_encode([
		"minWidth" => $min_width,
		"minHeight" => $min_height,
		"currentlyKey" => $this->Key,
		"type" => "image"
	]));
?>
<div class="image_field" id="<?=$this->ID?>">
	<div class="contain">
		<input<?php if ($this->Required) { ?> class="required"<?php } ?> type="file" tabindex="<?=$this->TabIndex?>" name="<?=$this->Key?>" data-min-width="<?=$min_width?>" data-min-height="<?=$min_height?>" accept="image/*" />
		<?php
			if (empty($this->Settings["disable_browse"])) {
		?>
		<span class="or"><?=$text_or?></span>
		<a href="#<?=$this->ID?>_currently" data-options="<?=$button_options?>" class="button resource_browser_button"><span class="icon_images"></span><?=$text_browse?></a>
		<?php
			}
		?>
	</div>
	
	<div class="contain">
		<div class="currently<?php if (!empty($this->Settings["preview_files_square"])) { ?> currently_files_square<?php } ?>" <?php if (!$this->Value) { ?> style="display: none;"<?php } ?> id="<?=$this->ID?>_currently">
			<?php
				if (empty($this->Settings["disable_remove"])) {
			?>
			<a href="#" class="remove_resource"></a>
			<?php
				}
			?>
			
			<div class="currently_wrapper">
				<?php if ($preview_image) { ?>
				<a href="<?=$this->Value?>" target="_blank"><img src="<?=$preview_image?>" alt="" /></a>
				<?php } ?>
			</div>
			
			<label><?=$text_current?></label>
			<input type="hidden" name="<?=$this->Key?>" value="<?=$this->Value?>" />
			<?php
				if (!empty($this->ForcedRecrop)) {
					if (strpos($this->Key, "[") === false) {
						$recrop_key = "__".$this->Key."_recrop__";
					} else {
						$parts = explode("[", $this->Key);
						$last_part = $parts[count($parts) - 1];
						$parts[count($parts) - 1] = "__".substr($last_part, 0, -1)."_recrop__]";
						$recrop_key = implode("[", $parts);
					}
			?>
			<input type="hidden" name="<?=$recrop_key?>" value="true">
			<?php
				}
			?>
		</div>
		<?php
			if (!empty($this->Value) && empty($this->ForcedRecrop) && count($filtered_crops)) {
		?>
		<div class="recrop_button_container">
			<button class="button green recrop_button"><?=$text_choose_new?></button>
			<?php
				if (!defined("BIGTREE_CALLOUT_RESOURCES") && $image && !empty($image->Settings["crops"]) && count($image->Settings["crops"])) {
			?>
			&nbsp;&nbsp;&nbsp;
			<button class="button show_crops_button"><?=$text_show_existing?></button>
			<?php
				}
			?>
			<p class="recrop_status_text"><?=$text_currently_using_existing?></p>
		</div>
		<?php
			}
		?>
	</div>
	<?php
		if (!defined("BIGTREE_CALLOUT_RESOURCES")) {
	?>
	<div class="existing_crops_container" style="display: none;"></div>
	<?php
		}
	?>
</div>

<script>
	(function() {
		var Container = $("#<?=$this->ID?>");
		var ExistingCropsContainer = Container.find(".existing_crops_container");

		Container.find(".recrop_button").on("click", function(ev) {
			ev.preventDefault();

			if ($(this).hasClass("green")) {
				$(this).addClass("red").removeClass("green");
				$(this).html("<?=$text_use_existing?>");
				$(this).siblings(".recrop_status_text").html("<?=$text_currently_generating?>");

				if ("<?=$this->Key?>".indexOf("[") > -1) {
					var parts = "<?=$this->Key?>".split("[");
					var last_part = parts[parts.length - 1];

					parts[parts.length - 1] = "__" + last_part.substr(0, last_part.length - 1) + "_recrop__]";

					$(this).after('<input type="hidden" name="' + parts.join("[") + '" value="true">');
				} else {
					$(this).after('<input type="hidden" name="__<?=$this->Key?>_recrop__" value="true">');
				}
			} else {
				$(this).removeClass("red").addClass("green");
				$(this).siblings("input").remove();
				$(this).html("<?=$text_choose_new?>");
				$(this).siblings(".recrop_status_text").html("<?=$text_currently_using_existing?>");
			}
		});

		Container.find(".show_crops_button").on("click", function(ev) {
			ev.preventDefault();

			if ($(this).html() === "<?=$text_hide_existing?>") {
				ExistingCropsContainer.hide();
				ExistingCropsContainer.parents("fieldset").removeClass("existing_crops_open");
				$(this).html("<?=$text_show_existing?>");
			} else {
				if (ExistingCropsContainer.hasClass("loaded")) {
					cropsContainerLoaded();
				} else {
					$(this).addClass("disabled").after('<span class="button_loader"></span>');
		
					ExistingCropsContainer.load("<?=ADMIN_ROOT?>ajax/files/load-crops-preview/", {
						file: "<?=$this->Value?>",
						crops: <?=json_encode($filtered_crops)?>
					}, cropsContainerLoaded);
				}
			}
		});

		function cropsContainerLoaded() {
			ExistingCropsContainer.addClass("loaded").show();
			ExistingCropsContainer.parents("fieldset").addClass("existing_crops_open");
			Container.find(".show_crops_button").removeClass("disabled").html("<?=$text_hide_existing?>").siblings(".button_loader").remove();
		}
	})();
</script>