<?php
	namespace BigTree;
	
	$gateway->Service = "payflow";
	$gateway->Settings["payflow-vendor"] = $_POST["payflow-vendor"];
	$gateway->Settings["payflow-partner"] = $_POST["payflow-partner"];
	$gateway->Settings["payflow-username"] = $_POST["payflow-username"];
	$gateway->Settings["payflow-password"] = $_POST["payflow-password"];
	$gateway->Settings["payflow-environment"] = $_POST["payflow-environment"];
	$gateway->saveSettings();
	
	$admin->growl("Developer","Updated Payment Gateway");
	Router::redirect(DEVELOPER_ROOT);
	