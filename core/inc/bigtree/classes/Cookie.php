<?php
	/*
		Class: BigTree\Cookie
			Provides an interface for handling cookies.
	*/
	
	namespace BigTree;
	
	class Cookie {
		
		/*
			Function: create
				Creates a site-wide cookie with support for arrays.
				Cookies set by Cookie::set should be retrieved via Cookie::get (all values are JSON encoded).

			Parameters:
				id - The cookie identifier
				value - The value to set for the cookie
				expiration - Cookie expiration time (in seconds since UNIX epoch) or a string value compatible with strtotime (defaults to session expiration)
		*/
		
		static function create(string $id, $value, int $expiration = 0): void {
			$expiration = is_string($expiration) ? strtotime($expiration) : $expiration;
			$value = json_encode($value);
			
			// Make it available immediately
			$_COOKIE[$id] = $value;
			
			// Set in the browser with correct path and expiration
			setcookie($id, $value, $expiration, str_replace(DOMAIN, "", WWW_ROOT));
		}
		
		/*
			Function: delete
				Deletes a site-wide cookie set by Cookie::create.

			Parameters:
				id - The cookie identifier
		*/
		
		static function delete(string $id): void {
			// Remove from the browser
			setcookie($id, "", strtotime("-1 week"), str_replace(DOMAIN, "", WWW_ROOT));
			
			// Remove from runtime
			unset($_COOKIE[$id]);
		}
		
		/*
			Function: get
				Gets a cookie created by Cookie::create and decodes it.

			Parameters:
				id - The id of the cookie (can contain [] to reference sub-cookies, i.e. name[first])

			Returns:
				The decoded cookie or false if the cookie was not found.
		*/
		
		static function get(string $id) {
			// Allow for sub-cookies
			if (strpos($id, "[") !== false) {
				$pieces = explode("[", $id);
				$cookie = $_COOKIE;
				
				foreach ($pieces as $piece) {
					$piece = str_replace("]", "", $piece);
					if (isset($cookie[$piece])) {
						$cookie = $cookie[$piece];
					} else {
						return null;
					}
				}
				
				return json_decode($cookie, true);
			} else {
				if (isset($_COOKIE[$id])) {
					return json_decode($_COOKIE[$id], true);
				} else {
					return null;
				}
			}
		}
		
	}
