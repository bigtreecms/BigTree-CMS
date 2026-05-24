<?php
	namespace BigTree\Api\Exceptions;

	class ConflictException extends ApiException {
		public $status = 409;
		public $code_string = "conflict";
	}
