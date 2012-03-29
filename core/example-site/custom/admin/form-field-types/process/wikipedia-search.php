<? 
	$value = $data[$key]; 
	
	// Independently set other attributes in the saved item.
	$item["wiki_title"] = htmlspecialchars($data["wiki_title"]);
	$item["wiki_url"] = htmlspecialchars($data["wiki_url"]);
?> 