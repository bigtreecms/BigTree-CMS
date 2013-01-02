<?
	$content_css = $cms->getSetting("tinymce-content-css");
?>
<script>
	$(document).ready(function() {
		tinyMCE.init({
  			<? if ($content_css) { ?>content_css: "<?=$content_css?>",<? } ?>
  			skin : "BigTree",
  			inlinepopups_skin: "BigTreeModal",
			theme: "advanced",
			mode: "exact",
			elements: "<?=implode(",",$bigtree["html_fields"])?>",
			file_browser_callback: "BigTreeFileManager.tinyMCEOpen",
			plugins: "advimage,paste,table,inlinepopups,spellchecker",
			theme_advanced_blockformats: "p,h2,h3,h4",
			theme_advanced_buttons1: "undo,redo,separator,blockquote,bold,italic,underline,strikethrough,separator,formatselect,justifyleft,justifycenter,justifyright,justifyfull,bullist,numlist,separator,spellchecker,code",
			/* theme_advanced_buttons2: "link,unlink,anchor,image,separator,hr,removeformat,visualaid,separator,table,tablecontrols,separator,paste,pasteword", */
			theme_advanced_buttons2: "link,unlink,anchor,image,separator,hr,removeformat,visualaid,separator,table,row_after,delete_row,col_after,delete_col,separator,pasteword",
			theme_advanced_buttons3: "",
			theme_advanced_disable: "cleanup,charmap",
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
			remove_script_host: false,
			gecko_spellcheck: true,
			extended_valid_elements : "object[classid|codebase|width|height|align],param[name|value],embed[quality|type|pluginspage|width|height|src|align],iframe[src|class|width|height|name|align|style]"
			<? if (isset($mce_width)) { ?>,width: "<?=$mce_width?>"<? } ?>
			<? if (isset($mce_height)) { ?>,height: "<?=$mce_height?>"<? } ?>
		});
	});
</script>