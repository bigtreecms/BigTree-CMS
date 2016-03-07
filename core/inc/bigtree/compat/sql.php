<?php
	// Backwards compatibility
	function sqlquery($query) {
		return BigTreeCMS::$DB->query($query);
	}

	function sqlfetch($query) {
		return $query->fetch();
	}

	function sqlrows($result) {
		return $result->rows();
	}

	function sqlid() {
		return BigTreeCMS::$DB->insertID();
	}

	function sqlescape($string) {
		return BigTreeCMS::$DB->escape($string);
	}