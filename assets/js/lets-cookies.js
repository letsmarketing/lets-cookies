/*!
 * Lets Cookies — banner e consentimento (LGPD).
 * Sem dependências. Nada de terceiros é carregado antes da escolha do visitante.
 */
(function (window, document) {
	'use strict';

	var cfg = window.LetsCookiesConfig;
	if (!cfg) {
		return;
	}

	var CATS = ['analytics', 'embedded', 'marketing'];
	var T = cfg.texts || {};
	var enabled = cfg.categories
		.filter(function (c) { return !c.required; })
		.map(function (c) { return c.key; });

	var state = null;       // Escolha válida atual (ou null).
	var bannerEl = null;
	var prefsEl = null;
	var floatEl = null;
	var lastFocus = null;

	/* ---------- Estado e cookie ---------- */

	function uuid() {
		if (window.crypto && window.crypto.randomUUID) {
			return window.crypto.randomUUID();
		}
		var b = new Uint8Array(16);
		window.crypto.getRandomValues(b);
		b[6] = (b[6] & 0x0f) | 0x40;
		b[8] = (b[8] & 0x3f) | 0x80;
		var h = Array.prototype.map.call(b, function (x) { return ('0' + x.toString(16)).slice(-2); }).join('');
		return h.slice(0, 8) + '-' + h.slice(8, 12) + '-' + h.slice(12, 16) + '-' + h.slice(16, 20) + '-' + h.slice(20);
	}

	function readState() {
		var m = document.cookie.match(new RegExp('(?:^|; )' + cfg.cookieName + '=([^;]*)'));
		if (!m) {
			return null;
		}
		try {
			var s = JSON.parse(decodeURIComponent(m[1]));
			// Política nova = pedir consentimento de novo.
			if (!s || s.v !== cfg.version || !s.c || !s.id) {
				return null;
			}
			return s;
		} catch (e) {
			return null;
		}
	}

	function writeState(s) {
		var value = encodeURIComponent(JSON.stringify(s));
		var secure = window.location.protocol === 'https:' ? '; Secure' : '';
		document.cookie = cfg.cookieName + '=' + value + '; Max-Age=' + (cfg.cookieDays * 86400) + '; Path=/; SameSite=Lax' + secure;
	}

	function granted(cat) {
		return !!(state && state.c && state.c[cat] === true);
	}

	/* ---------- Ativação do que foi autorizado ---------- */

	function activateScripts() {
		var nodes = document.querySelectorAll('script[type="text/plain"][data-lets-category]');
		Array.prototype.forEach.call(nodes, function (old) {
			var cat = old.getAttribute('data-lets-category');
			if (!granted(cat) || old.getAttribute('data-lets-done')) {
				return;
			}
			old.setAttribute('data-lets-done', '1');
			var s = document.createElement('script');
			Array.prototype.forEach.call(old.attributes, function (a) {
				if (a.name !== 'type' && a.name.indexOf('data-lets-') !== 0) {
					s.setAttribute(a.name, a.value);
				}
			});
			var src = old.getAttribute('data-lets-src');
			if (src) {
				s.src = src;
				s.async = true;
			} else {
				s.text = old.text;
			}
			old.parentNode.insertBefore(s, old.nextSibling);
		});
	}

	function updateConsentMode() {
		var g = function (b) { return b ? 'granted' : 'denied'; };
		if (typeof window.gtag === 'function') {
			window.gtag('consent', 'update', {
				analytics_storage: g(granted('analytics')),
				ad_storage: g(granted('marketing')),
				ad_user_data: g(granted('marketing')),
				ad_personalization: g(granted('marketing')),
				functionality_storage: g(granted('embedded'))
			});
		}
	}

	/* O Clarity só existe depois que o script é ativado, por isso vem depois. */
	function updateClarity() {
		var g = function (b) { return b ? 'granted' : 'denied'; };
		if (typeof window.clarity === 'function') {
			window.clarity('consentv2', {
				analytics_Storage: g(granted('analytics')),
				ad_Storage: g(granted('marketing'))
			});
		}
	}

	function loadEmbed(box) {
		var tpl = box.querySelector('template');
		if (!tpl) {
			return;
		}
		var frame = tpl.content ? tpl.content.querySelector('iframe') : null;
		if (!frame) {
			return;
		}
		frame = frame.cloneNode(true);
		frame.src = frame.getAttribute('data-lets-src');
		frame.removeAttribute('data-lets-src');
		box.innerHTML = '';
		box.appendChild(frame);
		box.classList.add('is-loaded');
		box.classList.remove('is-pending');
	}

	function loadPendingEmbeds() {
		if (!granted('embedded')) {
			return;
		}
		Array.prototype.forEach.call(document.querySelectorAll('.lets-ck-embed:not(.is-loaded)'), loadEmbed); // Com consentimento, todos os conteúdos carregam.
	}

	/* Apaga cookies próprios de categorias revogadas (os de terceiros não são acessíveis). */
	function clearRevokedCookies(prev) {
		var known = {
			analytics: [/^_ga($|_)/, /^_clck$/, /^_clsk$/],
			marketing: [/^_fbp$/, /^__trf\.src$/, /^rdtrk$/],
			embedded: []
		};
		var names = document.cookie.split('; ').map(function (c) { return c.split('=')[0]; });
		var host = window.location.hostname;
		var domains = ['', host, '.' + host, '.' + host.split('.').slice(-3).join('.'), '.' + host.split('.').slice(-2).join('.')];
		CATS.forEach(function (cat) {
			if (!(prev && prev.c && prev.c[cat]) || granted(cat)) {
				return;
			}
			names.forEach(function (n) {
				if (known[cat].some(function (re) { return re.test(n); })) {
					domains.forEach(function (d) {
						document.cookie = n + '=; Max-Age=0; Path=/' + (d ? '; Domain=' + d : '');
					});
				}
			});
		});
	}

	/* ---------- Registro ---------- */

	/*
	 * O registro comprova o consentimento (Guia ANPD, pág. 19). Até o servidor
	 * confirmar, a escolha fica marcada como pendente (l: 0) e é reenviada na
	 * próxima página, para nenhuma escolha ficar sem registro por falha de rede.
	 */
	function log(action) {
		if (!cfg.restUrl || !window.fetch) {
			return;
		}
		var choices = {};
		CATS.forEach(function (c) { choices[c] = granted(c); });
		state.a = action;
		state.l = 0;
		writeState(state);
		var current = state;
		try {
			window.fetch(cfg.restUrl, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				credentials: 'omit',
				keepalive: true,
				body: JSON.stringify({
					consent_id: state.id,
					version: cfg.version,
					action: action,
					choices: choices,
					page: window.location.pathname
				})
			}).then(function (res) {
				// 2xx: registrado. 400: corpo recusado, não adianta reenviar.
				// 429 ou erro de servidor: fica pendente para a próxima página.
				if (current !== state) {
					return;
				}
				if (res && (res.ok || res.status === 400)) {
					state.l = 1;
					writeState(state);
				}
			}).catch(function () {});
		} catch (e) { /* o registro nunca bloqueia a navegação */ }
	}

	/* ---------- Salvar escolha ---------- */

	function save(choices, action) {
		var prev = state;
		var revoked = prev && CATS.some(function (c) { return prev.c[c] && !choices[c]; });
		state = {
			v: cfg.version,
			id: prev && prev.id ? prev.id : uuid(),
			c: choices,
			t: Date.now()
		};
		writeState(state);
		if (revoked && action === 'custom') {
			action = CATS.some(function (c) { return choices[c]; }) ? 'custom' : 'revoke';
		}
		log(action);
		closePrefs();
		hideBanner();
		showFloating();
		updateConsentMode();
		activateScripts();
		updateClarity();
		loadPendingEmbeds();
		clearRevokedCookies(prev);
		document.dispatchEvent(new CustomEvent('lets_cookies_consent', { detail: { choices: choices, action: action } }));
		// Scripts já executados não podem ser desligados: recarregar garante que parem.
		if (revoked) {
			window.setTimeout(function () { window.location.reload(); }, 300);
		}
	}

	function all(value) {
		var c = {};
		CATS.forEach(function (k) { c[k] = value && enabled.indexOf(k) !== -1; });
		return c;
	}

	/* ---------- Interface ---------- */

	function el(tag, attrs, text) {
		var node = document.createElement(tag);
		Object.keys(attrs || {}).forEach(function (k) {
			if (k === 'className') {
				node.className = attrs[k];
			} else {
				node.setAttribute(k, attrs[k]);
			}
		});
		if (text) {
			node.textContent = text;
		}
		return node;
	}

	function buttons(onCustomize) {
		var row = el('div', { className: 'lets-ck-actions' });
		var reject = el('button', { type: 'button', className: 'lets-ck-btn', 'data-lets-action': 'reject' }, T.reject_all);
		var custom = el('button', { type: 'button', className: 'lets-ck-btn', 'data-lets-action': 'customize' }, onCustomize ? T.customize : T.save);
		var accept = el('button', { type: 'button', className: 'lets-ck-btn', 'data-lets-action': 'accept' }, T.accept_all);
		reject.addEventListener('click', function () { save(all(false), 'reject_all'); });
		accept.addEventListener('click', function () { save(all(true), 'accept_all'); });
		custom.addEventListener('click', onCustomize || saveFromPrefs);
		row.appendChild(reject);
		row.appendChild(custom);
		row.appendChild(accept);
		return row;
	}

	function links() {
		var p = el('p', { className: 'lets-ck-links' });
		if (cfg.policyUrl) {
			p.appendChild(el('a', { href: cfg.policyUrl }, T.policy_link));
		}
		// Link para exercer direitos já no 1º nível (Guia ANPD, pág. 31).
		// Sem canal próprio configurado, aponta para a Política.
		var rights = cfg.rightsUrl || cfg.policyUrl;
		if (rights) {
			if (p.childNodes.length) {
				p.appendChild(document.createTextNode(' · '));
			}
			p.appendChild(el('a', { href: rights }, T.rights_link));
		}
		return p;
	}

	// Como bloquear pelo navegador (Guia ANPD, pág. 32).
	function browserHelp() {
		var wrap = el('div', { className: 'lets-ck-browser' });
		wrap.appendChild(el('p', {}, T.browser_info));
		var list = (cfg.browserLinks || []).filter(function (b) { return /^https:\/\//.test(b.url); });
		if (list.length) {
			var ul = el('ul', { className: 'lets-ck-browser-links' });
			list.forEach(function (b) {
				var li = el('li');
				li.appendChild(el('a', { href: b.url, target: '_blank', rel: 'noopener noreferrer' }, b.name));
				ul.appendChild(li);
			});
			wrap.appendChild(ul);
		}
		return wrap;
	}

	function showBanner() {
		if (bannerEl) {
			bannerEl.hidden = false;
			return;
		}
		bannerEl = el('div', { className: 'lets-ck-banner lets-ck-pos-' + cfg.position, role: 'region', 'aria-label': T.title });
		bannerEl.appendChild(el('p', { className: 'lets-ck-title' }, T.title));
		bannerEl.appendChild(el('p', { className: 'lets-ck-text' }, T.message));
		bannerEl.appendChild(links());
		bannerEl.appendChild(buttons(function () { openPrefs(); }));
		document.body.appendChild(bannerEl);
	}

	function hideBanner() {
		if (bannerEl) {
			bannerEl.hidden = true;
		}
	}

	function buildPrefs() {
		var overlay = el('div', { className: 'lets-ck-overlay' });
		var dialog = el('div', { className: 'lets-ck-prefs', role: 'dialog', 'aria-modal': 'true', 'aria-labelledby': 'lets-ck-prefs-title' });
		var close = el('button', { type: 'button', className: 'lets-ck-close', 'aria-label': 'Fechar' }, '×');
		close.addEventListener('click', closePrefs);
		dialog.appendChild(close);
		dialog.appendChild(el('h2', { id: 'lets-ck-prefs-title', className: 'lets-ck-title' }, T.prefs_title));
		dialog.appendChild(el('p', { className: 'lets-ck-text' }, T.prefs_intro));

		cfg.categories.forEach(function (cat) {
			var box = el('div', { className: 'lets-ck-cat' });
			var head = el('div', { className: 'lets-ck-cat-head' });
			var id = 'lets-ck-cat-' + cat.key;
			if (cat.required) {
				head.appendChild(el('span', { className: 'lets-ck-cat-name' }, cat.label));
				head.appendChild(el('span', { className: 'lets-ck-always' }, T.always_active));
			} else {
				var label = el('label', { 'for': id, className: 'lets-ck-cat-name' }, cat.label);
				var input = el('input', { type: 'checkbox', id: id, role: 'switch', 'data-lets-cat': cat.key, className: 'lets-ck-switch' });
				head.appendChild(label);
				head.appendChild(input);
			}
			box.appendChild(head);
			box.appendChild(el('p', { className: 'lets-ck-cat-desc' }, cat.description));
			if (cat.cookies && cat.cookies.length) {
				var det = el('details', { className: 'lets-ck-cookies' });
				det.appendChild(el('summary', {}, 'Ver cookies (' + cat.cookies.length + ')'));
				var table = el('table');
				var thead = el('tr');
				['Cookie', 'Provedor', 'Finalidade', 'Prazo'].forEach(function (h) { thead.appendChild(el('th', { scope: 'col' }, h)); });
				table.appendChild(thead);
				cat.cookies.forEach(function (ck) {
					var tr = el('tr');
					[ck.name, ck.provider, ck.purpose, ck.duration].forEach(function (v) { tr.appendChild(el('td', {}, v)); });
					table.appendChild(tr);
				});
				det.appendChild(table);
				box.appendChild(det);
			}
			dialog.appendChild(box);
		});

		dialog.appendChild(browserHelp());
		dialog.appendChild(links());
		dialog.appendChild(buttons(null));
		overlay.appendChild(dialog);

		overlay.addEventListener('keydown', function (e) {
			if (e.key === 'Escape') {
				closePrefs();
			} else if (e.key === 'Tab') {
				trapFocus(e, dialog);
			}
		});
		return overlay;
	}

	function trapFocus(e, root) {
		var f = root.querySelectorAll('button, [href], input, summary');
		if (!f.length) {
			return;
		}
		var first = f[0];
		var last = f[f.length - 1];
		if (e.shiftKey && document.activeElement === first) {
			e.preventDefault();
			last.focus();
		} else if (!e.shiftKey && document.activeElement === last) {
			e.preventDefault();
			first.focus();
		}
	}

	function openPrefs(focusCat) {
		lastFocus = document.activeElement;
		if (!prefsEl) {
			prefsEl = buildPrefs();
			document.body.appendChild(prefsEl);
		}
		// Seletores sempre refletem a escolha atual; sem escolha, tudo desligado.
		Array.prototype.forEach.call(prefsEl.querySelectorAll('input[data-lets-cat]'), function (i) {
			i.checked = granted(i.getAttribute('data-lets-cat'));
		});
		prefsEl.hidden = false;
		document.documentElement.classList.add('lets-ck-open');
		var target = focusCat ? prefsEl.querySelector('input[data-lets-cat="' + focusCat + '"]') : null;
		(target || prefsEl.querySelector('.lets-ck-close')).focus();
	}

	function closePrefs() {
		if (!prefsEl || prefsEl.hidden) {
			return;
		}
		prefsEl.hidden = true;
		document.documentElement.classList.remove('lets-ck-open');
		if (lastFocus && lastFocus.focus) {
			lastFocus.focus();
		}
	}

	function saveFromPrefs() {
		var c = all(false);
		Array.prototype.forEach.call(prefsEl.querySelectorAll('input[data-lets-cat]'), function (i) {
			c[i.getAttribute('data-lets-cat')] = i.checked;
		});
		save(c, 'custom');
	}

	function showFloating() {
		if (!cfg.showFloating) {
			return;
		}
		if (!floatEl) {
			floatEl = el('button', { type: 'button', className: 'lets-ck-float', 'aria-label': T.floating_label, title: T.floating_label });
			floatEl.innerHTML = '<svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12 2a10 10 0 1 0 10 10 4 4 0 0 1-5-5 4 4 0 0 1-5-5Zm-4.5 9a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3Zm3 5a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3Zm6-1a1 1 0 1 1 0 2 1 1 0 0 1 0-2ZM9 6.5a1 1 0 1 1 0 2 1 1 0 0 1 0-2Z"/></svg>';
			floatEl.addEventListener('click', function () { openPrefs(); });
			document.body.appendChild(floatEl);
		}
		floatEl.hidden = false;
	}

	/* ---------- Eventos globais ---------- */

	document.addEventListener('click', function (e) {
		var t = e.target;
		if (!t || !t.closest) {
			return;
		}
		// Links "Preferências de cookies" em qualquer lugar do site.
		var opener = t.closest('.lets-cookies-open, a[href="#lets-cookies"], a[href="#preferencias-de-cookies"]');
		if (opener) {
			e.preventDefault();
			openPrefs();
			return;
		}
		// Botão "Carregar conteúdo" dos conteúdos incorporados.
		var btn = t.closest('.lets-ck-embed__btn');
		if (btn) {
			var box = btn.closest('.lets-ck-embed');
			if (granted('embedded')) {
				loadEmbed(box);
			} else {
				box.classList.add('is-pending');
				openPrefs(enabled.indexOf('embedded') !== -1 ? 'embedded' : null);
			}
		}
	});

	/* API pública mínima, para o tema e para testes. */
	window.LetsCookies = {
		open: openPrefs,
		granted: granted,
		getState: function () { return state; }
	};

	/* ---------- Início ---------- */

	function start() {
		state = readState();
		if (state && state.l === 0) {
			log(state.a || 'custom'); // Registro que não chegou ao servidor.
		}
		if (state) {
			updateConsentMode();
			activateScripts();
			loadPendingEmbeds();
			updateClarity();
			showFloating();
		} else {
			showBanner();
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', start);
	} else {
		start();
	}
})(window, document);
