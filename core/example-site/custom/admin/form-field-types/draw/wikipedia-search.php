<fieldset class="right">
	<label>Articles Found <a href="#" id="clear_wiki_results" style="float: right;">Clear</a></label>
	<div id="wiki_results">
	</div>
</fieldset>
<fieldset class="text_input left" id="wiki_search">
	<?
		if ($title) {
	?>
	<label<?=$label_validation_class?>><?=$title?><? if ($subtitle) { ?> <small><?=$subtitle?></small><? } ?></label>
	<?
		}
		
		$st = $options["sub_type"];
		if (!$st) {
	?>
	<input<?=$input_validation_class?> type="text" tabindex="<?=$tabindex?>" name="<?=$key?>" value="<?=$value?>" id="field_<?=$key?>" />
	<?
		}
	?>
	<a href="#" class="button small" id="do_wiki_search">Search Wikipedia &rarr;</a>
</fieldset>
<fieldset class="text_input left" id="wiki_active">
	<br class="clear" />
	<label>Selected Article</label>	
	<p id="wiki_preview">
		<strong><?=$item["wiki_title"]?></strong><br /><a href="<?=$item["wiki_url"]?>" target="_blank"><?=$item["wiki_url"]?></a>
	</p>
	<input type="hidden" name="wiki_title" class="title" value="<?=$item["wiki_title"]?>" id="field_wiki_title" />
	<input type="hidden" name="wiki_url" class="url" value="<?=$item["wiki_url"]?>" id="field_wiki_url" />
</fieldset>

<style>
	#wiki_search input { float: left; width: 290px; }
	#do_wiki_search { border-radius: 3px; display: block; float: right; font-size: 10px; height: 22px; line-height: 24px; margin: 4px 0 0; padding: 0 10px; }
	#wiki_preview { background: #fcfcfc; border: 1px solid #ddd; border-radius: 3px; padding: 10px 15px; }
	#wiki_results { background: #fcfcfc; border: 1px solid #ccc; border-radius: 3px; padding: 15px 20px; }
	#wiki_results ul { list-style: disc; line-height: 1.2; margin-left: 10px; }
	#wiki_results ul li { margin: 0 0 5px; }
	#wiki_results ul li a { color: #777; display: block; }
	#wiki_results ul li a:hover { color: #578FB1; }
	#wiki_results ul li a.active { color: #222; }
</style>
<script type="text/javascript">
	function doWikiSearch(e) {
		e.preventDefault();
		e.stopPropagation();
		
		var val = $("#wiki_search input").val();
		$.ajax({
			url: "<?=$admin_root?>ajax/search-wikipedia/",
			data: { q: val },
			success: function(data) {
				$("#wiki_results").html(data);
			}
		});
	}
	
	function clearWikiSearch(e) {
		e.preventDefault();
		e.stopPropagation();
		
		$("#wiki_results").html("");
	}
	
	function setWikiArticle(e) {
		e.preventDefault();
		e.stopPropagation();
		
		var $target = $(this);
		var $parent = $("#wiki_results");
		var $active = $("#wiki_active");
		
		$parent.find("a").removeClass("active");
		$target.addClass("active");
		
		var title = $target.find("strong").html();
		var url = $target.attr("data-url");
		
		$active.find(".title").val(title);
		$active.find(".url").val(url);
		
		$("#wiki_preview").html('<strong>' + title + '</strong><br /><a href="' + url + '" target="_blank">' + url + '</a>');
	}
	
	$(document).ready(function() {
		$("#do_wiki_search").on("click", doWikiSearch);
		$("#clear_wiki_results").on("click", clearWikiSearch);
		$("#wiki_results").on("click", "a", setWikiArticle);
	});
</script>

<br class="clear" />
<br />