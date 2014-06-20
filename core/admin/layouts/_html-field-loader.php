<?
	$width = isset($bigtree["html_editor_width"]) ? $bigtree["html_editor_width"] : false;
	$height = isset($bigtree["html_editor_height"]) ? $bigtree["html_editor_height"] : false;
	$content_css = $cms->getSetting("tinymce-content-css");
	$html_editor = isset($bigtree["config"]["html_editor"]) ? $bigtree["config"]["html_editor"]["name"] : "TinyMCE 3";
?>
<script>
	$(document).ready(function() {
		<?
			if ($html_editor == "TinyMCE 3") {
				if (count($bigtree["html_fields"])) {
		?>
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
			<? if (defined("BIGTREE_CALLOUT_RESOURCES")) { ?>
			theme_advanced_buttons1: "blockquote,bold,italic,strikethrough,separator,formatselect,justifyleft,justifycenter,justifyright,justifyfull,separator,bullist,numlist,outdent,indent,separator,spellchecker,code",
			<? } else { ?>
			theme_advanced_buttons1: "undo,redo,separator,blockquote,bold,italic,strikethrough,separator,formatselect,justifyleft,justifycenter,justifyright,justifyfull,separator,bullist,numlist,outdent,indent,separator,spellchecker,code",
			<? } ?>
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
			extended_valid_elements : "object[classid|codebase|width|height|align],param[name|value],embed[quality|type|pluginspage|width|height|src|align],iframe[src|class|width|height|name|align|style],figure[class],figcaption[class]"
			<? if ($width) { ?>,width: "<?=$width?>"<? } ?>
			<? if ($height) { ?>,height: "<?=$height?>"<? } ?>
		});
		<?
				}
				if (count($bigtree["simple_html_fields"])) {
		?>
		tinyMCE.init({
  			<? if ($content_css) { ?>content_css: "<?=$content_css?>",<? } ?>
  			skin : "BigTree",
  			inlinepopups_skin: "BigTreeModal",
			theme: "advanced",
			mode: "exact",
			elements: "<?=implode(",",$bigtree["simple_html_fields"])?>",
			file_browser_callback: "BigTreeFileManager.tinyMCEOpen",
			plugins: "inlinepopups,paste",
			theme_advanced_buttons1: "link,unlink,bold,italic,pasteword",
			theme_advanced_buttons2: "",
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
			gecko_spellcheck: true,
			relative_urls: false,
			remove_script_host: false,
			extended_valid_elements : "object[classid|codebase|width|height|align],param[name|value],embed[quality|type|pluginspage|width|height|src|align]"
			<? if ($width) { ?>,width: "<?=$width?>"<? } ?>
			<? if ($height) { ?>,height: "<?=$height?>"<? } ?>
		});
		<?
				}
			} elseif ($html_editor == "TinyMCE 4") {
				if (count($bigtree["html_fields"])) {
		?>
		tinyMCE.init({
  			<? if ($content_css) { ?>content_css: "<?=$content_css?>",<? } ?>
  			theme: "modern",
			mode: "exact",
			elements: "<?=implode(",",$bigtree["html_fields"])?>",
			file_browser_callback: BigTreeFileManager.tinyMCEOpen,
			menubar: false,
			plugins: "code,anchor,image,link,paste,table,visualblocks,lists",
			toolbar: "undo redo | styleselect | bold italic | bullist numlist outdent indent | hr anchor link unlink image table | paste | visualblocks code",
			paste_remove_spans: true,
			paste_remove_styles: true,
			paste_strip_class_attributes: true,
			paste_auto_cleanup_on_paste: true,
			relative_urls: false,
			remove_script_host: false,
			gecko_spellcheck: true,
			extended_valid_elements : "object[classid|codebase|width|height|align],param[name|value],embed[quality|type|pluginspage|width|height|src|align],iframe[src|class|width|height|name|align|style],figure[class],figcaption[class]"
			<? if ($width) { ?>,width: "<?=$width?>"<? } ?>
			<? if ($height) { ?>,height: "<?=$height?>"<? } ?>
		});
		<?
				}
				if (count($bigtree["simple_html_fields"])) {
		?>
		tinyMCE.init({
  			<? if ($content_css) { ?>content_css: "<?=$content_css?>",<? } ?>
  			theme: "modern",
			mode: "exact",
			elements: "<?=implode(",",$bigtree["simple_html_fields"])?>",
			file_browser_callback: BigTreeFileManager.tinyMCEOpen,
			menubar: false,
			plugins: "paste,link,code,visualblocks,lists",
			toolbar: "link unlink bold italic underline paste code",
			paste_remove_spans: true,
			paste_remove_styles: true,
			paste_strip_class_attributes: true,
			paste_auto_cleanup_on_paste: true,
			gecko_spellcheck: true,
			relative_urls: false,
			remove_script_host: false,
			extended_valid_elements : "object[classid|codebase|width|height|align],param[name|value],embed[quality|type|pluginspage|width|height|src|align]"
			<? if ($width) { ?>,width: "<?=$width?>"<? } ?>
			<? if ($height) { ?>,height: "<?=$height?>"<? } ?>
		});
		<?
				}
			} elseif ($html_editor == "Redactor") {
				if (count($bigtree["html_fields"])) {
					foreach ($bigtree["html_fields"] as $field) {
		?>
		$("#<?=$field?>").redactor({
			iframe: true,
			initCallback: function() {
				// Add the resize option in the bottom right corner
				$(this.getBox()).append($('<span class="resize"></span>').on("mousedown",$.proxy(function(ev) {
					var iframe = $(this.getIframe()).css({ overflow: "hidden" });
					this.lastMouseY = ev.clientY;
					this.iFrameHeight = iframe.height();
					this.iFrameOffsetTop = iframe.offset().top;

					// Event for mouse movement when in the main document
					this.moveProxy = $.proxy(function(ev) {
						this.iFrameHeight += (ev.clientY - this.lastMouseY);
						this.lastMouseY = ev.clientY;
						$(this.getIframe()).height(this.iFrameHeight);
					},this);

					// Event for mouse movement when we get into the iframe
					this.moveProxyiFrame = $.proxy(function(ev) {
						// Figure out where this iframe is relative to the scroll offset and all that
						y = ev.clientY + (this.iFrameOffsetTop - $(window).scrollTop());
						this.iFrameHeight += (y - this.lastMouseY);
						this.lastMouseY = y;
						$(this.getIframe()).height(this.iFrameHeight);
					},this);

					// The mouseup event to stop movement when dragging
					this.upProxy = $.proxy(function() {
						$(window).off("mousemove",this.moveProxy).off("mouseup",this.upProxy);
						$(this.getIframe()).css({ overflow: "auto" }).contents().find("body").off("mouseup",this.upProxy).off("mousemove",this.moveProxyiFrame);
						$.cookie("bigtree[redactor_height]",$(this.getIframe()).height(),{ expires: 1000, path: "/" });
					},this);

					// Hook the window and the iframe
					$(window).on("mousemove",this.moveProxy).on("mouseup",this.upProxy);
					$(iframe).contents().find("body").on("mousemove",this.moveProxyiFrame).on("mouseup",this.upProxy);
				},this)));
				<? if ($_COOKIE["bigtree"]["redactor_height"]) { ?>
				$(this.getIframe()).height(<?=$_COOKIE["bigtree"]["redactor_height"]?>);
				<? } ?>
			}
		});
		<?
					}
				}
			}
		?>
	});
</script>