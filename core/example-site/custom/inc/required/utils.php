<?
	function targetBlank($url) {
		return BigTree::isExternalLink($url) ? ' target="_blank"' : "";
	}
?>