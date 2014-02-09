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
	var ids = ['description', 'content_comment_form', 'content_task_form', 'opinion'];

	for (var i = 0; i < ids.length; i++) {
		var textarea = jQuery("#" + ids[i]);
		if (textarea.length > 0) {
			textarea.before('<div id="toolbar'+ids[i]+'"></div>');
			if (textarea.parents("form").find("input[name=id]").length === 0) {
				textarea.before('<input type="hidden" name="id" value="'+bds.gup('id')+'" />');
			}
			initToolbar('toolbar'+ids[i], ids[i], toolbar);
		}
	}

	//show/hide opinion
	$opinion_row = jQuery("#bds_change_issue textarea[name=opinion]").parents("div[class=row]");
	
	if ($opinion_row.length > 0) {
		$opinion_row.hide();
		jQuery("#bds_change_issue select[name=state]").change(function() {
			switch (jQuery(this).val()) {
				case "0":
				case "1":
					$opinion_row.hide();
				break;

				case "2":
				case "3":
				case "4":
					$opinion_row.show();
				break;
			}
		});
	}

	jQuery(".bds_block")
		.each(function() {
			$h1 = jQuery(this).find("h1").html(
				function(index, oldhtml) {
					return '<span class="toggle">'+oldhtml+'</span>';
				});

			$h1.find(".toggle").css(
				{
					'background': 'url("lib/plugins/bds/images/collapsed.png") no-repeat scroll 4px 50% rgba(0, 0, 0, 0)',
					'border': 'medium none',
					'border-radius': '0.3em',
					'box-shadow': '0.1em 0.1em 0.3em 0 #BBBBBB',
					'color': '#222222',
					'padding': '0.3em 0.5em 0.3em 20px',
					'text-shadow': '0.1em 0.1em #FCFCFC',
					'cursor': 'pointer'
				});

			var show = function() {
					console.log(this);
					jQuery(this).siblings(".bds_block_content").show();
					jQuery(this).find(".toggle").css("background-image", "url(lib/plugins/bds/images/expanded.png)");
				};
			var hide = function() {
					jQuery(this).siblings(".bds_block_content").hide();
					jQuery(this).find(".toggle").css("background-image", "url(lib/plugins/bds/images/collapsed.png)");
				};

			var showed = "bds_history";
			var hash = window.location.hash.substring(1);
			if (hash === "comment_form") {
				var showed = "comment_form";
			} else if (hash === "task_form") {
				var showed = "task_form";
			} else if (hash === "bds_change_issue") {
				var showed = "bds_change_issue";
			}

			if (jQuery(this).attr("id") === showed) {
				jQuery(this).find(".toggle").css("background-image", "url(lib/plugins/bds/images/expanded.png)");
				jQuery(this).find("h1").toggle(hide, show);
			} else {
				jQuery(this).find(".bds_block_content").hide();
				jQuery(this).find("h1").toggle(show, hide);
			}
		});
});
