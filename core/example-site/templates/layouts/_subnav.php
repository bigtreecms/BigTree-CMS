<?
	/* BASIC NAV RECURSION */
	function recurseNav($nav, $isSitemap = false) {
		$current_page = BigTree::currentURL();
		$i = 0;
		$count = count($nav);
		if ($count > 0) {
			echo '<ul>';
			foreach ($nav as $navitem) {
				$link = $navitem["link"];
				$target = (isset($navitem['new_window']) && $navitem['new_window'] == 'Yes') ? ' target="_blank"' : '';
				$hasSubnav = strpos($current_page,$link) !== false && $navitem["children"];
				
				$class = ' class="';
				if (strpos($current_page, $link) !== false) {
					$class .= 'sub_current';
				}
				if ($current_page == $link) {
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
				if (strpos($current_page, $link) !== false) {
					$li_class .= ' sub_current';
				}
				if ($current_page == $link) {
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

				if ((strpos($current_page, $link) !== false && $navitem["children"]) || $isSitemap === true) {
					recurseNav($navitem["children"], $isSitemap);
				}

				echo '</li>';
				$i++;
			}
			echo '</ul>';
		}
	}

	recurseNav($nav);
?>