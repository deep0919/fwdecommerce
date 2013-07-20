/**
 * Admin javascript.
 *
 * Copyright 2012 Forward.
 * Licensed under the Apache License v2.0
 * http://www.apache.org/licenses/LICENSE-2.0
 */
$('body').bind('ready', function ()
{	
	// Add hidden submit input to forms with submit button links.
	$('form .field span.error').eachUnique(function ()
	{
		if ($(this).text() != "")
		{
			$(this).closest('.field').addClass('error');
		}
	});
	
	// Numeric only inputs.
	$('input.numeric').eachUnique(function ()
	{
		$(this).numeric();
	});
	
	/* Make action buttons appear by default (might make it easier to figure out) */
	$('table').eachUnique(function ()
	{
		$('code.act:first', this).eachUnique(function ()
		{
			$(this).css({ visibility: 'visible' })
				.closest('table').one('hover', function ()
				{
					$('code.act', this).css({ visibility: '' });
				});
		});
	});
	
	// Make view textareas autosize.
	$('#view textarea').eachUnique(function ()
	{
		$(this).addClass('autosize').trigger('change');
	});
});
$(document).ready(function ()
{
	$('body').trigger('ready');
});
$('body').ajaxComplete(function ()
{
	$('body').trigger('ready');
});

/**
 * Live behaviors.
 */
