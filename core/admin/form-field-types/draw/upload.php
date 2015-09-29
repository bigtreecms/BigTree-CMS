<?php
	// If we're using a preset, the prefix may be there
	if (!empty($field['options']['preset'])) {
	    if (!isset($bigtree['media_settings'])) {
	        $bigtree['media_settings'] = $cms->getSetting('bigtree-internal-media-settings');
	    }
	    $preset = $bigtree['media_settings']['presets'][$field['options']['preset']];
	    if (!empty($preset['preview_prefix'])) {
	        $field['options']['preview_prefix'] = $preset['preview_prefix'];
	    }
	}

	// Get min width/height designations
	$min_width = $field['options']['min_width'] ? intval($field['options']['min_width']) : 0;
	$min_height = $field['options']['min_height'] ? intval($field['options']['min_height']) : 0;
?>
<div class="<?php if (empty($field['options']['image'])) {
    ?>upload_field<?php 
} else {
    ?>image_field<?php 
} ?>">
	<input<?php if ($field['required']) {
    ?> class="required"<?php 
} ?> type="file" tabindex="<?=$field['tabindex']?>" name="<?=$field['key']?>" data-min-width="<?=$min_width?>" data-min-height="<?=$min_height?>" />
	<?php	
		if (!isset($field['options']['image']) || !$field['options']['image']) {
		    if ($field['value']) {
		        $pathinfo = BigTree::pathInfo($field['value']);
		        ?>
	<div class="currently_file">
		<input type="hidden" name="<?=$field['key']?>" value="<?=$field['value']?>" />
		<strong>Currently:</strong> <?=$pathinfo['basename']?> <a href="#" class="remove_resource">Remove</a>
	</div>
	<?php

		    }
		} else {
		    if ($field['value']) {
		        if ($field['options']['preview_prefix']) {
		            $preview_image = BigTree::prefixFile($field['value'], $field['options']['preview_prefix']);
		        } else {
		            $preview_image = $field['value'];
		        }
		    } else {
		        $preview_image = false;
		    }

			// Generate the file manager restrictions
			$button_options = htmlspecialchars(json_encode(array(
				'minWidth' => $min_width,
				'minHeight' => $min_height,
				'currentlyKey' => $field['key'],
			)));

		    if (!defined('BIGTREE_FRONT_END_EDITOR') && !$bigtree['form']['embedded']) {
		        ?>
	<span class="or">OR</span>
	<a href="#<?=$field['id']?>" data-options="<?=$button_options?>" class="button form_image_browser"><span class="icon_images"></span>Browse</a>
	<?php

		    }
		    ?>
	<br class="clear" />
	<div class="currently" id="<?=$field['id']?>"<?php if (!$field['value']) {
    ?> style="display: none;"<?php 
}
		    ?>>
		<a href="#" class="remove_resource"></a>
		<div class="currently_wrapper">
			<?php if ($preview_image) {
    ?>
			<img src="<?=$preview_image?>" alt="" />
			<?php 
}
		    ?>
		</div>
		<label>CURRENT</label>
		<input type="hidden" name="<?=$field['key']?>" value="<?=$field['value']?>" />
	</div>
	<?php

		}
	?>
</div>