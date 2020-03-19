<?php
	namespace BigTree;
	
	$valid_extensions = !empty($this->Settings["valid_extensions"]) ? $this->Settings["valid_extensions"] : "";
?>
<field-type-file-upload name="<?=$this->Key?>" value="<?=$this->Value?>" valid_extensions="<?=$valid_extensions?>"
						required="<?=$this->Required?>"></field-type-file-upload>
