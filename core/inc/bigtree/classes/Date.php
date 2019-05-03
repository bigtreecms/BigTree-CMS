<?php
	/*
		Class: BigTree\Date
			Provides an interface for manipulating dates.
	*/
	
	namespace BigTree;
	
	use DateTime;
	use DateInterval;
	
	class Date
	{
		
		/*
			Function: convertTojQuery
				Converts a PHP date() format to jQuery date picker format.

			Parameters:
				format - PHP date() formatting string

			Returns:
				jQuery date picker formatting string.
		*/
		
		public static function convertTojQuery(string $format): string
		{
			$new_format = "";
			
			for ($i = 0; $i < strlen($format); $i++) {
				$c = substr($format, $i, 1);
				
				// Day with leading zeroes
				if ($c == "d") {
					$new_format .= "dd";
				// Day without leading zeroes
				} elseif ($c == "j") {
					$new_format .= "d";
				// Full day name (i.e. Sunday)
				} elseif ($c == "l") {
					$new_format .= "DD";
				// Numeric day of the year (0-365)
				} elseif ($c == "z") {
					$new_format .= "o";
				// Full month name (i.e. January)
				} elseif ($c == "F") {
					$new_format .= "MM";
				// Month with leading zeroes
				} elseif ($c == "m") {
					$new_format .= "mm";
				// Month without leading zeroes
				} elseif ($c == "n") {
					$new_format .= "m";
				// 4 digit year
				} elseif ($c == "Y") {
					$new_format .= "yy";
				// Many others are the same or not a date format part
				} else {
					$new_format .= $c;
				}
			}
			
			return $new_format;
		}
		
		/*
			Function: format
				Formats a date that originates in the config defined date format into another.

			Parameters:
				date - Date (in any format that strtotime understands or a unix timestamp)
				format - Format (in any format that PHP's date function understands, defaults to Y-m-d H:i:s)

			Returns:
				A date string or false if date parsing failed
		*/
		
		public static function format($date, string $format = "Y-m-d H:i:s"): string
		{
			$date_object = DateTime::createFromFormat(Router::$Config["date_format"], $date);
			
			// Fallback to SQL standards for handling pre 4.2 values
			if (!$date_object) {
				$date_object = DateTime::createFromFormat("Y-m-d", $date);
			}
			
			if ($date_object) {
				return $date_object->format($format);
			}
			
			return false;
		}
		
		/*
			Function: fromOffset
				Returns a formatted date from a date and an offset.
				e.g. "January 1, 2015" and "2 months" returns "2015-03-01 00:00:00"

			Parameters:
				start_date - Date to start at (in any format that strtotime understands or a unix timestamp)
				offset - Offset (in any "relative" PHP time format)
				format - Format for returned date (in any format that PHP's date function understands, defaults to Y-m-d H:i:s)

			Returns:
				A date string

			See Also:
				http://php.net/manual/en/datetime.formats.php (for strtotime formats)
				http://php.net/manual/en/datetime.formats.relative.php (for relative time formats)
				http://php.net/manual/en/function.date.php (for date formats)
		*/
		
		public static function fromOffset($start_date, string $offset, string $format = "Y-m-d H:i:s"): string
		{
			$time = is_numeric($start_date) ? $start_date : strtotime($start_date);
			
			$date = DateTime::createFromFormat("Y-m-d H:i:s", date("Y-m-d H:i:s", $time));
			$date->add(DateInterval::createFromDateString($offset));
			
			return $date->format($format);
		}
		
		/*
			Function: relativeTime
				Turns a timestamp into "… hours ago" formatting.

			Parameters:
				time - A date/time stamp understandable by strtotime

			Returns:
				A string describing how long ago the passed time was.
		*/
		
		public static function relativeTime(string $time): string
		{
			$minute = 60;
			$hour = 3600;
			$day = 86400;
			$month = 2592000;
			$delta = strtotime(date('r')) - strtotime($time);
			
			if ($delta < 2 * $minute) {
				return "1 min ago";
			} elseif ($delta < 45 * $minute) {
				$minutes = floor($delta / $minute);
				
				return $minutes == 1 ? "1 minute ago" : "$minutes minutes ago";
			} elseif ($delta < 24 * $hour) {
				$hours = floor($delta / $hour);
				
				return $hours == 1 ? "1 hour ago" : "$hours hours ago";
			} elseif ($delta < 30 * $day) {
				$days = floor($delta / $day);
				
				return $days == 1 ? "yesterday" : "$days days ago";
			} elseif ($delta < 12 * $month) {
				$months = floor($delta / $day / 30);
				
				return $months == 1 ? "1 month ago" : "$months months ago";
			} else {
				$years = floor($delta / $day / 365);
				
				return $years == 1 ? "1 year ago" : "$years years ago";
			}
		}
		
	}
