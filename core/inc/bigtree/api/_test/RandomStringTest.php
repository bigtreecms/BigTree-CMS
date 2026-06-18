<?php
	function test_random_string_length() {
		T::equals(strlen(BigTree::randomString(64)), 64, "length 64");
		T::equals(strlen(BigTree::randomString(32)), 32, "length 32");
		T::equals(strlen(BigTree::randomString()), 8, "default length 8");
	}

	function test_random_string_charset() {
		$s = BigTree::randomString(200, 'hexidec');
		T::ok(strspn($s, '0123456789abcdef') === strlen($s), "hexidec charset");

		$alphanum = BigTree::randomString(200, 'alphanum');
		T::ok(strspn($alphanum, 'ABCDEFGHJKLMNPQRTUVWXY0123456789') === strlen($alphanum), "alphanum charset");
	}

	function test_random_string_uniqueness() {
		$all = [];

		for ($i = 0; $i < 1000; $i++) {
			$all[] = BigTree::randomString(32);
		}

		T::equals(count(array_unique($all)), 1000, "1000 distinct strings");
	}
