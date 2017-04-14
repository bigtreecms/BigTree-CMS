<?php
	namespace BigTree;
	
	CSRF::verify();
	Lock::refresh($_POST["table"],$_POST["id"]);
	