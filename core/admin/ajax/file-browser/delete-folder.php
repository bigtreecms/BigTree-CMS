<?php
	namespace BigTree;
	
	Auth::user()->requireLevel(1);
	
	$folder = new ResourceFolder($_POST["folder"]);
	$folder->delete();
	
	echo $folder->Parent ?: 0;