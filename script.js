bds = {};

bds.gup = function (name) {
    name = name.replace(/[\[]/, "\\\[").replace(/[\]]/, "\\\]");
    var regexS = "[\\?&]" + name + "=([^&#]*)";
    var regex = new RegExp(regexS);
    var results = regex.exec(window.location.href);
    if (results == null)
        return "";
    else
        return results[1];
};

jQuery(function() {
	var ids = ['description', 'content_comment_form', 'content_task_form'];

	for (var i = 0; i < ids.length; i++) {
		var textarea = jQuery("#" + ids[i]);
		if (textarea.length > 0) {
			textarea.before('<div id="toolbar'+ids[i]+'"></div>');
			textarea.before('<input type="hidden" name="id" value="'+bds.gup('id')+'" />');
			initToolbar('toolbar'+ids[i], ids[i], toolbar);
		}
	}
});
