<?php
	namespace BigTree;
	
	if ($this->Input) {
		$this->Output = Auth::user()->convertTimestampFrom($this->Input, "H:i:s");
	}
	