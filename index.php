<?php
	/**
	 * Public install entry point (site root index.php).
	 *
	 * The installer lives under core/setup/ with the other setup assets
	 * (SQL seeds, environment templates, install.css). On a successful install
	 * this file is either replaced (Basic Routing → redirect into /site/) or
	 * deleted (rewrite / IIS routing → .htaccess or site docroot handles traffic).
	 */
	require __DIR__ . "/core/setup/install.php";
