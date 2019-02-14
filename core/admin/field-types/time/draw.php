<?php
	namespace BigTree;
	
	if ($this->Value) {
		$this->Value = Auth::user()->convertTimestampTo($this->Value ?: "now", "h:i a");
	}

	// We draw the picker inline for callouts
	if (defined("BIGTREE_CALLOUT_RESOURCES")) {
		$time = strtotime($this->Value ? $this->Value : "Today");
?>
<input type="hidden" name="<?=$this->Key?>" value="<?php if ($this->Value) { echo date("h:i a",strtotime($this->Value)); } ?>" />
<div class="time_picker_inline" data-hour="<?=date("H",$time)?>" data-minute="<?=date("i",$time)?>"></div>
<?php
	} else {
		$bigtree["timepickers"][] = $this->ID;
?>
<input type="text" tabindex="<?=$this->TabIndex?>" name="<?=$this->Key?>" value="<?php if ($this->Value) { echo date("h:i a",strtotime($this->Value)); } ?>" autocomplete="off" id="<?=$this->ID?>" class="time_picker<?php if ($this->Required) { ?> required<?php } ?>" />
<span class="icon_small icon_small_clock time_picker_icon"></span>
<?php
	}
?>