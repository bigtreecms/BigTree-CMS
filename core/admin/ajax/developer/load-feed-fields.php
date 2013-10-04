<?
	if ($_GET["table"]) {
		$table = $_GET["table"];
	}

	$used = array();
	$unused = array();
	
	$tblfields = array();

	// To tolerate someone selecting the blank spot again when creating a feed.
	if ($table) {
		$table_description = BigTree::describeTable($table);
	} else {
		$table_description = array("columns" => array());
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
				$unused[] = array(
					"title" => ucwords(str_replace("_"," ",$field)),
					"field" => $field
				);
			}
		}		
	} else {
		$fields = array();
		foreach ($tblfields as $f) {
			$title = ucwords(str_replace(array("-","_")," ",$f));
			$title = str_replace(array("Url","Pdf","Sql","Id"),array("URL","PDF","SQL","ID"),$title);
			$fields[$f] = array("title" => ucwords(str_replace(array("-","_")," ",$title)));
		}
	}

	if (count($fields)) {
?>
<fieldset class="last">
	<label>Fields</label>

	<div class="form_table">
		<header></header>
		<div class="labels">
			<span class="developer_view_title">Title</span>
			<span class="developer_view_parser">Parser</span>
			<span class="developer_resource_action">Delete</span>
		</div>
		<ul id="sort_table">
			<? foreach ($fields as $key => $field) { ?>
			<li id="row_<?=$key?>">
				<input type="hidden" name="fields[<?=$key?>][width]" value="<?=$field["width"]?>" />
				<section class="developer_view_title">
					<span class="icon_sort"></span>
					<input type="text" name="fields[<?=$key?>][title]" value="<?=$field["title"]?>" />
				</section>
				<section class="developer_view_parser">
					<input type="text" name="fields[<?=$key?>][parser]" value="<?=$field["parser"]?>"  placeholder="PHP code to transform $value (which contains the column value.)" />
				</section>
				<section class="developer_resource_action">
					<a href="#" class="icon_delete"></a>
				</section>
			</li>
			<? } ?>
		</ul>
	</div>
</fieldset>

<script>
	$(".form_table").on("click",".icon_delete",function() {
		tf = $(this).parents("li").find("section").find("input");
		
		title = tf.val();
		key = tf.attr("name").substr(7);
		key = key.substr(0,key.length-8);
		
		fieldSelect.addField(key,title);

		$(this).parents("li").remove();		
		return false;
	});		
	
	BigTree.localHooks = function() {
		$("#sort_table").sortable({ axis: "y", containment: "parent", handle: ".icon_sort", items: "li", placeholder: "ui-sortable-placeholder", tolerance: "pointer" });
	}
	BigTree.localHooks();

	fieldSelect = new BigTreeFieldSelect(".form_table header",<?=json_encode($unused)?>,function(el,fs) {
		title = el.title;
		key = el.field;
		
		if (title) {
			li = $('<li id="row_' + key + '">');
			li.html('<section class="developer_view_title"><span class="icon_sort"></span><input type="text" name="fields[' + key + '][title]" value="' + title + '" /></section><section class="developer_view_parser"><input type="text" class="parser" name="fields[' + key + '][parser]" value="" placeholder="PHP code to transform $value (which contains the column value.)"/></section><section class="developer_resource_action"><a href="#" class="icon_delete"></a></section>');
		
			fs.removeCurrent();
			$("#sort_table").append(li);
			BigTree.localHooks();
		} else {
			new BigTreeDialog("Add Custom Column",'<fieldset><label>Column Key <small>(must be unique)</small></label><input type="text" name="key" /></fieldset><fieldset><label>Column Title</label><input type="text" name="title" /></fieldset>',function(data) {
				key = htmlspecialchars(data.key);
				title = htmlspecialchars(data.title);
				
				li = $('<li id="row_' + key + '">');
				li.html('<section class="developer_view_title"><span class="icon_sort"></span><input type="text" name="fields[' + key + '][title]" value="' + title + '" /></section><section class="developer_view_parser"><input type="text" class="parser" name="fields[' + key + '][parser]" value="" placeholder="PHP code to transform $value (which contains the column value.)" /></section><section class="developer_resource_action"><a href="#" class="icon_delete"></a></section>');
				$("#sort_table").append(li);
				BigTree.localHooks();
			});
		}
	});

</script>
<?
	} else {
?>
<p>Please choose a table to populate this area.</p>
<?
	}
?>