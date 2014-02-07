jQuery(function() {
	var textarea = jQuery("#description");
	textarea.before('<div id="toolbar"></div>');
	initToolbar('toolbar', 'description', toolbar);
});
