<?
	header("Content-type: text/javascript");
	$permission = $admin->getPageAccessLevel($_GET["current_page_id"]);
?>
var BigTreeBar = {

	cancel: function() {
		if (document.getElementById("bigtree_bar_overlay")) {
			BigTreeBar.body.removeChild(document.getElementById("bigtree_bar_overlay"));
		}
		if (document.getElementById("bigtree_bar_frame")) {
			BigTreeBar.body.removeChild(document.getElementById("bigtree_bar_frame"));
		}
	},

	createCookie: function(name,value,days) {
		if (days) {
			var date = new Date();
			date.setTime(date.getTime()+(days*24*60*60*1000));
			var expires = "; expires="+date.toGMTString();
		} else {
			var expires = "";
		}
		document.cookie = name+"="+value+expires+"; path=/";
	},

	getStyle: function(oElm, strCssRule){
		var strValue = "";
		if (document.defaultView && document.defaultView.getComputedStyle){
			strValue = document.defaultView.getComputedStyle(oElm, "").getPropertyValue(strCssRule);
		} else if (oElm.currentStyle){
			strCssRule = strCssRule.replace(/\-(\w)/g, function (strMatch, p1){
				return p1.toUpperCase();
			});
			strValue = oElm.currentStyle[strCssRule];
		}
		return strValue;
	},

	refresh: function(preview) {
		window.location.href = preview;
	},

	show: function() {
		BigTreeBar.createCookie("hide_bigtree_bar","",365);

		// Find out the current padding of the body
		BigTreeBar.body_padding = parseInt(BigTreeBar.getStyle(BigTreeBar.body,"padding-top"));
		BigTreeBar.body.style.paddingTop = (BigTreeBar.body_padding + 40) + "px";
		
		// Remove the closed class, add the open class
		document.getElementsByTagName('body')[0].className = (document.getElementsByTagName('body')[0].className.replace("bigtree_bar_closed", "") + " bigtree_bar_open").trim();
		
		// Add the bar
		bigtree_bar = document.createElement("div");
		bigtree_bar.setAttribute("id","bigtree_bar");
		
		bigtree_bar_html = '<a href="<?=ADMIN_ROOT?>" id="bigtree_bar_logo"></a>';
		<? if ($permission) { ?>
		bigtree_bar_html += '<a class="bigtree_link" id="bigtree_edit_content" href="#">Edit Content</a><a class="bigtree_link" href="<?=ADMIN_ROOT?>pages/edit/<?=htmlspecialchars(strip_tags($_GET["current_page_id"]))?>/?return=front">Edit in BigTree</a>';
		<? } ?>
		bigtree_bar_html += '<a href="#" id="bigtree_bar_close"></a><a href="<?=ADMIN_ROOT?>login/logout/" id="bigtree_logout">Logout</a><div class="divider"></div><span id="bigtree_name"><?=str_replace("'","\'",htmlspecialchars(strip_tags($_GET["username"])))?></span>';
		<? if ($_GET["previewing"]) { ?>
		bigtree_bar_html += '<span id="bigtree_preview_notice">THIS IS A PREVIEW OF PENDING CHANGES</span>';
		<? } elseif (!empty($bigtree["config"]["maintenance_url"])) { ?>
		bigtree_bar_html += '<span id="bigtree_preview_notice">MAINTENANCE MODE</span>';
		<? } elseif (!empty($bigtree["config"]["developer_mode"])) { ?>
		bigtree_bar_html += '<span id="bigtree_preview_notice">DEVELOPER MODE</span>';
		<? } ?>
		bigtree_bar.innerHTML = bigtree_bar_html;
		
		BigTreeBar.body.appendChild(bigtree_bar);
		
		// Close Button
		document.getElementById("bigtree_bar_close").onclick = function(e) {
			if (document.getElementById("bigtree_bar_overlay")) {
				BigTreeBar.body.removeChild(document.getElementById("bigtree_bar_overlay"));
			}
			if (document.getElementById("bigtree_bar_frame")) {
				BigTreeBar.body.removeChild(document.getElementById("bigtree_bar_frame"));
			}
			BigTreeBar.body.removeChild(document.getElementById("bigtree_bar"));
			BigTreeBar.body.style.paddingTop = BigTreeBar.body_padding + "px";
			
			// Remove the open class, add the closed class
			document.getElementsByTagName('body')[0].className = (document.getElementsByTagName('body')[0].className.replace("bigtree_bar_open", "") + " bigtree_bar_closed").trim();
			
			BigTreeBar.createCookie("hide_bigtree_bar","on",365);
			
			BigTreeBar.dispatch("closebigtreebar");
			
			return false;
		};
		
		document.getElementById("bigtree_edit_content").onclick = function() {
			if (!document.getElementById("bigtree_bar_overlay")) {
				leftd = parseInt((BigTreeBar.windowWidth() - 820) / 2);
				topd = parseInt((BigTreeBar.windowHeight() - 615) / 2);
				
				bigtree_bar_overlay = document.createElement("div");
				bigtree_bar_overlay.setAttribute("id","bigtree_bar_overlay");
				BigTreeBar.body.appendChild(bigtree_bar_overlay);
				
				bigtree_bar_frame = document.createElement("iframe");
				bigtree_bar_frame.setAttribute("id","bigtree_bar_frame");
				bigtree_bar_frame.setAttribute("src","<?=ADMIN_ROOT?>pages/front-end-edit/<?=htmlspecialchars(strip_tags($_GET["current_page_id"]))?>/");
				bigtree_bar_frame.style.left = leftd + "px";
				bigtree_bar_frame.style.top = topd + "px";
				BigTreeBar.body.appendChild(bigtree_bar_frame);
			}
			
			return false;
		};
		
		BigTreeBar.dispatch("openbigtreebar");
		
		return false;
	},

	showPreview: function(return_link) {
		BigTreeBar.createCookie("hide_bigtree_bar","",365);

		// Find out the current padding of the body
		BigTreeBar.body_padding = parseInt(BigTreeBar.getStyle(BigTreeBar.body,"padding-top"));
		BigTreeBar.body.style.paddingTop = (BigTreeBar.body_padding + 40) + "px";
		
		// Add the bar
		bigtree_bar = document.createElement("div");
		bigtree_bar.setAttribute("id","bigtree_bar");
		
		bigtree_bar_html = '<a href="<?=ADMIN_ROOT?>" id="bigtree_bar_logo"></a><a class="bigtree_link" id="bigtree_edit_content" href="' + return_link + '">Continue Editing</a><a href="' + return_link + '" id="bigtree_bar_close"></a><a href="<?=ADMIN_ROOT?>login/logout/" id="bigtree_logout">Logout</a><span id="bigtree_name"><?=htmlspecialchars(strip_tags(str_replace("'","\'",$_GET["username"])))?></span><span id="bigtree_preview_notice">PAGE PREVIEW</span>';
		bigtree_bar.innerHTML = bigtree_bar_html;
		
		BigTreeBar.body.appendChild(bigtree_bar);
		
		return false;
	},

	windowHeight: function() {
		if (window.innerHeight) {
			windowHeight = window.innerHeight;
		} else if (document.documentElement && document.documentElement.clientHeight) {
			windowHeight = document.documentElement.clientHeight;
		} else if (document.body) {
			windowHeight = document.body.clientHeight;
		}
		return windowHeight;
	},

	windowWidth: function() {
		if (window.innerWidth) {
			windowWidth = window.innerWidth;
		} else if (document.documentElement && document.documentElement.clientWidth) {
			windowWidth = document.documentElement.clientWidth;
		} else if (document.body) {
			windowWidth = document.body.clientWidth;
		}
		return windowWidth;
	},
	
	dispatch: function(evtName) {
		var evt = document.createEvent("Event");
		evt.initEvent(evtName, true, true); 
		document.dispatchEvent(evt);
	}
};

