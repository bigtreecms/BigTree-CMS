<?php
	namespace BigTree;

	$redirect = new Redirect($_POST["id"]);
	$redirect->RedirectURL = $_POST["value"];
	$redirect->save();