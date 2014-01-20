document.getElementById("bigtree_embeddable_form_container_{id}").innerHTML = '<iframe src="admin_root/ajax/auto-modules/embeddable-form/?hash={hash}" id="bigtree_embeddable_form_{id}" style="width: 100%; border: none;" scrolling="no"></iframe>';
var BigTreeEmbeddableForm{id} = {
	redirect: function(url) {
		document.location.href = url;
	},
	resize: function(height) {
		document.getElementById("bigtree_embeddable_form_{id}").style.height = (parseInt(height) + 260) + "px";
	},
	scrollToTop: function() {
		window.scrollTo(0,0);
	}
};