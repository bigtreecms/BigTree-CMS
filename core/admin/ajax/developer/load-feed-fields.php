<?
	if ($_GET["table"]) {
		$table = $_GET["table"];
	}

	$used = array();
	$unused = array();
	
	$tblfields = array();
	$table_description = BigTree::describeTable($table);
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
				$unused[$field] = $field;
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
?>
<fieldset>
	<label>Fields <small>(For RSS feeds, you do not need to manage this section.  Please edit the feed options to assign fields to RSS entries.)</small></label>

	<div class="form_table">
		<header>
			<a href="#" class="add add_field"><span></span>Add</a>
			<select id="unused_field" class="custom_control">
				<? foreach ($unused as $field => $title) { ?>
				<option value="<?=htmlspecialchars($title)?>"><?=htmlspecialchars($field)?></option>
				<? } ?>
			</select>
		</header>
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
					<input type="text" name="fields[<?=$key?>][parser]" value="<?=$field["parser"]?>" />
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
	var current_editing_key;
	
	$(".form_table .icon_delete").live("click",function() {
		new BigTreeDialog("Delete Resource",'<p class="confirm">Are you sure you want to delete this field?</p>',$.proxy(function() {
			tf = $(this).parents("li").find("section").find("input");
		
			title = tf.val();
			key = tf.attr("name").substr(7);
			key = key.substr(0,key.length-8);
			
			sel = $("#unused_field").get(0);
			sel.options[sel.options.length] = new Option(key,title,false,false);
			$(this).parents("li").remove();
		},this),"delete",false,"OK");

		return false;
	});
		
	
	$("#sort_table").sortable({ axis: "y", containment: "parent", handle: ".icon_sort", items: "li", placeholder: "ui-sortable-placeholder", tolerance: "pointer" });
	
	$("#field_area .add").click(function() {
		un = $("#unused_field").get(0);
		
		if (un.selectedIndex > -1) {
			key = un.options[un.selectedIndex].text;
			title = un.options[un.selectedIndex].value;

			li = $('<li id="row_' + key + '">');
			li.html('<section class="developer_view_title"><span class="icon_sort"></span><input type="text" name="fields[' + key + '][title]" value="' + title + '" /></section><section class="developer_view_parser"><input type="text" class="parser" name="fields[' + key + '][parser]" value="" /></section><section class="developer_resource_action"><a href="#" class="icon_delete"></a></section>');
		
			$("#sort_table").append(li);
			$("#sort_table").sortable({ items: "li", handle: ".icon_sort" });
			un.remove(un.selectedIndex);
		} else {
			new BigTreeDialog("Add Custom Column",'<fieldset><label>Column Key <small>(must be unique)</small></label><input type="text" name="key" /></fieldset><fieldset><label>Column Title</label><input type="text" name="title" /></fieldset>',function(data) {
				key = htmlspecialchars(data.key);
				title = htmlspecialchars(data.title);
				
				li = $('<li id="row_' + key + '">');
				li.html('<section class="developer_view_title"><span class="icon_sort"></span><input type="text" name="fields[' + key + '][title]" value="' + title + '" /></section><section class="developer_view_parser"><input type="text" class="parser" name="fields[' + key + '][parser]" value="" /></section><section class="developer_resource_action"><a href="#" class="icon_delete"></a></section>');
		
				$("#sort_table").append(li);
				$("#sort_table").sortable({ items: "li", handle: ".icon_sort" });
			});
		}
		
		return false;
	});
</script>