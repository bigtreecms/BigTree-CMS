<?php
	namespace BigTree;

	$redirect = new Redirect($_POST["id"]);
	$redirect->delete();