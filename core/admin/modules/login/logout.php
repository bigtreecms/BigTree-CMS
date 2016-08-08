<?php
	namespace BigTree;
	
	Auth::logout();
	Router::redirect(ADMIN_ROOT);
	