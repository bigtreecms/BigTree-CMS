<?
	$fields = explode(",",$options["fields"]);
	$location = array();
	foreach ($fields as $field) {
		$location[] = $data[trim($field)];
	}
	$location = urlencode(trim(implode(", ",$location)));
	
	$file = utf8_encode(file_get_contents("http://maps.google.com/maps/geo?q=$location&output=xml&key=".$GLOBALS["gmaps_key"]));
	$xml = new SimpleXMLElement($file);
	try {
		$coords = explode(",",$xml->Response->Placemark->Point->coordinates);
		$item["latitude"] = $coords[1];
		$item["longitude"] = $coords[0];
	} catch (Exception $e) {
	}
	
	$no_process = true;
?>