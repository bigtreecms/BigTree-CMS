<?php
	namespace BigTree;

	Image::processCrops($_POST["crop_key"]);
	
	// For embedded forms we let them process crops first since we're not saving the data to the database and we'll lose their uploads otherwise
	if (count($_SESSION["bigtree_admin"]["form_data"]["errors"])) {
		Router::redirect($form->Root."error/?id=".$form->ID."&hash=".$form->Hash);
	} else {
		unset($_SESSION["bigtree_admin"]["form_data"]);
		Router::redirect($form->Root."complete/?id=".$form->ID."&hash=".$form->Hash);
	}
	