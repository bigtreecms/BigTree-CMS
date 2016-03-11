<?php
	$resource = BigTree\Resource::getByFile($_POST["file"]);
	$resource->Name = $_POST["title"];
	$resource->save();
