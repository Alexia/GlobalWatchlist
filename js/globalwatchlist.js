$(document).ready(function() {
	$('#inline_site_search').focus(function() {
		$(this).val('');
		$('#inline_site_search').keyup();
	});

	$('#inline_site_search').keyup(function() {
		var search = $(this).val().toLowerCase();
		if (search == '') {
			$("label.hideable").css({"background": 'none'});
			$("label.hideable").show();
			return;
		}
		$("label.hideable").css({"background": 'none'});
		$("label.hideable").hide();
		$("label.hideable[data-name*='"+search+"']").css({"background-color": 'yellow'});
		$("label.hideable[data-name*='"+search+"']").show();
	});

	$('legend .site_expand_collapse').click(function() {
		var fieldset = $(this).parents('fieldset');
		if ($(fieldset).hasClass('collasped')) {
			$(fieldset).removeClass('collasped');
			$('span', this).text('-');
		} else {
			$(fieldset).addClass('collasped');
			$('span', this).text('+');
		}
	});

	$('#expand_all').click(function() {
		$('.mw-changeslist-site fieldset').removeClass('collasped');
	});

	$('#collapse_all').click(function() {
		$('.mw-changeslist-site fieldset').addClass('collasped');
	});

    $('#checkAll').click(function() {
        $('input:checkbox').attr('checked', 'checked');
    });

    $('#uncheckAll').click(function() {
        $('input:checkbox').removeAttr('checked');
    });
});