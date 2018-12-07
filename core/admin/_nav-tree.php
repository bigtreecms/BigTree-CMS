<?php
	// BigTree Admin Nav Tree
	$bigtree["nav_tree"] = [
		"dashboard" => ["title" => "Dashboard", "link" => "dashboard", "icon" => "dashboard", "related" => true, "children" => [
			"overview" => ["title" => "Overview", "link" => "", "hidden" => true],
			"pending-changes" => ["title" => "Pending Changes", "link" => "dashboard/pending-changes", "icon" => "pending", "hidden" => true],
			"messages" => ["title" => "Message Center", "link" => "dashboard/messages", "icon" => "messages", "hidden" => true, "children" => [
				["title" => "View Messages", "link" => "dashboard/messages", "icon" => "messages", "nav_icon" => "list"],
				["title" => "New Message", "link" => "dashboard/messages/new", "icon" => "add_message", "nav_icon" => "add"]
			]],
			"analytics" => ["title" => "Analytics", "link" => "dashboard/vitals-statistics/analytics", "hidden" => true, "icon" => "analytics", "children" => [
				["title" => "Statistics", "link" => "dashboard/vitals-statistics/analytics", "nav_icon" => "bar_graph"],
				["title" => "Service Providers", "link" => "dashboard/vitals-statistics/analytics/service-providers", "nav_icon" => "network"],
				["title" => "Traffic Sources", "link" => "dashboard/vitals-statistics/analytics/traffic-sources", "nav_icon" => "car"],
				["title" => "Keywords", "link" => "dashboard/vitals-statistics/analytics/keywords", "nav_icon" => "key"],
				["title" => "Configure", "link" => "dashboard/vitals-statistics/analytics/configure", "nav_icon" => "setup", "level" => 1],
				["title" => "Caching Data", "link" => "dashboard/vitals-statistics/analytics/cache", "hidden" => true]
			]],
			"404-report" => ["title" => "404 Report", "link" => "dashboard/vitals-statistics/404", "hidden" => true, "level" => 1, "icon" => "page_404", "children" => [
				["title" => "Active 404s", "link" => "dashboard/vitals-statistics/404", "nav_icon" => "error"],
				["title" => "Ignored 404s", "link" => "dashboard/vitals-statistics/404/ignored", "nav_icon" => "ignored"],
				["title" => "Clear 404s", "link" => "dashboard/vitals-statistics/404/clear", "nav_icon" => "delete"],
				["title" => "301 Redirects", "link" => "dashboard/vitals-statistics/404/301", "nav_icon" => "redirect"],
				["title" => "Add 301 Redirect", "link" => "dashboard/vitals-statistics/404/add-301", "nav_icon" => "add"],
				["title" => "Upload 301 Redirect CSV", "link" => "dashboard/vitals-statistics/404/upload-csv", "nav_icon" => "up"],
				["title" => "Import 301 Redirect CSV", "link" => "dashboard/vitals-statistics/404/import-csv", "hidden" => true],
				["title" => "301 Redirect CSV Processed", "link" => "dashboard/vitals-statistics/404/process-csv", "hidden" => true]
			]],
			"integrity" => ["title" => "Site Integrity", "link" => "dashboard/vitals-statistics/integrity", "icon" => "integrity", "hidden" => true, "level" => 1]
		]],
		"pages" => ["title" => "Pages", "link" => "pages", "icon" => "page", "nav_icon" => "pages", "no_top_level_children" => true, "children" => [
			"view-tree" => ["title" => "View Subpages", "link" => "pages/view-tree/{id}", "nav_icon" => "list"],
			"add" => ["title" => "Add Subpage", "link" => "pages/add/{id}", "icon" => "add_page", "nav_icon" => "add"],
			"edit" => ["title" => "Edit Page", "link" => "pages/edit/{id}", "icon" => "edit_page", "nav_icon" => "edit"],
			"revisions" => ["title" => "Revisions", "link" => "pages/revisions/{id}", "icon" => "page_versions", "nav_icon" => "refresh"],
			"move" => ["title" => "Move Page", "link" => "pages/move/{id}", "icon" => "move_page", "nav_icon" => "truck", "level" => 1],
			"duplicate" => ["title" => "Duplicate Page", "link" => "pages/duplicate/{id}", "icon" => "duplicate", "nav_icon" => "duplicate"]
		]],
		"modules" => ["title" => "Modules", "link" => "modules", "icon" => "modules", "no_top_level_children" => true, "children" => []],
		"files" => ["title" => "Files", "link" => "files", "icon" => "files", "nav_icon" => "files", "children" => []],
		"users" => ["title" => "Users", "link" => "users", "icon" => "users", "level" => 1, "no_top_level_children" => true, "children" => [
			["title" => "View Users", "link" => "users", "nav_icon" => "list"],
			["title" => "Add User", "link" => "users/add", "nav_icon" => "add"],
			["title" => "Edit User", "link" => "users/edit", "icon" => "gravatar", "hidden" => true],
			["title" => "Profile", "link" => "users/profile", "icon" => "gravatar", "hidden" => true]
		]],
		"settings" => ["title" => "Settings", "link" => "settings", "icon" => "settings", "no_top_level_children" => true, "children" => [
			["title" => "Edit Setting", "link" => "settings/edit", "hidden" => true]
		]],
		"tags" => ["title" => "Tags", "link" => "tags", "icon" => "tags", "no_top_level_children" => true, "children" => [
			["title" => "View Tags", "link" => "tags", "nav_icon" => "list"],
			["title" => "Add Tag", "link" => "tags/add", "nav_icon" => "add"],
			["title" => "Merge Tag", "link" => "tags/merge", "hidden" => true]
		]],
		"developer" => ["title" => "Developer", "link" => "developer", "icon" => "developer", "nav_icon" => "developer", "level" => 2, "related" => true, "children" => [
			["title" => "Create", "group" => true],
			["title" => "Templates", "link" => "developer/templates", "icon" => "templates", "hidden" => true, "children" => [
				["title" => "View Templates", "link" => "developer/templates", "nav_icon" => "list"],
				["title" => "Add Template", "link" => "developer/templates/add", "nav_icon" => "add"],
				["title" => "Edit Template", "link" => "developer/templates/edit", "hidden" => true]
			]],
			["title" => "Modules", "link" => "developer/modules", "icon" => "modules", "hidden" => true, "children" => [
				["title" => "View Modules", "link" => "developer/modules", "nav_icon" => "list"],
				["title" => "Add Module","link" => "developer/modules/start","nav_icon" => "add"],
				["title" => "Add Module","link" => "developer/modules/add","nav_icon" => "add", "hidden" => true],
				["title" => "Module Designer","link" => "developer/modules/designer","nav_icon" => "edit", "hidden" => true],
				["title" => "View Groups", "link" => "developer/modules/groups", "nav_icon" => "list"],
				["title" => "Add Group", "link" => "developer/modules/groups/add", "nav_icon" => "add"],
				["title" => "Edit Module", "link" => "developer/modules/edit", "hidden" => true],
				["title" => "Edit Group", "link" => "developer/modules/groups/edit", "hidden" => true],
				["title" => "Module Created", "link" => "developer/modules/create", "hidden" => true],
				["title" => "Add View", "link" => "developer/modules/views/add", "hidden" => true],
				["title" => "Edit View", "link" => "developer/modules/views/edit", "hidden" => true],
				["title" => "Style View", "link" => "developer/modules/views/style", "hidden" => true],
				["title" => "Created View", "link" => "developer/modules/views/create", "hidden" => true],
				["title" => "Add Form", "link" => "developer/modules/forms/add", "hidden" => true],
				["title" => "Edit Form", "link" => "developer/modules/forms/edit", "hidden" => true],
				["title" => "Created Form", "link" => "developer/modules/forms/create", "hidden" => true],
				["title" => "Add Action", "link" => "developer/modules/actions/add", "hidden" => true],
				["title" => "Edit Action", "link" => "developer/modules/actions/edit", "hidden" => true],
				["title" => "Add Report", "link" => "developer/modules/reports/add", "hidden" => true],
				["title" => "Edit Report", "link" => "developer/modules/reports/edit", "hidden" => true],
				["title" => "Add Embeddable Form", "link" => "developer/modules/embeds/add", "hidden" => true],
				["title" => "Edit Embeddable Form", "link" => "developer/modules/embeds/edit", "hidden" => true]
			]],
			["title" => "Callouts", "link" => "developer/callouts", "icon" => "callouts", "hidden" => true, "children" => [
				["title" => "View Callouts", "link" => "developer/callouts", "nav_icon" => "list"],
				["title" => "Add Callout", "link" => "developer/callouts/add", "nav_icon" => "add"],
				["title" => "Edit Callout", "link" => "developer/callouts/edit", "hidden" => true],
				["title" => "View Groups", "link" => "developer/callouts/groups", "nav_icon" => "list"],
				["title" => "Add Group", "link" => "developer/callouts/groups/add", "nav_icon" => "add"],
				["title" => "Edit Group", "link" => "developer/callouts/groups/edit", "hidden" => true]
			]],
			["title" => "Field Types", "link" => "developer/field-types", "icon" => "field_types", "hidden" => true, "children" => [
				["title" => "View Field Types", "link" => "developer/field-types", "nav_icon" => "list"],
				["title" => "Add Field Type", "link" => "developer/field-types/add", "nav_icon" => "add"],
				["title" => "Edit Field Type", "link" => "developer/field-types/edit", "hidden" => true],
				["title" => "Field Type Created", "link" => "developer/field-types/new", "hidden" => true]
			]],
			["title" => "Feeds", "link" => "developer/feeds", "icon" => "feeds", "hidden" => true, "children" => [
				["title" => "View Feeds", "link" => "developer/feeds", "nav_icon" => "list"],
				["title" => "Add Feed", "link" => "developer/feeds/add", "nav_icon" => "add"],
				["title" => "Edit Feed", "link" => "developer/feeds/edit", "hidden" => true],
				["title" => "Created Feed", "link" => "developer/feeds/create", "hidden" => true]
			]],
			["title" => "Settings", "link" => "developer/settings", "icon" => "settings", "hidden" => true, "children" => [
				["title" => "View Settings", "link" => "developer/settings", "nav_icon" => "list"],
				["title" => "Add Setting", "link" => "developer/settings/add", "nav_icon" => "add"],
				["title" => "Edit Setting", "link" => "developer/settings/edit", "hidden" => true]
			]],
			["title" => "Extensions", "link" => "developer/extensions", "icon" => "package", "hidden" => true, "children" => [
				["title" => "View Extensions", "link" => "developer/extensions", "nav_icon" => "list"],
				["title" => "Build Extension", "link" => "developer/extensions/build", "nav_icon" => "shovel"],
				["title" => "Install Extension", "link" => "developer/extensions/install", "nav_icon" => "add"],
				["title" => "Refresh Hooks Cache", "link" => "developer/extensions/recache-hooks", "nav_icon" => "lightning"]
			]],
			["title" => "Configure", "group" => true],
			["title" => "Cloud Storage", "link" => "developer/cloud-storage", "icon" => "cloud", "hidden" => true, "children" => [
				["title" => "Local Storage", "link" => "developer/cloud-storage/local", "icon" => "local_storage", "hidden" => true],
				["title" => "Amazon S3", "link" => "developer/cloud-storage/amazon", "icon" => "amazon", "hidden" => true],
				["title" => "Rackspace Cloud Files", "link" => "developer/cloud-storage/rackspace", "icon" => "rackspace", "hidden" => true],
				["title" => "Google Cloud Storage", "link" => "developer/cloud-storage/google", "icon" => "google", "hidden" => true]
			]],
			["title" => "Payment Gateway", "link" => "developer/payment-gateway", "icon" => "payment", "hidden" => true, "children" => [
				["title" => "Authorize.Net", "link" => "developer/payment-gateway/authorize", "icon" => "authorize", "hidden" => true],
				["title" => "PayPal REST API", "link" => "developer/payment-gateway/paypal-rest", "icon" => "paypal", "hidden" => true],
				["title" => "PayPal Payments Pro", "link" => "developer/payment-gateway/paypal", "icon" => "paypal", "hidden" => true],
				["title" => "PayPal Payflow Gateway", "link" => "developer/payment-gateway/payflow", "icon" => "payflow", "hidden" => true],
				["title" => "First Data / LinkPoint", "link" => "developer/payment-gateway/linkpoint", "icon" => "linkpoint", "hidden" => true]
			]],
			["title" => "Geocoding", "link" => "developer/geocoding", "icon" => "geocoding", "hidden" => true, "children" => [
				["title" => "Google", "link" => "developer/geocoding/google", "icon" => "google", "hidden" => true],
				["title" => "Bing", "link" => "developer/geocoding/bing", "icon" => "bing", "hidden" => true],
				["title" => "Yahoo", "link" => "developer/geocoding/yahoo", "icon" => "yahoo", "hidden" => true],
				["title" => "Yahoo BOSS", "link" => "developer/geocoding/yahoo-boss", "icon" => "yahoo", "hidden" => true],
				["title" => "MapQuest", "link" => "developer/geocoding/mapquest", "icon" => "mapquest", "hidden" => true]
			]],
			["title" => "Email Delivery", "link" => "developer/email", "icon" => "messages", "hidden" => true],
			["title" => "Service APIs", "link" => "developer/services", "icon" => "api", "hidden" => true, "children" => [
				["title" => "Twitter API", "link" => "developer/services/twitter", "icon" => "twitter", "hidden" => true],
				["title" => "Instagram API", "link" => "developer/services/instagram", "icon" => "instagram", "hidden" => true],
				["title" => "Google+ API", "link" => "developer/services/googleplus", "icon" => "googleplus", "hidden" => true],
				["title" => "YouTube API", "link" => "developer/services/youtube", "icon" => "youtube", "hidden" => true],
				["title" => "Flickr API", "link" => "developer/services/flickr", "icon" => "flickr", "hidden" => true],
				["title" => "Salesforce API", "link" => "developer/services/salesforce", "icon" => "cloud", "hidden" => true],
				["title" => "Disqus API", "link" => "developer/services/disqus", "icon" => "disqus", "hidden" => true]
			]],
			["title" => "Media Presets", "link" => "developer/media", "icon" => "images", "hidden" => true],
			["title" => "File Metadata", "link" => "developer/files", "icon" => "files", "hidden" => true],
			["title" => "Security", "link" => "developer/security", "icon" => "lock", "hidden" => true],
			["title" => "Debug", "group" => true],
			["title" => "Site Status", "link" => "developer/status", "icon" => "vitals", "hidden" => true],
			["title" => "User Emulator", "link" => "developer/user-emulator", "icon" => "users", "hidden" => true],
			["title" => "Audit Trail", "link" => "developer/audit", "icon" => "trail", "hidden" => true],
			["title" => "System Upgrade", "link" => "developer/upgrade", "icon" => "vitals", "hidden" => true, "top_level_hidden" => true]
		]],
		"search" => ["title" => "Advanced Search", "link" => "search", "icon" => "search", "hidden" => true],
		"credits" => ["title" => "Credits & Licenses", "link" => "credits", "icon" => "credits", "hidden" => true]
	];
	
	$bigtree["nav_tree"] = $admin->runHooks("menu", null, $bigtree["nav_tree"]);