BigTreeBar.head = document.getElementsByTagName("head")[0];
BigTreeBar.body = document.getElementsByTagName("body")[0];

// Include our CSS
BigTreeBar.css = document.createElement('link');
BigTreeBar.css.setAttribute("rel","stylesheet");
BigTreeBar.css.setAttribute("type","text/css");
BigTreeBar.css.setAttribute("href","<?=str_replace(array("http://","https://"),"//",ADMIN_ROOT)?>css/bar.css");
BigTreeBar.head.appendChild(BigTreeBar.css);

// Add the bar tab
BigTreeBar.tab = document.createElement("a");
BigTreeBar.tab.innerHTML = '<span></span>';
BigTreeBar.tab.setAttribute("id","bigtree_bar_tab");
BigTreeBar.tab.setAttribute("href","#");
BigTreeBar.tab.onclick = BigTreeBar.show;
BigTreeBar.body.appendChild(BigTreeBar.tab);

// Add BigTree Bar class to the body, keep outside global namespace
document.getElementsByTagName('body')[0].className = (document.getElementsByTagName('body')[0].className.replace("bigtree_bar_open", "") + " bigtree_bar_closed").trim();	
<? if ($_GET["show_bar"]) { ?>
BigTreeBar.show();
<? } ?>

<? if ($_GET["show_preview"]) { ?>
BigTreeBar.showPreview("<?=htmlspecialchars(strip_tags($_GET["return_link"]))?>");
<? } ?>