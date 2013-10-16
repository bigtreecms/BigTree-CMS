document.getElementById('bigtree_embeddable_form_container').innerHTML = '<iframe src="admin_root/ajax/auto-modules/embeddable-form/?hash={hash}" id="bigtree_embeddable_form" style="width: 100%; border: none;" scrolling="no"></iframe>';
function BigTreeEmbedFormResize(height) {
	document.getElementById("bigtree_embeddable_form").style.height = (parseInt(height) + 260) + "px";
}
function BigTreeFormRedirect(url) {
	document.location.href = url;
}