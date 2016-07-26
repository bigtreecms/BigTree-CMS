<?php
	namespace BigTree;
	
	Auth::user()->requireLevel(1);
	
	$folder = new ResourceFolder($_POST["folder"]);
	echo json_encode($folder->Statistics);