if (!window.liveBehaviorsRun)
{
	// Confirm links.
	$('a.confirm, a.delete').live('click', function ()
	{
		if (!$(this).hasClass('view-link'))
		{
			if (!confirm('Really?'))
			{
				return false;
			}
		}
	});
	
	// Load a list.
	$('#list').bind('load', function (event, url, data, mode)
	{
		var dat = $(this).data();
		
		url = url || dat.url;
		data = data || dat.data;
		mode = mode || "replace";
		
		$list_results = $(this).find('.list-results');
		
		if (data != dat.data)
		{
			$list_results.opacity(0.6);
		}
		
		$(this).data('url', url);
		$(this).data('data', data);
		
		// Save window scroll point.
		var scroll_top = $(window).scrollTop();
		
		// Replace results (default).
		if (!mode || mode == "replace")
		{
			$list = $(this);
		}
		// Append results.
		else if (mode == "append")
		{
			$(this).data('appended', true);
			
			// Create a dummy list to load results and replace items.
			$results = $(this).find('ul');
			$list = $('<ul class="hidden list-results"></ul>');
			$results.after($list);
		}
		
		// Load!
		$list.load(url, data, function ()
		{
			$list_results.opaque();
			if (window.view_pages[window.view_index] && (list_item_id = window.view_pages[window.view_index].id))
			{
				$('#'+list_item_id).closest('li').addClass('active');
			}
			
			$(window).scrollTop(scroll_top);
			
			// Append loaded results?
			if (mode == "append")
			{
				$list.find('li').each(function ()
				{
					$(this).appendTo($results);
				});
			}
		});
	});
	
	// Load info.
	$('#info').bind('load', function (event, url, data)
	{
		var dat = $(this).data();
		
		url = url || dat.url;
		data = data || dat.data;
		
		$(this).data('url', url);
		$(this).data('data', data);
		
		$(this).find('.info-container').load(url, data);
	});
	$('#info').bind('loading', function (event, currently)
	{
		// Is info panel currently loading?
		if (currently !== false)
		{
			$('.info-container').hide();
			
			$loader = $('#info .loader');
			
			if ($loader.length == 0)
			{
				$loader = $('<div class="loader"></div>').appendTo('#info');
			}
			
			$loader.spin({
				speed: 3,
				length: 7,
				width: 3
			});
		}
		else
		{
			$('#info .loader').spin(false);
		}
	});
	
	// Scroll over view prevents outer window scroll.
	$('#view').bind('mousewheel', function (event, delta)
	{
		var el = $('.view-content', this).get(0);
		
		var top = el.scrollTop + $(el).height();
		var height =  el.scrollHeight;
		var delta = window.event.wheelDelta ? (window.event.wheelDelta/120) : (-window.event.detail/3);
		
		if (delta < 0 && (top + 55) > height)
		{
			return false;
		}
		if (delta > 0 && el.scrollTop == 0)
		{
			return false;
		}
		
		return true;
		
	});
	
	// Auto size textarea elements inside #view.
	$('#view').delegate('textarea.autosize', 'keydown keyup focus change', function ()
	{
		var $self = $(this);
		var $sizer = $self.next();
		
		// Create hidden element to use for size and positioning.
		if (!$sizer.hasClass('textarea-sizer'))
		{	
			$self.parent().css({ position: "relative" });
			
			var pos = $self.position();
			
			$sizer = $self.after('<div class="textarea-sizer"></div>')
				.next().css({ display: "none", position: "absolute", top: pos.top, left: pos.left, border: "1px solid #666" });
		}
		
		var val = $self.val() || $self.attr('placeholder');
		
		// Set value with newline.
		$sizer.text(val+"\n");
		
		// Using a timeout helps prevent input lag.
		setTimeout(function() {
			$self.height($sizer.height()+40);
			if (window.view_height_sized)
			{
				window.view_height = null;
				$(window).trigger('resize');
				window.view_height_sized = false;
			}
		}, 20);
	});
	
	
	// Load a view.
	window.view_pages = window.view_pages || [];
	window.view_index = window.view_index || 0;
	$('#view').bind('load', function (event, url, data)
	{
		// Remember view scroll position.
		window.view_scrolltop = $('.view-content').scrollTop();
		
		// Clear errors.
		$(this).find('.control-group.error').removeClass('error')
		$(this).find('.view-messages, label .error').hide();
		
		// Cache last view content?
		if (window.view_pages[window.view_index])
		{
			window.view_pages[window.view_index].content = $('.view-container', this).clone(true, true);
		}

		// Track/traverse view history.
		if (url == ':back')
		{
			window.view_index -= 1;
		}
		else if (url == ':next')
		{
			window.view_index += 1;
		}
		else if (url)
		{
			// Starts with "http" or "/".
			if (url.substring(0, 1) != "/")
			{
				url = url.substring(url.indexOf('/', 8));
			}
			var view_parts = url.split('?', 2);
			if (window.view_pages[window.view_index] && window.view_pages[window.view_index].base == view_parts[0])
			{
				window.view_pages[window.view_index].url = url;
				window.view_pages[window.view_index].data = data;
			}
			else
			{
				window.view_index++;
				window.view_pages[window.view_index] = {
					base: view_parts[0],
					url: url,
					data: data
				};
			}
		}
		else
		{
			window.view_pages[window.view_index].data = data;
			if (data.tab)
			{
				url = window.view_pages[window.view_index].url.replace(/tab=[^\&]+/, 'tab='+data.tab);
				window.view_pages[window.view_index].url = url;
			}
		}
		
		// Select next page.
		var next_page = window.view_pages[window.view_index] || {};
		
		// Set list item id?
		if (!next_page.id)
		{
			var parts = next_page.url.split('/');
			if (parts[0] == 'http:')
			{
				var context = parts[3];
				var item_id = parts[5];
			}
			else if (parts[0] == '')
			{
				var context = parts[1];
				var item_id = parts[3];
			}
			else
			{
				var context = parts[0];
				var item_id = parts[2];
			}
			if (parts.length >= 3)
			{
				var list_item_id = 'item_'+context+'_'+item_id;
				window.view_pages[window.view_index].id = list_item_id;
			}
		}
		
		// Is view different from current?
		if (next_page.base && $(this).data('base') != next_page.base)
		{
			var different = true;
			$('.view-container', this).animate({ left: "-530px" }, 300, function ()
			{
				$(this).hide();
			});
		}
		
		// Animate out?
		if (different || $(this).data('closed'))
		{
			$('#info').trigger('loading');
		}
		// Already out?
		else
		{
			$('#view').trigger('loading');
		}
		
		// Save to view data.
		$(this).data('url', next_page.url);
		$(this).data('base', next_page.base);
		$(this).data('params', next_page.data);
		
		// Load view by cache or ajax.
		if (url == ':back' || url == ':next')
		{
			// Get view by cache.
			$(this).empty().append(next_page.content);
			
			$(this).trigger('loaded', [true, true]);
		}
		else
		{
			// Get view by ajax.
			$(this).load(next_page.url, data, function ()
			{	
				$(this).trigger('loaded', [different]);
			});
		}
	});
	$('#view').bind('loading', function (event, currently)
	{
		// Is the view currently loading?
		if (currently !== false)
		{
			$('#view .view-content').addClass('loading');
				
			$loader = $('#view .loader');
			
			if ($loader.length == 0)
			{
				$loader = $('<div class="loader"></div>').appendTo('#view');
			}
			
			$loader.spin({
				speed: 3,
				length: 5,
				width: 2,
				radius: 6
			});
		}
		else
		{
			$(this).show().find('.view-content').removeClass('loading');
			$('#info .loader, #view .loader').spin(false);
		}
	});
	$('#view').bind('loaded', function (event, different, is_cache)
	{
		// Done!
		$(this).trigger('loading', [false]);
		
		window.view_height = null;
		$(window).trigger('resize');
		
		// Signal to elements sizing after load, might need to resize one more time.
		window.view_height_sized = true;
		
		// Animate view out.
		if (different || $(this).data('closed'))
		{
			$('.view-container', this).show().css({ left: "-530px" }).animate({ left: "-35px" }, 200);
			
			setTimeout("$('#view .view-container input.focus').focus()", 400);
		}
		else
		{
			// Same view, re-set scroll top.
			$('.view-content').opacity(0);
			setTimeout("$('.view-content').scrollTop(window.view_scrolltop).animate({opacity:1}, 100);", 50);
		}
		
		$(this).data('closed', false);
		
		if (!is_cache)
		{	
			// Default tab/pane?
			var default_pane =
				window.view_tabs[window.view_index]
				|| window.view_tabs[window.view_index-1];
			
			if (default_pane)
			{
				$('#view .view-content ul.nav li').each(function ()
				{
					if ($('a', this).data('pane') == default_pane)
					{
						$(this).addClass('active');
					}
					else
					{
						$(this).removeClass('active');
					}
				});
				if ($('#view .tabbable ul.nav li.active').length == 0)
				{
					$('#view .tabbable ul.nav li:first').addClass('active');
				}
			}
			
			// Trigger tab.
			$('#view .view-content ul.nav li.active a').trigger('click');
		
			// Trigger view message.
			if ($('#view .view-messages .alert-success').length)
			{
				$('<div class="loaded"><i class="icon-ok icon-white"></i></div>')
					.appendTo('#view').fadeIn();
				
				if (!$('#list').data('appended'))
				{
					$('#list').trigger('load');
				}
				
				setTimeout('$("#view > .loaded").fadeOut("normal", function () { $(this).remove(); })', 1500);
			}
			
			if ($('.info-container').data('view-reload'))
			{
				$('#info').trigger('load');
			}
		}
	});
	$('#view').bind('close', function ()
	{
		$('#list ul.list-results li').removeClass('active');
		$('.info-container').show();
		$('#info .loader').spin(false);
		$('.view-container', this).animate({ left: "-530px" }, 200, function ()
		{
			$('#view').hide().data('closed', true);
		});
	});
	
	// View form submit.
	$('#view form').live('submit', function ()
	{
		if (!$(this).hasClass('noview'))
		{
			$('#view').trigger('load', [$(this).attr('action'), $(this).serializeObject()]);
			return false;
		}
	});
	
	// View link or button.
	$('.view-link').live('click', function ()
	{
		if ($(this).hasClass('confirm'))
		{
			if (!confirm('Really?'))
			{
				return false;
			}
		}
		
		var href = this.href || $(this).data('href');
		
		if ($('#view').length)
		{
			var data = $(this).data('post') == true ? {post: true} : '';
			$('#view').trigger('load', [href, data]);
		}
		else
		{
			window.location.href = href;
		}
		
		return false;
	});
	
	// View back link or button.
	$('.view-back').live('click', function ()
	{
		$('#view').trigger('load', [':back']);
		return false;
	});
	// View back link or button.
	$('.view-next').live('click', function ()
	{
		$('#view').trigger('load', [':next']);
		return false;
	});
	
	// View close link or button.
	$('.view-cancel, .view-close').live('click', function ()
	{
		$('#view').trigger('close');
		return false;
	});
	
	// List item links.
	$('a.list-item').live('click', function ()
	{
		if ($('#view').length)
		{
			$(this).closest('ul').find('li').removeClass('active');
			$(this).closest('li').addClass('active');
			$('#view').trigger('load', [this.href]);
			return false;
		}
	});
	$('ul.list-results li').live('click', function (event)
	{
		if (!event.target.href)
		{
			$(this).find('a.list-item').trigger('click');
			return false;
		}
	});
	
	// View resize.
	$('#view').bind('resize', function ()
	{
		window.view_height = null;
		$('.view-content').css({ height: 'auto' });
		$(window).trigger('resize');
	});
	$(window).resize(function ()
	{
		// Size view container.
		var max_height = $('#list').height()-13;
		var height = $(window).height()-93;
		
		if (height > max_height)
		{
			height = max_height;
		}
		
		$('.view-container')
			.width($('#info').width()+25);
		
		// Size view content.
		if (window.view_height == null)
		{
			$('.view-content').height('');
			window.view_height = $('.view-content').height();
		}
		
		var height = window.view_height;
		var min_height = $(window).height() - 268;
		
		if (min_height < height)
		{
			min_height = min_height < 50 ? 50 : min_height;
			height = min_height;
		}
		
		if (height > $('.list-results').height() - 109)
		{
			height = $('.list-results').height() - 109;
		}
		
		
		$('.view-content').height(height);
	});
	
	// View tabs.
	window.view_tabs = window.view_tabs || {};
	$('#view .tabbable ul.nav a').live('click', function ()
	{
		var pane = $(this).data('pane');
		
		$all_tabs = $(this).closest('ul.nav').find('li');
		$tab = $(this).closest('li');
		$all_tabs.removeClass('active');
		$tab.addClass('active');
		
		$all_panes = $(this).closest('.tabbable').find('.tab-pane');
		$pane = $all_panes.filter('.'+(pane || 'default'));
		$all_panes.hide().find('input, select, textarea').attr('disabled', true);
		$pane.fadeIn('fast').find('input, select, textarea').attr('disabled', false);
		$pane.find('input.disabled, select.disabled, textarea.disabled').attr('disabled', true);
		
		if (pane && $pane.find('input[name="tab"]').length == 0)
		{
			$pane.prepend('<input type="hidden" name="tab" value="'+pane+'" />');
		}
		
		$all_controls = $(this).closest('.tabbable').find('.tab-controls').hide();
		$controls = $all_controls.filter('.'+(pane || 'default')).show();
		
		// Save default tab for this view.
		window.view_tabs[window.view_index] = pane || 'default';
		
		$('#view').trigger('resize');
		return false;
	});
	
	/**
	* Upload image field.
	* Keep invisible upload input under mouse for click.
	* This is the only way to trigger click on a file input.
	*/
	$('.field .image').live('mousemove', function (e)
	{
		$('input.upload', this).offset({
			top: e.pageY - 10,
			left: e.pageX - 10
		});
	});
	// On file input change (file select).
	$('.field .image input.upload').live('change', function ()
	{
		var field = $(this).attr('name');
		var filename = $(this).val().replace(/.*\\/, '');
		var $image = $(this).closest('.image');

		var $form = $image.closest('form');
		var action = $form.attr('action');
		var target = $form.attr('target');
		
		// Set form upload url and Iframe target.
		var upload_url = $image.data('upload');
		$form.attr('action', upload_url);
		$form.attr('target', 'image_upload');

		// Disable other upload inputs before submit.
		// This allows multiple uploads in one form.
		$(this).closest('.tab-pane').find('input.upload').attr('disabled', true);
		$(this).attr('disabled', false);

		// Look like it's loading.
		$image.addClass('loading').spin();
		$image.find('em.name').text('Uploading...').show();
		
		// Create Iframe for upload (one time).
		if ($form.find('iframe.image_upload').length == 0)
		{
			// Create Iframe for upload.
			$form.append('<iframe class="image_upload" name="image_upload" src="about:blank" style="display: none"></iframe>');
			
			// On Iframe load (upload complete).
			$form.find('iframe.image_upload').bind('load', function ()
			{
				// If field has hidden input.uploaded, set uploaded file path.
				if ($image.find('input.uploaded').length > 0)
				{
					// Get uploaded file name from Iframe and set to hidden image field.
					if (files = $.parseJSON($(this).contents().find('body').html()))
					{
						if (file = files[0].file)
						{
							$image.find('input.uploaded').val(file);
						
							parts = file.split('/public/');
							if (parts[1])
							{
								$image.find('img').attr('src', parts[1]);
							}
						}
					}
				}

				// Iframe done loading, set form back to normal.
				$image.find('em.name').text(filename).append('<a class="cancel" href="">&times;</a>');
				$image.removeClass('loading').spin(false);
				$form.removeClass('noview');
				$form.attr('target', target);
				$form.find('iframe.image_upload').remove();
				$form.attr('action', action);
			});
		}

		// Trigger form submit to iframe.
		$form.addClass('noview');
		$form.trigger('submit');

		// Un-disable other inputs.
		$(this).closest('.tab-pane').find('input.upload').attr('disabled', false);
	});
	$('.field .image a.cancel').live('click', function ()
	{
		$image = $(this).closest('.image');
		$image.find('input.uploaded').remove();
		$image.find('em.name').fadeOut();
		return false;
	});
	
	// Form errors.
	$('input,select,textarea').live('error', function (event, error_message)
	{
		$(this).closest('.field').addClass('error');
		
		if (error_message && error_message != "required")
		{
			$label = $(this).closest('.field').find('label');
			$label.find('span.error').remove();
			$label.append(' <span class="error">'+error_message+'</span>');
		}
	});
	
	// Clear field error.
	$('input,select,textarea').live('error_clear', function ()
	{
		$(this).closest('.field')
			.removeClass('error')
			.find('label .error').remove();
	});

	// Never again.
	window.liveBehaviorsRun = true;
}

