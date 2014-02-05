<?
	// BigTree Admin Nav Tree
	$bigtree["nav_tree"] = array(
		"dashboard" => array("title" => "Dashboard","link" => "dashboard","icon" => "dashboard","related" => true,"children" => array(
			array("title" => "Pending Changes","link" => "dashboard/pending-changes","icon" => "pending","hidden" => true),
			"messages" => array("title" => "Message Center","link" => "dashboard/messages","icon" => "messages","hidden" => true,"children" => array(
				array("title" => "View Messages","link" => "dashboard/messages","icon" => "messages","nav_icon" => "list"),
				array("title" => "New Message","link" => "dashboard/messages/new","icon" => "add_message","nav_icon" => "add")
			)),
			array("title" => "Vitals & Statistics","link" => "dashboard/vitals-statistics","icon" => "vitals","related" => true,"hidden" => true,"level" => 1,"children" => array(
				array("title" => "Analytics","link" => "dashboard/vitals-statistics/analytics","hidden" => true,"icon" => "analytics","children" => array(
					array("title" => "Statistics","link" => "dashboard/vitals-statistics/analytics","nav_icon" => "bar_graph"),
					array("title" => "Service Providers","link" => "dashboard/vitals-statistics/analytics/service-providers","nav_icon" => "network"),
					array("title" => "Traffic Sources","link" => "dashboard/vitals-statistics/analytics/traffic-sources","nav_icon" => "car"),
					array("title" => "Keywords","link" => "dashboard/vitals-statistics/analytics/keywords","nav_icon" => "key"),
					array("title" => "Configure","link" => "dashboard/vitals-statistics/analytics/configure","nav_icon" => "setup","level" => 1),
					array("title" => "Caching Data","link" => "dashboard/vitals-statistics/analytics/cache","hidden" => true)
				)),
				array("title" => "404 Report","link" => "dashboard/vitals-statistics/404","hidden" => true,"level" => 1,"icon" => "page_404","children" => array(
					array("title" => "Active 404s","link" => "dashboard/vitals-statistics/404","nav_icon" => "error"),
					array("title" => "Ignored 404s","link" => "dashboard/vitals-statistics/404/ignored","nav_icon" => "ignored"),
					array("title" => "301 Redirects","link" => "dashboard/vitals-statistics/404/301","nav_icon" => "redirect"),
					array("title" => "Clear 404s","link" => "dashboard/vitals-statistics/404/clear","nav_icon" => "delete")
				)),
				array("title" => "Site Integrity","link" => "dashboard/vitals-statistics/integrity","icon" => "integrity","hidden" => true,"level" => 1)
			))
		)),
		"pages" => array("title" => "Pages","link" => "pages","icon" => "page","nav_icon" => "pages","children" => array(
			"view-tree" => array("title" => "View Subpages","link" => "pages/view-tree/{id}","nav_icon" => "list"),
			"add" => array("title" => "Add Subpage","link" => "pages/add/{id}","icon" => "add_page","nav_icon" => "add"),
			"edit" => array("title" => "Edit Page","link" => "pages/edit/{id}","icon" => "edit_page","nav_icon" => "edit"),
			"revisions" => array("title" => "Revisions","link" => "pages/revisions/{id}","icon" => "page_versions","nav_icon" => "refresh"),
			"move" => array("title" => "Move Page","link" => "pages/move/{id}","icon" => "move_page","nav_icon" => "truck","level" => 1)
		)),
		"modules" => array("title" => "Modules","link" => "modules","icon" => "modules","children" => array()),
		"users" => array("title" => "Users","link" => "users","icon" => "users","level" => 1,"children" => array(
			array("title" => "View Users","link" => "users","nav_icon" => "list"),
			array("title" => "Add User","link" => "users/add","nav_icon" => "add"),
			array("title" => "Edit User","link" => "users/edit","icon" => "gravatar","hidden" => true),
			array("title" => "Profile","link" => "users/profile","icon" => "gravatar","hidden" => true)
		)),
		"settings" => array("title" => "Settings","link" => "settings","icon" => "settings","children" => array(
			array("title" => "Edit Setting","link" => "settings/edit","hidden" => true)
		)),
		"developer" => array("title" => "Developer","link" => "developer","icon" => "developer","nav_icon" => "developer","level" => 2,"related" => true,"children" => array(
			array("title" => "Templates","link" => "developer/templates","icon" => "templates","hidden" => true,"children" => array(
				array("title" => "View Templates","link" => "developer/templates","nav_icon" => "list"),
				array("title" => "Add Template","link" => "developer/templates/add","nav_icon" => "add"),
				array("title" => "Edit Template","link" => "developer/templates/edit","hidden" => true)
			)),
			array("title" => "Modules","link" => "developer/modules","icon" => "modules","hidden" => true,"children" => array(
				array("title" => "View Modules","link" => "developer/modules","nav_icon" => "list"),
				array("title" => "Add Module","link" => "developer/modules/add","nav_icon" => "add"),
				array("title" => "Module Designer","link" => "developer/modules/designer","nav_icon" => "edit"),
				array("title" => "View Groups","link" => "developer/modules/groups","nav_icon" => "list"),
				array("title" => "Add Group","link" => "developer/modules/groups/add","nav_icon" => "add"),
				array("title" => "Edit Module","link" => "developer/modules/edit","hidden" => true),
				array("title" => "Edit Group","link" => "developer/modules/groups/edit","hidden" => true),
				array("title" => "Module Created","link" => "developer/modules/create","hidden" => true),
				array("title" => "Add View","link" => "developer/modules/views/add","hidden" => true),
				array("title" => "Edit View","link" => "developer/modules/views/edit","hidden" => true),
				array("title" => "Style View","link" => "developer/modules/views/style","hidden" => true),
				array("title" => "Created View","link" => "developer/modules/views/create","hidden" => true),
				array("title" => "Add Form","link" => "developer/modules/forms/add","hidden" => true),
				array("title" => "Edit Form","link" => "developer/modules/forms/edit","hidden" => true),
				array("title" => "Created Form","link" => "developer/modules/forms/create","hidden" => true),
				array("title" => "Add Action","link" => "developer/modules/actions/add","hidden" => true),
				array("title" => "Edit Action","link" => "developer/modules/actions/edit","hidden" => true),
				array("title" => "Add Report","link" => "developer/modules/reports/add","hidden" => true),
				array("title" => "Edit Report","link" => "developer/modules/reports/edit","hidden" => true),
				array("title" => "Add Embeddable Form","link" => "developer/modules/embeds/add","hidden" => true),
				array("title" => "Edit Embeddable Form","link" => "developer/modules/embeds/edit","hidden" => true)
			)),
			array("title" => "Callouts","link" => "developer/callouts","icon" => "callouts","hidden" => true,"children" => array(
				array("title" => "View Callouts","link" => "developer/callouts","nav_icon" => "list"),
				array("title" => "Add Callout","link" => "developer/callouts/add","nav_icon" => "add"),
				array("title" => "Edit Callout","link" => "developer/callouts/edit","hidden" => true),
				array("title" => "View Groups","link" => "developer/callouts/groups","nav_icon" => "list"),
				array("title" => "Add Group","link" => "developer/callouts/groups/add","nav_icon" => "add"),
				array("title" => "Edit Group","link" => "developer/callouts/groups/edit","hidden" => true)
			)),
			array("title" => "Field Types","link" => "developer/field-types","icon" => "field_types","hidden" => true,"children" => array(
				array("title" => "View Field Types","link" => "developer/field-types","nav_icon" => "list"),
				array("title" => "Add Field Type","link" => "developer/field-types/add","nav_icon" => "add"),
				array("title" => "Edit Field Type","link" => "developer/field-types/edit","hidden" => true),
				array("title" => "Field Type Created","link" => "developer/field-types/new","hidden" => true)
			)),
			array("title" => "Feeds","link" => "developer/feeds","icon" => "feeds","hidden" => true,"children" => array(
				array("title" => "View Feeds","link" => "developer/feeds","nav_icon" => "list"),
				array("title" => "Add Feed","link" => "developer/feeds/add","nav_icon" => "add"),
				array("title" => "Edit Feed","link" => "developer/feeds/edit","hidden" => true),
				array("title" => "Created Feed","link" => "developer/feeds/create","hidden" => true)
			)),
			array("title" => "Settings","link" => "developer/settings","icon" => "settings","hidden" => true,"children" => array(
				array("title" => "View Settings","link" => "developer/settings","nav_icon" => "list"),
				array("title" => "Add Setting","link" => "developer/settings/add","nav_icon" => "add"),
				array("title" => "Edit Setting","link" => "developer/settings/edit","hidden" => true)
			)),
			array("title" => "Packages","link" => "developer/packages","icon" => "package","hidden" => true,"children" => array(
				array("title" => "View Packages","link" => "developer/packages","nav_icon" => "list"),
				array("title" => "Install Package","link" => "developer/packages/install","nav_icon" => "add"),
				array("title" => "Build Package","link" => "developer/packages/build","nav_icon" => "shovel")
			)),
			array("title" => "Cloud Storage","link" => "developer/cloud-storage","icon" => "cloud","hidden" => true,"children" => array(
				array("title" => "Local Storage","link" => "developer/cloud-storage/local","icon" => "local_storage","hidden" => true),
				array("title" => "Amazon S3","link" => "developer/cloud-storage/amazon","icon" => "amazon","hidden" => true),
				array("title" => "Rackspace Cloud Files","link" => "developer/cloud-storage/rackspace","icon" => "rackspace","hidden" => true),
				array("title" => "Google Cloud Storage","link" => "developer/cloud-storage/google","icon" => "google","hidden" => true)
			)),
			array("title" => "Payment Gateway","link" => "developer/payment-gateway","icon" => "payment","hidden" => true,"children" => array(
				array("title" => "Authorize.Net","link" => "developer/payment-gateway/authorize","icon" => "authorize","hidden" => true),
				array("title" => "PayPal REST API","link" => "developer/payment-gateway/paypal-rest","icon" => "paypal","hidden" => true),
				array("title" => "PayPal Payments Pro","link" => "developer/payment-gateway/paypal","icon" => "paypal","hidden" => true),
				array("title" => "PayPal Payflow Gateway","link" => "developer/payment-gateway/payflow","icon" => "payflow","hidden" => true),
				array("title" => "First Data / LinkPoint","link" => "developer/payment-gateway/linkpoint","icon" => "linkpoint","hidden" => true)
			)),
			array("title" => "Geocoding","link" => "developer/geocoding","icon" => "geocoding","hidden" => true,"children" => array(
				array("title" => "Google","link" => "developer/geocoding/google","icon" => "google","hidden" => true),
				array("title" => "Bing","link" => "developer/geocoding/bing","icon" => "bing","hidden" => true),
				array("title" => "Yahoo","link" => "developer/geocoding/yahoo","icon" => "yahoo","hidden" => true),
				array("title" => "Yahoo BOSS","link" => "developer/geocoding/yahoo-boss","icon" => "yahoo","hidden" => true),
				array("title" => "MapQuest","link" => "developer/geocoding/mapquest","icon" => "mapquest","hidden" => true)
			)),
			array("title" => "Service APIs","link" => "developer/services","icon" => "api","hidden" => true, "children" => array(
				array("title" => "Twitter API","link" => "developer/services/twitter","icon" => "twitter","hidden" => true),
				array("title" => "Instagram API","link" => "developer/services/instagram","icon" => "instagram","hidden" => true),
				array("title" => "Google+ API","link" => "developer/services/googleplus","icon" => "googleplus","hidden" => true),
				array("title" => "YouTube API","link" => "developer/services/youtube","icon" => "youtube","hidden" => true),
				array("title" => "Flickr API","link" => "developer/services/flickr","icon" => "flickr","hidden" => true),
				array("title" => "Salesforce API","link" => "developer/services/salesforce","icon" => "cloud","hidden" => true),
				array("title" => "Disqus API","link" => "developer/services/disqus","icon" => "disqus","hidden" => true)
			)),
			array("title" => "Site Status","link" => "developer/status","icon" => "vitals","hidden" => true),
			array("title" => "User Emulator","link" => "developer/user-emulator","icon" => "users","hidden" => true),
			array("title" => "Audit Trail","link" => "developer/audit","icon" => "trail","hidden" => true),
			array("title" => "System Upgrade","link" => "developer/upgrade","icon" => "vitals","hidden" => true)
		)),
		"search" => array("title" => "Advanced Search","link" => "search","icon" => "search","hidden" => true),
		"credits" => array("title" => "Credits & Licenses","link" => "credits","icon" => "credits","hidden" => true)
	);
?>