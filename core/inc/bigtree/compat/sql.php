<?php
	// Backwards compatibility
	function sqlquery($query) {
		return BigTree\SQL::query($query);
	}

	function sqlfetch(BigTree\SQL $query) {
		return $query->fetch();
	}

	function sqlrows(BigTree\SQL $result) {
		return $result->rows();
	}

	function sqlid() {
		return BigTree\SQL::insertID();
	}

	function sqlescape($string) {
		return BigTree\SQL::escape($string);
	}
	
	class SQL extends BigTree\SQL {}
	