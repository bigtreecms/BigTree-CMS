<?php
	namespace BigTree\Api\Exceptions;

	class AuthenticationException extends ApiException {
		public $status = 401;
		public $code_string = "authentication_required";
	}
