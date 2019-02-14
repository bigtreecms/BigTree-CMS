<?php
	namespace BigTree;
	
	/**
	 * @global array $bigtree
	 */
	
	if (!empty($this->Settings["simple"]) || (isset($this->Settings["simple_by_permission"]) && $this->Settings["simple_by_permission"] > Auth::user()->Level)) {
		$bigtree["simple_html_fields"][] = $this->ID;
	} else {
		$bigtree["html_fields"][] = $this->ID;
	}
?>
<textarea class="<?=$this->Settings["validation"]?>" name="<?=$this->Key?>" tabindex="<?=$this->TabIndex?>" id="<?=$this->ID?>"><?=htmlspecialchars($this->Value)?></textarea>