/**
 * jQuery plugins.
 */
jQuery.fn.extend(
{	
	/**
	 * Allows a window to scroll to an element.
	 */
	scrollTo: function (speed, easing)
	{
		return this.each(function ()
		{
			var targetOffset = $(this).offset().top - 30;
			$('html,body').animate({scrollTop: targetOffset}, speed, easing);
		});
  	},
  	
  	/**
  	 * Checks and notes whether an element has behaviors already.
  	 */
  	bindUnique: function (event, callback)
  	{
  		//this.unbind(event, callback);
  		//return this[event](callback);

  		return this.each(function ()
  		{
  			var key = new String(callback).replace(/[^a]/, '');
  			this.__uniqueEvents = this.__uniqueEvents || [];
  			if (jQuery.inArray(key, this.__uniqueEvents) == -1)
  			{
  				$(this)[event](callback);
  				this.__uniqueEvents[this.__uniqueEvents.length] = key;
  			}
  		});
  	},
  	
  	/**
  	 * Apply a method to elements only once
  	 */
  	eachUnique: function (callback)
  	{
  		return this.each(function ()
  		{
  			var key = new String(callback).replace(/[^a]/, '');
  			this.__uniqueCallbacks = this.__uniqueCallbacks || [];
  			if (jQuery.inArray(key, this.__uniqueCallbacks) == -1)
  			{
  				callback.call(this);
  				this.__uniqueCallbacks[this.__uniqueCallbacks.length] = key;
  			}
  		});
  	},

	/**
	 * Set cursor position of input.
	 */
	setCursorPosition: function (pos)
	{
		this.each(function (index, elem)
		{
			var pos = pos || $(elem).text().length;
			if (elem.setSelectionRange)
			{
				elem.setSelectionRange(pos, pos);
			}
			else if (elem.createTextRange)
			{
				var range = elem.createTextRange();
				range.collapse(true);
				range.moveEnd('character', pos);
				range.moveStart('character', pos);
				range.select();
			}
		});
		return this;
	}
});

