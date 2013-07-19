<?
	// Notices :(
	if (count($_GET)) {
		$field = isset($_GET["field"]) ? $_GET["field"] : "";
		$table = isset($_GET["table"]) ? $_GET["table"] : "";
		$sort = isset($_GET["sort"]) ? $_GET["sort"] : "";
	} else {
		$field = isset($_POST["field"]) ? $_POST["field"] : "";
		$table = isset($_POST["table"]) ? $_POST["table"] : "";
		$sort = isset($_POST["sort"]) ? $_POST["sort"] : "";	
	}
	
	if ($table) {
		$table_description = BigTree::describeTable($table);
?>
<select name="<?=$field?>">
	<?=BigTree::getFieldSelectOptions($table,$field,$sort)?>
</select>
<?
	} else {
		echo "&mdash;";
	}
?>
<script>BigTreeCustomControls();</script>