<?php
	namespace BigTree;

	$redirect = new Redirect($_POST["id"]);
	$redirect->Ignored = true;
	$redirect->save();