<?php
	namespace BigTree;
	
	CSRF::verify();
	Image::processCrop($_POST["crop_key"], $_POST["index"], $_POST["x"], $_POST["y"], $_POST["width"], $_POST["height"]);
	