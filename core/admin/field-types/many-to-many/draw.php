<?php
	namespace BigTree;
	
	// Find out whether this is a draggable Many to Many.
	$table_description = SQL::describeTable($this->Settings["mtm-connecting-table"]);
	$draggable = !empty($table_description["columns"]["position"]);
	$maximum = !empty($this->Settings["max"]) ? $this->Settings["max"] : 0;
	$minimum = !empty($this->Settings["min"]) ? $this->Settings["min"] : 0;
	$entries = [];
	$list = [];
	
	if ($minimum) {
		$this->Required = true;
	}
	
	// If we have existing data then this item is either pending or has pending changes so we use that data.
	if (is_array($this->Value)) {
		$entries = $this->Value;
	// No pending data, let's query the connecting table directly for the entries, but only if this isn't a new entry
	} elseif ($this->EntryID) {
		$entries = SQL::fetchAllSingle("SELECT `".$this->Settings["mtm-other-id"]."`
										FROM `".$this->Settings["mtm-connecting-table"]."`
										WHERE `".$this->Settings["mtm-my-id"]."` = ?".
									   ($draggable ? "ORDER BY `position` DESC" : ""), $this->EntryID);
	}

	// Gather a list of the items that could possibly be tagged.
	$query = SQL::query("SELECT id, `".$this->Settings["mtm-other-descriptor"]."`
						 FROM `".$this->Settings["mtm-other-table"]."`
						 ORDER BY ".$this->Settings["mtm-sort"]);
	
	while ($record = $query->fetch()) {
		$list[$record["id"]] = $record[$this->Settings["mtm-other-descriptor"]];
	}
	
	// If we have a parser, send a list of the options to it.
	if (isset($this->Settings["mtm-list-parser"]) && $this->Settings["mtm-list-parser"]) {
		$list = call_user_func($this->Settings["mtm-list-parser"], $list, true);
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
<field-type-relationship title="<?=$this->Title?>" subtitle="<?=$this->Subtitle?>" draggable="<?=$draggable?>"
						 required="<?=$this->Required?>" minimum="<?=$minimum?>" maximum="<?=$maximum?>"
						 name="<?=$this->Key?>" :value="<?=htmlspecialchars(json_encode($entries))?>"
						 :options="<?=htmlspecialchars(json_encode($options))?>"></field-type-relationship>
