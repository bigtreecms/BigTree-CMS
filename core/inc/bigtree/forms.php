<?
	/*
		Class: BigTreeForms
			A database/form data validator/sanitizer.
	*/
	
	class BigTreeForms {
		var $Table = "";
		
		/*
			Constructor:
				Initiates the class with a provided table.
			
			Parameters:
				table - The table to sanitize/validate data for.
		*/
		
		function __construct($table) {
			$this->Table = $table;
			
			$fields = sqlcolumns($this->Table);
			$this->Columns = $fields;

			foreach ($fields as $key => $field) {
				$this->Fields[$key] = $field["type"];
			}
		}
		
		/*
			Function: errorMessage
				Returns an error message for a form element that failed validation.
			
			Parameters:
				data - The form's posted data for a given field.
				type - Validation requirements (required, numeric, email, link).
		
			Returns:
				A string containing reasons the validation failed.
				
			See Also:
				<validate>
		*/
		
		public function errorMessage($data,$type) {
			$parts = explode(" ",$type);
			// Not required and it's blank
			$message = "This field ";
			$mparts = array();
			
			if (!$data && in_array("required",$parts)) {
				$mparts[] = "is required";
			}
			
			// Requires numeric and it isn't
			if (in_array("numeric",$parts) && !is_numeric($data)) {
				$mparts[] = "must be numeric";
			// Requires email and it isn't
			} elseif (in_array("email",$parts) && !filter_var($data,FILTER_VALIDATE_EMAIL)) {
				$mparts[] = "must be an email address";
			// Requires url and it isn't
			} elseif (in_array("link",$parts) && !filter_var($data,FILTER_VALIDATE_URL)) {
				$mparts[] = "must be a link";
			}
			
			$message .= implode(" and ",$mparts).".";
			
			return $message;
		}
		
		/*
			Function: sanitizeFormDataForDB
				Processes form data into values understandable by the MySQL table.
			
			Parameters:
				data - Array of key->value pairs
			
			Returns:
				Array of data safe for MySQL.
		*/	
		
		function sanitizeFormDataForDB($data) {
			foreach ($data as $key => $val) {
				$type = $this->Fields[$key];
				if ($type == "tinyint" || $type == "smallint" || $type == "mediumint" || $type == "int" || $type == "bigint") {
					$data[$key] = $this->sanitizeInteger($val);
				}
				if ($type == "float" || $type == "double" || $type == "decimal") {
					$data[$key] = $this->sanitizeFloat($val);
				}
				if ($type == "datetime" || $type == "timestamp") {
					$data[$key] = $this->sanitizeDateTime($val,$this->Columns[$key]["null"]);
				}
				if ($type == "date" || $type == "year") {
					$data[$key] = $this->sanitizeDate($val,$this->Columns[$key]["null"]);
				}
				if ($type == "time") {
					$data[$key] = $this->sanitizeTime($val,$this->Columns[$key]["null"]);
				}
			}
			return $data;
		}
		
		/*
			Function: sanitizeDate
				Private function for sanitizing DATE fields.
			
			See Also:
				<sanitizeFormDataForDB>
		*/
				
		private function sanitizeDate($val,$allow_null) {
			if (substr($val,0,3) == "NOW") {
				return "NOW()";
			}
			if (!$val && $allow_null == "YES") {
				return "NULL";
			}
			if (!$val) {
				return "0000-00-00";
			}
			return date("Y-m-d",strtotime($val));
		}
		
		/*
			Function: sanitizeDateTime
				Private function for sanitizing DATETIME fields.
			
			See Also:
				<sanitizeFormDataForDB>
		*/
		
		private function sanitizeDateTime($val,$allow_null) {
			if (substr($val,0,3) == "NOW") {
				return "NOW()";
			}
			if (!$val && $allow_null == "YES") {
				return "NULL";
			}
			if ($val == "") {
				return "0000-00-00 00:00:00";
			}
			return date("Y-m-d H:i:s",strtotime($val));
		}
		
		/*
			Function: sanitizeFloat
				Private function for sanitizing FLOAT/DOUBLE/DECIMAL fields.
			
			See Also:
				<sanitizeFormDataForDB>
		*/
		
		private function sanitizeFloat($val) {
			return floatval(str_replace(array(",","$"),"",$val));
		}
		
		/*
			Function: sanitizeInteger
				Private function for sanitizing INT fields.
			
			See Also:
				<sanitizeFormDataForDB>
		*/
		
		private function sanitizeInteger($val) {
			return intval(str_replace(array(",","$"),"",$val));
		}
		
		/*
			Function: sanitizeTime
				Private function for sanitizing TIME fields.
			
			See Also:
				<sanitizeFormDataForDB>
		*/
		
		private function sanitizeTime($val,$allow_null) {
			if (substr($val,0,3) == "NOW") {
				return "NOW()";
			}
			if (!$val && $allow_null == "YES") {
				return "NULL";
			}
			if (!$val) {
				return "00:00:00";
			}
			return date("H:i:s",strtotime($val));
		}
		
		/*
			Function: validate
				Validates a form element based on its validation requirements.
			
			Parameters:
				data - The form's posted data for a given field.
				type - Validation requirements (required, numeric, email, link).
		
			Returns:
				True if validation passed, otherwise false.
			
			See Also:
				<errorMessage>
		*/
		
		public function validate($data,$type) {
			$parts = explode(" ",$type);
			// Not required and it's blank
			if (!in_array("required",$parts) && !$data) {
				return true;
			} else {
				// Requires numeric and it isn't
				if (in_array("numeric",$parts) && !is_numeric($data)) {
					return false;
				// Requires email and it isn't
				} elseif (in_array("email",$parts) && !filter_var($data,FILTER_VALIDATE_EMAIL)) {
					return false;
				// Requires url and it isn't
				} elseif (in_array("link",$parts) && !filter_var($data,FILTER_VALIDATE_URL)) {
					return false;
				} elseif (in_array("required",$parts) && !$data) {
					return false;
				// It exists and validates as numeric, an email, or URL
				} else {
					return true;
				}
			}
		}
	}
?>