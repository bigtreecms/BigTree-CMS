<?php
	// Backwards compatibility class.
	class BigTreeGeocoding {

		function geocode($address,$ignore_cache = false) {
			$geocode = new BigTree\Geocode($address, $ignore_cache);

			if ($geocode->Latitude) {
				return $geocode->Array;
			} else {
				return false;
			}
		}

	}
	