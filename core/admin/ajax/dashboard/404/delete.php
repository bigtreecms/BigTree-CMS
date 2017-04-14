<?php
	namespace BigTree;
	
	CSRF::verify();

	$redirect = new Redirect($_POST["id"]);
	$redirect->delete();