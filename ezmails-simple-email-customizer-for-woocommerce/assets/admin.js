(function ($) {
	'use strict';

	$(function () {
		var $wrap = $('.ezmails-wrap');
		var last = null; // last focused insertable target: {el} or {editor}

		$('.ezmails-color').wpColorPicker();

		/* Tabs */
		var $tabs = $('.ezmails-tabs a');
		var $panels = $('.ezmails-panel');

		function showTab(id) {
			if (!$panels.filter('#ezmails-panel-' + id).length) {
				id = 'design';
			}
			$tabs.removeClass('nav-tab-active').filter('[data-tab="' + id + '"]').addClass('nav-tab-active');
			$panels.hide().filter('#ezmails-panel-' + id).show();
			$wrap.attr('data-tab', id);
			try { sessionStorage.setItem('ezmailsTab', id); } catch (e) {}
		}

		$tabs.on('click', function (e) {
			e.preventDefault();
			showTab($(this).data('tab'));
		});

		var initial = window.location.hash.replace('#', '');
		if (!initial) {
			try { initial = sessionStorage.getItem('ezmailsTab') || ''; } catch (e) {}
		}
		showTab(initial || 'design');

		/* Logo picker */
		var frame;
		$('.ezmails-media').on('click', function (e) {
			e.preventDefault();
			var $field = $(this).closest('.ezmails-logo-field');
			if (!frame) {
				frame = wp.media({
					title: ezmailsAdmin.i18n.chooseImg,
					button: { text: ezmailsAdmin.i18n.useImg },
					library: { type: 'image' },
					multiple: false
				});
			}
			frame.off('select').on('select', function () {
				var att = frame.state().get('selection').first().toJSON();
				$field.find('.ezmails-logo-url').val(att.url);
				$field.find('.ezmails-logo-preview').html($('<img>', { src: att.url, alt: '' }));
			});
			frame.open();
		});
		$('.ezmails-media-clear').on('click', function () {
			var $field = $(this).closest('.ezmails-logo-field');
			$field.find('.ezmails-logo-url').val('');
			$field.find('.ezmails-logo-preview').empty();
		});

		/* Shortcode insertion */
		$(document).on('focusin', '.ezmails-insertable', function () {
			last = { el: this };
		});
		$(document).on('tinymce-editor-init', function (e, ed) {
			if (ed.id === 'ezmails_footer_text') {
				ed.on('focus', function () { last = { editor: ed }; });
			}
		});

		function insert(code) {
			if (last && last.editor) {
				last.editor.execCommand('mceInsertContent', false, code);
				return true;
			}
			if (last && last.el && document.body.contains(last.el)) {
				var el = last.el, s = el.selectionStart, en = el.selectionEnd, v = el.value;
				el.value = v.slice(0, s) + code + v.slice(en);
				el.selectionStart = el.selectionEnd = s + code.length;
				el.focus();
				$(el).trigger('input');
				return true;
			}
			return false;
		}

		function toast(msg) {
			var $t = $('<div class="ezmails-toast"/>').text(msg).appendTo('body');
			setTimeout(function () { $t.fadeOut(300, function () { $t.remove(); }); }, 2200);
		}

		$('.ezmails-chip').on('mousedown', function (e) { e.preventDefault(); }).on('click', function () {
			var code = $(this).data('code');
			if (!insert(code)) {
				if (navigator.clipboard) { navigator.clipboard.writeText(code); }
				toast(ezmailsAdmin.i18n.copied);
			}
		});

		/* Body mode: toggle relevant fields */
		$('.ezmails-mode-select').on('change', function () {
			$(this).closest('.ezmails-email').attr('data-mode', this.value);
		});

		/* Design presets */
		var presets = {
			classic: { page_bg: '#f4f4f4', body_bg: '#ffffff', text_color: '#444444', accent: '#7f54b3', border_color: '#e5e5e5', header_bg: '#ffffff', header_color: '#333333', header_align: 'center', heading_bg: '#7f54b3', heading_color: '#ffffff', heading_style: 'bar', heading_font: 'inherit', heading_transform: 'none', heading_weight: '600', font: 'helvetica', footer_bg: '#2b2b2b', footer_color: '#cccccc', button_bg: '#7f54b3', button_color: '#ffffff', radius: 6, button_radius: 4 },
			dark: { page_bg: '#111111', body_bg: '#1e1e1e', text_color: '#dddddd', accent: '#4cc2ff', border_color: '#3a3a3a', header_bg: '#1e1e1e', header_color: '#ffffff', header_align: 'center', heading_bg: '#2a2a2a', heading_color: '#ffffff', heading_style: 'bar', heading_font: 'inherit', heading_transform: 'none', heading_weight: '600', font: 'system', footer_bg: '#111111', footer_color: '#999999', button_bg: '#4cc2ff', button_color: '#111111', radius: 8, button_radius: 6 },
			minimal: { page_bg: '#ffffff', body_bg: '#ffffff', text_color: '#222222', accent: '#000000', border_color: '#dddddd', header_bg: '#ffffff', header_color: '#000000', header_align: 'left', heading_bg: '#ffffff', heading_color: '#000000', heading_style: 'inline', heading_font: 'georgia', heading_transform: 'none', heading_weight: '400', font: 'georgia', footer_bg: '#ffffff', footer_color: '#777777', button_bg: '#000000', button_color: '#ffffff', radius: 0, button_radius: 0 }
		};

		$('.ezmails-preset').on('click', function () {
			var set = presets[$(this).data('preset')];
			if (!set) { return; }
			$.each(set, function (key, val) {
				var $el = $('[name="ezmails[general][' + key + ']"]');
				if (!$el.length) { return; }
				if ($el.hasClass('wp-color-picker')) {
					$el.wpColorPicker('color', val);
				} else {
					$el.val(val);
				}
			});
		});

		/* Preview & test */
		function params(extra) {
			return $.extend({
				_wpnonce: ezmailsAdmin.nonce,
				email_id: $('#ezmails-prev-email').val(),
				order_id: $('#ezmails-prev-order').val()
			}, extra);
		}

		function loadPreview() {
			var url = ezmailsAdmin.ajax + '?' + $.param(params({ action: 'ezmails_preview' }));
			$('#ezmails-prev-frame').attr('src', url).addClass('is-loaded');
		}

		$('#ezmails-prev-load').on('click', loadPreview);

		$('.ezmails-goto-preview').on('click', function (e) {
			e.preventDefault();
			$('#ezmails-prev-email').val($(this).data('email'));
			showTab('preview');
			loadPreview();
		});

		$('#ezmails-prev-frame').on('load', function () {
			try {
				var doc = this.contentDocument;
				if (doc && doc.body) { this.style.height = Math.max(500, doc.body.scrollHeight + 20) + 'px'; }
			} catch (e) {}
		});

		$('#ezmails-test-send').on('click', function () {
			var $msg = $('#ezmails-test-msg').removeClass('is-error is-ok').text(ezmailsAdmin.i18n.sending);
			$.post(ezmailsAdmin.ajax, params({ action: 'ezmails_send_test', to: $('#ezmails-test-to').val() }))
				.done(function (res) {
					$msg.addClass(res.success ? 'is-ok' : 'is-error').text(res.data);
				})
				.fail(function () {
					$msg.addClass('is-error').text(ezmailsAdmin.i18n.error);
				});
		});
	});
})(jQuery);
