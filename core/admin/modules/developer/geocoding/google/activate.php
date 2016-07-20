<?php
	namespace BigTree;
	
	$setting = new Setting("bigtree-internal-geocoding-service");
	$setting->Value["service"] = "google";
	$setting->save();

	Utils::growl("Developer","Geocoding Service set to Google");
	Router::redirect(DEVELOPER_ROOT);
	