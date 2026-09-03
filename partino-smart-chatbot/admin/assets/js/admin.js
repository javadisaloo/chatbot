/**
 * Partino Smart Parts Chatbot — admin scripts.
 * Vanilla JS: confirmations, AI test, media upload, live preview.
 */
(function () {
	'use strict';

	var DATA = window.PartinoAdminData || {};
	var I18N = DATA.i18n || {};

	/* Confirmations ------------------------------------------------------ */
	document.addEventListener('click', function (e) {
		var target = e.target.closest('[data-partino-confirm]');
		if (!target) {
			return;
		}
		var kind = target.getAttribute('data-partino-confirm');
		var message = kind === 'reset' ? (I18N.confirmReset || 'Reset settings?') : (I18N.confirmDelete || 'Are you sure?');
		if (!window.confirm(message)) {
			e.preventDefault();
			e.stopPropagation();
		}
	});

	/* Check-all ------------------------------------------------------------ */
	var checkAll = document.querySelector('[data-partino-check-all]');
	if (checkAll) {
		checkAll.addEventListener('change', function () {
			document.querySelectorAll('input[name="inquiry_ids[]"]').forEach(function (cb) {
				cb.checked = checkAll.checked;
			});
		});
	}

	/* AI connection test ---------------------------------------------------- */
	var testBtn = document.getElementById('partino-test-ai');
	if (testBtn) {
		testBtn.addEventListener('click', function () {
			var result = document.getElementById('partino-test-ai-result');
			result.textContent = I18N.testing || '...';
			result.className = '';
			window
				.fetch(DATA.restUrl + '/admin/test-ai', {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': DATA.nonce
					},
					credentials: 'same-origin'
				})
				.then(function (res) {
					return res.json();
				})
				.then(function (json) {
					result.textContent = json.message || '';
					result.className = json.success ? 'ok' : 'err';
				})
				.catch(function () {
					result.textContent = 'Error';
					result.className = 'err';
				});
		});
	}

	/* Media uploader for logo ------------------------------------------------ */
	var uploadBtn = document.getElementById('partino-logo-upload');
	if (uploadBtn && window.wp && window.wp.media) {
		var frame = null;
		uploadBtn.addEventListener('click', function (e) {
			e.preventDefault();
			if (!frame) {
				frame = window.wp.media({
					title: uploadBtn.textContent,
					multiple: false,
					library: { type: 'image' }
				});
				frame.on('select', function () {
					var attachment = frame.state().get('selection').first().toJSON();
					var input = document.getElementById('partino-logo-url');
					if (input) {
						input.value = attachment.url;
						input.dispatchEvent(new Event('input', { bubbles: true }));
					}
				});
			}
			frame.open();
		});
	}

	/* Live preview -------------------------------------------------------- */
	var preview = document.getElementById('partino-preview');
	if (preview) {
		var win = preview.querySelector('.partino-preview-window');

		var applyVar = function (name, value) {
			win.style.setProperty(name, value);
		};

		var bindText = function (key, value) {
			preview.querySelectorAll('[data-preview-bind="' + key + '"]').forEach(function (node) {
				node.textContent = value;
			});
		};

		var handlers = {
			primary_color: function (v) { applyVar('--pv-primary', v); },
			background_color: function (v) { applyVar('--pv-bg', v); },
			text_color: function (v) { applyVar('--pv-text', v); },
			border_color: function (v) { applyVar('--pv-border', v); },
			border_radius: function (v) { applyVar('--pv-radius', v + 'px'); },
			button_radius: function (v) { applyVar('--pv-btn-radius', v + 'px'); },
			font_size: function (v) { applyVar('--pv-font', v + 'px'); },
			title: function (v) { bindText('title', v); },
			subtitle: function (v) { bindText('subtitle', v); },
			badge_text: function (v) { bindText('badge_text', v); },
			welcome_message: function (v) { bindText('welcome_message', v); },
			placeholder: function (v) { bindText('placeholder', v); },
			logo_url: function (v) {
				var img = preview.querySelector('.partino-preview-logo img');
				var span = preview.querySelector('.partino-preview-logo span');
				if (v) {
					img.src = v;
					img.style.display = 'block';
					span.style.display = 'none';
				} else {
					img.style.display = 'none';
					span.style.display = 'flex';
				}
			}
		};

		document.querySelectorAll('[data-partino-preview]').forEach(function (input) {
			var key = input.getAttribute('data-partino-preview');
			if (!handlers[key]) {
				return;
			}
			// Init.
			handlers[key](input.value);
			input.addEventListener('input', function () {
				handlers[key](input.value);
			});
		});
	}
})();
