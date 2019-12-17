<?php
	namespace BigTree;
	
	$sub_type = !empty($this->Settings["sub_type"]) ? $this->Settings["sub_type"] : false;
	$max_length = !empty($this->Settings["max_length"]) ? intval($this->Settings["max_length"]) : false;
	$required = !empty($this->Required);
	
	if ($sub_type === "address") {
?>
<field-type-address name="<?=$this->Key?>" :value="<?=htmlspecialchars(json_encode($this->Value))?>"
					title="<?=$this->Title?>" subtitle="<?=$this->Subtitle?>" required="<?=$this->Required?>">
</field-type-address>
<?php
	} elseif ($sub_type === "phone") {
?>
<field-type-phone name="<?=$this->Key?>" :value="<?=htmlspecialchars(json_encode($this->Value))?>"
				  title="<?=$this->Title?>" subtitle="<?=$this->Subtitle?>" required="<?=$this->Required?>">
</field-type-phone>
<?php
	} elseif ($sub_type === "email") {
?>
<field-type-text name="<?=$this->Key?>" value="<?=htmlspecialchars($this->Value)?>" type="email"
				 title="<?=$this->Title?>" subtitle="<?=$this->Subtitle?>" required="<?=$this->Required?>">
</field-type-text>
<?php
	} else {
?>
<field-type-text name="<?=$this->Key?>" value="<?=htmlspecialchars($this->Value)?>" maxlength="<?=$max_length?>"
				 title="<?=$this->Title?>" subtitle="<?=$this->Subtitle?>" required="<?=$this->Required?>">
</field-type-text>
<?php
	}
?>