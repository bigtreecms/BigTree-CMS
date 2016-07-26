<?php
	namespace BigTree;
	
	Auth::user()->requireLevel(1);
	