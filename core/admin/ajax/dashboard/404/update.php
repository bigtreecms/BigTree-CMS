<?php
	namespace BigTree;
	
	CSRF::verify();

	$redirect = new Redirect($_POST["id"]);
	$redirect->RedirectURL = $_POST["value"];
	$redirect->save();
	