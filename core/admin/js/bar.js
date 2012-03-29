bigtree_bar_head = document.getElementsByTagName("head")[0];
bigtree_bar_body = document.getElementsByTagName("body")[0];

// Include our CSS
bigtree_bar_css = document.createElement('link');
bigtree_bar_css.setAttribute("rel","stylesheet");
bigtree_bar_css.setAttribute("type","text/css");
bigtree_bar_css.setAttribute("href","admin_root/css/bar.css");
bigtree_bar_head.appendChild(bigtree_bar_css);

// Add the bar tab
bigtree_bar_tab = document.createElement("a");
bigtree_bar_tab.setAttribute("id","bigtree_bar_tab");
bigtree_bar_tab.setAttribute("href","#");
bigtree_bar_tab.onclick = bigtree_show_bar;
bigtree_bar_body.appendChild(bigtree_bar_tab);

function bigtree_bar_get_style(oElm, strCssRule){
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
}

function bigtree_show_bar() {
	bigtree_bar_create_cookie("hide_bigtree_bar","",365);

	// Find out the current padding of the body
	bigtree_bar_body_padding = parseInt(bigtree_bar_get_style(bigtree_bar_body,"padding-top"));
	bigtree_bar_body.style.paddingTop = (bigtree_bar_body_padding + 40) + "px";
	
	document.getElementsByTagName('body')[0].className += ' bigtree_bar_open';
	
	// Add the bar
	bigtree_bar = document.createElement("div");
	bigtree_bar.setAttribute("id","bigtree_bar");
	
	bigtree_bar_html = '<a href="admin_root/" id="bigtree_bar_logo"></a><a class="bigtree_link" id="bigtree_edit_content" href="#">Edit Content</a><a class="bigtree_link" href="admin_root/pages/edit/' + bigtree_current_page_id + '/?return=front">View in BigTree</a><a href="#" id="bigtree_bar_close"></a><a href="admin_root/login/logout/" id="bigtree_logout">Logout</a><span id="bigtree_name">' + bigtree_user_name + '</span>';
	if (bigtree_is_previewing) {
		bigtree_bar_html += '<span id="bigtree_preview_notice">THIS IS A PREVIEW OF PENDING CHANGES</span>';
	}
	bigtree_bar.innerHTML = bigtree_bar_html;
	
	bigtree_bar_body.appendChild(bigtree_bar);
	
	document.getElementById("bigtree_bar_close").onclick = function() {
		if (document.getElementById("bigtree_bar_overlay")) {
			bigtree_bar_body.removeChild(document.getElementById("bigtree_bar_overlay"));
		}
		if (document.getElementById("bigtree_bar_frame")) {
			bigtree_bar_body.removeChild(document.getElementById("bigtree_bar_frame"));
		}
		bigtree_bar_body.removeChild(document.getElementById("bigtree_bar"));
		bigtree_bar_body.style.paddingTop = bigtree_bar_body_padding + "px";
		
		var bodyClass = document.getElementsByTagName('body')[0].className.replace("bigtree_bar_open", "").trim();
		document.getElementsByTagName('body')[0].className = bodyClass;
		
		bigtree_bar_create_cookie("hide_bigtree_bar","on",365);
		
		return false;
	};
	
	document.getElementById("bigtree_edit_content").onclick = function() {
		if (!document.getElementById("bigtree_bar_overlay")) {
			leftd = parseInt((bigtree_window_width() - 820) / 2);
			topd = parseInt((bigtree_window_height() - 615) / 2);
			
			bigtree_bar_overlay = document.createElement("div");
			bigtree_bar_overlay.setAttribute("id","bigtree_bar_overlay");
			bigtree_bar_body.appendChild(bigtree_bar_overlay);
			
			bigtree_bar_frame = document.createElement("iframe");
			bigtree_bar_frame.setAttribute("id","bigtree_bar_frame");
			bigtree_bar_frame.setAttribute("src","admin_root/pages/front-end-edit/" + bigtree_current_page_id + "/");
			bigtree_bar_frame.style.left = leftd + "px";
			bigtree_bar_frame.style.top = topd + "px";
			bigtree_bar_body.appendChild(bigtree_bar_frame);
		}
		
		return false;
	};
	
	return false;
}

function bigtree_show_preview_bar(return_link) {
	bigtree_bar_create_cookie("hide_bigtree_bar","",365);

	// Find out the current padding of the body
	bigtree_bar_body_padding = parseInt(bigtree_bar_get_style(bigtree_bar_body,"padding-top"));
	bigtree_bar_body.style.paddingTop = (bigtree_bar_body_padding + 40) + "px";
	
	// Add the bar
	bigtree_bar = document.createElement("div");
	bigtree_bar.setAttribute("id","bigtree_bar");
	
	bigtree_bar_html = '<a href="admin_root/" id="bigtree_bar_logo"></a><a class="bigtree_link" id="bigtree_edit_content" href="' + return_link + '">Continue Editing</a><a href="' + return_link + '" id="bigtree_bar_close"></a><a href="admin_root/login/logout/" id="bigtree_logout">Logout</a><span id="bigtree_name">' + bigtree_user_name + '</span><span id="bigtree_preview_notice">PAGE PREVIEW</span>';
	bigtree_bar.innerHTML = bigtree_bar_html;
	
	bigtree_bar_body.appendChild(bigtree_bar);
	
	return false;
}

function bigtree_bar_create_cookie(name,value,days) {
	if (days) {
		var date = new Date();
		date.setTime(date.getTime()+(days*24*60*60*1000));
		var expires = "; expires="+date.toGMTString();
	} else {
		var expires = "";
	}
	document.cookie = name+"="+value+expires+"; path=/";
}

function bigtree_window_width() {
	if (window.innerWidth) {
		windowWidth = window.innerWidth;
	} else if (document.documentElement && document.documentElement.clientWidth) {
		windowWidth = document.documentElement.clientWidth;
	} else if (document.body) {
		windowWidth = document.body.clientWidth;
	}
	return windowWidth;
}

function bigtree_window_height() {
	if (window.innerHeight) {
		windowHeight = window.innerHeight;
	} else if (document.documentElement && document.documentElement.clientHeight) {
		windowHeight = document.documentElement.clientHeight;
	} else if (document.body) {
		windowHeight = document.body.clientHeight;
	}
	return windowHeight;
}

function bigtree_bar_refresh(preview) {
	window.location.href = preview;
}

function bigtree_bar_cancel() {
	if (document.getElementById("bigtree_bar_overlay")) {
			bigtree_bar_body.removeChild(document.getElementById("bigtree_bar_overlay"));
	}
	if (document.getElementById("bigtree_bar_frame")) {
		bigtree_bar_body.removeChild(document.getElementById("bigtree_bar_frame"));
	}
}

if (bigtree_bar_show) {
	bigtree_show_bar();
}
if (bigtree_preview_bar_show) {
	bigtree_show_preview_bar(bigtree_return_link);
}