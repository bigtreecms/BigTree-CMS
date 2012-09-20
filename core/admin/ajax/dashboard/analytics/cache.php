<?
	include "_setup.php";
	
	function _local_parseGA($node) {
		$views = 0;
		$visits = 0;
		$bounces = 0;
		$time_on_site = 0;
		
		if (count($node->rows)) {
			foreach ($node->rows as $entry) {
				$title = $entry[0];
				$views += $entry[1];
				$visits += $entry[2];
				$bounces += $entry[3];
				$time_on_site += $entry[4];
			}
			
			@$time_on_site = round($time_on_site / $visits,2);
			$hours = floor($time_on_site / 3600);
			$minutes = floor(($time_on_site - (3600 * $hours)) / 60);
			$seconds = floor($time_on_site - (3600 * $hours) - (60 * $minutes));
			
			$hpart = str_pad($hours,2,"0",STR_PAD_LEFT).":";
			$mpart = str_pad($minutes,2,"0",STR_PAD_LEFT).":";
			$time = $hpart.$mpart.str_pad($seconds,2,"0",STR_PAD_LEFT);
		} else {
			$time = "00:00:00";
		}
		
		return array("views" => $views,"visits" => $visits,"bounces" => $bounces,"average_time" => $time,"average_time_seconds" => $time_on_site);
	}
	
	$cache = array();
	
	// First we're going to update the monthly view counts for all pages.
	$response = $analytics->data_ga->get("ga:".$settings["profile"],date('Y-m-d',strtotime('1 month ago')),date("Y-m-d"),"ga:pageviews",array("dimensions" => "ga:pagePath", "sort" => "ga:pagePath", "max-results" => 100000));
	$used_paths = array();
	foreach ($response->rows as $item) {
		$clean_path = sqlescape(trim($item[0],"/"));
		$views = sqlescape($item[1]);
		
		// Sometimes Google has slightly different routes like "cheese" and "cheese/" so we need to add these page views together.
		if (in_array($clean_path,$used_paths)) {
			sqlquery("UPDATE bigtree_pages SET ga_page_views = (ga_page_views + $views) WHERE `path` = '$clean_path'");
		} else {
			sqlquery("UPDATE bigtree_pages SET ga_page_views = $views WHERE `path` = '$clean_path'");
			$used_paths[] = $clean_path;
		}
	}
	
	// Service Provider report
	$response = $analytics->data_ga->get("ga:".$settings["profile"],date('Y-m-d',strtotime('1 month ago')),date("Y-m-d"),"ga:pageviews,ga:visits",array("dimensions" => "ga:networkLocation", "sort" => "-ga:pageviews", "max-results" => 100000));
	
	foreach ($response->rows as $item) {
		$cache["service_providers"][] = array("name" => $item[0], "views" => $item[1], "visits" => $item[2]);
	}
	
	// Referrer report
	$response = $analytics->data_ga->get("ga:".$settings["profile"],date('Y-m-d',strtotime('1 month ago')),date("Y-m-d"),"ga:pageviews,ga:visits",array("dimensions" => "ga:source", "sort" => "-ga:pageviews", "max-results" => 100000));
	
	foreach ($response->rows as $item) {
		$cache["referrers"][] = array("name" => $item[0], "views" => $item[1], "visits" => $item[2]);
	}
	
	// Keyword report
	$response = $analytics->data_ga->get("ga:".$settings["profile"],date('Y-m-d',strtotime('1 month ago')),date("Y-m-d"),"ga:pageviews,ga:visits",array("dimensions" => "ga:keyword", "sort" => "-ga:pageviews", "max-results" => 100000));
	
	foreach ($response->rows as $item) {
		$cache["keywords"][] = array("name" => $item[0], "views" => $item[1], "visits" => $item[2]);
	}
	
	// Yearly Report
	$cache["year"] = _local_parseGA($analytics->data_ga->get("ga:".$settings["profile"],date('Y-01-01'),date("Y-m-d"),"ga:pageviews,ga:visits,ga:bounces,ga:timeOnSite",array("dimensions" => "ga:browser", "max-results" => 100000)));
	$cache["year_ago_year"] = _local_parseGA($analytics->data_ga->get("ga:".$settings["profile"],date('Y-01-01',strtotime("-1 year")),date("Y-m-d",strtotime("-1 year")),"ga:pageviews,ga:visits,ga:bounces,ga:timeOnSite",array("dimensions" => "ga:browser", "max-results" => 100000)));
	
	// Quarterly Report
	$current_quarter_month = date("m") - date("m") % 3;
	$cache["quarter"] = _local_parseGA($analytics->data_ga->get("ga:".$settings["profile"],date("Y-".str_pad($current_quarter_month,2,"0",STR_PAD_LEFT)."-01"),date("Y-m-d"),"ga:pageviews,ga:visits,ga:bounces,ga:timeOnSite",array("dimensions" => "ga:browser", "max-results" => 100000)));
	$cache["year_ago_quarter"] = _local_parseGA($analytics->data_ga->get("ga:".$settings["profile"],date("Y-".str_pad($current_quarter_month,2,"0",STR_PAD_LEFT)."-01",strtotime("-1 year")),date("Y-m-d",strtotime("-1 year")),"ga:pageviews,ga:visits,ga:bounces,ga:timeOnSite",array("dimensions" => "ga:browser", "max-results" => 100000)));
				
	// Monthly Report
	$cache["month"] = _local_parseGA($analytics->data_ga->get("ga:".$settings["profile"],date('Y-m-01'),date("Y-m-d"),"ga:pageviews,ga:visits,ga:bounces,ga:timeOnSite",array("dimensions" => "ga:browser", "max-results" => 100000)));
	$cache["year_ago_month"] = _local_parseGA($analytics->data_ga->get("ga:".$settings["profile"],date('Y-m-01',strtotime("-1 year")),date("Y-m-d",strtotime("-1 year")),"ga:pageviews,ga:visits,ga:bounces,ga:timeOnSite",array("dimensions" => "ga:browser", "max-results" => 100000)));
	
	// Two Week Heads Up
	$response = $analytics->data_ga->get("ga:".$settings["profile"],date('Y-m-d',strtotime("-2 weeks")),date("Y-m-d",strtotime("-1 day")),"ga:visits",array("dimensions" => "ga:date", "sort" => "ga:date", "max-results" => 100000));
	foreach ($response->rows as $item) {
		$cache["two_week"][$item[0]] = $item[1];
	}
	
	// Let's cache this sucker in bigtree_settings now as an internal setting.
	if (!$admin) {
	    $admin = new BigTreeAdmin;
	}
	if (!$admin->settingExists("bigtree-internal-google-analytics-cache")) {
	    $admin->createSetting(array(
	    	"id" => "bigtree-internal-google-analytics-cache",
	    	"name" => "BigTree's Internal Google Analytics Data Cache",
	    	"system" => "on"
	    ));
	}
	$admin->updateSettingValue("bigtree-internal-google-analytics-cache",$cache);
?>