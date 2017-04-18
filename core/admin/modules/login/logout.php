<?php
	namespace BigTree;
	
	CSRF::verify();
	Auth::logout();
	Router::redirect(ADMIN_ROOT);
	