<?php
	$width = isset($bigtree["html_editor_width"]) ? $bigtree["html_editor_width"] : false;
	$height = isset($bigtree["html_editor_height"]) ? $bigtree["html_editor_height"] : false;
	$content_css = $cms->getSetting("tinymce-content-css");
?>
<script>
	$(document).ready(function() {
		<?php
			if (is_array($bigtree["html_fields"]) && count($bigtree["html_fields"])) {
		?>
		tinyMCE.init({
			<?php if ($content_css) { ?>content_css: "<?=$content_css?>",<?php } ?>
			theme: "modern",
			mode: "exact",
			elements: "<?=implode(",",$bigtree["html_fields"])?>",
			file_browser_callback: BigTreeFileManager.tinyMCEOpen,
			menubar: false,
			plugins: "code,anchor,image,link,table,visualblocks,lists,hr",
			toolbar: "undo redo | styleselect | bold italic underline removeformat | bullist numlist outdent indent | hr anchor link unlink image table | visualblocks code",
			image_dimensions: false,
			paste_remove_spans: true,
			paste_remove_styles: true,
			paste_strip_class_attributes: true,
			paste_auto_cleanup_on_paste: true,
			relative_urls: false,
			remove_script_host: false,
			browser_spellcheck: true,
			extended_valid_elements : "*[*]"
			<?php if ($width) { ?>,width: "<?=$width?>"<?php } ?>
			<?php if ($height) { ?>,height: "<?=$height?>"<?php } ?>
		});
		<?php
			}

			if (is_array($bigtree["simple_html_fields"]) && count($bigtree["simple_html_fields"])) {
		?>
		tinyMCE.init({
			<?php if ($content_css) { ?>content_css: "<?=$content_css?>",<?php } ?>
			theme: "modern",
			mode: "exact",
			elements: "<?=implode(",",$bigtree["simple_html_fields"])?>",
			file_browser_callback: BigTreeFileManager.tinyMCEOpen,
			menubar: false,
			plugins: "link,code,visualblocks,lists",
			toolbar: "link unlink bold italic underline removeformat",
			paste_remove_spans: true,
			paste_remove_styles: true,
			paste_strip_class_attributes: true,
			paste_auto_cleanup_on_paste: true,
			browser_spellcheck: true,
			relative_urls: false,
			remove_script_host: false,
			extended_valid_elements : "*[*]"
			<?php if ($width) { ?>,width: "<?=$width?>"<?php } ?>
			<?php if ($height) { ?>,height: "<?=$height?>"<?php } ?>
		});
		<?php
			}
		?>
	});
</script>