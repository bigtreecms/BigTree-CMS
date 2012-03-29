<?
	/* BASIC NAV RECURSION */
	function recurseNav($nav, $mypage = "", $isSitemap = false) {
		global $config, $www_root;
		$lpage = $config["domain"].$_SERVER["REQUEST_URI"];
		$i = 0;
		$count = count($nav);
		if ($count > 0) {
			echo '<ul>';
			foreach ($nav as $navitem) {
				$link = $navitem["link"];
				$target = (isset($navitem['new_window']) && $navitem['new_window'] == 'Yes') ? ' target="_blank"' : '';
				$hasSubnav = strpos($mypage,$link) !== false && $navitem["children"];
				
				$class = ' class="';
				if (strpos($mypage, $link) !== false) {
					$class .= 'sub_current';
				}
				if ($mypage == $link) {
					$class .= ' active';
				}
				if ($navitem["class"] != "") {
					$class .= " ".$navitem["class"];
				}
				$class .= '"';
				
				$li_class = ' class="';
				if ($hasSubnav) {
					$li_class .= ' has_subnav';
				}
				if (strpos($mypage, $link) !== false) {
					$li_class .= ' sub_current';
				}
				if ($mypage == $link) {
					$li_class .= ' active';
				}
				if ($i == 0) {
					$li_class .= ' first';
				}
				if ($i == $count-1) {
					$li_class .= ' last';
				}
				if ($i % 2 == 0) {
					$li_class .= " even";
				} else {
					$li_class .= " odd";
				}
				$li_class .= '"';
				
				echo '<li' . $li_class . '>';
				echo '<a href="' . $link . '"' . $target . $class . '>' . $navitem["title"].'</a>';

				if ((strpos($lpage, $link) !== false && $navitem["children"]) || $isSitemap === true) {
					recurseNav($navitem["children"], $mypage, $isSitemap);
				}

				echo '</li>';
				$i++;
			}
			echo '</ul>';
		}
	}
	
	
	/* STRING FUNCTIONS */
	function splitFirstP($html) {
		$parts = explode("</p>", $html, 2);
		$parts[0] = $parts[0]."</p>";
		return $parts;
	}
	
	function splitCenterContent($html) {
		$return = array();
		$parts = explode("</p>", $html);
		$middle = floor(count($parts) / 2) - 1;
		$j = 0;
		for ($i = 0; $i < count($parts); $i++) {
			$return[$j] .= $parts[$i]."</p>";
			if ($i == $middle) {
				$j++;
			}
		}
		return $return;
	}
	
	function splitAtP($html, $middle) {
		$return = array();
		$parts = explode("</p>", $html);
		$count = count($parts);
		
		if ($middle < $count) {
			$middle = $middle - 1;
			$j = 0;
			for ($i = 0; $i < $count; $i++) {
				$return[$j] .= $parts[$i]."</p>";
				if ($i == $middle) {
					$j++;
				}
			}
			return $return;
		}
		return array($html, "");
	}
	
	function trimFirstP($html, $count) {
		$html = getFirstP($html);
		$html =  BigTree::trimLength($html, $count);
		return $html;
	}
	
	function getFirstP($html) {
		$start = strpos($html, '<p>');
		$end = strpos($html, '</p>', $start);
		$html = substr($html, $start, ($end - $start + 4));
		return $html;
	}
	
	function getFirstSection($html) {
		$start = strpos($html, '<p>');
		$end = strpos($html, '<table', $start);
		$html = substr($html, $start, ($end - $start));
		return $html;
	}
	
	/* GEOCODING */
	function geocodeAddress($location) {
		global $server_root;
		
		$location = urlencode(trim(strip_tags($location)));
		$cache_file = $server_root . "cache/custom/google-maps-" . md5($location);
		$cache_age = file_exists($cache_file) ? filemtime($cache_file) : 0;
		
		if ($cache_age === false || $cache_age < (time() - (60 * 60 * 24))) {
			$return = array();
			$file = utf8_encode(file_get_contents("http://maps.google.com/maps/geo?q=$location&output=xml"));
			$xml = new SimpleXMLElement($file);
			try {
				$coords = explode(",", $xml->Response->Placemark->Point->coordinates);
				$return["latitude"] = $coords[1];
				$return["longitude"] = $coords[0];
			} catch (Exception $e) {
			}
			
			file_put_contents($cache_file, json_encode($return));
			chmod($cache_file, 0777);
		} else {
			$return = json_decode(file_get_contents($cache_file), true);
		}
		
		$no_process = true;
		return $return;
	}
?>