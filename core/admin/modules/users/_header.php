<?php
	namespace BigTree;

	if (end($bigtree["path"]) != "password" && $bigtree["path"][2] != "profile") {
		$admin->Auth->requireLevel(1);
	}

	$policy = array_filter((array)$bigtree["security-policy"]["password"]) ? $bigtree["security-policy"]["password"] : false;

	if ($policy) {
		$policy_text = "<p>".Text::translate("Requirements")."</p><ul>";
		if ($policy["length"]) {
			$policy_text .= "<li>".Text::translate("Minimum length &mdash; :length: characters", false, array(":length:" => $policy["length"]))."</li>";
		}
		if ($policy["mixedcase"]) {
			$policy_text .= "<li>".Text::translate("Both upper and lowercase letters")."</li>";
		}
		if ($policy["numbers"]) {
			$policy_text .= "<li>".Text::translate("At least one number")."</li>";
		}
		if ($policy["nonalphanumeric"]) {
			$policy_text .= "<li>".Text::translate("At least one special character (i.e. $%*)")."</li>";
		}
		$policy_text .= "</ul>";
	}
	