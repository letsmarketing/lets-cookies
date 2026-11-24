const { JSDOM, VirtualConsole } = require('jsdom');
const fs = require('fs');
const code = fs.readFileSync(require('path').join(__dirname, '..', 'assets', 'js', 'lets-cookies.js'), 'utf8');
let fail = 0;
const ok = (c, m) => { console.log((c ? '  ok    ' : '  FALHA  ') + m); if (!c) fail++; };

const config = {
  version: 2, cookieName: 'lets_cookies_consent', cookieDays: 180,
  categories: [
    { key: 'necessary', label: 'Necessários', description: 'd', required: true, cookies: [{ name: 'lets_cookies_consent', provider: 'Este site', purpose: 'p', duration: '6 meses' }] },
    { key: 'analytics', label: 'Analíticos', description: 'd', required: false, cookies: [] },
    { key: 'embedded', label: 'Conteúdo incorporado', description: 'd', required: false, cookies: [] },
  ],
  texts: { title: 'Sua privacidade', message: 'm', policy_link: 'Política', rights_link: 'Exercer seus direitos', accept_all: 'Aceitar todos', reject_all: 'Rejeitar não necessários', customize: 'Personalizar', save: 'Salvar preferências', prefs_title: 'Preferências', prefs_intro: 'i', always_active: 'Sempre ativos', browser_info: 'b', floating_label: 'Preferências de cookies' },
  policyUrl: 'https://site.test/aviso/#politica-de-cookies', rightsUrl: '', restUrl: 'https://site.test/wp-json/lets-cookies/v1/consent', position: 'bottom-right', showFloating: true, clickToLoad: true,
  browserLinks: [{ name: 'Google Chrome', url: 'https://support.google.com/chrome/answer/95647?hl=pt-BR' }, { name: 'Malicioso', url: 'javascript:alert(1)' }],
};

function page(cookie, opts = {}) {
  const html = `<!doctype html><html><head></head><body>
    <script type="text/plain" data-lets-category="analytics" data-lets-service="ga4">window.__gaRan = true;</script>
    <script type="text/plain" data-lets-category="marketing">window.__mkRan = true;</script>
    <div class="lets-ck-embed" data-lets-category="embedded"><template><iframe data-lets-src="https://www.youtube-nocookie.com/embed/x"></iframe></template><div class="lets-ck-embed__box"><button type="button" class="lets-ck-embed__btn">Carregar</button></div></div>
    <div class="lets-ck-embed" id="embed2" data-lets-category="embedded"><template><iframe data-lets-src="https://www.youtube-nocookie.com/embed/y"></iframe></template><div class="lets-ck-embed__box"><button type="button" class="lets-ck-embed__btn">Carregar</button></div></div>
    <a href="#lets-cookies" id="footer-link">Preferências de cookies</a>
  </body></html>`;
  const vc = new VirtualConsole();
  let holder = {};
  vc.on('jsdomError', e => { if (/navigation/.test(e.message)) holder.w.__reloaded = true; });
  const dom = new JSDOM(html, { url: 'https://site.test/contato/?x=1', runScripts: 'dangerously', virtualConsole: vc });
  const w = dom.window; holder.w = w;
  w.__posts = [];
  w.fetch = (url, o) => { w.__posts.push({ url, body: JSON.parse(o.body), credentials: o.credentials }); return opts.fail ? Promise.reject(new Error('offline')) : Promise.resolve({ ok: true, status: 201 }); };
  w.crypto.randomUUID = () => '3f2a9c1e-5b7d-4e8a-9c21-7d4e5f6a8b90';
  if (cookie) w.document.cookie = cookie + '; Path=/';
  w.LetsCookiesConfig = Object.assign(JSON.parse(JSON.stringify(config)), opts.config || {});
  w.eval(code);
  return new Promise(r => { if (w.document.readyState !== 'loading') r(w); else w.document.addEventListener('DOMContentLoaded', () => setTimeout(() => r(w), 0)); });
}
const q = (w, s) => w.document.querySelector(s);
const click = (w, el) => el.dispatchEvent(new w.MouseEvent('click', { bubbles: true }));

