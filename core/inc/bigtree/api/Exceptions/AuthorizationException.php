<?php
	namespace BigTree\Api\Exceptions;

	class AuthorizationException extends ApiException {
		public $status = 403;
		public $code_string = "permission_denied";
	}
