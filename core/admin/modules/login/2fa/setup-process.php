<?php
	namespace BigTree;
	
	Auth::assign2FASecret($_POST["secret"]);
	