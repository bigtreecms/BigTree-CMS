<?php
	/*
		Class: BigTree\ErrorHandler
			Provides an interface for logging and retrieving errors.
	*/
	
	namespace BigTree;
	
	class ErrorHandler
	{
	
		public static $Errors = [];
		
		public static function logError(string $field_title, string $error_message): void
		{
			static::$Errors[] = ["field" => $field_title, "error" => $error_message];
		}
	
	}