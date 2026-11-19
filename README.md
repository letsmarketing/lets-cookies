# Lets Cookies

Plugin WordPress de banner de cookies e registro de consentimento (LGPD) da Lets Marketing.

**Status:** protótipo `0.1.0`, para testes em homologação. Não usar em produção antes do piloto e da revisão jurídica.

## Princípios

1. **Zero requisições externas antes do consentimento.** Banner, estilos e scripts do plugin vêm do próprio site.
2. **Registro local.** O consentimento fica no banco do WordPress do cliente.
3. **Escolha real.** Aceitar, Rejeitar e Personalizar com o mesmo destaque; nada pré-marcado.
4. **O plugin controla o que carrega.** GA4, Clarity, RD Station, Meta e LinkedIn entram pelo painel, nunca colados no tema.

Os requisitos vêm do *Guia Orientativo Cookies e Proteção de Dados Pessoais* (ANPD, out/2022).

## O que já faz

- Banner de 1º nível (Rejeitar / Personalizar / Aceitar) e painel de 2º nível com uma chave por categoria, desligada por padrão.
- Categorias: Necessários (sempre ativos), Analíticos, Conteúdo incorporado e Marketing, ativadas por site.
- Integrações GA4, Microsoft Clarity, RD Station, Meta Pixel e LinkedIn Insight: os scripts saem como `type="text/plain"` e só rodam após o consentimento da categoria.
- Google Consent Mode v2 (tudo negado por padrão) e API de consentimento do Clarity.
- Iframes de YouTube (trocado por youtube-nocookie), Vimeo, Spotify e Google Maps no conteúdo viram um aviso com o botão "Carregar conteúdo".
- Registro de consentimento em tabela própria: ID aleatório, data UTC, versão da política, escolha por categoria, IP truncado em HMAC e hash encadeado. Exportação CSV e limpeza automática pelo prazo configurado.
- Versão da política: ao aumentar o número, o banner volta a pedir consentimento a todos.
- Revogação: botão flutuante e links "Preferências de cookies"; ao revogar, cookies próprios conhecidos são apagados e a página recarrega.

## Uso nos temas

```php
// Vídeo, mapa ou podcast já bloqueado:
echo lets_cookies_embed( 'https://www.youtube.com/embed/ID', array( 'title' => 'Vídeo institucional' ) );

// Script próprio bloqueado por categoria:
echo lets_cookies_script( 'analytics', 'console.log("só após consentimento");' );
```

Link que reabre as preferências (rodapé): `<a href="#lets-cookies">Preferências de cookies</a>` ou o shortcode `[lets_cookies_preferences]`.

Tabela de cookies na Política: shortcode `[lets_cookies_table]`.

Para estender o catálogo: filtro `lets_cookies_catalog`. Para outros provedores de iframe: filtro `lets_cookies_embed_providers`. Evento JS após cada escolha: `document.addEventListener('lets_cookies_consent', …)`.

## Desinstalação

O registro de consentimento **é mantido** ao desinstalar, porque é prova para o cliente. Para apagar tudo, defina `LETS_COOKIES_DELETE_DATA` como `true` no `wp-config.php` antes de remover.

## Próximos passos

- Verificador automático (navegador sem interface) que confirma zero requisições externas antes da escolha.
- Atualização pelo wp-admin (Plugin Update Checker), com o endereço em `LETS_COOKIES_UPDATE_URL`.
- Pacotes assinados.
- Confirmar os prazos marcados como "confirmar" no catálogo.
