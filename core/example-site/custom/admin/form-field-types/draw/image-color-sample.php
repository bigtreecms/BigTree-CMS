<?
	if ($bigtree["entry"][$field["options"]["source"]]) {
		include SERVER_ROOT."custom/inc/lib/colors.inc.php";
		$img_source = BigTree::prefixFile($bigtree["entry"][$field["options"]["source"]],"t_");
		$img_source = str_replace(array(STATIC_ROOT,WWW_ROOT),SITE_ROOT,$img_source);
		$colors = colorPalette($img_source,10);
		foreach ($colors as $color) {
?>
<a href="#" class="color_button<? if ($color == $field["value"]) { ?> selected<? } ?>" style="background-color: #<?=$color?>" data-value="<?=$color?>"></a>
<?
		}
?>
<input type="hidden" name="<?=$field["key"]?>" id="<?=$field["id"]?>" value="<?=$field["value"]?>" />
<style>
	.color_button { background: #FCFCFC; border: 3px solid #EEE; border-radius: 2px; display: block; float: left; height: 30px; margin: 0 5px 0 0; text-align: center; transition: border-color 0.2s, opacity 0.2s; width: 30px; }
	.color_button:hover { border-color: #CCC; }
	.color_button.selected { border-color: #999; opacity: 1; }
</style>
<script>
	$(document).ready(function() {
		$(".color_button").click(function(ev) {
			ev.preventDefault();
			ev.stopPropagation();
			fieldset = $(this).parents("fieldset");
			fieldset.find(".color_button").removeClass("selected");
			$(this).addClass("selected");
			fieldset.find("input").val($(this).attr("data-value"));
		});
	});
</script>
<?
	} else {
?>
<input type="text" disabled="disabled" value="After uploading an image you can return to set the background color." />
<?		
	}
?>