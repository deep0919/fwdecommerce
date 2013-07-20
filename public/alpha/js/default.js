/**
 * Alpha default javascript
 */
$(document).ready(function ()
{
	// Inputs with placeholders (IE).
	$('input,select,textarea').each(function ()
	{
		if ($(this).attr('placeholder'))
		{
			$(this).addClass('placeholder')
			.focus(function ()
			{
				if ($(this).val() == $(this).attr('placeholder'))
				{
					$(this).val('').removeClass('default');
				}
			})
			.blur(function ()
			{
				if ($(this).val() == '')
				{
					$(this).addClass('default').val($(this).attr('placeholder'));
				}
			})
			.trigger('blur');
		}
	});
	// Clear placeholders on submit.
	$('form').live('submit', function ()
	{
		$('.placeholder', this).each(function ()
		{
			if ($(this).val() == $(this).attr('placeholder'))
			{
				$(this).val('');
			}
		});
	});
});