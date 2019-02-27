<?php
	namespace BigTree;
	
	Auth::logoutAllUsers();
	Router::redirect(ADMIN_ROOT);
	