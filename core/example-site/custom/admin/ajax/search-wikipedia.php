<?	
	if ($_GET["q"] != "") {
		$wikiAPI = new BTXWikipediaAPI;
		$results = $wikiAPI->search($_GET["q"]);
		if (count($results[1]) && is_array($results[1])) {
			echo '<ul>';
			foreach ($results[1] as $item) {
				echo '<li><a href="#" data-url="' . $wikiAPI->articleURL(str_ireplace(" ", "_", $item)) . '"><strong>' . $item . '</strong></a></a>';
			}
			echo '</ul>';
		} else {
			echo '<p>[No Results]</p>';
		}
	}
?>