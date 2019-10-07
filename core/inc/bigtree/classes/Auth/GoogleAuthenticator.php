<?php
	
	namespace BigTree\Auth;
	
	// Based on PHPGangsta/GoogleAuthenticator - Copyright (c) 2012, Michael Kliewe All rights reserved.
	class GoogleAuthenticator
	{
		
		private static $ValidChars = [
			'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', //  7
			'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', // 15
			'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', // 23
			'Y', 'Z', '2', '3', '4', '5', '6', '7', // 31
			'=',  // padding char
		];
		
		public static function generateSecret(): string
		{
			$secret = '';
			$rnd = false;
			
			if (function_exists('random_bytes')) {
				$rnd = random_bytes(64);
			} elseif (function_exists('mcrypt_create_iv')) {
				$rnd = mcrypt_create_iv(64, MCRYPT_DEV_URANDOM);
			} elseif (function_exists('openssl_random_pseudo_bytes')) {
				$rnd = openssl_random_pseudo_bytes(64, $cryptoStrong);
				
				if (!$cryptoStrong) {
					$rnd = false;
				}
			}
			
			if ($rnd !== false) {
				for ($i = 0; $i < 64; ++$i) {
					$secret .= static::$ValidChars[ord($rnd[$i]) & 31];
				}
			}
			
			return $secret;
		}
		
		public static function getCode(string $secret, int $time_slice = null): string
		{
			if (is_null($time_slice)) {
				$time_slice = floor(time() / 30);
			}
			
			$secret = static::base32Decode($secret);
			
			// Pack time into binary string
			$time = chr(0).chr(0).chr(0).chr(0).pack('N*', $time_slice);
			// Hash it with users secret key
			$hm = hash_hmac('SHA1', $time, $secret, true);
			// Use last nipple of result as index/offset
			$offset = ord(substr($hm, -1)) & 0x0F;
			// grab 4 bytes of the result
			$hashpart = substr($hm, $offset, 4);
			
			// Unpak binary value
			$value = unpack('N', $hashpart);
			$value = $value[1];
			// Only 32 bits
			$value = $value & 0x7FFFFFFF;
			
			$modulo = pow(10, 6);
			
			return str_pad($value % $modulo, 6, '0', STR_PAD_LEFT);
		}
		
		public static function getQRCode(string $name, string $secret): string
		{
			$chl = rawurlencode('otpauth://totp/'.str_replace(" ", "%20", $name).'?secret='.$secret.'&issuer=BigTree');
			
			return 'https://chart.googleapis.com/chart?chs=200x200&chld=M|0&cht=qr&chl='.$chl;
		}
		
		public static function verifyCode(string $secret, $code, int $discrepancy = 1): bool
		{
			if (is_int($code)) {
				$code = (string) $code;
			}
			
			$currentTimeSlice = floor(time() / 30);
			
			if (strlen($code) != 6) {
				return false;
			}
			
			for ($i = -$discrepancy; $i <= $discrepancy; ++$i) {
				$calculatedCode = static::getCode($secret, $currentTimeSlice + $i);
				
				if (hash_equals($calculatedCode, $code)) {
					return true;
				}
			}
			
			return false;
		}
		
		protected static function base32Decode(string $secret): string
		{
			if (empty($secret)) {
				return '';
			}
			
			$base32chars = static::$ValidChars;
			$base32charsFlipped = array_flip($base32chars);
			
			$paddingCharCount = substr_count($secret, $base32chars[32]);
			$allowedValues = [6, 4, 3, 1, 0];
			
			if (!in_array($paddingCharCount, $allowedValues)) {
				return '';
			}
			
			for ($i = 0; $i < 4; ++$i) {
				if ($paddingCharCount == $allowedValues[$i] &&
					substr($secret, -($allowedValues[$i])) != str_repeat($base32chars[32], $allowedValues[$i])) {
					
					return '';
				}
			}
			
			$secret = str_replace('=', '', $secret);
			$secret = str_split($secret);
			$binaryString = '';
			
			for ($i = 0; $i < count($secret); $i = $i + 8) {
				$x = '';
				
				if (!in_array($secret[$i], $base32chars)) {
					return '';
				}
				
				for ($j = 0; $j < 8; ++$j) {
					$x .= str_pad(base_convert(@$base32charsFlipped[@$secret[$i + $j]], 10, 2), 5, '0', STR_PAD_LEFT);
				}
				
				$eightBits = str_split($x, 8);
				
				for ($z = 0; $z < count($eightBits); ++$z) {
					$binaryString .= (($y = chr(base_convert($eightBits[$z], 2, 10))) || ord($y) == 48) ? $y : '';
				}
			}
			
			return $binaryString;
		}
		
	}
