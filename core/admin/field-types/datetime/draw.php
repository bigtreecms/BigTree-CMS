<?php
	namespace BigTree;
	use DateTime;
	
	if (!$this->Value && isset($this->Settings["default_now"]) && $this->Settings["default_now"]) {
		$this->Value = Auth::user()->convertTimestampTo("now", Router::$Config["date_format"]." h:i a");
	} elseif ($this->Value && $this->Value != "0000-00-00 00:00:00") {
		$this->Value = Auth::user()->convertTimestampTo($this->Value, Router::$Config["date_format"]." h:i a");
	} else {
		$this->Value = "";
	}
	
	// We draw the picker inline for callouts
	if (defined("BIGTREE_CALLOUT_RESOURCES")) {
		// Required and in-line is hard to validate, so default to today's date regardless
		if ($this->Required && empty($this->Value)) {
			$this->Value =  Auth::user()->convertTimestampTo("now", Router::$Config["date_format"]." h:i a");
		}

		// Process hour/minute
		if ($this->Value) {
			$date = DateTime::createFromFormat(Router::$Config["date_format"]." h:i a",$this->Value);
			$hour = $date->format("H");
			$minute = $date->format("i");
		} else {
			$hour = "0";
			$minute = "0";
		}
?>
<input type="hidden" name="<?=$this->Key?>" value="<?=$this->Value?>">
<div class="date_time_picker_inline" data-date="<?=$this->Value?>" data-hour="<?=$hour?>" data-minute="<?=$minute?>"></div>
<?php
		if (empty($this->Required)) {
			echo '<div class="date_picker_clear">'.Text::translate("Clear Date").'</div>';
		}
	} else {
?>
<input type="text" tabindex="<?=$this->TabIndex?>" name="<?=$this->Key?>" value="<?=$this->Value?>" autocomplete="off" id="<?=$this->ID?>" class="date_time_picker<?php if ($this->Required) { ?> required<?php } ?>">
<span class="icon_small icon_small_calendar date_picker_icon"></span>
<?php
	}
?>