/**
 * Serialize object.
 */
$.fn.serializeObject = function()
{
    var o = {};
    var a = this.serializeArray();
    $.each(a, function() {
        if (o[this.name] !== undefined) {
            if (!o[this.name].push) {
                o[this.name] = [o[this.name]];
            }
            o[this.name].push(this.value || '');
        } else {
            o[this.name] = this.value || '';
        }
    });
    return o;
};

/**
 * Cookie
 */
jQuery.cookie = function(name, value, options) {
    if (typeof value != 'undefined') { // name and value given, set cookie
        options = options || {};
        if (value === null) {
            value = '';
            options.expires = -1;
        }
        var expires = '';
        if (options.expires && (typeof options.expires == 'number' || options.expires.toUTCString)) {
            var date;
            if (typeof options.expires == 'number') {
                date = new Date();
                date.setTime(date.getTime() + (options.expires * 24 * 60 * 60 * 1000));
            } else {
                date = options.expires;
            }
            expires = '; expires=' + date.toUTCString(); // use expires attribute, max-age is not supported by IE
        }
        // CAUTION: Needed to parenthesize options.path and options.domain
        // in the following expressions, otherwise they evaluate to undefined
        // in the packed version for some reason...
        var path = options.path ? '; path=' + (options.path) : '';
        var domain = options.domain ? '; domain=' + (options.domain) : '';
        var secure = options.secure ? '; secure' : '';
        document.cookie = [name, '=', encodeURIComponent(value), expires, path, domain, secure].join('');
    } else { // only name given, get cookie
        var cookieValue = null;
        if (document.cookie && document.cookie != '') {
            var cookies = document.cookie.split(';');
            for (var i = 0; i < cookies.length; i++) {
                var cookie = jQuery.trim(cookies[i]);
                // Does this cookie string begin with the name we want?
                if (cookie.substring(0, name.length + 1) == (name + '=')) {
                    cookieValue = decodeURIComponent(cookie.substring(name.length + 1));
                    break;
                }
            }
        }
        return cookieValue;
    }
};

