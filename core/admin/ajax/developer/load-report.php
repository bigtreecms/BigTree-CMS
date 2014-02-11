<?
	$filter_types = array(
		"search" => "Simple Search",
		"dropdown" => "Dropdown Select",
		"boolean" => "Yes/No/Both Select",
		"date-range" => "Date Range"
	);
	$type = isset($_POST["report_type"]) ? $_POST["report_type"] : $type;
	
	$used_fields = array();
	$used_filters = array();
	$unused_fields = array();
	$unused_filters = array();
	
	$table = isset($_POST["table"]) ? $_POST["table"] : $table;
	$table_columns = array();

	if (isset($fields)) {
		foreach ($fields as $key => $field) {
			$used_fields[] = $key;
		}
		foreach ($filters as $key => $field) {
			$used_filters[] = $key;
		}
		// Figure out the fields we're not using so we can offer them back.
		$table_description = BigTree::describeTable($table);
		foreach ($table_description["columns"] as $column => $details) {
			if (!in_array($column,$used_fields)) {
				$unused_fields[] = array("field" => $column, "title" => str_replace(array("Url","Pdf","Sql"),array("URL","PDF","SQL"),ucwords(str_replace(array("-","_")," ",$details["name"]))));
			}
			if (!in_array($column,$used_filters)) {
				$unused_filters[] = array("field" => $column, "title" => str_replace(array("Url","Pdf","Sql"),array("URL","PDF","SQL"),ucwords(str_replace(array("-","_")," ",$details["name"]))));
			}
			$table_columns[] = $column;
		}
	} else {
		$fields = array();
		$filters = array();

		// To tolerate someone selecting the blank spot in the table dropdown again when creating a form.
		if ($table) {
			$table_info = BigTree::describeTable($table);
		} else {
			$table_info = array("foreign_keys" => array(), "columns" => array());
		}

		foreach ($table_info["columns"] as $column) {
			$table_columns[] = $column["name"];
			$title = str_replace(array("Url","Pdf","Sql"),array("URL","PDF","SQL"),ucwords(str_replace(array("-","_")," ",$column["name"])));
			$fields[$column["name"]] = $title;

			$type = "search";
			if ($column["type"] == "date" || $column["type"] == "datetime" || $column["type"] == "timestamp") {
				$type = "date-range";
			}
			if ($column["name"] == "approved" || $column["name"] == "archived" || $column["name"] == "featured") {
				$type = "boolean";
			}

			$filters[$column["name"]] = array("title" => $title,"type" => $type);
		}
	}

	if (count($fields)) {
?>
<fieldset id="filter_table" class="last">
	<label>Report Filters</label>
	
	<div class="form_table">
		<header></header>
		<div class="labels">
			<span class="developer_report_filter_title">Title</span>
			<span class="developer_report_filter_type">Type</span>
			<span class="developer_report_action"></span>
		</div>
		<ul>
			<?
				foreach ($filters as $key => $filter) {
					// If this column is no longer in the table, we're going to remove it.
					if (in_array($key,$table_columns)) {
						$used[] = $key;
			?>
			<li>
				<section class="developer_report_filter_title">
					<span class="icon_sort"></span>
					<input type="text" name="filters[<?=$key?>][title]" value="<?=htmlspecialchars($filter["title"])?>" />
				</section>
				<section class="developer_report_filter_type">
					<select name="filters[<?=$key?>][type]">
						<? foreach ($filter_types as $t => $d) { ?>
						<option value="<?=$t?>"<? if ($t == $filter["type"]) { ?> selected="selected"<? } ?>><?=$d?></option>
						<? } ?>
					</select>
				</section>
				<section class="developer_report_action">
					<a href="#" class="icon_delete" name="<?=$key?>"></a>
				</section>
			</li>
			<?
					}
				}
			?>
		</ul>
	</div>
</fieldset>

<fieldset id="field_table" class="last"<? if ($type != "csv") { ?> style="display: none;"<? } ?>>
	<br /><br />
	<label>Fields to Include in CSV File</label>
	<div class="form_table">
		<header></header>
		<div class="labels">
			<span class="developer_report_field_title">Title</span>
			<span class="developer_report_action"></span>
		</div>
		<ul>
			<?
				foreach ($fields as $key => $field) {
					// If this column is no longer in the table, we're going to remove it.
					if (in_array($key,$table_columns)) {
						$used[] = $key;
			?>
			<li>
				<section class="developer_report_field_title">
					<span class="icon_sort"></span>
					<input type="text" name="fields[<?=$key?>]" value="<?=htmlspecialchars($field)?>" />
				</section>
				<section class="developer_report_action">
					<a href="#" class="icon_delete" name="<?=$key?>"></a>
				</section>
			</li>
			<?
					}
				}
			?>
		</ul>
	</div>
</fieldset>

<script>
	fieldSelect = new BigTreeFieldSelect("#field_table header",<?=json_encode($unused_fields)?>,function(el,fs) {
		title = el.title;
		key = el.field;
		
		li = $('<li id="row_' + key + '">');
		li.html('<section class="developer_report_field_title"><span class="icon_sort"></span><input type="text" name="fields[' + key + ']" value="' + title + '" /></section><section class="developer_report_action"><a href="#" class="icon_delete" name="' + key + '"></a></section>');
		
		$("#field_table ul").append(li);
		fs.removeCurrent();
		BigTree.localHooks();
	});

	filterSelect = new BigTreeFieldSelect("#filter_table header",<?=json_encode($unused_filters)?>,function(el,fs) {
		title = el.title;
		key = el.field;
		
		li = $('<li id="row_' + key + '">');
		li.html('<section class="developer_report_filter_title"><span class="icon_sort"></span><input type="text" name="filters[' + key + '][title]" value="' + title + '" /></section><section class="developer_report_filter_type"><select name="filters[' + key + '][type]"><? foreach ($filter_types as $k => $v) { ?><option value="<?=$k?>"><?=$v?></option><? } ?></select></section><section class="developer_report_action"><a href="#" class="icon_delete" name="' + key + '"></a></section>');
		
		$("#filter_table ul").append(li);
		fs.removeCurrent();
		BigTree.localHooks();
	});
</script>
<?
	} else {
?>
<p>Please choose a table to populate this area.</p>
<?
	}
?>