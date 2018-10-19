/* ==================================================================
   Site
================================================================== */

	$(document).ready(function() {
		$(".js-background").background({ theme: "" });
		$(".js-carousel").carousel({ theme: "" });
		$(".js-navigation").navigation({ theme: "" });

		$("body").find(".typography table").wrap('<div class="table_wrapper"><div class="table_wrapper_inner"></div></div>');
		tableOverflow();

		$(window).on("resize", onResize);

		setInterval("demoTimer()", 1000);
	});

	function onResize() {
		tableOverflow();
	}

	function tableOverflow() {
		$(".table_wrapper").each(function() {
			$(this).removeClass("table_wrapper_overflow");
			if ($(this).prop("scrollWidth") > $(this).width() + 1) {
				$(this).addClass("table_wrapper_overflow");
			}
			else {
				$(this).removeClass("table_wrapper_overflow");
			}
		});
	}

	function demoTimer() {
		var d = new Date();
		minutes = 59 - d.getMinutes();
		seconds = 59 - d.getSeconds();

		$("#reset_minutes").html(minutes);
		$("#reset_seconds").html((seconds < 10) ? "0" + seconds : seconds);

		if (minutes == 0 && seconds == 1) {
			setTimeout("window.reload();", 5000);
		}
	}