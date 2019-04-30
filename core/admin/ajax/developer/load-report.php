<?php
	namespace BigTree;
	
	/**
	 * @global array $filters
	 * @global string $table
	 * @global string $type
	 */

	$filter_types = [
		"search" => Text::translate("Simple Search"),
		"dropdown" => Text::translate("Dropdown Select"),
		"boolean" => Text::translate("Yes/No/Both Select"),
		"date-range" => Text::translate("Date Range")
	];
	$report_type = isset($_POST["report_type"]) ? $_POST["report_type"] : $type;
	
	$used_fields = [];
	$used_filters = [];
	$unused_fields = [];
	$unused_filters = [];
	
	$table = isset($_POST["table"]) ? $_POST["table"] : $table;
	$table_columns = [];

	if (isset($fields)) {
		foreach ($fields as $key => $field) {
			$used_fields[] = $key;
		}
		foreach ($filters as $key => $field) {
			$used_filters[] = $key;
		}
		// Figure out the fields we're not using so we can offer them back.
		$table_description = SQL::describeTable($table);
		foreach ($table_description["columns"] as $column => $details) {
			if (!in_array($column,$used_fields)) {
				$unused_fields[] = ["field" => $column, "title" => str_replace(["Url", "Pdf", "Sql"], ["URL", "PDF", "SQL"], ucwords(str_replace(["-", "_"], " ", $details["name"])))];
			}
			if (!in_array($column,$used_filters)) {
				$unused_filters[] = ["field" => $column, "title" => str_replace(["Url", "Pdf", "Sql"], ["URL", "PDF", "SQL"], ucwords(str_replace(["-", "_"], " ", $details["name"])))];
			}
			$table_columns[] = $column;
		}
	} else {
		$fields = [];
		$filters = [];

		// To tolerate someone selecting the blank spot in the table dropdown again when creating a form.
		if ($table) {
			$table_info = SQL::describeTable($table);
		} else {
			$table_info = ["foreign_keys" => [], "columns" => []];
		}

		foreach ($table_info["columns"] as $column) {
			$table_columns[] = $column["name"];
			$title = str_replace(["Url", "Pdf", "Sql"], ["URL", "PDF", "SQL"], ucwords(str_replace(["-", "_"], " ", $column["name"])));
			$fields[$column["name"]] = $title;

			$type = "search";
			if ($column["type"] == "date" || $column["type"] == "datetime" || $column["type"] == "timestamp") {
				$type = "date-range";
			}
			if ($column["name"] == "approved" || $column["name"] == "archived" || $column["name"] == "featured") {
				$type = "boolean";
			}

			$filters[$column["name"]] = ["title" => $title, "type" => $type];
		}
	}

	if (count($fields)) {
?>
<fieldset id="filter_table" class="last">
	<label><?=Text::translate("Report Filters")?></label>
	
	<div class="form_table">
		<header></header>
		<div class="labels">
			<span class="developer_report_filter_title"><?=Text::translate("Title")?></span>
			<span class="developer_report_filter_type"><?=Text::translate("Type")?></span>
			<span class="developer_report_action"></span>
		</div>
		<ul>
			<?php
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
						<?php foreach ($filter_types as $t => $d) { ?>
						<option value="<?=$t?>"<?php if ($t == $filter["type"]) { ?> selected="selected"<?php } ?>><?=$d?></option>
						<?php } ?>
					</select>
				</section>
				<section class="developer_report_action">
					<a href="#" class="icon_delete" name="<?=$key?>"></a>
				</section>
			</li>
			<?php
					}
				}
			?>
		</ul>
	</div>
</fieldset>

<fieldset id="field_table" class="last"<?php if ($report_type != "csv") { ?> style="display: none;"<?php } ?>>
	<br /><br />
	<label><?=Text::translate("Fields to Include in CSV File")?></label>
	<div class="form_table">
		<header></header>
		<div class="labels">
			<span class="developer_report_field_title"><?=Text::translate("Title")?></span>
			<span class="developer_report_action"></span>
		</div>
		<ul>
			<?php
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
			<?php
					}
				}
			?>
		</ul>
	</div>
</fieldset>

<script>
	BigTree.localFieldSelect = BigTreeFieldSelect({
		selector: "#field_table header",
		elements: <?=json_encode($unused_fields)?>,
		callback: function(el,fs) {
			var title = el.title;
			var key = el.field;
			
			var li = $('<li id="row_' + key + '">');
			li.html('<section class="developer_report_field_title"><span class="icon_sort"></span><input type="text" name="fields[' + key + ']" value="' + title + '" /></section><section class="developer_report_action"><a href="#" class="icon_delete" name="' + key + '"></a></section>');
			
			$("#field_table").find("ul").append(li);
			fs.removeCurrent();
			BigTree.localHooks();
		}
	});

	BigTree.localFilterSelect = BigTreeFieldSelect({
		selector: "#filter_table header",
		elements: <?=json_encode($unused_filters)?>,
		callback: function(el,fs) {
			var title = el.title;
			var key = el.field;
			
			var li = $('<li id="row_' + key + '">');
			li.html('<section class="developer_report_filter_title"><span class="icon_sort"></span><input type="text" name="filters[' + key + '][title]" value="' + title + '" /></section><section class="developer_report_filter_type"><select name="filters[' + key + '][type]"><?php foreach ($filter_types as $k => $v) { ?><option value="<?=$k?>"><?=$v?></option><?php } ?></select></section><section class="developer_report_action"><a href="#" class="icon_delete" name="' + key + '"></a></section>');
			
			$("#filter_table").find("ul").append(li);
			fs.removeCurrent();
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