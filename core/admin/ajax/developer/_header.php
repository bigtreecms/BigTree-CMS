<?php
	/**
	 * @global BigTreeAdmin $admin
	 */

	Auth::user()->requireLevel(2);