/*
 *
 * Copyright (c) 2006-2011 Sam Collett (http://www.texotela.co.uk)
 * Dual licensed under the MIT (http://www.opensource.org/licenses/mit-license.php)
 * and GPL (http://www.opensource.org/licenses/gpl-license.php) licenses.
 * 
 * Version 1.3.1
 * Demo: http://www.texotela.co.uk/code/jquery/numeric/
 *
 */
(function($) {
	/*
	 * Allows only valid characters to be entered into input boxes.
	 * Note: fixes value when pasting via Ctrl+V, but not when using the mouse to paste
	  *      side-effect: Ctrl+A does not work, though you can still use the mouse to select (or double-click to select all)
	 *
	 * @name     numeric
	 * @param    config      { decimal : "." , negative : true }
	 * @param    callback     A function that runs if the number is not valid (fires onblur)
	 * @author   Sam Collett (http://www.texotela.co.uk)
	 * @example  $(".numeric").numeric();
	 * @example  $(".numeric").numeric(","); // use , as separater
	 * @example  $(".numeric").numeric({ decimal : "," }); // use , as separator
	 * @example  $(".numeric").numeric({ negative : false }); // do not allow negative values
	 * @example  $(".numeric").numeric(null, callback); // use default values, pass on the 'callback' function
	 *
	 */
	$.fn.numeric = function(config, callback)
	{
		if(typeof config === 'boolean')
		{
			config = { decimal: config };
		}
		config = config || {};
		// if config.negative undefined, set to true (default is to allow negative numbers)
		if(typeof config.negative == "undefined") config.negative = true;
		// set decimal point
		var decimal = (config.decimal === false) ? "" : config.decimal || ".";
		// allow negatives
		var negative = (config.negative === true) ? true : false;
		// callback function
		var callback = typeof callback == "function" ? callback : function(){};
		// set data and methods
		return this.data("numeric.decimal", decimal).data("numeric.negative", negative).data("numeric.callback", callback).keypress($.fn.numeric.keypress).keyup($.fn.numeric.keyup).blur($.fn.numeric.blur);
	}
	
	$.fn.numeric.keypress = function(e)
	{
		// get decimal character and determine if negatives are allowed
		var decimal = $.data(this, "numeric.decimal");
		var negative = $.data(this, "numeric.negative");
		// get the key that was pressed
		var key = e.charCode ? e.charCode : e.keyCode ? e.keyCode : 0;
		// allow enter/return key (only when in an input box)
		if(key == 13 && this.nodeName.toLowerCase() == "input")
		{
			return true;
		}
		else if(key == 13)
		{
			return false;
		}
		var allow = false;
		// allow Ctrl+A
		if((e.ctrlKey && key == 97 /* firefox */) || (e.ctrlKey && key == 65) /* opera */) return true;
		// allow Ctrl+X (cut)
		if((e.ctrlKey && key == 120 /* firefox */) || (e.ctrlKey && key == 88) /* opera */) return true;
		// allow Ctrl+C (copy)
		if((e.ctrlKey && key == 99 /* firefox */) || (e.ctrlKey && key == 67) /* opera */) return true;
		// allow Ctrl+Z (undo)
		if((e.ctrlKey && key == 122 /* firefox */) || (e.ctrlKey && key == 90) /* opera */) return true;
		// allow or deny Ctrl+V (paste), Shift+Ins
		if((e.ctrlKey && key == 118 /* firefox */) || (e.ctrlKey && key == 86) /* opera */
		|| (e.shiftKey && key == 45)) return true;
		// if a number was not pressed
		if(key < 48 || key > 57)
		{
		  var value = $(this).val();
			/* '-' only allowed at start and if negative numbers allowed */
			if(value.indexOf("-") != 0 && negative && key == 45 && (value.length == 0 || ($.fn.getSelectionStart(this)) == 0)) return true;
			/* only one decimal separator allowed */
			if(decimal && key == decimal.charCodeAt(0) && value.indexOf(decimal) != -1)
			{
				allow = false;
			}
			// check for other keys that have special purposes
			if(
				key != 8 /* backspace */ &&
				key != 9 /* tab */ &&
				key != 13 /* enter */ &&
				key != 35 /* end */ &&
				key != 36 /* home */ &&
				key != 37 /* left */ &&
				key != 39 /* right */ &&
				key != 46 /* del */
			)
			{
				allow = false;
			}
			else
			{
				// for detecting special keys (listed above)
				// IE does not support 'charCode' and ignores them in keypress anyway
				if(typeof e.charCode != "undefined")
				{
					// special keys have 'keyCode' and 'which' the same (e.g. backspace)
					if(e.keyCode == e.which && e.which != 0)
					{
						allow = true;
						// . and delete share the same code, don't allow . (will be set to true later if it is the decimal point)
						if(e.which == 46) allow = false;
					}
					// or keyCode != 0 and 'charCode'/'which' = 0
					else if(e.keyCode != 0 && e.charCode == 0 && e.which == 0)
					{
						allow = true;
					}
				}
			}
			// if key pressed is the decimal and it is not already in the field
			if(decimal && key == decimal.charCodeAt(0))
			{
				if(value.indexOf(decimal) == -1)
				{
					allow = true;
				}
				else
				{
					allow = false;
				}
			}
		}
		else
		{
			allow = true;
		}
		return allow;
	}
	
	$.fn.numeric.keyup = function(e)
	{
		var val = $(this).value;
		if(val && val.length > 0)
		{
			// get carat (cursor) position
			var carat = $.fn.getSelectionStart(this);
			// get decimal character and determine if negatives are allowed
			var decimal = $.data(this, "numeric.decimal");
			var negative = $.data(this, "numeric.negative");
	
			// prepend a 0 if necessary
			if(decimal != "")
			{
				// find decimal point
				var dot = val.indexOf(decimal);
				// if dot at start, add 0 before
				if(dot == 0)
				{
					this.value = "0" + val;
				}
				// if dot at position 1, check if there is a - symbol before it
				if(dot == 1 && val.charAt(0) == "-")
				{
					this.value = "-0" + val.substring(1);
				}
				val = this.value;
			}
	
			// if pasted in, only allow the following characters
			var validChars = [0,1,2,3,4,5,6,7,8,9,'-',decimal];
			// get length of the value (to loop through)
			var length = val.length;
			// loop backwards (to prevent going out of bounds)
			for(var i = length - 1; i >= 0; i--)
			{
				var ch = val.charAt(i);
				// remove '-' if it is in the wrong place
				if(i != 0 && ch == "-")
				{
					val = val.substring(0, i) + val.substring(i + 1);
				}
				// remove character if it is at the start, a '-' and negatives aren't allowed
				else if(i == 0 && !negative && ch == "-")
				{
					val = val.substring(1);
				}
				var validChar = false;
				// loop through validChars
				for(var j = 0; j < validChars.length; j++)
				{
					// if it is valid, break out the loop
					if(ch == validChars[j])
					{
						validChar = true;
						break;
					}
				}
				// if not a valid character, or a space, remove
				if(!validChar || ch == " ")
				{
					val = val.substring(0, i) + val.substring(i + 1);
				}
			}
			// remove extra decimal characters
			var firstDecimal = val.indexOf(decimal);
			if(firstDecimal > 0)
			{
				for(var i = length - 1; i > firstDecimal; i--)
				{
					var ch = val.charAt(i);
					// remove decimal character
					if(ch == decimal)
					{
						val = val.substring(0, i) + val.substring(i + 1);
					}
				}
			}
			// set the value and prevent the cursor moving to the end
			this.value = val;
			$.fn.setSelection(this, carat);
		}
	}
	
	$.fn.numeric.blur = function()
	{
		var decimal = $.data(this, "numeric.decimal");
		var callback = $.data(this, "numeric.callback");
		var val = this.value;
		if(val != "")
		{
			var re = new RegExp("^\\d+$|\\d*" + decimal + "\\d+");
			if(!re.exec(val))
			{
				callback.apply(this);
			}
		}
	}
	
	$.fn.removeNumeric = function()
	{
		return this.data("numeric.decimal", null).data("numeric.negative", null).data("numeric.callback", null).unbind("keypress", $.fn.numeric.keypress).unbind("blur", $.fn.numeric.blur);
	}
	
	// Based on code from http://javascript.nwbox.com/cursor_position/ (Diego Perini <dperini@nwbox.com>)
	$.fn.getSelectionStart = function(o)
	{
		if (o.createTextRange)
		{
			var r = document.selection.createRange().duplicate();
			r.moveEnd('character', o.value.length);
			if (r.text == '') return o.value.length;
			return o.value.lastIndexOf(r.text);
		} else return o.selectionStart;
	}
	
	// set the selection, o is the object (input), p is the position ([start, end] or just start)
	$.fn.setSelection = function(o, p)
	{
		// if p is number, start and end are the same
		if(typeof p == "number") p = [p, p];
		// only set if p is an array of length 2
		if(p && p.constructor == Array && p.length == 2)
		{
			if (o.createTextRange)
			{
				var r = o.createTextRange();
				r.collapse(true);
				r.moveStart('character', p[0]);
				r.moveEnd('character', p[1]);
				r.select();
			}
			else if(o.setSelectionRange)
			{
				o.focus();
				o.setSelectionRange(p[0], p[1]);
			}
		}
	}

})(jQuery);

