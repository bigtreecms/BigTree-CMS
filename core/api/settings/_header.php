<?php
	namespace BigTree;
	
	if (!Auth::$Level) {
		API::triggerError("You do not have permission to access settings.", "settings:notallowed", "permissions");
	}
