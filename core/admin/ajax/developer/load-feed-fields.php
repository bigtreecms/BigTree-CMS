<?php
	namespace BigTree;
	
	/**
	 * @global string $table
	 */

	if ($_GET["table"]) {
		$table = $_GET["table"];
	}

	$used = [];
	$unused = [];
	
	$tblfields = [];

	// To tolerate someone selecting the blank spot again when creating a feed.
	if ($table) {
		$table_description = SQL::describeTable($table);
	} else {
		$table_description = ["columns" => []];
	}
	
	foreach ($table_description["columns"] as $column => $details) {
		$tblfields[] = $column;
	}

	if (isset($fields)) {
		foreach ($fields as $key => $field) {
			$used[] = $key;
		}
		
		// Figure out the fields we're not using so we can offer them back.
		foreach ($tblfields as $field) {
			if (!in_array($field,$used)) {
				$unused[] = [
					"title" => ucwords(str_replace("_"," ",$field)),
					"field" => $field
				];
			}
		}		
	} else {
		$fields = [];
		
		foreach ($tblfields as $f) {
			$title = ucwords(str_replace(["-", "_"]," ",$f));
			$title = str_replace(["Url", "Pdf", "Sql", "Id"], ["URL", "PDF", "SQL", "ID"], $title);
			$fields[$f] = ["title" => ucwords(str_replace(["-", "_"], " ", $title))];
		}
	}

	if (count($fields)) {
		$parser_placeholder = Text::translate('PHP code to transform $value (which contains the column value.)', true);
?>
<fieldset class="last">
	<label><?=Text::translate("Fields")?></label>

	<div class="form_table">
		<header></header>
		<div class="labels">
			<span class="developer_view_title"><?=Text::translate("Title")?></span>
			<span class="developer_view_parser"><?=Text::translate("Parser")?></span>
			<span class="developer_resource_action"><?=Text::translate("Delete")?></span>
		</div>
		<ul id="sort_table">
			<?php foreach ($fields as $key => $field) { ?>
			<li id="row_<?=$key?>">
				<input type="hidden" name="fields[<?=$key?>][width]" value="<?=$field["width"]?>" />
				<section class="developer_view_title">
					<span class="icon_sort"></span>
					<input type="text" name="fields[<?=$key?>][title]" value="<?=$field["title"]?>" />
				</section>
				<section class="developer_view_parser">
					<input type="text" name="fields[<?=$key?>][parser]" value="<?=htmlspecialchars($field["parser"])?>"  placeholder="<?=$parser_placeholder?>" />
				</section>
				<section class="developer_resource_action">
					<a href="#" class="icon_delete"></a>
				</section>
			</li>
			<?php } ?>
		</ul>
	</div>
</fieldset>

<script>
	$(".form_table").on("click",".icon_delete",function() {
		var tf = $(this).parents("li").find("section").find("input");
		
		var title = tf.val();
		var key = tf.attr("name").substr(7);
		key = key.substr(0,key.length-8);
		
		BigTree.localFieldSelect.addField(key,title);

		$(this).parents("li").remove();		
		return false;
	});		
	
	BigTree.localHooks = function() {
		$("#sort_table").sortable({ axis: "y", containment: "parent", handle: ".icon_sort", items: "li", placeholder: "ui-sortable-placeholder", tolerance: "pointer" });
	};
	BigTree.localHooks();

	BigTree.localFieldSelect = BigTreeFieldSelect({
		selector: ".form_table header",
		elements: <?=json_encode($unused)?>,
		callback: function(el,fs) {
			var title = el.title;
			var key = el.field;
			
			var li = $('<li id="row_' + key + '">');
			li.html('<section class="developer_view_title"><span class="icon_sort"></span><input type="text" name="fields[' + key + '][title]" value="' + title + '" /></section><section class="developer_view_parser"><input type="text" class="parser" name="fields[' + key + '][parser]" value="" placeholder="<?=$parser_placeholder?>" /></section><section class="developer_resource_action"><a href="#" class="icon_delete"></a></section>');
		
			fs.removeCurrent();
			$("#sort_table").append(li);
			BigTree.localHooks();
		}
	});

</script>
<?php
	} else {
?>
<p><?=Text::translate("Please choose a table to populate this area.")?></p>
<?php
	}
?>