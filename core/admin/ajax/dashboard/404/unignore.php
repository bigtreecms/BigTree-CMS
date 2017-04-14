<?php
	namespace BigTree;
	
	CSRF::verify();

	$redirect = new Redirect($_POST["id"]);
	$redirect->Ignored = false;
	$redirect->save();
	