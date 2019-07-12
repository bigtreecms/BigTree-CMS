<?php
	/*
		Class: BigTree\DailyDigest
			Provides an interface for handling BigTree daily digest emails.
	*/
	
	namespace BigTree;
	
	class DailyDigest extends SQLObject
	{
		
		public static $CoreOptions = [
			"pending-changes" => [
				"name" => "Pending Changes",
				"function" => "BigTree\\DailyDigest::getChanges"
			],
			"alerts" => [
				"name" => "Content Age Alerts",
				"function" => "BigTree\\DailyDigest::getAlerts"
			]
		];
		public static $Plugins = [];
		
		/*
			Function: getAlerts
				Generates markup for daily digest alerts for a given user.

			Parameters:
				user - A user array

			Returns:
				HTML markup for daily digest email
		*/
		
		public static function getAlerts(array $user): string
		{
			$alerts = Page::getAlertsForUser($user);
			$alerts_markup = "";
			$wrapper = '<div style="margin: 20px 0 30px;">
							<h3 style="color: #333; font-size: 18px; font-weight: normal; margin: 0 0 10px; padding: 0;">Content Age Alerts</h3>
							<table cellspacing="0" cellpadding="0" style="border: 1px solid #eee; border-width: 1px 1px 0; width: 100%;">
								<thead style="background: #ccc; color: #fff; font-size: 10px; text-align: left; text-transform: uppercase;">
									<tr>
										<th style="font-weight: normal; padding: 4px 0 3px 15px;" align="left">Page</th>
										<th style="font-weight: normal; padding: 4px 20px 3px 15px; text-align: right; width: 50px;" align="left">Age</th>
										<th style="font-weight: normal; padding: 4px 0 3px; text-align: center; width: 50px;" align="left">View</th>
										<th style="font-weight: normal; padding: 4px 0 3px; text-align: center; width: 50px;" align="left">Edit</th>
									</tr>
								</thead>
								<tbody style="color: #333; font-size: 13px;">
									{content_alerts}
								</tbody>
							</table>
						</div>';
			
			// Alerts
			if (is_array($alerts) && count($alerts)) {
				foreach ($alerts as $alert) {
					$alerts_markup .= '<tr>
										<td style="border-bottom: 1px solid #eee; padding: 10px 0 10px 15px;">'.$alert["nav_title"].'</td>
										<td style="border-bottom: 1px solid #eee; padding: 10px 20px 10px 15px; text-align: right;">'.$alert["current_age"].' Days</td>
										<td style="border-bottom: 1px solid #eee; padding: 10px 0; text-align: center;"><a href="'.WWW_ROOT.$alert["path"].'/"><img src="'.ADMIN_ROOT.'images/email/launch.gif" alt="Launch" /></a></td>
										<td style="border-bottom: 1px solid #eee; padding: 10px 0; text-align: center;"><a href="'.ADMIN_ROOT."pages/edit/".$alert["id"].'/"><img src="'.ADMIN_ROOT.'images/email/edit.gif" alt="Edit" /></a></td>
									 </tr>';
				}
			}
			
			if ($alerts_markup) {
				return str_replace("{content_alerts}", $alerts_markup, $wrapper);
			}
			
			return "";
		}
		
		/*
			Function: getChanges
				Generates markup for daily digest pending changes for a given user.

			Parameters:
				user - A user array

			Returns:
				HTML markup for daily digest email
		*/
		
		public static function getChanges(array $user): string
		{
			$user = new User($user);
			$changes = PendingChange::allPublishableByUser($user);
			
			$changes_markup = "";
			$wrapper = '<div style="margin: 20px 0 30px;">
							<h3 style="color: #333; font-size: 18px; font-weight: normal; margin: 0 0 10px; padding: 0;">Pending Changes</h3>
							<table cellspacing="0" cellpadding="0" style="border: 1px solid #eee; border-width: 1px 1px 0; width: 100%;">
								<thead style="background: #ccc; color: #fff; font-size: 10px; text-align: left; text-transform: uppercase;">
									<tr>
										<th style="font-weight: normal; padding: 4px 0 3px 15px; width: 150px;" align="left">Author</th>
										<th style="font-weight: normal; padding: 4px 0 3px 15px; width: 180px;" align="left">Module</th>
										<th style="font-weight: normal; padding: 4px 0 3px 15px;" align="left">Type</th>
										<th style="font-weight: normal; padding: 4px 0 3px; text-align: center; width: 50px;" align="left">View</th>
									</tr>
								</thead>
								<tbody style="color: #333; font-size: 13px;">
									{pending_changes}
								</tbody>
							</table>
						</div>';
			
			if (is_array($changes) && count($changes)) {
				foreach ($changes as $change) {
					$changes_markup .= '<tr>';
					$changes_markup .= '<td style="border-bottom: 1px solid #eee; padding: 10px 0 10px 15px;">'.$change->User->Name.'</td>';
					
					if ($change->Table == "bigtree_pages") {
						$changes_markup .= '<td style="border-bottom: 1px solid #eee; padding: 10px 0 10px 15px;">Pages</td>';
					} else {
						$changes_markup .= '<td style="border-bottom: 1px solid #eee; padding: 10px 0 10px 15px;">'.$change->Module->Name.'</td>';
					}
					
					if (is_null($change->ItemID)) {
						$changes_markup .= '<td style="border-bottom: 1px solid #eee; padding: 10px 0 10px 15px;">Addition</td>';
					} else {
						$changes_markup .= '<td style="border-bottom: 1px solid #eee; padding: 10px 0 10px 15px;">Edit</td>';
					}
					
					$changes_markup .= '<td style="border-bottom: 1px solid #eee; padding: 10px 0; text-align: center;"><a href="'.$change->EditLink.'"><img src="'.ADMIN_ROOT.'images/email/launch.gif" alt="Launch" /></a></td>'."\r\n";
					$changes_markup .= '</tr>';
				}
				
				return str_replace("{pending_changes}", $changes_markup, $wrapper);
			} else {
				return "";
			}
		}
		
		/*
			Function: send
				Sends out a daily digest email to all who have subscribed.
		*/
		
		public static function send(): void
		{
			global $bigtree;
			
			// We're going to show the site's title in the email
			$site_title = SQL::fetchSingle("SELECT `nav_title` FROM `bigtree_pages` WHERE id = '0'");
			
			// Find out what blocks are on
			$extension_settings = Setting::value("bigtree-internal-extension-settings");
			$digest_settings = $extension_settings["digest"];
			
			// Get a list of blocks we'll draw in emails
			$blocks = [];
			$positions = [];
			
			// Cache extension plugins
			Extension::initializeCache();
			
			// We're going to get the position setups and the multi-sort the list to get it in order
			foreach (static::$CoreOptions as $id => $details) {
				if (empty($digest_settings[$id]["disabled"])) {
					$blocks[] = $details["function"];
					$positions[] = isset($digest_settings[$id]["position"]) ? $digest_settings[$id]["position"] : 0;
				}
			}
			
			foreach (static::$Plugins as $extension => $set) {
				foreach ($set as $id => $details) {
					$id = $extension."*".$id;
					if (empty($digest_settings[$id]["disabled"])) {
						$blocks[] = $details["function"];
						$positions[] = isset($digest_settings[$id]["position"]) ? $digest_settings[$id]["position"] : 0;
					}
				}
			}
			
			array_multisort($positions, SORT_DESC, $blocks);
			
			// Loop through each user who has opted in to emails
			$daily_digest_users = SQL::fetchAll("SELECT * FROM bigtree_users WHERE daily_digest = 'on'");
			
			foreach ($daily_digest_users as $user) {
				$block_markup = "";
				
				foreach ($blocks as $function) {
					$block_markup .= call_user_func($function, $user);;
				}
				
				// Send it
				if (trim($block_markup)) {
					$body = file_get_contents(Router::getIncludePath("admin/email/daily-digest.html"));
					$body = str_ireplace("{www_root}", $bigtree["config"]["www_root"], $body);
					$body = str_ireplace("{admin_root}", $bigtree["config"]["admin_root"], $body);
					$body = str_ireplace("{site_title}", $site_title, $body);
					$body = str_ireplace("{date}", date("F j, Y", time()), $body);
					$body = str_ireplace("{blocks}", $block_markup, $body);
					
					$reply_to = "no-reply@".(isset($_SERVER["HTTP_HOST"]) ? str_replace("www.", "", $_SERVER["HTTP_HOST"]) : str_replace(["http://www.", "https://www.", "http://", "https://"], "", DOMAIN));
					
					$email = new Email;
					
					$email->Subject = "$site_title Daily Digest";
					$email->HTML = $body;
					$email->To = $user["email"];
					$email->From = $email->Settings["bigtree_from"] ?: $reply_to;
					$email->ReplyTo = $reply_to;
					
					$email->send();
				}
			}
		}
		
	}
