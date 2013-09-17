<?
	$results = $admin->searchAuditTrail($_GET["user"],$_GET["table"],$_GET["entry"],$_GET["start"],$_GET["end"]);
	// Setup caches so for big trails we don't retrieve stuff multiple times
	$page_cache = array();
	$user_cache = array();
	$setting_cache = array();
	$form_cache = array();
?>
<div class="table audit_trail">
	<summary><h2>Search Results</h2></summary>
	<header>
		<span class="view_column audit_date">Date</span>
		<span class="view_column audit_user">User</span>
		<span class="view_column audit_table">Table</span>
		<span class="view_column audit_entry">Entry</span>
		<span class="view_column audit_action">Action</span>
	</header>
	<ul>
		<?
			foreach ($results as $r) {
				if ($r["table"] == "bigtree_pages") {
					if (!isset($page_cache[$r["entry"]])) {
						$page_cache[$r["entry"]] = $cms->getPage($r["entry"],false);
					}
					$page = $page_cache[$r["entry"]];
					$link = '<a target="_blank" href="'.ADMIN_ROOT.'pages/edit/'.$page["id"].'/">'.$page["nav_title"].'</a>';
				} elseif ($r["table"] == "bigtree_settings") {
					if (!isset($setting_cache[$r["entry"]])) {
						$setting_cache[$r["entry"]] = $admin->getSetting($r["entry"]);
					}
					$setting = $setting_cache[$r["entry"]];
					if ($setting && !$setting["system"]) {
						$link = '<a target="_blank" href="'.ADMIN_ROOT.'settings/edit/'.$setting["id"].'/">'.($setting["name"] ? $setting["name"] : $setting["id"]).'</a>';
					} else {
						$link = $r["entry"];
					}
				} elseif ($r["table"] == "bigtree_users") {
					if (!isset($user_cache[$r["entry"]])) {
						$user_cache[$r["entry"]] = $admin->getUser($r["entry"]);
					}
					$user = $user_cache[$r["entry"]];
					if ($user) {
						$link = '<a target="_blank" href="'.ADMIN_ROOT.'users/edit/'.$user["id"].'/">'.$user["name"].'</a>';
					} else {
						$link = 'Deleted User: '.$r["entry"];
					}
				} else {
					if (!isset($form_cache[$r["table"]])) {
						$view = BigTreeAutoModule::getViewForTable($r["table"]);
						$form = BigTreeAutoModule::getRelatedFormForView($view);
						$module = BigTreeAutoModule::getModuleForForm($form);
						$action = $admin->getModuleActionForForm($form);
						if ($module && $action) {
							$module = $admin->getModule($module);
							$form_cache[$r["table"]] = ADMIN_ROOT.$module["route"]."/".$action["route"]."/";
						}
					}
					$form_link = $form_cache[$r["table"]];
					if ($form_link) {
						$link = '<a target="_blank" href="'.$form_link.$r["entry"].'/">View Entry (id: '.$r["entry"].')</a>';
					} else {
						$link = $r["entry"]." (Unknown Form)";
					}
				}
		?>
		<li>
			<section class="view_column audit_date"><?=date("m/d/y @ g:ia",strtotime($r["date"]))?></section>
			<section class="view_column audit_user"><a target="_blank" href="<?=ADMIN_ROOT?>users/edit/<?=$r["user"]["id"]?>/"><?=$r["user"]["name"]?></a></section>
			<section class="view_column audit_table"><?=$r["table"]?></section>
			<section class="view_column audit_entry"><?=$link?></section>
			<section class="view_column audit_action"><?=ucwords(str_replace("-"," ",$r["type"]))?></section>
		</li>
		<?
			}
		?>
	</ul>
</div>