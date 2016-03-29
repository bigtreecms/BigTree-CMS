<?php
	// Backwards compatibility
	function sqlquery($query) {
		return SQL::query($query);
	}

	function sqlfetch($query) {
		return $query->fetch();
	}

	function sqlrows($result) {
		return $result->rows();
	}

	function sqlid() {
		return SQL::insertID();
	}

	function sqlescape($string) {
		return SQL::escape($string);
	}