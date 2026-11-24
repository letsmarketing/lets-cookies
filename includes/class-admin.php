<?php
/**
 * Painel em Configurações > Lets Cookies.
 *
 * @package LetsCookies
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Lets_Cookies_Admin {

	const PAGE = 'lets-cookies';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( LETS_COOKIES_FILE ), array( __CLASS__, 'action_links' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
	}

	/**
	 * Avisa quando falta o que o Guia da ANPD pede no banner: link para a
	 * Política e para o exercício de direitos (pág. 31).
	 */
	public static function notices() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s = Lets_Cookies_Settings::get();
		if ( '' === $s['policy_url'] ) {
			printf(
				'<div class="notice notice-warning"><p><strong>Lets Cookies:</strong> preencha o link da Política de Cookies em <a href="%s">Configurações &gt; Lets Cookies</a>. Sem ele, o banner fica sem o link para a política e para o exercício de direitos.</p></div>',
				esc_url( admin_url( 'options-general.php?page=' . self::PAGE ) )
			);
		}
	}

	public static function menu() {
		add_options_page( 'Lets Cookies', 'Lets Cookies', 'manage_options', self::PAGE, array( __CLASS__, 'render' ) );
	}

	public static function register() {
		register_setting(
			'lets_cookies',
			Lets_Cookies_Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'Lets_Cookies_Settings', 'sanitize' ),
				'default'           => Lets_Cookies_Settings::defaults(),
			)
		);
	}

	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'options-general.php?page=' . self::PAGE ) ) . '">Configurações</a>' );
		return $links;
	}

	private static function field_name( $key, $sub = null ) {
		$name = Lets_Cookies_Settings::OPTION . '[' . $key . ']';
		return null === $sub ? $name : $name . '[' . $sub . ']';
	}

	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s      = Lets_Cookies_Settings::get();
		$tab    = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'geral'; // phpcs:ignore WordPress.Security.NonceVerification
		$tabs   = array(
			'geral'      => 'Geral',
			'textos'     => 'Textos',
			'integracoes' => 'Integrações',
			'registro'   => 'Registro de consentimento',
		);
		$labels = Lets_Cookies_Settings::category_labels();
		?>
		<div class="wrap">
			<h1>Lets Cookies</h1>
			<nav class="nav-tab-wrapper">
				<?php foreach ( $tabs as $key => $label ) : ?>
					<a class="nav-tab <?php echo $tab === $key ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'options-general.php?page=' . self::PAGE . '&tab=' . $key ) ); ?>"><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</nav>

			<?php if ( 'registro' === $tab ) : ?>
				<?php self::render_log(); ?>
			<?php else : ?>
			<form method="post" action="options.php">
				<?php settings_fields( 'lets_cookies' ); ?>
				<?php
				// Campos das outras abas seguem ocultos, para não serem apagados ao salvar.
				self::hidden_fields_except( $tab, $s );
				?>
				<table class="form-table" role="presentation">
				<?php if ( 'geral' === $tab ) : ?>
					<tr>
						<th scope="row">Categorias ativas</th>
						<td>
							<p><label><input type="checkbox" checked disabled> <?php echo esc_html( $labels['necessary'] ); ?> (sempre ativos)</label></p>
							<?php foreach ( Lets_Cookies_Settings::CATEGORIES as $cat ) : ?>
								<p><label><input type="checkbox" name="<?php echo esc_attr( self::field_name( 'enabled_categories' ) ); ?>[]" value="<?php echo esc_attr( $cat ); ?>" <?php checked( in_array( $cat, (array) $s['enabled_categories'], true ) ); ?>> <?php echo esc_html( $labels[ $cat ] ); ?></label></p>
							<?php endforeach; ?>
							<p class="description">Ative só as categorias que o site realmente usa.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="lc-policy">Link da Política de Cookies</label></th>
						<td><input id="lc-policy" type="url" class="regular-text" name="<?php echo esc_attr( self::field_name( 'policy_url' ) ); ?>" value="<?php echo esc_attr( $s['policy_url'] ); ?>" placeholder="https://site.com.br/aviso-de-privacidade/#politica-de-cookies">
						<p class="description">Pode ser uma âncora dentro do Aviso de Privacidade.</p></td>
					</tr>
					<tr>
						<th scope="row"><label for="lc-rights">Link para exercer direitos</label></th>
						<td><input id="lc-rights" type="url" class="regular-text" name="<?php echo esc_attr( self::field_name( 'rights_url' ) ); ?>" value="<?php echo esc_attr( $s['rights_url'] ); ?>" placeholder="Página ou e-mail do encarregado (mailto:)">
						<p class="description">Canal do encarregado (DPO) para revogação, eliminação e oposição.</p></td>
					</tr>
					<tr>
						<th scope="row"><label for="lc-version">Versão da política</label></th>
						<td><input id="lc-version" type="number" min="1" class="small-text" name="<?php echo esc_attr( self::field_name( 'policy_version' ) ); ?>" value="<?php echo esc_attr( $s['policy_version'] ); ?>">
						<p class="description">Aumente o número quando a política ou os cookies mudarem: o banner volta a pedir consentimento a todos.</p></td>
					</tr>
					<tr>
						<th scope="row"><label for="lc-retention">Guarda do registro (dias)</label></th>
						<td><input id="lc-retention" type="number" min="30" max="3650" class="small-text" name="<?php echo esc_attr( self::field_name( 'retention_days' ) ); ?>" value="<?php echo esc_attr( $s['retention_days'] ); ?>">
						<p class="description">Registros mais antigos são apagados automaticamente. Use o prazo definido na política do cliente.</p></td>
					</tr>
					<tr>
						<th scope="row">Posição do banner</th>
						<td><select name="<?php echo esc_attr( self::field_name( 'position' ) ); ?>">
							<?php foreach ( array( 'bottom-right' => 'Canto inferior direito', 'bottom-left' => 'Canto inferior esquerdo', 'bottom-bar' => 'Barra no rodapé' ) as $value => $label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $s['position'], $value ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select></td>
					</tr>
					<tr>
						<th scope="row">Botão flutuante</th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( self::field_name( 'show_floating' ) ); ?>" value="1" <?php checked( $s['show_floating'], 1 ); ?>> Mostrar botão para reabrir as preferências</label>
						<p class="description">Se desligar, coloque o shortcode [lets_cookies_preferences] ou um link para #lets-cookies no rodapé.</p></td>
					</tr>
					<tr>
						<th scope="row">Conteúdo incorporado</th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( self::field_name( 'click_to_load' ) ); ?>" value="1" <?php checked( $s['click_to_load'], 1 ); ?>> Carregar vídeos, mapas e podcasts só quando o visitante clicar</label>
						<p class="description">Mesmo depois do consentimento, cada conteúdo espera o clique do visitante. Se desligar, os conteúdos carregam sozinhos para quem já autorizou a categoria.</p></td>
					</tr>
					<?php foreach ( array( 'color_primary' => 'Cor principal', 'color_text' => 'Cor do texto', 'color_background' => 'Cor de fundo' ) as $key => $label ) : ?>
					<tr>
						<th scope="row"><label for="lc-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
						<td><input id="lc-<?php echo esc_attr( $key ); ?>" type="color" name="<?php echo esc_attr( self::field_name( $key ) ); ?>" value="<?php echo esc_attr( $s[ $key ] ); ?>"></td>
					</tr>
					<?php endforeach; ?>

				<?php elseif ( 'textos' === $tab ) : ?>
					<?php
					$text_labels = array(
						'title'          => 'Título do banner',
						'message'        => 'Mensagem do banner',
						'policy_link'    => 'Texto do link da política',
						'rights_link'    => 'Texto do link de direitos',
						'accept_all'     => 'Botão aceitar',
						'reject_all'     => 'Botão rejeitar',
						'customize'      => 'Botão personalizar',
						'save'           => 'Botão salvar preferências',
						'prefs_title'    => 'Título das preferências',
						'prefs_intro'    => 'Introdução das preferências',
						'always_active'  => 'Rótulo "sempre ativos"',
						'browser_info'   => 'Bloqueio pelo navegador',
						'floating_label' => 'Rótulo do botão flutuante',
						'cat_necessary'  => 'Descrição: Necessários',
						'cat_analytics'  => 'Descrição: Analíticos',
						'cat_embedded'   => 'Descrição: Conteúdo incorporado',
						'cat_marketing'  => 'Descrição: Marketing',
					);
					foreach ( $text_labels as $key => $label ) :
						$long = in_array( $key, array( 'message', 'prefs_intro', 'browser_info', 'cat_necessary', 'cat_analytics', 'cat_embedded', 'cat_marketing' ), true );
						?>
					<tr>
						<th scope="row"><label for="lc-t-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
						<td>
						<?php if ( $long ) : ?>
							<textarea id="lc-t-<?php echo esc_attr( $key ); ?>" class="large-text" rows="3" name="<?php echo esc_attr( self::field_name( 'texts', $key ) ); ?>"><?php echo esc_textarea( $s['texts'][ $key ] ); ?></textarea>
						<?php else : ?>
							<input id="lc-t-<?php echo esc_attr( $key ); ?>" type="text" class="regular-text" name="<?php echo esc_attr( self::field_name( 'texts', $key ) ); ?>" value="<?php echo esc_attr( $s['texts'][ $key ] ); ?>">
						<?php endif; ?>
						</td>
					</tr>
					<?php endforeach; ?>

				<?php elseif ( 'integracoes' === $tab ) : ?>
					<?php
					$fields = array(
						'ga4'       => array( 'Google Analytics 4', 'G-XXXXXXXXXX', 'Analíticos' ),
						'clarity'   => array( 'Microsoft Clarity', 'abcdefghij', 'Analíticos' ),
						'rdstation' => array( 'RD Station (código do loader)', 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx', 'Marketing' ),
						'meta'      => array( 'Meta Pixel', '123456789012345', 'Marketing' ),
						'linkedin'  => array( 'LinkedIn Insight (partner ID)', '1234567', 'Marketing' ),
					);
					foreach ( $fields as $key => $f ) :
						?>
					<tr>
						<th scope="row"><label for="lc-i-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $f[0] ); ?></label></th>
						<td><input id="lc-i-<?php echo esc_attr( $key ); ?>" type="text" class="regular-text code" name="<?php echo esc_attr( self::field_name( 'integrations', $key ) ); ?>" value="<?php echo esc_attr( $s['integrations'][ $key ] ); ?>" placeholder="<?php echo esc_attr( $f[1] ); ?>">
						<p class="description">Categoria: <?php echo esc_html( $f[2] ); ?>. Só carrega depois do consentimento. A categoria precisa estar ativa na aba Geral.</p></td>
					</tr>
					<?php endforeach; ?>
					<tr><td colspan="2"><p><strong>Regra da Lets:</strong> nenhum código de rastreamento colado direto no tema. Scripts próprios usam <code>lets_cookies_script( 'analytics', $codigo )</code>.</p></td></tr>
				<?php endif; ?>
				</table>
				<?php submit_button( 'Salvar' ); ?>
			</form>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Repete como campos ocultos os valores das abas que não estão na tela,
	 * já que o formulário grava a opção inteira de uma vez.
	 */
	private static function hidden_fields_except( $tab, $s ) {
		$name = Lets_Cookies_Settings::OPTION;
		$out  = array();
		if ( 'geral' !== $tab ) {
			foreach ( (array) $s['enabled_categories'] as $cat ) {
				$out[] = array( $name . '[enabled_categories][]', $cat );
			}
			foreach ( array( 'policy_url', 'rights_url', 'policy_version', 'retention_days', 'position', 'color_primary', 'color_text', 'color_background' ) as $key ) {
				$out[] = array( $name . '[' . $key . ']', $s[ $key ] );
			}
			if ( $s['show_floating'] ) {
				$out[] = array( $name . '[show_floating]', '1' );
			}
			if ( $s['click_to_load'] ) {
				$out[] = array( $name . '[click_to_load]', '1' );
			}
		}
		if ( 'textos' !== $tab ) {
			foreach ( $s['texts'] as $key => $value ) {
				$out[] = array( $name . '[texts][' . $key . ']', $value );
			}
		}
		if ( 'integracoes' !== $tab ) {
			foreach ( $s['integrations'] as $key => $value ) {
				$out[] = array( $name . '[integrations][' . $key . ']', $value );
			}
		}
		foreach ( $out as $field ) {
			printf( '<input type="hidden" name="%s" value="%s">', esc_attr( $field[0] ), esc_attr( $field[1] ) );
		}
	}

	private static function render_log() {
		$rows  = Lets_Cookies_Consent_Log::recent( 50 );
		$total = Lets_Cookies_Consent_Log::count();
		$s     = Lets_Cookies_Settings::get();
		$yes   = function ( $v ) {
			return $v ? 'Sim' : 'Não';
		};
		$acts  = array(
			'accept_all' => 'Aceitou todos',
			'reject_all' => 'Rejeitou',
			'custom'     => 'Personalizou',
			'revoke'     => 'Revogou',
		);
		?>
		<p><?php echo esc_html( sprintf( '%d registros guardados. Registros com mais de %d dias são apagados automaticamente.', $total, $s['retention_days'] ) ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="lets_cookies_export">
			<?php wp_nonce_field( 'lets_cookies_export' ); ?>
			<?php submit_button( 'Exportar CSV', 'secondary', 'submit', false ); ?>
		</form>
		<h2>Últimos 50 registros</h2>
		<table class="widefat striped">
			<thead><tr><th>Data (UTC)</th><th>ID do visitante</th><th>Ação</th><th>Analíticos</th><th>Conteúdo incorporado</th><th>Marketing</th><th>Versão</th><th>Página</th></tr></thead>
			<tbody>
			<?php if ( ! $rows ) : ?>
				<tr><td colspan="8">Nenhum registro ainda.</td></tr>
			<?php endif; ?>
			<?php foreach ( $rows as $r ) : ?>
				<tr>
					<td><?php echo esc_html( $r['created_at'] ); ?></td>
					<td><code><?php echo esc_html( $r['consent_id'] ); ?></code></td>
					<td><?php echo esc_html( isset( $acts[ $r['action'] ] ) ? $acts[ $r['action'] ] : $r['action'] ); ?></td>
					<td><?php echo esc_html( $yes( $r['analytics'] ) ); ?></td>
					<td><?php echo esc_html( $yes( $r['embedded'] ) ); ?></td>
					<td><?php echo esc_html( $yes( $r['marketing'] ) ); ?></td>
					<td><?php echo esc_html( $r['policy_version'] ); ?></td>
					<td><?php echo esc_html( $r['page'] ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}
}
