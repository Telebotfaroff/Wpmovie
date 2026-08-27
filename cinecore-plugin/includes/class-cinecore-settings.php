<?php
defined('ABSPATH') || exit;

/**
 * Settings Page Class - Phase 2 + Phase 5.
 *
 * Handles CineCore Settings submenu and API keys storage via Settings API.
 *
 * @since 1.0.0
 */
class CineCore_Settings {

	/**
	 * Option name - TMDB.
	 *
	 * @var string
	 */
	const OPTION_KEY = 'cinecore_tmdb_api_key';

	/**
	 * Option name - Telegram Bot Token.
	 *
	 * @var string
	 */
	const OPTION_BOT_TOKEN = 'cinecore_telegram_bot_token';

	/**
	 * Option name - Telegram Chat ID.
	 *
	 * @var string
	 */
	const OPTION_CHAT_ID = 'cinecore_telegram_chat_id';

	/**
	 * Settings group.
	 *
	 * @var string
	 */
	const SETTINGS_GROUP = 'cinecore_settings_group';

	/**
	 * Page slug.
	 *
	 * @var string
	 */
	const PAGE_SLUG = 'cinecore-settings';

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Add submenu under Settings.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function add_settings_page() {
		add_options_page(
			__( 'CineCore Settings', 'cinecore' ),
			__( 'CineCore Settings', 'cinecore' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register settings via Settings API.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_settings() {
		// TMDB API Key.
		register_setting(
			self::SETTINGS_GROUP,
			self::OPTION_KEY,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_api_key' ),
				'default'           => '',
				'show_in_rest'      => false,
			)
		);

		// Telegram Bot Token - Phase 5.
		register_setting(
			self::SETTINGS_GROUP,
			self::OPTION_BOT_TOKEN,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_bot_token' ),
				'default'           => '',
				'show_in_rest'      => false,
			)
		);

		// Telegram Chat ID - Phase 5.
		register_setting(
			self::SETTINGS_GROUP,
			self::OPTION_CHAT_ID,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_chat_id' ),
				'default'           => '',
				'show_in_rest'      => false,
			)
		);

		// Section: TMDB.
		add_settings_section(
			'cinecore_main_section',
			__( 'TMDB API Configuration', 'cinecore' ),
			array( $this, 'render_section' ),
			self::PAGE_SLUG
		);

		add_settings_field(
			self::OPTION_KEY,
			__( 'TMDB API Key', 'cinecore' ),
			array( $this, 'render_field' ),
			self::PAGE_SLUG,
			'cinecore_main_section'
		);

		// Section: Telegram - Phase 5.
		add_settings_section(
			'cinecore_telegram_section',
			__( 'Telegram Auto-Post', 'cinecore' ),
			array( $this, 'render_telegram_section' ),
			self::PAGE_SLUG
		);

		add_settings_field(
			self::OPTION_BOT_TOKEN,
			__( 'Telegram Bot Token', 'cinecore' ),
			array( $this, 'render_bot_token_field' ),
			self::PAGE_SLUG,
			'cinecore_telegram_section'
		);

		add_settings_field(
			self::OPTION_CHAT_ID,
			__( 'Telegram Chat ID', 'cinecore' ),
			array( $this, 'render_chat_id_field' ),
			self::PAGE_SLUG,
			'cinecore_telegram_section'
		);
	}

	/**
	 * Sanitize API key.
	 *
	 * @since 1.0.0
	 * @param string $value Raw value.
	 * @return string
	 */
	public function sanitize_api_key( $value ) {
		$value = sanitize_text_field( trim( (string) $value ) );
		return $value;
	}

	/**
	 * Sanitize Telegram Bot Token.
	 *
	 * @since 1.0.0
	 * @param string $value Raw value.
	 * @return string
	 */
	public function sanitize_bot_token( $value ) {
		$value = sanitize_text_field( trim( (string) $value ) );
		// Bot token format: 123456789:AAH... - allow alphanumeric, colon, underscore, dash.
		$value = preg_replace( '/[^A-Za-z0-9:_\-]/', '', $value );
		return $value;
	}

	/**
	 * Sanitize Telegram Chat ID.
	 *
	 * @since 1.0.0
	 * @param string $value Raw value.
	 * @return string
	 */
	public function sanitize_chat_id( $value ) {
		$value = sanitize_text_field( trim( (string) $value ) );
		// Chat ID can be negative (e.g., -100123...) and @username.
		$value = preg_replace( '/[^A-Za-z0-9_\-@]/', '', $value );
		return $value;
	}

	/**
	 * Render TMDB section description.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_section() {
		echo '<p>' . esc_html__( 'Enter your TMDB API Key. You can generate one from https://www.themoviedb.org/settings/api', 'cinecore' ) . '</p>';
	}

	/**
	 * Render Telegram section description.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_telegram_section() {
		echo '<p>' . esc_html__( 'Configure Telegram Bot for auto-posting when movies or TV shows are published. Create a bot via @BotFather.', 'cinecore' ) . '</p>';
	}

	/**
	 * Render API key field.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_field() {
		$value = get_option( self::OPTION_KEY, '' );
		?>
		<input
			type="password"
			name="<?php echo esc_attr( self::OPTION_KEY ); ?>"
			id="<?php echo esc_attr( self::OPTION_KEY ); ?>"
			value="<?php echo esc_attr( $value ); ?>"
			class="regular-text"
			placeholder="<?php esc_attr_e( 'Enter TMDB API Key', 'cinecore' ); ?>"
			autocomplete="off"
			spellcheck="false"
		/>
		<p class="description"><?php esc_html_e( 'Stored securely in wp_options. Never exposed to frontend.', 'cinecore' ); ?></p>
		<?php
	}

	/**
	 * Render Bot Token field.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_bot_token_field() {
		$value = get_option( self::OPTION_BOT_TOKEN, '' );
		?>
		<input
			type="password"
			name="<?php echo esc_attr( self::OPTION_BOT_TOKEN ); ?>"
			id="<?php echo esc_attr( self::OPTION_BOT_TOKEN ); ?>"
			value="<?php echo esc_attr( $value ); ?>"
			class="regular-text"
			placeholder="<?php esc_attr_e( 'e.g. 123456789:AAHdq...', 'cinecore' ); ?>"
			autocomplete="off"
			spellcheck="false"
		/>
		<p class="description"><?php esc_html_e( 'Bot token from @BotFather. Keep secret.', 'cinecore' ); ?></p>
		<?php
	}

	/**
	 * Render Chat ID field.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_chat_id_field() {
		$value = get_option( self::OPTION_CHAT_ID, '' );
		?>
		<input
			type="text"
			name="<?php echo esc_attr( self::OPTION_CHAT_ID ); ?>"
			id="<?php echo esc_attr( self::OPTION_CHAT_ID ); ?>"
			value="<?php echo esc_attr( $value ); ?>"
			class="regular-text"
			placeholder="<?php esc_attr_e( 'e.g. -100123456789 or @channel', 'cinecore' ); ?>"
			autocomplete="off"
			spellcheck="false"
		/>
		<p class="description"><?php esc_html_e( 'Channel/group Chat ID where posts will be sent.', 'cinecore' ); ?></p>
		<?php
	}

	/**
	 * Render settings page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'CineCore Settings', 'cinecore' ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( self::SETTINGS_GROUP );
				do_settings_sections( self::PAGE_SLUG );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}
}

