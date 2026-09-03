/**
 * Partino Smart Parts Chatbot — frontend widget.
 * Vanilla JS, no dependencies, namespaced, error-boundary protected.
 */
(function () {
	'use strict';

	if (typeof window.PartinoChatbotData === 'undefined') {
		return;
	}

	var DATA = window.PartinoChatbotData;
	var CFG = DATA.config || {};
	var STR = CFG.strings || {};
	var STORAGE_KEY = 'partino_chatbot_conversation';
	var OPEN_KEY = 'partino_chatbot_dismissed';

	var root = document.getElementById('partino-chatbot');
	if (!root || !CFG.enabled) {
		return;
	}

	/* ------------------------------------------------------------------ */
	/* State                                                                */
	/* ------------------------------------------------------------------ */
	var state = {
		open: false,
		conversation: null,
		inputMode: 'text',
		busy: false,
		started: false,
		recognizing: false
	};

	/* ------------------------------------------------------------------ */
	/* Utilities                                                            */
	/* ------------------------------------------------------------------ */
	function el(tag, className, attrs) {
		var node = document.createElement(tag);
		if (className) {
			node.className = className;
		}
		if (attrs) {
			Object.keys(attrs).forEach(function (k) {
				if (k === 'text') {
					node.textContent = attrs[k];
				} else {
					node.setAttribute(k, attrs[k]);
				}
			});
		}
		return node;
	}

	function storageGet(key) {
		try {
			return window.localStorage.getItem(key);
		} catch (e) {
			return null;
		}
	}

	function storageSet(key, value) {
		try {
			if (value === null) {
				window.localStorage.removeItem(key);
			} else {
				window.localStorage.setItem(key, value);
			}
		} catch (e) {
			/* private mode — non-fatal */
		}
	}

	function nowTime() {
		try {
			return new Date().toLocaleTimeString('fa-IR', { hour: '2-digit', minute: '2-digit' });
		} catch (e) {
			var d = new Date();
			return d.getHours() + ':' + ('0' + d.getMinutes()).slice(-2);
		}
	}

	function api(path, method, body) {
		var options = {
			method: method || 'GET',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': DATA.nonce
			},
			credentials: 'same-origin'
		};
		if (body) {
			options.body = JSON.stringify(body);
		}
		return window
			.fetch(DATA.restUrl + path, options)
			.then(function (res) {
				return res
					.json()
					.catch(function () {
						return { success: false };
					})
					.then(function (json) {
						json.__status = res.status;
						return json;
					});
			});
	}

	/* ------------------------------------------------------------------ */
	/* Icons (inline SVG)                                                   */
	/* ------------------------------------------------------------------ */
	var ICONS = {
		close: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>',
		send: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 19V5"/><path d="M5 12l7-7 7 7"/></svg>',
		mic: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="2" width="6" height="12" rx="3"/><path d="M5 10v1a7 7 0 0 0 14 0v-1"/><path d="M12 18v4"/><path d="M8 22h8"/></svg>',
		chat: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>',
		dots: '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="12" cy="5" r="1.8"/><circle cx="12" cy="12" r="1.8"/><circle cx="12" cy="19" r="1.8"/></svg>',
		cart: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>',
		plus: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>',
		clock: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg>'
	};

	/* ------------------------------------------------------------------ */
	/* DOM construction                                                     */
	/* ------------------------------------------------------------------ */
	var app = CFG.appearance || {};

	root.classList.add(CFG.position === 'bottom_right' ? 'partino-pos-right' : 'partino-pos-left');
	root.style.setProperty('--partino-primary', app.primaryColor || '#2069b2');
	root.style.setProperty('--partino-secondary', app.secondaryColor || '#16a34a');
	root.style.setProperty('--partino-bg', app.backgroundColor || '#eef2f7');
	root.style.setProperty('--partino-text', app.textColor || '#1e293b');
	root.style.setProperty('--partino-border', app.borderColor || '#e2e8f0');
	root.style.setProperty('--partino-radius', (app.borderRadius || 24) + 'px');
	root.style.setProperty('--partino-btn-radius', (app.buttonRadius || 14) + 'px');
	root.style.setProperty('--partino-width', (app.chatWidth || 400) + 'px');
	root.style.setProperty('--partino-height', (app.chatHeight || 640) + 'px');
	root.style.setProperty('--partino-header-h', (app.headerHeight || 84) + 'px');
	root.style.setProperty('--partino-font-size', (app.fontSize || 14) + 'px');
	root.style.setProperty('--partino-launcher', app.launcherColor || app.primaryColor || '#2069b2');

	// Launcher.
	var launcher = el('button', 'partino-chatbot-launcher', {
		type: 'button',
		'aria-label': STR.open || 'باز کردن چت'
	});
	launcher.innerHTML = ICONS.chat;
	launcher.hidden = true;

	// Window.
	var win = el('section', 'partino-chatbot-window', {
		role: 'dialog',
		'aria-modal': 'false',
		'aria-label': app.title || 'چت‌بات',
		'aria-expanded': 'false',
		id: 'partino-chatbot-dialog'
	});
	win.hidden = true;
	launcher.setAttribute('aria-controls', 'partino-chatbot-dialog');

	// Header.
	var header = el('header', 'partino-chatbot-header');
	var closeBtn = el('button', 'partino-chatbot-header-close', {
		type: 'button',
		'aria-label': STR.close || 'بستن چت'
	});
	closeBtn.innerHTML = ICONS.close;

	var logo = el('div', 'partino-chatbot-logo');
	if (app.logoUrl) {
		var logoImg = el('img', '', { alt: app.title || '', src: app.logoUrl, loading: 'lazy' });
		logo.appendChild(logoImg);
	} else {
		var fallback = el('div', 'partino-chatbot-logo-fallback', { text: (app.title || 'پ').charAt(0) });
		logo.appendChild(fallback);
	}

	var texts = el('div', 'partino-chatbot-header-texts');
	texts.appendChild(el('div', 'partino-chatbot-title', { text: app.title || '' }));
	texts.appendChild(el('div', 'partino-chatbot-subtitle', { text: app.subtitle || '' }));

	header.appendChild(texts);
	header.appendChild(logo);
	header.appendChild(closeBtn);

	// Body.
	var body = el('div', 'partino-chatbot-body', { role: 'log', 'aria-live': 'polite' });

	// Bottom action bar.
	var actionbar = el('div', 'partino-chatbot-actionbar');
	var actionsScroll = el('div', 'partino-chatbot-actions-scroll');

	function makeAction(label, icon, accent, handler) {
		var btn = el('button', 'partino-chatbot-action' + (accent ? ' partino-accent' : ''), { type: 'button' });
		btn.innerHTML = icon + '<span></span>';
		btn.querySelector('span').textContent = label;
		btn.addEventListener('click', handler);
		return btn;
	}

	var quickBuyBtn = makeAction(STR.quickBuy || 'خرید سریع', ICONS.cart, true, function () {
		restartFlow();
	});
	var newReqBtn = makeAction(STR.newRequest || 'درخواست جدید', ICONS.plus, false, function () {
		restartFlow();
	});
	var statusBtn = makeAction(STR.checkStatus || 'بررسی وضعیت', ICONS.clock, false, function () {
		addBotMessage('برای پیگیری وضعیت استعلام، همکاران ما با شماره ثبت‌شده تماس می‌گیرند. اگر درخواست جدیدی داری روی «درخواست جدید» بزن 🙌', []);
	});

	actionsScroll.appendChild(quickBuyBtn);
	actionsScroll.appendChild(newReqBtn);
	actionsScroll.appendChild(statusBtn);

	var menuBtn = el('button', 'partino-chatbot-menu-btn', {
		type: 'button',
		'aria-label': STR.menu || 'منو',
		'aria-expanded': 'false',
		'aria-controls': 'partino-chatbot-menu'
	});
	menuBtn.innerHTML = ICONS.dots;

	var menu = el('div', 'partino-chatbot-menu', { id: 'partino-chatbot-menu' });
	menu.hidden = true;
	var menuRestart = el('button', '', { type: 'button', text: STR.newRequest || 'درخواست جدید' });
	var menuClose = el('button', '', { type: 'button', text: STR.close || 'بستن چت' });
	menu.appendChild(menuRestart);
	menu.appendChild(menuClose);

	actionbar.appendChild(actionsScroll);
	actionbar.appendChild(menuBtn);

	// Input bar.
	var inputbar = el('div', 'partino-chatbot-inputbar');
	var micBtn = el('button', 'partino-chatbot-mic', {
		type: 'button',
		'aria-label': STR.mic || 'ضبط پیام صوتی'
	});
	micBtn.innerHTML = ICONS.mic;

	var inputWrap = el('div', 'partino-chatbot-input-wrap');
	var input = el('input', 'partino-chatbot-input', {
		type: 'text',
		placeholder: app.placeholder || 'پیام خود را بنویسید...',
		'aria-label': app.placeholder || 'پیام خود را بنویسید...',
		autocomplete: 'off',
		maxlength: '500'
	});
	var sendBtn = el('button', 'partino-chatbot-send', {
		type: 'button',
		'aria-label': STR.send || 'ارسال پیام'
	});
	sendBtn.innerHTML = ICONS.send;

	inputWrap.appendChild(sendBtn);
	inputWrap.appendChild(input);
	inputbar.appendChild(micBtn);
	inputbar.appendChild(inputWrap);

	win.appendChild(header);
	win.appendChild(body);
	win.appendChild(actionbar);
	win.appendChild(inputbar);
	win.appendChild(menu);

	root.appendChild(launcher);
	root.appendChild(win);

	/* ------------------------------------------------------------------ */
	/* Rendering                                                            */
	/* ------------------------------------------------------------------ */
	var typingNode = null;
	var firstBot = true;

	function scrollToBottom() {
		if (CFG.behavior && CFG.behavior.autoScroll === false) {
			return;
		}
		window.requestAnimationFrame(function () {
			body.scrollTop = body.scrollHeight;
		});
	}

	function addTimestamp() {
		if (!(CFG.behavior && CFG.behavior.showTimestamp)) {
			return;
		}
		var t = el('div', 'partino-chatbot-time', { text: nowTime() });
		body.appendChild(t);
	}

	function addBotMessage(text, options, withBadge) {
		var msg = el('div', 'partino-chatbot-msg partino-role-assistant');
		var card = el('div', 'partino-chatbot-card');
		if (withBadge && app.badgeText) {
			var badge = el('span', 'partino-chatbot-badge', { text: app.badgeText });
			card.appendChild(badge);
			card.appendChild(el('br'));
		}
		card.appendChild(document.createTextNode(text));
		msg.appendChild(card);
		body.appendChild(msg);

		if (options && options.length) {
			renderOptions(options);
		}
		addTimestamp();
		scrollToBottom();
	}

	function addUserMessage(text) {
		var msg = el('div', 'partino-chatbot-msg partino-role-user');
		var card = el('div', 'partino-chatbot-card', { text: text });
		msg.appendChild(card);
		body.appendChild(msg);
		scrollToBottom();
	}

	function renderOptions(options) {
		var wrap = el('div', 'partino-chatbot-options' + (options.length > 4 ? ' partino-two-cols' : ''));
		options.forEach(function (opt) {
			var btn = el('button', 'partino-chatbot-option' + (opt.style === 'primary' ? ' partino-primary' : ''), {
				type: 'button',
				'data-option': opt.id
			});
			btn.textContent = opt.label;
			btn.addEventListener('click', function () {
				if (state.busy) {
					return;
				}
				disableOptions(wrap);
				selectOption(opt.id, opt.label);
			});
			wrap.appendChild(btn);
		});
		body.appendChild(wrap);
	}

	function disableOptions(wrap) {
		var buttons = (wrap || body).querySelectorAll('.partino-chatbot-option');
		buttons.forEach(function (b) {
			b.disabled = true;
		});
	}

	function showTyping() {
		if (!(CFG.behavior && CFG.behavior.typingIndicator)) {
			return;
		}
		hideTyping();
		typingNode = el('div', 'partino-chatbot-typing', { 'aria-label': '...' });
		typingNode.innerHTML = '<i></i><i></i><i></i>';
		body.appendChild(typingNode);
		scrollToBottom();
	}

	function hideTyping() {
		if (typingNode && typingNode.parentNode) {
			typingNode.parentNode.removeChild(typingNode);
		}
		typingNode = null;
	}

	function showError(message, retry) {
		var wrap = el('div', 'partino-chatbot-error');
		wrap.textContent = message || STR.networkError || 'خطا در ارتباط';
		if (retry) {
			var btn = el('button', 'partino-chatbot-option', { type: 'button' });
			btn.textContent = STR.retry || 'تلاش مجدد';
			btn.style.marginTop = '8px';
			btn.addEventListener('click', function () {
				wrap.parentNode && wrap.parentNode.removeChild(wrap);
				retry();
			});
			wrap.appendChild(el('br'));
			wrap.appendChild(btn);
		}
		body.appendChild(wrap);
		scrollToBottom();
	}

	function setInputMode(mode) {
		state.inputMode = mode === 'phone' ? 'phone' : 'text';
		if (state.inputMode === 'phone') {
			input.classList.add('partino-ltr-input');
			input.setAttribute('inputmode', 'tel');
			input.setAttribute('placeholder', STR.phonePlaceholder || 'شماره موبایل');
			input.setAttribute('aria-label', STR.phonePlaceholder || 'شماره موبایل');
			sendBtn.setAttribute('aria-label', STR.submitPhone || 'ثبت استعلام');
		} else {
			input.classList.remove('partino-ltr-input');
			input.setAttribute('inputmode', 'text');
			input.setAttribute('placeholder', app.placeholder || 'پیام خود را بنویسید...');
			input.setAttribute('aria-label', app.placeholder || 'پیام خود را بنویسید...');
			sendBtn.setAttribute('aria-label', STR.send || 'ارسال پیام');
		}
	}

	function setBusy(busy) {
		state.busy = busy;
		sendBtn.disabled = busy;
		input.disabled = busy;
	}

	/* ------------------------------------------------------------------ */
	/* Conversation flow                                                    */
	/* ------------------------------------------------------------------ */
	function handleResponse(json, fallbackRetry) {
		hideTyping();
		setBusy(false);

		if (!json || json.success !== true) {
			if (json && json.__status === 429) {
				showError(json.message || 'تعداد درخواست‌ها زیاد است. کمی صبر کنید.');
				return;
			}
			if (json && json.code === 'partino_conversation_not_found') {
				storageSet(STORAGE_KEY, null);
				state.conversation = null;
				startConversation();
				return;
			}
			showError((json && json.message) || STR.networkError, fallbackRetry);
			return;
		}

		var data = json.data || {};

		if (data.conversation) {
			state.conversation = data.conversation;
			storageSet(STORAGE_KEY, data.conversation);
		}

		if (data.history && data.history.length) {
			body.innerHTML = '';
			firstBot = true;
			data.history.forEach(function (m) {
				if (m.role === 'assistant') {
					addBotMessage(m.content, m.options || [], firstBot);
					firstBot = false;
				} else {
					addUserMessage(m.content);
				}
			});
		}

		(data.messages || []).forEach(function (text, i) {
			addBotMessage(text, i === (data.messages.length - 1) ? (data.options || []) : [], firstBot);
			firstBot = false;
		});

		if (data.ui && data.ui.input_mode) {
			setInputMode(data.ui.input_mode);
		}
	}

	function startConversation() {
		if (state.busy) {
			return;
		}
		setBusy(true);
		showTyping();
		var payload = {
			page_url: window.location.href.split('#')[0]
		};
		var existing = storageGet(STORAGE_KEY);
		if (existing) {
			payload.conversation = existing;
		}
		api('/conversation/start', 'POST', payload)
			.then(function (json) {
				state.started = true;
				handleResponse(json, startConversation);
			})
			.catch(function () {
				hideTyping();
				setBusy(false);
				showError(STR.networkError, startConversation);
			});
	}

	function sendText(text) {
		if (!text || state.busy || !state.conversation) {
			return;
		}
		if (state.inputMode === 'phone') {
			sendPhone(text);
			return;
		}
		addUserMessage(text);
		setBusy(true);
		showTyping();
		api('/conversation/message', 'POST', {
			conversation: state.conversation,
			text: text
		})
			.then(function (json) {
				handleResponse(json);
			})
			.catch(function () {
				hideTyping();
				setBusy(false);
				showError(STR.networkError);
			});
	}

	function sendPhone(phone) {
		addUserMessage(phone);
		setBusy(true);
		showTyping();
		api('/conversation/phone', 'POST', {
			conversation: state.conversation,
			phone: phone
		})
			.then(function (json) {
				handleResponse(json);
				if (json && json.success && json.data && json.data.done) {
					storageSet(STORAGE_KEY, null);
				}
			})
			.catch(function () {
				hideTyping();
				setBusy(false);
				showError(STR.networkError);
			});
	}

	function selectOption(optionId, label) {
		if (state.busy || !state.conversation) {
			return;
		}
		addUserMessage(label);
		setBusy(true);
		showTyping();
		api('/conversation/select', 'POST', {
			conversation: state.conversation,
			option: optionId,
			label: label
		})
			.then(function (json) {
				handleResponse(json);
			})
			.catch(function () {
				hideTyping();
				setBusy(false);
				showError(STR.networkError);
			});
	}

	function restartFlow() {
		storageSet(STORAGE_KEY, null);
		state.conversation = null;
		body.innerHTML = '';
		firstBot = true;
		setInputMode('text');
		startConversation();
		closeMenu();
	}

	/* ------------------------------------------------------------------ */
	/* Open / close                                                         */
	/* ------------------------------------------------------------------ */
	var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

	function openChat() {
		if (state.open) {
			return;
		}
		state.open = true;
		win.hidden = false;
		launcher.hidden = true;
		win.setAttribute('aria-expanded', 'true');
		if (CFG.behavior && CFG.behavior.animation && !reduceMotion) {
			win.classList.add('partino-anim-in');
			window.setTimeout(function () {
				win.classList.remove('partino-anim-in');
			}, 320);
		}
		if (!state.started) {
			startConversation();
		}
		window.setTimeout(function () {
			try {
				closeBtn.focus({ preventScroll: true });
			} catch (e) { /* noop */ }
		}, 60);
	}

	function closeChat() {
		if (!state.open) {
			return;
		}
		state.open = false;
		win.hidden = true;
		launcher.hidden = false;
		win.setAttribute('aria-expanded', 'false');
		storageSet(OPEN_KEY, '1');
		closeMenu();
		try {
			launcher.focus({ preventScroll: true });
		} catch (e) { /* noop */ }
	}

	function toggleMenu() {
		if (menu.hidden) {
			menu.hidden = false;
			menuBtn.setAttribute('aria-expanded', 'true');
		} else {
			closeMenu();
		}
	}

	function closeMenu() {
		menu.hidden = true;
		menuBtn.setAttribute('aria-expanded', 'false');
	}

	/* ------------------------------------------------------------------ */
	/* Voice input (browser-side only; no audio upload)                     */
	/* ------------------------------------------------------------------ */
	var recognition = null;

	function setupVoice() {
		if (!(CFG.behavior && CFG.behavior.voiceInput)) {
			micBtn.style.display = 'none';
			return;
		}
		var SR = window.SpeechRecognition || window.webkitSpeechRecognition;
		if (!SR) {
			micBtn.disabled = true;
			micBtn.title = STR.micUnsupported || '';
			return;
		}
		recognition = new SR();
		recognition.lang = 'fa-IR';
		recognition.interimResults = false;
		recognition.maxAlternatives = 1;

		recognition.onresult = function (event) {
			var transcript = event.results && event.results[0] && event.results[0][0] ? event.results[0][0].transcript : '';
			if (transcript) {
				input.value = transcript;
				input.focus();
			}
		};
		recognition.onend = function () {
			state.recognizing = false;
			micBtn.classList.remove('partino-recording');
		};
		recognition.onerror = function () {
			state.recognizing = false;
			micBtn.classList.remove('partino-recording');
		};

		micBtn.addEventListener('click', function () {
			if (state.recognizing) {
				recognition.stop();
				return;
			}
			try {
				recognition.start();
				state.recognizing = true;
				micBtn.classList.add('partino-recording');
			} catch (e) { /* already started */ }
		});
	}

	/* ------------------------------------------------------------------ */
	/* Events                                                               */
	/* ------------------------------------------------------------------ */
	launcher.addEventListener('click', openChat);
	closeBtn.addEventListener('click', closeChat);
	menuBtn.addEventListener('click', toggleMenu);
	menuRestart.addEventListener('click', restartFlow);
	menuClose.addEventListener('click', closeChat);

	sendBtn.addEventListener('click', function () {
		var text = input.value.trim();
		if (!text) {
			return;
		}
		input.value = '';
		sendText(text);
	});

	input.addEventListener('keydown', function (e) {
		if (e.key === 'Enter' && !e.shiftKey) {
			e.preventDefault();
			var text = input.value.trim();
			if (text) {
				input.value = '';
				sendText(text);
			}
		}
	});

	document.addEventListener('keydown', function (e) {
		if (e.key === 'Escape' && state.open) {
			closeChat();
		}
	});

	document.addEventListener('click', function (e) {
		if (!menu.hidden && !menu.contains(e.target) && e.target !== menuBtn && !menuBtn.contains(e.target)) {
			closeMenu();
		}
	});

	setupVoice();

	/* ------------------------------------------------------------------ */
	/* Boot                                                                 */
	/* ------------------------------------------------------------------ */
	launcher.hidden = false;

	var dismissed = storageGet(OPEN_KEY) === '1';

	if (CFG.autoOpen && !dismissed) {
		window.setTimeout(function () {
			openChat();
		}, Math.max(0, parseInt(CFG.openDelay, 10) || 0));
	}
})();
