/*
	
*/
(function($) {
	$.fn.breakpoints = function(opts) {
		var options = jQuery.extend({
			breakpoints: [ 1220, 960, 720, 480, 320 ]
		}, opts);
		
		var lastBPmax = lastBPmin = '';
		var timeout = null;
		
		var methods = {
			respond: function(e) {
				methods.clearTimeout();
				timeout = setTimeout(function() {
					methods.doRespond();
				}, 10);
			},
			doRespond: function() {
				methods.clearTimeout();
				var w = (window.innerWidth) ? window.innerWidth : document.body.clientWidth - 20;
				for (var bp in options.breakpoints) {
					bp = parseInt(bp, 10);
					if (options.breakpoints[bp + 1]) {
						if (w <= options.breakpoints[bp] && w >= options.breakpoints[bp + 1]) {
							$('body').addClass('bp-min-' + options.breakpoints[bp + 1] + "-max-" + options.breakpoints[bp]);
						} else {
							$('body').removeClass('bp-min-' + options.breakpoints[bp + 1] + "-max-" + options.breakpoints[bp]);
						}
					}
					if (w <= options.breakpoints[bp] && (w > options.breakpoints[bp + 1] || typeof options.breakpoints[bp + 1] === "undefined")) {
						if (!$('body').hasClass('bp-max-' + options.breakpoints[bp])) {
							$('body').addClass('bp-max-' + options.breakpoints[bp]).addClass('bp-min-' + options.breakpoints[bp + 1]);
							$('body').removeClass('bp-max-' + lastBPmax).removeClass('bp-min-' + lastBPmin);
							
							$(window).trigger('breakpoints.exit', [ lastBPmax ]);
							$(window).trigger('breakpoints.enter', [ options.breakpoints[bp] ]);
							
							lastBPmax = options.breakpoints[bp];
							lastBPmin = options.breakpoints[bp + 1];
						}
					}
				}
			},
			sort: function(a, b) { 
				return (b - a); 
			},
			clearTimeout: function() {
				if (timeout != null) {
					clearTimeout(timeout);
					timeout = null;
				}
			}
		};
		
		options.breakpoints.push(10000);
		options.breakpoints.sort(methods.sort);
		
		$(window).resize(methods.respond);
		methods.respond();
	};
})(jQuery);
