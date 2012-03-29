<?
	$content_css = $cms->getSetting("tinymce-content-css");
?>
<script type="text/javascript">
  tinyMCE.init({
  		<? if ($content_css) { ?>content_css: "<?=$content_css?>",<? } ?>
  		skin : "BigTree",
  		inlinepopups_skin: "BigTreeModal",
		theme: "advanced",
		mode: "exact",
		elements: "<?=implode(",",$small_htmls)?>",
		file_browser_callback: "BigTreeFileManager.tinyMCEOpen",
		plugins: "inlinepopups",
/* 		theme_advanced_buttons1: "link,unlink,bold,italic,underline,help,code,paste,pasteword,code", */
		theme_advanced_buttons1: "link,unlink,bold,italic,underline,help,code,pasteword,code",
		theme_advanced_buttons2: '',
		theme_advanced_buttons3: '',
		theme_advanced_disable: 'cleanup,charmap',
	 	theme_advanced_toolbar_location: "top",
		theme_advanced_toolbar_align: "left",
		theme_advanced_statusbar_location : "bottom",
		theme_advanced_resizing: true,
		theme_advanced_resize_horizontal: false,
		theme_advanced_resize_vertial: true,
		paste_remove_spans: true,
		paste_remove_styles: true,
		paste_strip_class_attributes: true,
		paste_auto_cleanup_on_paste: true,
		relative_urls: false,
		gecko_spellcheck: true,
		remove_script_host: false,
		extended_valid_elements : "object[classid|codebase|width|height|align],param[name|value],embed[quality|type|pluginspage|width|height|src|align]",
		width: "340",
		height: "175"
	});
</script>