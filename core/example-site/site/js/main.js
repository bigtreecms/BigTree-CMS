// IE HTML5 DOM Fix | http://jdbartlett.github.com/innershiv | WTFPL License
window.innerShiv=(function(){var d,r;return function(h,u){if(!d){d=document.createElement('div');r=document.createDocumentFragment();/*@cc_on d.style.display = 'none'@*/}var e=d.cloneNode(true);/*@cc_on document.body.appendChild(e);@*/e.innerHTML=h.replace(/^\s\s*/, '').replace(/\s\s*$/, '');/*@cc_on document.body.removeChild(e);@*/if(u===false){return e.childNodes;}var f=r.cloneNode(true),i=e.childNodes.length;while(i--){f.appendChild(e.firstChild);}return f;}}());
// Create Missing Console
if (window.console === undefined) { window.console = { log: function() {}, error: function() {}, warn: function() {} }; }	
	
	
	var Site = {
		_currentPrefix: "",
		
		init: function() {
			if ($.browser.webkit) Site._currentPrefix = "-webkit-";
			if ($.browser.mozilla) Site._currentPrefix = "-moz-";
			if ($.browser.msie) Site._currentPrefix = "-ms-";
			if ($.browser.opera) Site._currentPrefix = "-o-";
			
			if (typeof wonderId !== 'undefined') {
				WikiHistory.init();
				InstagramViewer.init();
				TwitterTimeline.init();
				YouTubeVideos.init();
			}
		}
	};
	
	var WikiHistory = {
		init: function() {
			WikiHistory.$section = $(".wiki_history");
			
			if (WikiHistory.$section.length > 0) {
				$.ajax({
					type: "GET",
					url: "www_root/ajax/load-content/",
					data: { 
						type: "wikipedia", 
						wonder: wonderId 
					},
					success: WikiHistory.onLoad
				});
			}
		},
		onLoad: function(data) {
			var container = WikiHistory.$section.find(".container_12");
			
			WikiHistory.$section.removeClass("loading");
			container.html(data);
			
			var height = Math.ceil(container.height() / 20) * 20;
			container.css({ height: height });
		}
	};
	
	var InstagramViewer = {
		_imageCount: 0,
		
		init: function() {
			InstagramViewer.$section = $(".instagram_viewer");
			
			if (InstagramViewer.$section.length > 0) {
				$.ajax({
					type: "GET",
					url: "www_root/ajax/load-content/",
					data: { 
						type: "instagram", 
						wonder: wonderId 
					},
					success: InstagramViewer.onLoad
				});
			}
		},
		onLoad: function(data) {
			InstagramViewer.$section.find(".container_12").html(data);
			InstagramViewer.$section.removeClass("loading");
			
			InstagramViewer.$large = InstagramViewer.$section.find(".large");
			InstagramViewer.$thumbnails = InstagramViewer.$section.find(".pic");
			
			InstagramViewer._imageCount = InstagramViewer.$thumbnails.length;
			
			InstagramViewer.$section.on("click", ".pic", InstagramViewer.select)
		},
		select: function(e) {
			e.preventDefault();
			e.stopPropagation();
			
			var $target = $(this);
			var index = InstagramViewer.$thumbnails.index($target);
			
			InstagramViewer.$large.find("img").attr("src", $target.attr("data-large"));
			InstagramViewer.$large.find("p").html($target.attr("data-caption"));
			InstagramViewer.$large.find("strong").html($target.attr("data-user"));
			
			InstagramViewer.$thumbnails.filter(".active").removeClass("active");
			InstagramViewer.$thumbnails.eq(index).addClass("active");
		}
	};
	
	var TwitterTimeline = {
		_index: 0,
		_pageCount: 0,
		
		init: function() {
			TwitterTimeline.$section = $(".twitter_timeline");
			
			if (TwitterTimeline.$section.length > 0) {
				$.ajax({
					type: "GET",
					url: "www_root/ajax/load-content/",
					data: { 
						type: "twitter", 
						wonder: wonderId 
					},
					success: TwitterTimeline.onLoad
				});
			}
		},
		onLoad: function(data) {
			TwitterTimeline.$section.find(".container_12").html(data);
			TwitterTimeline.$section.removeClass("loading");
			
			TwitterTimeline.$timeline = TwitterTimeline.$section.find(".inner");
			TwitterTimeline.$articles = TwitterTimeline.$section.find("article");
			
			TwitterTimeline._pageWidth = TwitterTimeline.$articles.eq(0).outerWidth(true) * 3;
			TwitterTimeline._pageCount = Math.ceil(TwitterTimeline.$articles.length / 3);
			
			TwitterTimeline.$section.on("click", ".trigger", TwitterTimeline.advance)
		},
		advance: function() {
			var index = TwitterTimeline._index + ($(this).hasClass("previous") ? -1 : 1);
			
			if (index > TwitterTimeline._pageCount - 1) {
				index = TwitterTimeline._pageCount - 1;
			}
			if (index < 0) {
				index = 0;
			}
			
			TwitterTimeline.$timeline.css({ left: -(index * TwitterTimeline._pageWidth) });
			
			TwitterTimeline._index = index;
		}
	};
	
	var YouTubeVideos = {
		init: function() {
			YouTubeVideos.$section = $(".youtube_videos");
			
			if (YouTubeVideos.$section.length > 0) {
				$.ajax({
					type: "GET",
					url: "www_root/ajax/load-content/",
					data: { 
						type: "youtube", 
						wonder: wonderId 
					},
					success: YouTubeVideos.onLoad
				});
			}
		},
		onLoad: function(data) {
			YouTubeVideos.$section.find(".container_12").html(data);
			YouTubeVideos.$section.removeClass("loading");
		}
	};
	
	
	// Utilities
	var Utils = {
		// Global Settings
		settings: {
			debug: true
		},
		// Smart Logging
		log: function(data, type) {
			if (Utils.settings.debug) {
				switch (type) {
					case "error": // Utils.log(["one", "two"], "error");
						console.error.apply(console, [data]);
						break;
					case "warn": // Utils.log(["one", "two"], "warn");
						console.warn.apply(console, [data]);
						break;
					default: // Utils.log(["one", "two"]);
						console.log.apply(console, [data]);
						break;
				}
			}
			return false;
		},
		// Allius Utils.log
		error: function(data) {
			Utils.log(data, "error");
		},
		// Allius Utils.log 
		warn: function(data) {
			Utils.log(data, "warn");
		},
		// Trim strings
		trim: function(string) {
			return string.replace(/^\s\s*/, '').replace(/\s\s*$/, '');
		},
		// Email Validation
		validateEmail: function(email) {
			var regex = /^(([^<>()[\]\\.,;:\s@\"]+(\.[^<>()[\]\\.,;:\s@\"]+)*)|(\".+\"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
			return regex.test(email);
		},
		// Phone Validation
		validatePhone: function(phone) {
			var regex = /^\(?(\d{3})\)?[- ]?(\d{3})[- ]?(\d{4})$/;
			return regex.test(phone);
		},
		//Push Custom Google Analytics Events
		captureAction: function(category, action, label) {
			Utils.log("GA ACTION: " + category + ", " + action + ", " + label);
			if (typeof _gaq == undefined) _gaq = [];
			_gaq.push(['_trackEvent', category, action, label]);
		},
		capturePage: function(url) {
			Utils.log("GA PAGEVIEW: " + url);
			if (typeof _gaq == undefined) _gaq = [];
			if (!isAndroid && !isBlackBerry && !isIOS)
			{
				_gaq.push(['_trackPageview', url]);
			}
		}
	}
	
	//COOKIES! OMNOMNOMNOM!!!
	var Cookies = {
		create: function(name, value, days) {
			var expires = "";
			if (days) {
				var date = new Date();
				date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
				expires = "; expires=" + date.toGMTString();
			} 
			document.cookie = name + "=" + value + expires + "; path=/";
		},
		read: function(name) {
			var nameEQ = name + "=";
			var ca = document.cookie.split(';');
			for(var i = 0; i < ca.length; i++) {
				var c = ca[i];
				while (c.charAt(0) == ' ') {
					c = c.substring(1, c.length);
				}
				if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length, c.length);
			}
			return null;
		},
		erase: function(name) {
			Cookies.create(name, "", -1);
		}
	};
	
	$(document).ready(function() {
		Site.init();
	});
	
	
/*
 * jQuery doTimeout: Like setTimeout, but better! - v1.0 - 3/3/2010
 * http://benalman.com/projects/jquery-dotimeout-plugin/
 * 
 * Copyright (c) 2010 "Cowboy" Ben Alman
 * Dual licensed under the MIT and GPL licenses.
 * http://benalman.com/about/license/
 */
(function($){var a={},c="doTimeout",d=Array.prototype.slice;$[c]=function(){return b.apply(window,[0].concat(d.call(arguments)))};$.fn[c]=function(){var f=d.call(arguments),e=b.apply(this,[c+f[0]].concat(f));return typeof f[0]==="number"||typeof f[1]==="number"?this:e};function b(l){var m=this,h,k={},g=l?$.fn:$,n=arguments,i=4,f=n[1],j=n[2],p=n[3];if(typeof f!=="string"){i--;f=l=0;j=n[1];p=n[2]}if(l){h=m.eq(0);h.data(l,k=h.data(l)||{})}else{if(f){k=a[f]||(a[f]={})}}k.id&&clearTimeout(k.id);delete k.id;function e(){if(l){h.removeData(l)}else{if(f){delete a[f]}}}function o(){k.id=setTimeout(function(){k.fn()},j)}if(p){k.fn=function(q){if(typeof p==="string"){p=g[p]}p.apply(m,d.call(n,i))===true&&!q?o():e()};o()}else{if(k.fn){j===undefined?e():k.fn(j===false);return true}else{e()}}}})(jQuery);