(async () => {
console.log('Primeira visita');
let w = await page();
const banner = q(w, '.lets-ck-banner');
ok(banner && !banner.hidden, 'banner aparece sem escolha anterior');
const btns = [...banner.querySelectorAll('button')];
ok(btns.length === 3, 'banner tem 3 botões (nunca botão único)');
ok(btns.every(b => b.className === 'lets-ck-btn'), 'os 3 botões têm a mesma classe (mesmo destaque)');
ok(btns.map(b => b.textContent).join('|') === 'Rejeitar não necessários|Personalizar|Aceitar todos', 'ordem: Rejeitar, Personalizar, Aceitar');
ok(!w.__gaRan && !w.__mkRan, 'nenhum script de terceiro rodou antes da escolha');
ok(!q(w, '.lets-ck-embed iframe[src]'), 'nenhum iframe ativo antes da escolha');
ok(banner.querySelector('a[href*="politica-de-cookies"]'), 'banner tem link para a âncora da Política');
const rightsLink = [...banner.querySelectorAll('a')].find(a => a.textContent === 'Exercer seus direitos');
ok(rightsLink && rightsLink.href.includes('politica-de-cookies'), 'link para exercer direitos no 1º nível (cai na Política sem canal próprio)');

console.log('Personalizar');
click(w, btns[1]);
const prefs = q(w, '.lets-ck-prefs');
ok(prefs && prefs.getAttribute('role') === 'dialog' && prefs.getAttribute('aria-modal') === 'true', 'painel de 2º nível é um diálogo acessível');
const switches = [...prefs.querySelectorAll('input[data-lets-cat]')];
ok(switches.length === 2, 'uma chave por categoria não necessária');
ok(switches.every(s => !s.checked), 'nenhuma categoria pré-marcada');
ok(prefs.textContent.includes('Sempre ativos'), 'Necessários aparecem como sempre ativos, sem chave');
ok(prefs.querySelector('details table'), 'lista de cookies recolhida (sem excesso de informação)');
const bl = [...prefs.querySelectorAll('.lets-ck-browser-links a')];
ok(bl.length === 1 && bl[0].textContent === 'Google Chrome', 'links de ajuda dos navegadores no 2º nível (só https)');
const pbtns = [...prefs.querySelectorAll('.lets-ck-actions button')];
ok(pbtns.length === 3 && pbtns.every(b => b.className === 'lets-ck-btn'), '2º nível também tem Rejeitar/Salvar/Aceitar iguais');
switches.find(s => s.dataset.letsCat === 'analytics').checked = true;
click(w, pbtns[1]);
ok(w.__gaRan === true, 'Analíticos autorizados: script do GA ativado');
ok(!w.__mkRan, 'Marketing (desligada no site) continua bloqueado');
ok(!q(w, '.lets-ck-embed iframe[src]'), 'conteúdo incorporado continua bloqueado');
ok(w.__posts.length === 1, 'escolha enviada ao registro');
const p = w.__posts[0];
ok(p.body.action === 'custom' && p.body.choices.analytics === true && p.body.choices.embedded === false && p.body.choices.marketing === false, 'registro com as escolhas corretas');
ok(p.body.page === '/contato/' && p.credentials === 'omit', 'registro sem query string e sem cookies de login');
ok(/lets_cookies_consent=/.test(w.document.cookie), 'escolha salva em cookie próprio');
ok(q(w, '.lets-ck-banner').hidden, 'banner some após a escolha');
ok(q(w, '.lets-ck-float') && !q(w, '.lets-ck-float').hidden, 'botão flutuante aparece para revogar');

console.log('Conteúdo incorporado sob clique');
click(w, q(w, '.lets-ck-embed__btn'));
ok(!q(w, '.lets-ck-overlay').hidden, 'sem consentimento, o botão abre as preferências');
ok(w.document.activeElement && w.document.activeElement.dataset.letsCat === 'embedded', 'foco vai direto na chave de Conteúdo incorporado');
w.document.activeElement.checked = true;
click(w, [...q(w, '.lets-ck-prefs').querySelectorAll('.lets-ck-actions button')][1]);
ok(q(w, '.lets-ck-embed iframe[src="https://www.youtube-nocookie.com/embed/x"]'), 'após autorizar, o vídeo pendente carrega');
ok(!q(w, '#embed2 iframe'), 'só no clique: o outro vídeo da página continua esperando o clique');
click(w, q(w, '#embed2 .lets-ck-embed__btn'));
ok(q(w, '#embed2 iframe[src="https://www.youtube-nocookie.com/embed/y"]'), 'com consentimento, o clique carrega o vídeo direto');

console.log('Revogação');
click(w, q(w, '#footer-link'));
ok(!q(w, '.lets-ck-overlay').hidden, 'link "Preferências de cookies" do rodapé abre o painel');
click(w, [...q(w, '.lets-ck-prefs').querySelectorAll('.lets-ck-actions button')][0]);
const last = w.__posts[w.__posts.length - 1];
ok(last.body.action === 'reject_all' && Object.values(last.body.choices).every(v => v === false), 'rejeitar registra tudo negado');
await new Promise(r => setTimeout(r, 400));
{
  ok(w.__reloaded === true, 'ao revogar, a página recarrega para parar scripts já rodando');

  console.log('Visita seguinte e nova versão da política');
  const accepted = 'lets_cookies_consent=' + encodeURIComponent(JSON.stringify({ v: 2, id: '3f2a9c1e-5b7d-4e8a-9c21-7d4e5f6a8b90', c: { analytics: true, embedded: false, marketing: false }, t: 1 }));
  let w2 = await page(accepted);
  ok(!q(w2, '.lets-ck-banner'), 'com escolha válida, banner não aparece');
  ok(w2.__gaRan === true, 'scripts autorizados rodam direto');
  ok(w2.__posts.length === 0, 'nada é registrado sem nova escolha');
  ok(!q(w2, '.lets-ck-embed iframe[src]'), 'vídeo segue bloqueado quando só analíticos foram aceitos');
  const embOk = 'lets_cookies_consent=' + encodeURIComponent(JSON.stringify({ v: 2, id: '3f2a9c1e-5b7d-4e8a-9c21-7d4e5f6a8b90', c: { analytics: false, embedded: true, marketing: false }, t: 1, l: 1 }));
  let wE = await page(embOk);
  ok(!q(wE, '.lets-ck-embed iframe'), 'só no clique: na visita seguinte, o vídeo espera o clique mesmo com consentimento');
  click(wE, q(wE, '.lets-ck-embed__btn'));
  ok(q(wE, '.lets-ck-embed iframe[src="https://www.youtube-nocookie.com/embed/x"]') && (!q(wE, '.lets-ck-overlay') || q(wE, '.lets-ck-overlay').hidden), 'o clique carrega o vídeo sem abrir as preferências');
  let wA = await page(embOk, { config: { clickToLoad: false } });
  ok(wA.document.querySelectorAll('.lets-ck-embed iframe[src]').length === 2, 'com a opção desligada, os vídeos carregam direto para quem já autorizou');
  const old = accepted.replace('%22v%22%3A2', '%22v%22%3A1');
  let w3 = await page(old);
  ok(q(w3, '.lets-ck-banner') && !w3.__gaRan, 'versão antiga da política: banner volta e nada roda');

  console.log('Registro que falhou é reenviado');
  let w5 = await page(null, { fail: true });
  click(w5, q(w5, '.lets-ck-banner button[data-lets-action="reject"]'));
  await new Promise(r => setTimeout(r, 50));
  const pending = decodeURIComponent(w5.document.cookie.match(/lets_cookies_consent=([^;]*)/)[1]);
  ok(JSON.parse(pending).l === 0, 'sem resposta do servidor, a escolha fica marcada como pendente');
  let w6 = await page('lets_cookies_consent=' + encodeURIComponent(pending));
  await new Promise(r => setTimeout(r, 50));
  ok(w6.__posts.length === 1 && w6.__posts[0].body.action === 'reject_all', 'na página seguinte, o registro pendente é reenviado com a mesma ação');
  const done = decodeURIComponent(w6.document.cookie.match(/lets_cookies_consent=([^;]*)/)[1]);
  ok(JSON.parse(done).l === 1, 'depois da confirmação, deixa de ser pendente');
  let w7 = await page('lets_cookies_consent=' + encodeURIComponent(done));
  await new Promise(r => setTimeout(r, 50));
  ok(w7.__posts.length === 0, 'registro confirmado não é reenviado');

  console.log('Aceitar todos');
  let w4 = await page();
  click(w4, q(w4, '.lets-ck-banner button[data-lets-action="accept"]'));
  const a = w4.__posts[0].body;
  ok(a.action === 'accept_all' && a.choices.analytics && a.choices.embedded && !a.choices.marketing, 'aceitar todos só aceita categorias ativas no site');

  console.log(fail ? `\n${fail} FALHA(S)` : '\nTodos os testes passaram');
  process.exit(fail ? 1 : 0);
}
})();
