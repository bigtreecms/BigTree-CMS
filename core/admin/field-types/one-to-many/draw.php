<?php
	namespace BigTree;
	
	if (!is_array($this->Value)) {
		$this->Value = @json_decode($this->Value, true);
	}
	
	if (!is_array($this->Value)) {
		$this->Value = [];
	}

	$maximum = !empty($this->Settings["max"]) ? $this->Settings["max"] : 0;
	$minimum = !empty($this->Settings["min"]) ? $this->Settings["min"] : 0;
	
	if ($minimum) {
		$this->Required = true;
	}

	// Throw an exception if they didn't setup the field type properly
	if (!$this->Settings["table"] || !$this->Settings["title_column"]) {
		trigger_error("One-to-Many field type requires a table and a title field to be setup to function.", E_USER_ERROR);
	}

	$sort = $this->Settings["sort_by_column"] ? $this->Settings["sort_by_column"] : $this->Settings["title_column"]." ASC";

	// Gather a list of the items that could possibly be used
	$list = [];
	$query = SQL::query("SELECT `id`, `".$this->Settings["title_column"]."`
						 FROM `".$this->Settings["table"]."`
						 ORDER BY $sort");
	
	while ($record = $query->fetch()) {
		$list[$record["id"]] = $record[$this->Settings["title_column"]];
	}

	// If we have a parser, send a list of the entries and available items through it.
	if (!empty($this->Settings["parser"])) {
		$list = call_user_func($this->Settings["parser"], $list, true);
	}
	
	// Switch list to the options format expected by the relationship field
	$options = [];
	
	foreach ($list as $id => $title) {
		$options[] = [
			"value" => $id,
			"title" => $title
		];
	}
?>
<field-type-relationship title="<?=$this->Title?>" subtitle="<?=$this->Subtitle?>" draggable="true"
						 required="<?=$this->Required?>" minimum="<?=$minimum?>" maximum="<?=$maximum?>"
						 name="<?=$this->Key?>" :value="<?=htmlspecialchars(json_encode($this->Value))?>"
						 :options="<?=htmlspecialchars(json_encode($options))?>"></field-type-relationship>

