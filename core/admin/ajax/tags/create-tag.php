<?php
	namespace BigTree;
	
	CSRF::verify();
	Tag::create($_POST["tag"]);
	