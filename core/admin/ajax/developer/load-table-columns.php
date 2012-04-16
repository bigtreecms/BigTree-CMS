<?
	$field = $_GET["field"] ? $_GET["field"] : $_POST["field"];
	$table = $_GET["table"] ? $_GET["table"] : $_POST["table"];
	$sort = $_GET["sort"] ? $_GET["sort"] : $_POST["sort"];
	
	if ($table) {
		$cols = sqlcolumns($table);
?>
<select name="<?=$field?>">
	<option></option>
	<?
		foreach ($cols as $col) {
			if ($sort) {
				echo '<option>'.$col["name"].' ASC</option>';
				echo '<option>'.$col["name"].' DESC</option>';
			} else {
				echo '<option>'.$col["name"].'</option>';
			}		
		}
	?>
</select>
<?
	} else {
?>
<small>-- Please select a table --</small>
<?
	}
?>