/**
 * Spin.js
 * // fgnass.github.com/spin.js#v1.2.3
 */
(function(a,b,c){function n(a){var b={x:a.offsetLeft,y:a.offsetTop};while(a=a.offsetParent)b.x+=a.offsetLeft,b.y+=a.offsetTop;return b}function m(a){for(var b=1;b<arguments.length;b++){var d=arguments[b];for(var e in d)a[e]===c&&(a[e]=d[e])}return a}function l(a,b){for(var c in b)a.style[k(a,c)||c]=b[c];return a}function k(a,b){var e=a.style,f,g;if(e[b]!==c)return b;b=b.charAt(0).toUpperCase()+b.slice(1);for(g=0;g<d.length;g++){f=d[g]+b;if(e[f]!==c)return f}}function j(a,b,c,d){var g=["opacity",b,~~(a*100),c,d].join("-"),h=.01+c/d*100,j=Math.max(1-(1-a)/b*(100-h),a),k=f.substring(0,f.indexOf("Animation")).toLowerCase(),l=k&&"-"+k+"-"||"";e[g]||(i.insertRule("@"+l+"keyframes "+g+"{"+"0%{opacity:"+j+"}"+h+"%{opacity:"+a+"}"+(h+.01)+"%{opacity:1}"+(h+b)%100+"%{opacity:"+a+"}"+"100%{opacity:"+j+"}"+"}",0),e[g]=1);return g}function h(a,b,c){c&&!c.parentNode&&h(a,c),a.insertBefore(b,c||null);return a}function g(a,c){var d=b.createElement(a||"div"),e;for(e in c)d[e]=c[e];return d}var d=["webkit","Moz","ms","O"],e={},f,i=function(){var a=g("style");h(b.getElementsByTagName("head")[0],a);return a.sheet||a.styleSheet}(),o=function r(a){if(!this.spin)return new r(a);this.opts=m(a||{},r.defaults,p)},p=o.defaults={lines:12,length:7,width:5,radius:10,color:"#000",speed:1,trail:100,opacity:.25,fps:20},q=o.prototype={spin:function(a){this.stop();var b=this,c=b.el=l(g(),{position:"relative"}),d,e;a&&(e=n(h(a,c,a.firstChild)),d=n(c),l(c,{left:(a.offsetWidth>>1)-d.x+e.x+"px",top:(a.offsetHeight>>1)-d.y+e.y+"px"})),c.setAttribute("aria-role","progressbar"),b.lines(c,b.opts);if(!f){var i=b.opts,j=0,k=i.fps,m=k/i.speed,o=(1-i.opacity)/(m*i.trail/100),p=m/i.lines;(function q(){j++;for(var a=i.lines;a;a--){var d=Math.max(1-(j+a*p)%m*o,i.opacity);b.opacity(c,i.lines-a,d,i)}b.timeout=b.el&&setTimeout(q,~~(1e3/k))})()}return b},stop:function(){var a=this.el;a&&(clearTimeout(this.timeout),a.parentNode&&a.parentNode.removeChild(a),this.el=c);return this}};q.lines=function(a,b){function e(a,d){return l(g(),{position:"absolute",width:b.length+b.width+"px",height:b.width+"px",background:a,boxShadow:d,transformOrigin:"left",transform:"rotate("+~~(360/b.lines*c)+"deg) translate("+b.radius+"px"+",0)",borderRadius:(b.width>>1)+"px"})}var c=0,d;for(;c<b.lines;c++)d=l(g(),{position:"absolute",top:1+~(b.width/2)+"px",transform:b.hwaccel?"translate3d(0,0,0)":"",opacity:b.opacity,animation:f&&j(b.opacity,b.trail,c,b.lines)+" "+1/b.speed+"s linear infinite"}),b.shadow&&h(d,l(e("#000","0 0 4px #000"),{top:"2px"})),h(a,h(d,e(b.color,"0 0 1px rgba(0,0,0,.1)")));return a},q.opacity=function(a,b,c){b<a.childNodes.length&&(a.childNodes[b].style.opacity=c)},function(){var a=l(g("group"),{behavior:"url(#default#VML)"}),b;if(!k(a,"transform")&&a.adj){for(b=4;b--;)i.addRule(["group","roundrect","fill","stroke"][b],"behavior:url(#default#VML)");q.lines=function(a,b){function k(a,d,i){h(f,h(l(e(),{rotation:360/b.lines*a+"deg",left:~~d}),h(l(g("roundrect",{arcsize:1}),{width:c,height:b.width,left:b.radius,top:-b.width>>1,filter:i}),g("fill",{color:b.color,opacity:b.opacity}),g("stroke",{opacity:0}))))}function e(){return l(g("group",{coordsize:d+" "+d,coordorigin:-c+" "+ -c}),{width:d,height:d})}var c=b.length+b.width,d=2*c,f=e(),i=~(b.length+b.radius+b.width)+"px",j;if(b.shadow)for(j=1;j<=b.lines;j++)k(j,-2,"progid:DXImageTransform.Microsoft.Blur(pixelradius=2,makeshadow=1,shadowopacity=.3)");for(j=1;j<=b.lines;j++)k(j);return h(l(a,{margin:i+" 0 0 "+i,zoom:1}),f)},q.opacity=function(a,b,c,d){var e=a.firstChild;d=d.shadow&&d.lines||0,e&&b+d<e.childNodes.length&&(e=e.childNodes[b+d],e=e&&e.firstChild,e=e&&e.firstChild,e&&(e.opacity=c))}}else f=k(a,"animation")}(),a.Spinner=o})(window,document);

