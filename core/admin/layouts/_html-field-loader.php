<?php
	/**
	 * @global BigTreeCMS $cms
	 */
	
	$width = isset($bigtree["html_editor_width"]) ? $bigtree["html_editor_width"] : false;
	$height = isset($bigtree["html_editor_height"]) ? $bigtree["html_editor_height"] : false;
	$content_css = $cms->getSetting("tinymce-content-css");
?>
<script>
	$(document).ready(function() {
		<?php
			if (!empty($bigtree["html_fields"])) {
		?>
		tinymce.init({
			<?php if ($content_css) { ?>content_css: "<?=$content_css?>",<?php } ?>
			selector: "#<?=implode(",#", $bigtree["html_fields"])?>",
			file_picker_callback: BigTreeFileManager.tinyMCEOpen,
			menubar: false,
			plugins: "code,anchor,image,link,table,visualblocks,lists,template",
			toolbar: "undo redo styles bold italic underline | bullist numlist outdent indent | hr anchor link unlink image table | template visualblocks code",
			relative_urls: false,
			remove_script_host: false,
			browser_spellcheck: true,
			extended_valid_elements : "*[*]"
			<?php if ($width) { ?>,width: "<?=$width?>"<?php } ?>
			<?php if ($height) { ?>,height: "<?=$height?>"<?php } ?>
		});
		<?php
			}
		
			if (!empty($bigtree["simple_html_fields"])) {
		?>
		tinyMCE.init({
			<?php if ($content_css) { ?>content_css: "<?=$content_css?>",<?php } ?>
			selector: "#<?=implode(",#", $bigtree["simple_html_fields"])?>",
			file_picker_callback: BigTreeFileManager.tinyMCEOpen,
			menubar: false,
			plugins: "link,code,visualblocks,lists",
			toolbar: "link unlink bold italic underline code| bullist numlist outdent indent",
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
