<?
	if (is_numeric(end($bigtree["path"]))) {
		$bigtree["nav_tree"]["dashboard"]["children"]["messages"]["children"][] = array("title" => "Reply","link" => "dashboard/messages/reply/".end($bigtree["path"]),"nav_icon" => "reply","icon" => "reply_message");
		$bigtree["nav_tree"]["dashboard"]["children"]["messages"]["children"][] = array("title" => "Reply All","link" => "dashboard/messages/reply-all/".end($bigtree["path"]),"nav_icon" => "reply_all","icon" => "reply_message");
	}
?>