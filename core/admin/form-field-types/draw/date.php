<?php
	if (!$this->Value && isset($this->Settings["default_today"]) && $this->Settings["default_today"]) {
		$this->Value = date($bigtree["config"]["date_format"]);
	} elseif ($this->Value && $this->Value != "0000-00-00" && $this->Value != "0000-00-00 00:00:00") {
		$this->Value = date($bigtree["config"]["date_format"],strtotime($this->Value));
	} else {
		$this->Value = "";
	}
	
	// We draw the picker inline for callouts
	if (defined("BIGTREE_CALLOUT_RESOURCES")) {
?>
<input type="hidden" name="<?=$this->Key?>" value="<?=$this->Value?>" />
<div class="date_picker_inline" data-date="<?=$this->Value?>"></div>
<div class="date_picker_clear">Clear Date</div>
<?php
	} else {
?>
<input type="text" tabindex="<?=$this->TabIndex?>" name="<?=$this->Key?>" value="<?=$this->Value?>" autocomplete="off" id="<?=$this->ID?>" class="date_picker<?php if ($this->Required) { ?> required<?php } ?>" />
<span class="icon_small icon_small_calendar date_picker_icon"></span>
<?php
	}
?>