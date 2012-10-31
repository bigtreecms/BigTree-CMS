// IE HTML5 DOM Fix | http://jdbartlett.github.com/innershiv | WTFPL License
window.innerShiv=(function(){var d,r;return function(h,u){if(!d){d=document.createElement('div');r=document.createDocumentFragment();/*@cc_on d.style.display = 'none'@*/}var e=d.cloneNode(true);/*@cc_on document.body.appendChild(e);@*/e.innerHTML=h.replace(/^\s\s*/, '').replace(/\s\s*$/, '');/*@cc_on document.body.removeChild(e);@*/if(u===false){return e.childNodes;}var f=r.cloneNode(true),i=e.childNodes.length;while(i--){f.appendChild(e.firstChild);}return f;}}());

var Site = {
    currentPrefix: "",
    
    init: function() {
    	if ($.browser.webkit) {
    		Site.currentPrefix = "-webkit-";
    	}
    	if ($.browser.mozilla) {
    		Site.currentPrefix = "-moz-";
    	}
    	if ($.browser.msie) {
    		Site.currentPrefix = "-ms-";
    	}
    	if ($.browser.opera) {
    		Site.currentPrefix = "-o-";
    	}
    	
    	Site.$body = $("body");
    	Site.homeFeature = new HomeFeature($("#feature"));
    	Site.twitterTimeline = new TwitterTimeline($(".twitter_timeline"));
    	
    	$(window).bind("breakpoints.enter", Site.responder).breakpoints({ // exitBreakpoint
    		breakpoints: [ 1220, 980, 740, 500, 340 ]
    	});
    },
    
    responder: function(e, bp) {
    	if (e.type == "breakpoints" && e.namespace == "enter") { // only on enter
    		Site.homeFeature.respond();
    		Site.twitterTimeline.respond();
    		
    		var size = '';
    		if (bp > 340) {
    			size = "small";
    		}
    		if (bp > 500) {
    			size = "medium";
    		}
    		if (bp > 740) {
    			size = "large";
    		}
    		if (bp > 980) {
    			size = "xlarge";
    		}
    		
    		$("img.responder").each(function() {
    			var newSrc = $(this).attr("data-" + size);
    			if (newSrc) {
    				$(this).attr("src", newSrc);
    			}
    		});
    		
    		Site.$body.removeClass("small medium large xlarge").addClass(size);
    	}
    }
};


// HOME FEATURE
(function ($) {
    HomeFeature = function ($target) {
    	this.$feature = $target;
    	this.$viewport = this.$feature.find(".viewport");
    	this.$descriptions = this.$feature.find(".description");
    	this.$images = this.$feature.find(".image");
    	this.$credits = this.$feature.find(".credit");
    	this.$triggers = this.$feature.find(".triggers span");
    	this.$colorables = this.$feature.find(".background, .descriptions");
    	
    	this.animating = false;
    	this.index = 0;
    	this.total = this.$descriptions.length - 1;
    	
    	this.$triggers.on("click", $.proxy(this.advance, this));
    	
    	this.$feature.find(".content").show();
    	$.doTimeout(1000, this.respond());
    };
    HomeFeature.prototype = {
    	advance: function(e) {
    		if (!this.animating) {
    			var $target = $(e.currentTarget);
    			
    			if (!$target.hasClass("active")) {
    				var _this = this;
    				this.animating = true;
    				this.$viewport.addClass("animating");
    				
    				this.index = this.$triggers.index($target);
    				var $newDescription = this.$descriptions.eq(this.index);
    				
    				this.$colorables.css({ backgroundColor: $newDescription.attr("data-background") });
    				
    				this.$credits.filter(".active").removeClass("active");
    				this.$credits.eq(this.index).addClass("active");
    				
    				this.$viewport.css({ height: $newDescription.outerHeight(true) });
    				this.$descriptions.removeClass("active before after").each(function(i) {
    					if (i < _this.index) {
    						$(this).addClass("before");
    					} else if (i > _this.index) {
    						$(this).addClass("after");
    					}
    				});
    				$newDescription.addClass("active");
    				
    				var $oldImage = this.$images.filter(".active");
    				var $newImage = this.$images.eq(this.index);
    				
    				$oldImage.css({ zIndex: 3 })
    				$newImage.css({ display: "block", opacity: 0, zIndex: 4 }).animate({ opacity: 1 }, function() {
    					$oldImage.removeClass("active").css({ display: "none" });
    					$newImage.addClass("active");
    					
    					_this.animating = false;
    					_this.$viewport.removeClass("animating");
    				});
    				
    				this.$triggers.filter(".active").removeClass("active");
    				$target.addClass("active");
    			}
    		}
    	},
    	respond: function() {
    		this.$viewport.css({ height: this.$descriptions.eq(this.index).outerHeight(true) });
    	}
    };
}(jQuery));

(function ($) {
    TwitterTimeline = function ($target) {
    	this.loaded = false;
    	this.index = 0;
    	this.search = (typeof twitter_search != "undefined") ? twitter_search : "";
        this.timeline = (typeof twitter_timeline != "undefined") ? twitter_timeline : "";
    	
    	if (this.search) {
    		this.$section = $target;
    		if (this.$section.length) {
    			$.ajax({
    				type: "GET",
    				url: "www_root/ajax/load-twitter-search/",
    				data: { 
    					search: this.search,
                        sidebar: twitter_in_sidebar
    				},
    				success: $.proxy(this.onLoad, this)
    			});
    		}
    	} else if (this.timeline) {
            this.$section = $target;
            if (this.$section.length) {
                $.ajax({
                    type: "GET",
                    url: "www_root/ajax/load-twitter-timeline/",
                    data: { 
                        timeline: this.timeline,
                        sidebar: twitter_in_sidebar
                    },
                    success: $.proxy(this.onLoad, this)
                });
            }
        }
    };
    TwitterTimeline.prototype = {
    	onLoad: function(response) {
    		this.loaded = true;
    		
    		this.$timeline = this.$section.find(".timeline");
    		this.$timeline.html(response);
    		
    		this.$viewport = this.$section.find(".viewport");
    		this.$articles = this.$section.find("article");
    		
    		this.$section.removeClass("loading").on("click", ".trigger", $.proxy(this.advance, this));
    		this.respond();
    	},
    	advance: function(e) {
    		this.index = this.index + ($(e.currentTarget).hasClass("previous") ? -1 : 1);
    		
    		this.position();
    	},
    	respond: function() {
    		if (this.loaded) {
    			this.pageWidth = this.$viewport.outerWidth();
    			this.itemWidth = this.$articles.eq(0).outerWidth(true);
    			this.perPage = this.pageWidth / this.itemWidth;
    			this.pageCount = Math.ceil(this.$articles.length / this.perPage);
    			
    			this.position();
    		}
    	},
    	position: function() {
    		if (this.index > this.pageCount - 1) {
    			this.index = this.pageCount - 1;
    		}
    		if (this.index < 0) {
    			this.index = 0;
    		}
    		this.$timeline.css({ left: -(this.index * this.pageWidth) });
    	}
    };
}(jQuery));


$(document).ready(Site.init);