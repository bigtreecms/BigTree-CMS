<?php
	use BigTree\Image;

	Image::processCrops($_POST["crop_key"]);
	
	BigTree::redirect(ADMIN_ROOT."settings/");