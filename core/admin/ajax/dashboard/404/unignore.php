<?php
	namespace BigTree;

	$redirect = new Redirect($_POST["id"]);
	$redirect->Ignored = false;
	$redirect->save();