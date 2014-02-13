// IE HTML5 DOM Fix | http://jdbartlett.github.com/innershiv | WTFPL License
window.innerShiv=(function(){var d,r;return function(h,u){if(!d){d=document.createElement('div');r=document.createDocumentFragment();/*@cc_on d.style.display = 'none'@*/}var e=d.cloneNode(true);/*@cc_on document.body.appendChild(e);@*/e.innerHTML=h.replace(/^\s\s*/, '').replace(/\s\s*$/, '');/*@cc_on document.body.removeChild(e);@*/if(u===false){return e.childNodes;}var f=r.cloneNode(true),i=e.childNodes.length;while(i--){f.appendChild(e.firstChild);}return f;}}());
// Create Missing Console
if(window.console===undefined){window.console={log:function(){},error:function(){},warn:function(){}}}
// Fix Missing .indexOf()
//if(!Array.prototype.indexOf){Array.prototype.indexOf=function(e){var t=this.length>>>0;var n=Number(arguments[1])||0;n=n<0?Math.ceil(n):Math.floor(n);if(n<0)n+=t;for(;n<t;n++){if(n in this&&this[n]===e)return n}return-1}}
	
	// !Font Loader
	var WebFontConfig = {
		custom: {
			families: ["PTSans", "PTSerif"],
			urls: ["static_root/css/fonts.css"]
		}
	};
	
	// !Site
	var Site = {
		minWidth: 320,
		maxWidth: Infinity,
		windowHeight: 0,
		windowWidth: 0,
		
		_init: function() {
			Site.$window = $(window);
			Site.$body   = $("body");
			
			$.rubberband({
				maxWidth: [ 1220, 980, 740, 500, 320 ],
				minWidth: [ 1220, 980, 740, 500, 320 ]
			});
			Site.$window.on("snap", Site._onRespond)
						.on("resize", Site._onResize)
						.on("scroll", Site._onScroll);
			
			$.shifter({
				maxWidth: Infinity
			});
			$(".wallpapered").wallpaper();
			$(".lightbox").boxer({
				margin: 0,
				mobile: true,
				formatter: Site._formatCaptions
			});
			
			$(".scroll_to").not(".no_scroll").click(Site._scrollTo);
			
			ImageHeader._init();
			
			Site._onRAF();
			Site.$window.trigger("resize")
					    .trigger("scroll");
		},
		_onRespond: function(e, data) {
			Site.minWidth = data.minWidth;
			Site.maxWidth = data.maxWidth;
			
			ImageHeader._respond();
		},
		_onResize: function(e) {
			Site.windowHeight = Site.$window.height();
			Site.windowWidth = Site.$window.width();
		},
		_onRAF: function() {
			window.requestAnimationFrame(Site._onRAF);
			
			Site.scrollTop = Site.$window.scrollTop();
			
			ImageHeader._update();
		},
		_onScroll: function(e) {
			/*
			if (!Site.$body.hasClass("disable-hover")) {
				Site.$body.addClass("disable-hover");
			}
			$.doTimeout("site-scroll-end", 100, Site._onScrollEnd);
			*/
		},
		_onScrollEnd: function() {
			//Site.$body.removeClass("disable-hover");
		},
		_scrollTo: function(e) {
			var $target = $(e.currentTarget),
				href = $target.attr("href");
			
			if (href != "#") {
				e.stopPropagation();
				e.preventDefault();
				
				Site._scrollToElement(href);
			}
		},
		_scrollToElement: function(href) {
			var $el = $(href);
			
			if (!$el.length) {
				$el = $("[name="+href.substring(1)+"]");
			}
			
			if ($el.length) {
				var pos = $el.offset(),
					offset = 0;
				
				if (href == "#top") {
					pos.top = 0;
				}
				
				Site._scrollToPos(pos.top - offset);
			}
		},
		_scrollToPos: function(pos) {
			$("html, body").animate({ scrollTop: pos });
		},
		_formatCaptions: function($target) {
			var attribution = $target.data("attribution"),
				link = $target.data("link")
				caption = '';
			
			if (attribution) {
				attribution = 'Photo By ' + attribution;
				if (link) {
					caption += '<a href="' + link + '" target="_blank">' + attribution + '</a>';
				} else {
					caption += attribution;
				}
			}
			
			return '<p>' + caption + '</p>';
		}
	};
	
	
	// !Image Header
	var ImageHeader = {
		initialized: false,
		
		_init: function() {
			ImageHeader.$header = $(".image_header");
			
			if (ImageHeader.$header.length) {
				ImageHeader.initialized = true;
				
				ImageHeader.$positioner = ImageHeader.$header.find(".positioner");
				ImageHeader.$image = ImageHeader.$header.find(".wallpaper-container");
			}
		},
		
		_respond: function() {
			if (ImageHeader.initialized) {
				ImageHeader.positionerBottom = parseInt(ImageHeader.$positioner.css("bottom"), 10);
				ImageHeader.height = ImageHeader.$header.outerHeight(true) - ImageHeader.$positioner.outerHeight(true);
			}
		},
		
		_update: function() {
			if (ImageHeader.initialized) {
				if (Site.minWidth >= 980) {
					var perc = (ImageHeader.height - Site.scrollTop) / ImageHeader.height;
					if (perc > 1) perc = 1;
					if (perc < 0) perc = 0;
					
					ImageHeader.$image.css({ 
						opacity: perc
					});
					ImageHeader.$positioner.css({ 
						marginBottom: -(ImageHeader.positionerBottom * (1 - perc)),
						opacity: perc
					});
				} else {
					ImageHeader.$image.css({ 
						opacity: 1
					});
					ImageHeader.$positioner.css({ 
						marginBottom: 0,
						opacity: 1
					});
				}
			}
		}
	};
	
	
	// !DOM Ready
	$(document).ready(function() {
		Site._init();
	});
	
	
	/* !classCount */
	(function($) {
		$.fn.classCount = function(classes) {
			var $target = $(this),
				count = 0;
			for (var i in classes) {
				if ($target.hasClass(classes[i])) {
					count++;
				}
			}
			return count;
		};
	})(jQuery);
	
	/* !hasOneClass */
	(function($) {
		$.fn.hasOneClass = function(classes) {
			var $target = $(this);
			for (var i in classes) {
				if ($target.hasClass(classes[i])) {
					return true;
				}
			}
			return false;
		};
	})(jQuery);
	
	/* !hasAllClasses */
	(function($) {
		$.fn.hasAllClasses = function(classes) {
			var $target = $(this);
			for (var i in classes) {
				if (!$target.hasClass(classes[i])) {
					return false;
				}
			}
			return true;
		};
	})(jQuery);
	
	/* !formatNumber */
	(function($) {
		$.formatNumber = function(number) {
			var parts = number.toString().split(".");
			parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ",");
			return parts.join(".");
		};
	})(jQuery);