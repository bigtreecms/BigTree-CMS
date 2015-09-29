<?php

	$extension = $admin->getExtension($bigtree['commands'][0]);
	$j = json_decode($extension['manifest'], true);

	$_SESSION['bigtree_admin']['developer']['package'] = array(
		'id' => $j['id'],
		'version' => $j['version'],
		'compatibility' => $j['compatibility'],
		'title' => $j['title'],
		'description' => $j['description'],
		'keywords' => implode(', ', $j['keywords']),
		'author' => $j['author'],
		'files' => array(),
		'modules' => array(),
		'templates' => array(),
		'callouts' => array(),
		'settings' => array(),
		'feeds' => array(),
		'field_types' => array(),
		'tables' => array(),
	);
	foreach ($j['components'] as $k => $v) {
	    if ($k == 'tables') {
	        $tables = array();
	        foreach ($v as $table => $create_statement) {
	            $_SESSION['bigtree_admin']['developer']['package']['tables'][] = "$table#structure";
	        }
	    } else {
	        foreach ($v as $item) {
	            $_SESSION['bigtree_admin']['developer']['package'][$k][] = $item['id'];
	        }
	    }
	}
	foreach ($j['licenses'] as $l => $d) {
	    if (isset($available_licenses['Open Source'][$l])) {
	        $_SESSION['bigtree_admin']['developer']['package']['licenses'][] = $l;
	    } elseif (isset($available_licenses['Closed Source'][$l])) {
	        $_SESSION['bigtree_admin']['developer']['package']['license'] = $l;
	    } else {
	        $_SESSION['bigtree_admin']['developer']['package']['license_name'] = $l;
	        $_SESSION['bigtree_admin']['developer']['package']['license_url'] = $d;
	    }
	}

	BigTree::redirect(DEVELOPER_ROOT.'extensions/build/details/');
?>