/*

You can now create a spinner using any of the variants below:

$("#el").spin(); // Produces default Spinner using the text color of #el.
$("#el").spin("small"); // Produces a 'small' Spinner using the text color of #el.
$("#el").spin("large", "white"); // Produces a 'large' Spinner in white (or any valid CSS color).
$("#el").spin({ ... }); // Produces a Spinner using your custom settings.

$("#el").spin(false); // Kills the spinner.

*/
(function($) {
	$.fn.spin = function(opts, color) {
		var presets = {
			"default": { speed: 3, length: 7, width: 3 },
			"tiny": { lines: 8, length: 2, width: 2, radius: 3 },
			"small": { lines: 8, length: 4, width: 3, radius: 5 },
			"large": { lines: 10, length: 8, width: 4, radius: 8 }
		};
		if (Spinner) {
			return this.each(function() {
				var $this = $(this),
					data = $this.data();

				if (data.spinner) {
					data.spinner.stop();
					delete data.spinner;
				}
				if (opts !== false) {
					if (typeof opts === "string") {
						if (opts in presets) {
							opts = presets[opts];
						} else {
							opts = presets.default;
						}
						if (color) {
							opts.color = color;
						}
					} else {
						opts = presets.default;
					}
					data.spinner = new Spinner($.extend({color: $this.css('color')}, opts)).spin(this);
				}
			});
		} else {
			throw "Spinner class not available.";
		}
	};
})(jQuery);

