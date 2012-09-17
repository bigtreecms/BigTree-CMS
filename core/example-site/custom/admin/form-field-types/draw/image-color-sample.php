<fieldset>
	<?
		$img_source = str_ireplace(WWW_ROOT, SERVER_ROOT . "site/", str_ireplace("features/", "features/t_", $data["item"][$options["source"]]));
		
		if ($title) {
	?>
	<label<?=$label_validation_class?>><?=$title?><? if ($subtitle) { ?> <small><?=$subtitle?></small><? } ?></label>
	<?
		}
		
		if (!$img_source) {
	?>
	<input type="text" disabled="disabled" value="After uploading an image you can return to set the background color." />
	<?
		} else {
			include BigTree::path("inc/lib/colors.inc.php");
		
			$delta = 20;
			$reduce_brightness = false;
			$reduce_gradients = false;
			$num_results = 10;
			
			$samples = array();
		
			$colors = colorPalette($img_source, $num_results);
			foreach ($colors as $hex) {
				$samples[] = array(
					"value" => $hex
				);
			}
			
			foreach ($samples as $option) {
	?>
	<input type="radio" class="custom_control colors" name="<?=$key?>" tabindex="<?=$tabindex?>" value="<?=$option["value"]?>"<? if ($value == $option["value"]) { ?> checked="checked"<? } ?> />
	<?
			}
	?>
	<br class="clear" /><br /><br />
	<style>
		.color_button a { background: #FCFCFC; border: 3px solid #CCC; border-radius: 2px; display: block; float: left; height: 30px; margin: 0 5px 0 0; opacity: 0.25; text-align: center; transition: background-color 0.2s, box-shadow 0.2s; width: 30px; }
		.color_button a:hover { opacity: 1; }
		.color_button a.checked { border-color: #999; opacity: 1; }
		<? foreach ($samples as $option) { ?>
		.color_button a.c_<?=$option["value"]?> { background-color: #<?=$option["value"]?>; }
		<? } ?>
	</style>
	<script>
		$(document).ready(function() {
			$("input.colors").each(function() {
				if (!$(this).hasClass("custom_color_control")) {
					this.customControl = new CustomColorButton(this);
					$(this).addClass("custom_color_control");
				}
			});
		});
		// Custom Color Picker
		var CustomColorButton = Class.extend({
		
			Element: false,
			Link: false,
		
			init: function(element,text) {
				this.Element = $(element);
				
				div = $("<div>").addClass("color_button");
				a = $("<a>").attr("href","#color").addClass("c_" + this.Element.val());
				a.click($.proxy(this.click,this))
				 .focus($.proxy(this.focus,this))
				 .blur($.proxy(this.blur,this));
				
				if (element.checked) {
					a.addClass("checked");
				}
				
				if (element.tabIndex) {
					a.attr("tabIndex",element.tabIndex);
				}
				
				this.Link = a;
				
				div.append(a);
				this.Element.hide().after(div);
			},
			
			focus: function(ev) {
				this.Link.addClass("focused");
			},
			
			blur: function(ev) {
				this.Link.removeClass("focused");
			},
		
			click: function(ev) {
				if (this.Link.hasClass("checked")) {
					// If it's already clicked, nothing happens for radio buttons.
				} else {
					this.Link.addClass("checked");
					this.Element.attr("checked",true);
					$('input[name="' + this.Element.attr("name") + '"]').each(function() {
						if (!this.checked) {
							this.customControl.Link.removeClass("checked");
						}
					});
				}
				this.Element.trigger("checked:click");
				return false;
			}
		});
	</script>
	<?
		}
	?>
</fieldset>