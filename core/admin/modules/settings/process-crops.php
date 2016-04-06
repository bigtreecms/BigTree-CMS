<?php
	namespace BigTree;

	Image::processCrops($_POST["crop_key"]);
	
	Router::redirect(ADMIN_ROOT."settings/");
	