// Opaque.
(function($) {
	$.fn.opaque = function ()
	{
		return this.css({ opacity: '' });
	};
	$.fn.opacity = function (value)
	{
		return this.css({ opacity: value });
	};
})(jQuery);

// Mousewheel delta.
(function($) {
	var types = ['DOMMouseScroll', 'mousewheel'];
	$.event.special.mousewheel = {
	    setup: function() {
	        if ( this.addEventListener ) {
	            for ( var i=types.length; i; ) {
	                this.addEventListener( types[--i], handler, false );
	            }
	        } else {
	            this.onmousewheel = handler;
	        }
	    },
	    teardown: function() {
	        if ( this.removeEventListener ) {
	            for ( var i=types.length; i; ) {
	                this.removeEventListener( types[--i], handler, false );
	            }
	        } else {
	            this.onmousewheel = null;
	        }
	    }
	};
	$.fn.extend({
	    mousewheel: function(fn) {
	        return fn ? this.bind("mousewheel", fn) : this.trigger("mousewheel");
	    },
	
	    unmousewheel: function(fn) {
	        return this.unbind("mousewheel", fn);
	    }
	});
	function handler(event) {
	    var orgEvent = event || window.event, args = [].slice.call( arguments, 1 ), delta = 0, returnValue = true, deltaX = 0, deltaY = 0;
	    event = $.event.fix(orgEvent);
	    event.type = "mousewheel";
	    // Old school scrollwheel delta
	    if ( event.wheelDelta ) { delta = event.wheelDelta/120; }
	    if ( event.detail     ) { delta = -event.detail/3; }
	    // New school multidimensional scroll (touchpads) deltas
	    deltaY = delta;
	    // Gecko
	    if ( orgEvent.axis !== undefined && orgEvent.axis === orgEvent.HORIZONTAL_AXIS ) {
	        deltaY = 0;
	        deltaX = -1*delta;
	    }
	    // Webkit
	    if ( orgEvent.wheelDeltaY !== undefined ) { deltaY = orgEvent.wheelDeltaY/120; }
	    if ( orgEvent.wheelDeltaX !== undefined ) { deltaX = -1*orgEvent.wheelDeltaX/120; }
	    // Add event and delta to the front of the arguments
	    args.unshift(event, delta, deltaX, deltaY);
	    return $.event.handle.apply(this, args);
	}
})(jQuery);