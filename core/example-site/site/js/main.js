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
			var attribution = strip_tags($target.data("attribution")),
				link = strip_tags($target.data("link"))
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
	
	function strip_tags (input, allowed) {
		//	discuss at: http://locutus.io/php/strip_tags/
		// original by: Kevin van Zonneveld (http://kvz.io)
		// improved by: Luke Godfrey
		// improved by: Kevin van Zonneveld (http://kvz.io)
		//	input by: Pul
		//	input by: Alex
		//	input by: Marc Palau
		//	input by: Brett Zamir (http://brett-zamir.me)
		//	input by: Bobby Drake
		//	input by: Evertjan Garretsen
		// bugfixed by: Kevin van Zonneveld (http://kvz.io)
		// bugfixed by: Onno Marsman (https://twitter.com/onnomarsman)
		// bugfixed by: Kevin van Zonneveld (http://kvz.io)
		// bugfixed by: Kevin van Zonneveld (http://kvz.io)
		// bugfixed by: Eric Nagel
		// bugfixed by: Kevin van Zonneveld (http://kvz.io)
		// bugfixed by: Tomasz Wesolowski
		//	revised by: Rafał Kukawski (http://blog.kukawski.pl)
		//	 example 1: strip_tags('<p>Kevin</p> <br /><b>van</b> <i>Zonneveld</i>', '<i><b>')
		//	 returns 1: 'Kevin <b>van</b> <i>Zonneveld</i>'
		//	 example 2: strip_tags('<p>Kevin <img src="someimage.png" onmouseover="someFunction()">van <i>Zonneveld</i></p>', '<p>')
		//	 returns 2: '<p>Kevin van Zonneveld</p>'
		//	 example 3: strip_tags("<a href='http://kvz.io'>Kevin van Zonneveld</a>", "<a>")
		//	 returns 3: "<a href='http://kvz.io'>Kevin van Zonneveld</a>"
		//	 example 4: strip_tags('1 < 5 5 > 1')
		//	 returns 4: '1 < 5 5 > 1'
		//	 example 5: strip_tags('1 <br/> 1')
		//	 returns 5: '1	1'
		//	 example 6: strip_tags('1 <br/> 1', '<br>')
		//	 returns 6: '1 <br/> 1'
		//	 example 7: strip_tags('1 <br/> 1', '<br><br/>')
		//	 returns 7: '1 <br/> 1'
	
		// making sure the allowed arg is a string containing only tags in lowercase (<a><b><c>)
		allowed = (((allowed || '') + '').toLowerCase().match(/<[a-z][a-z0-9]*>/g) || []).join('')
	
		var tags = /<\/?([a-z][a-z0-9]*)\b[^>]*>/gi
		var commentsAndPhpTags = /<!--[\s\S]*?-->|<\?(?:php)?[\s\S]*?\?>/gi
	
		return input.replace(commentsAndPhpTags, '').replace(tags, function ($0, $1) {
			return allowed.indexOf('<' + $1.toLowerCase() + '>') > -1 ? $0 : ''
		})
	}