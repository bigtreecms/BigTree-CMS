<?php
	namespace BigTree;
	
	$site = new Page(0, null, false);
	$security_policy = Setting::value("bigtree-internal-security-policy");
	$policy = array_filter((array) $security_policy["password"]) ? $security_policy["password"] : false;
	$policy_text = null;
	
	if (!empty($policy["length"]) ||
		!empty($policy["mixedcase"]) ||
		!empty($policy["numbers"]) ||
		!empty($policy["nonalphanumeric"])
	)  {
		$policy_text = "<ul>";
		
		if ($policy["length"]) {
			$policy_text .= "<li>".Text::translate("Minimum length &mdash; :length: characters", false, [":length:" => $policy["length"]])."</li>";
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
	} else {
		$policy_text = "";
	}
?>
<login-form site_title="<?=$site->NavigationTitle?>"
			default_state="reset_password"
			reset_hash="<?=Text::htmlEncode(Router::$Commands[0])?>"
			remember_disabled="<?=(!empty($security_policy["remember_disabled"]))?>"
			password_policy="<?=Text::htmlEncode($policy_text)?>"></login-form>