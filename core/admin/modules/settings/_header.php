<?php
	namespace BigTree;
	
	Router::setLayout("new");
	Auth::user()->requireLevel(1);
