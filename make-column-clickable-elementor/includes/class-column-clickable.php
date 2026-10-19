<?php
/**
 * Main Class
 *
 * @author   Fernando_Acosta
 * @since    1.0.0
 * @package  make-column-clickable-elementor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Make_Column_Clickable_Setup' ) ) :

	/**
	 * The main Make_Column_Clickable_Setup class
	 */
	class Make_Column_Clickable_Setup {
		/**
		 * Output buffers opened for elements rendered in "real link" mode.
		 *
		 * @var array
		 */
		protected $buffers = [];

		public function __construct() {
			add_action( 'wp_enqueue_scripts', [ $this, 'frontend_scripts' ] );
			add_action( 'elementor/frontend/after_enqueue_styles', [ $this, 'enqueue_frontend_style' ] );

			foreach ( [ 'column' => 'section_advanced', 'section' => 'section_advanced', 'container' => 'section_layout' ] as $element => $section ) {
				add_action( "elementor/element/{$element}/{$section}/after_section_end", [ $this, 'widget_extensions' ], 10, 2 );
				add_action( "elementor/frontend/{$element}/before_render", [ $this, 'before_render_options' ], 10 );
				add_action( "elementor/frontend/{$element}/after_render", [ $this, 'after_render_options' ], 10 );
			}

			add_action( 'elementor/init', [ $this, 'maybe_clear_elementor_cache' ] );
			add_action( 'elementor/editor/after_enqueue_scripts', [ $this, 'editor_scripts' ] );
			add_action( 'wp_ajax_make_column_clickable_dismiss_promo', [ $this, 'dismiss_promo' ] );
			add_filter( 'plugin_row_meta', [ $this, 'plugin_row_meta' ], 10, 2 );

			add_action( 'admin_notices', [ $this, 'upgrade_notice' ] );
			add_action( 'admin_post_make_column_clickable_set_mode', [ $this, 'set_site_mode' ] );
			add_action( 'admin_post_make_column_clickable_dismiss_notice', [ $this, 'dismiss_upgrade_notice' ] );
			add_filter( 'plugin_action_links_' . plugin_basename( Make_Column_Clickable_Elementor::get_main_file() ), [ $this, 'plugin_action_links' ] );
		}


		/**
		 * After layout callback
		 *
		 * @param  object  $element
		 * @param  array  $args
		 *
		 * @return void
		 */
		public function widget_extensions( $element, $args ) {
			$element_name = $element->get_name();
			$label = $element_name === 'container' ? __( 'Container Link', 'make-column-clickable-elementor' ) : __( 'Column Link', 'make-column-clickable-elementor' );

			$element->start_controls_section(
				'_section_column_clickable',
				[
					'label' => $label,
					'tab'   => Elementor\Controls_Manager::TAB_LAYOUT,
				]
			);

			$element->add_control(
				'column_link',
				[
					'label'       => $label,
					'type'        => Elementor\Controls_Manager::URL,
					'dynamic'     => [
						'active' => true,
					],
					'placeholder' => __( 'https://your-link.com', 'elementor' ),
					'description' => defined( 'ELEMENTOR_PRO_VERSION' ) ? __( 'Tip: to open a popup, click the Dynamic Tags icon and choose Actions → Popup.', 'make-column-clickable-elementor' ) : '',
				]
			);

			$element->add_control(
				'column_link_text',
				[
					'label'       => __( 'Link text', 'make-column-clickable-elementor' ),
					'type'        => Elementor\Controls_Manager::TEXT,
					'dynamic'     => [
						'active' => true,
					],
					'description' => __( 'Read by search engines and screen readers. Leave empty to use the first heading inside.', 'make-column-clickable-elementor' ),
					'condition'   => [
						'column_link[url]!' => '',
						'column_link_mode!' => 'js',
					],
				]
			);

			$element->add_control(
				'column_link_mode',
				[
					'label'       => __( 'Link mode', 'make-column-clickable-elementor' ),
					'type'        => Elementor\Controls_Manager::SELECT,
					'default'     => '',
					'options'     => [
						''     => 'js' === $this->get_site_mode() ? __( 'Site default (legacy)', 'make-column-clickable-elementor' ) : __( 'Site default (real link)', 'make-column-clickable-elementor' ),
						'link' => __( 'Real link (SEO)', 'make-column-clickable-elementor' ),
						'js'   => __( 'JavaScript (legacy)', 'make-column-clickable-elementor' ),
					],
					'description' => __( 'Real link adds an HTML link that search engines can follow and keyboard users can reach. Use the legacy mode only if it conflicts with your layout.', 'make-column-clickable-elementor' ),
					'condition'   => [
						'column_link[url]!' => '',
					],
				]
			);

			$element->add_control(
				'column_link_track',
				[
					'label'       => __( 'Track clicks', 'make-column-clickable-elementor' ),
					'type'        => Elementor\Controls_Manager::SWITCHER,
					'description' => __( 'Sends a "column_click" event to Google Analytics 4 or Google Tag Manager, if installed on your site.', 'make-column-clickable-elementor' ),
					'condition'   => [
						'column_link[url]!' => '',
					],
				]
			);

			if ( defined( 'Elementor\Controls_Manager::NOTICE' ) && $this->should_show_promo() ) {
				$element->add_control(
					'make_column_clickable_promo',
					[
						'type'        => Elementor\Controls_Manager::NOTICE,
						'notice_type' => 'info',
						'dismissible' => true,
						'content'     => 'Envie formulários do Elementor pelo WhatsApp. <a href="https://rishi.com.br/whatsapp-elementor" target="_blank">Conheça a Rishi</a>',
						'separator'   => 'before',
					]
				);
			}

			$element->end_controls_section();
		}


		public function before_render_options( $element ) {
			$settings = $element->get_settings_for_display();

			if ( ! isset( $settings['column_link'], $settings['column_link']['url'] ) || empty( $settings['column_link']['url'] ) ) {
				return;
			}

			wp_enqueue_script( 'make-column-clickable-elementor' );
			wp_enqueue_style( 'make-column-clickable-elementor' );

			$link = $settings['column_link'];
			$url  = esc_url( $link['url'] );

			// start of WPML
			do_action( 'wpml_register_single_string', 'Make Column Clickable Elementor',
				'Link - ' . $url, $url );
			$link['url'] = apply_filters( 'wpml_translate_single_string',
				$url, 'Make Column Clickable Elementor',
				'Link - ' . $url );
			// end of WPML

			$track = ! empty( $settings['column_link_track'] ) && 'yes' === $settings['column_link_track'];

			$element->add_render_attribute( '_wrapper', 'class', 'make-column-clickable-elementor' );
			$element->add_render_attribute( '_wrapper', 'data-column-clickable', esc_url( $link['url'] ) );
			$element->add_render_attribute( '_wrapper', 'data-column-clickable-blank',
				! empty( $link['is_external'] ) ? '_blank' : '_self' );

			if ( $track ) {
				$element->add_render_attribute( '_wrapper', 'data-column-clickable-track', '1' );
			}

			if ( ! $this->should_render_link( $element, $settings ) ) {
				return;
			}

			$element->add_link_attributes( 'make_column_clickable_link', $link );
			$element->add_render_attribute( 'make_column_clickable_link', 'class', 'make-column-clickable-link' );

			if ( $track ) {
				$element->add_render_attribute( 'make_column_clickable_link', 'data-column-clickable-track', '1' );
			}

			$this->buffers[] = [
				'id'   => $element->get_id(),
				'text' => isset( $settings['column_link_text'] ) ? trim( wp_strip_all_tags( $settings['column_link_text'] ) ) : '',
			];

			ob_start();
		}


		/**
		 * Inject the real link right after the wrapper opening tag.
		 *
		 * @param  object  $element
		 *
		 * @return void
		 */
		public function after_render_options( $element ) {
			$buffer = end( $this->buffers );

			if ( ! $buffer || $buffer['id'] !== $element->get_id() ) {
				return;
			}

			array_pop( $this->buffers );

			$content = ob_get_clean();

			if ( '' === trim( $content ) ) {
				return;
			}

			$text = '' !== $buffer['text'] ? $buffer['text'] : $this->get_link_text( $content );

			$link = sprintf(
				'<a %s><span class="make-column-clickable-text">%s</span></a>',
				$element->get_render_attribute_string( 'make_column_clickable_link' ),
				esc_html( $text )
			);

			// PHPCS - The content has already been escaped by Elementor and the link is built from escaped attributes.
			echo preg_replace( '/^(\s*<[a-z][^>]*>)/i', '$1' . str_replace( [ '\\', '$' ], [ '\\\\', '\\$' ], $link ), $content, 1 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}


		/**
		 * Whether the real <a> link should be added to the element.
		 *
		 * @param  object  $element
		 * @param  array  $settings
		 *
		 * @return bool
		 */
		protected function should_render_link( $element, $settings ) {
			$mode = ! empty( $settings['column_link_mode'] ) ? $settings['column_link_mode'] : $this->get_site_mode();

			if ( 'js' === $mode ) {
				return false;
			}

			// Containers can be rendered as <a> or <button> natively, and links can't be nested.
			if ( isset( $settings['html_tag'] ) && in_array( $settings['html_tag'], [ 'a', 'button' ], true ) ) {
				return false;
			}

			$elementor = \Elementor\Plugin::$instance;

			if ( $elementor->editor->is_edit_mode() || $elementor->preview->is_preview_mode() ) {
				return false;
			}

			return (bool) apply_filters( 'make_column_clickable_render_link', true, $element );
		}


		/**
		 * Get the link text from the first heading, or from the first words of the content.
		 *
		 * @param  string  $content
		 *
		 * @return string
		 */
		protected function get_link_text( $content ) {
			if ( preg_match( '/<h[1-6][^>]*>(.*?)<\/h[1-6]>/is', $content, $matches ) ) {
				$text = trim( wp_strip_all_tags( $matches[1] ) );
			}

			if ( empty( $text ) ) {
				$text = wp_trim_words( wp_strip_all_tags( $content ), 12, '…' );
			}

			$text = trim( html_entity_decode( $text, ENT_QUOTES, get_bloginfo( 'charset' ) ) );

			return '' !== $text ? $text : __( 'Learn more', 'make-column-clickable-elementor' );
		}


		public function frontend_scripts() {
			wp_register_script( 'make-column-clickable-elementor',
				plugins_url( 'assets/js/make-column-clickable.js', plugin_dir_path( __FILE__ ) ), [],
				Make_Column_Clickable_Elementor::VERSION, true );

			wp_register_style( 'make-column-clickable-elementor',
				plugins_url( 'assets/css/make-column-clickable.css', plugin_dir_path( __FILE__ ) ), [],
				Make_Column_Clickable_Elementor::VERSION );
		}


		/**
		 * Load the stylesheet in the <head> of Elementor pages, avoiding a flash of unstyled links.
		 *
		 * @return void
		 */
		public function enqueue_frontend_style() {
			wp_enqueue_style( 'make-column-clickable-elementor' );
		}


		/**
		 * Clear the Elementor cache once after each plugin update, so cached pages get the new markup.
		 *
		 * @return void
		 */
		public function maybe_clear_elementor_cache() {
			$previous_version = get_option( 'make_column_clickable_version' );

			if ( Make_Column_Clickable_Elementor::VERSION === $previous_version ) {
				return;
			}

			update_option( 'make_column_clickable_version', Make_Column_Clickable_Elementor::VERSION );

			// Versions before 1.7.0 didn't save the version. Tell existing users that links changed.
			if ( false === $previous_version && $this->is_plugin_in_use() ) {
				update_option( 'make_column_clickable_upgrade_notice', 1 );
			}

			\Elementor\Plugin::$instance->files_manager->clear_cache();
		}


		/**
		 * Whether any Elementor element has a column link.
		 *
		 * @return bool
		 */
		protected function is_plugin_in_use() {
			global $wpdb;

			return (bool) $wpdb->get_var( $wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_elementor_data' AND meta_value LIKE %s LIMIT 1",
				'%' . $wpdb->esc_like( '"column_link":' ) . '%'
			) );
		}


		/**
		 * Site-wide link mode, used by elements that don't choose one.
		 *
		 * @return string "link" or "js".
		 */
		protected function get_site_mode() {
			return 'js' === get_option( 'make_column_clickable_mode' ) ? 'js' : 'link';
		}


		protected function get_set_mode_url( $mode ) {
			return wp_nonce_url( admin_url( 'admin-post.php?action=make_column_clickable_set_mode&mode=' . $mode ), 'make_column_clickable_set_mode' );
		}


		public function set_site_mode() {
			check_admin_referer( 'make_column_clickable_set_mode' );

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'make-column-clickable-elementor' ) );
			}

			$mode = isset( $_GET['mode'] ) && 'js' === $_GET['mode'] ? 'js' : 'link';

			update_option( 'make_column_clickable_mode', $mode );
			delete_option( 'make_column_clickable_upgrade_notice' );

			if ( class_exists( '\Elementor\Plugin' ) ) {
				\Elementor\Plugin::$instance->files_manager->clear_cache();
			}

			wp_safe_redirect( add_query_arg( 'make_column_clickable_mode', $mode, wp_get_referer() ? wp_get_referer() : admin_url( 'plugins.php' ) ) );
			exit;
		}


		public function dismiss_upgrade_notice() {
			check_admin_referer( 'make_column_clickable_dismiss_notice' );

			if ( current_user_can( 'manage_options' ) ) {
				delete_option( 'make_column_clickable_upgrade_notice' );
			}

			wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url() );
			exit;
		}


		public function upgrade_notice() {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}

			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only displays a message after the nonce-checked redirect.
			$changed = isset( $_GET['make_column_clickable_mode'] ) ? sanitize_key( $_GET['make_column_clickable_mode'] ) : '';

			if ( 'js' === $changed ) {
				printf(
					'<div class="notice notice-success"><p>%s <a href="%s">%s</a></p></div>',
					esc_html__( 'Make Column Clickable: legacy mode enabled. Your clickable elements work like before.', 'make-column-clickable-elementor' ),
					esc_url( $this->get_set_mode_url( 'link' ) ),
					esc_html__( 'Undo', 'make-column-clickable-elementor' )
				);
				return;
			}

			if ( 'link' === $changed ) {
				printf(
					'<div class="notice notice-success"><p>%s</p></div>',
					esc_html__( 'Make Column Clickable: real links enabled.', 'make-column-clickable-elementor' )
				);
				return;
			}

			$screen = get_current_screen();

			if ( ! get_option( 'make_column_clickable_upgrade_notice' ) || ! $screen || ! in_array( $screen->id, [ 'dashboard', 'plugins', 'edit-page', 'edit-post' ], true ) ) {
				return;
			}

			$dismiss_url = wp_nonce_url( admin_url( 'admin-post.php?action=make_column_clickable_dismiss_notice' ), 'make_column_clickable_dismiss_notice' );
			?>
			<div class="notice notice-info">
				<p>
					<strong><?php esc_html_e( 'Make Column Clickable for Elementor 1.7: your clickable columns now use real links.', 'make-column-clickable-elementor' ); ?></strong>
					<?php esc_html_e( 'Search engines can now follow them, keyboard users can reach them, and Ctrl/Cmd + click opens a new tab. Did something change in your layout? Switch back to the legacy mode with one click.', 'make-column-clickable-elementor' ); ?>
				</p>
				<p>
					<a href="<?php echo esc_url( $dismiss_url ); ?>" class="button button-primary"><?php esc_html_e( 'Everything looks good', 'make-column-clickable-elementor' ); ?></a>
					<a href="<?php echo esc_url( $this->get_set_mode_url( 'js' ) ); ?>" class="button"><?php esc_html_e( 'Switch back to legacy mode', 'make-column-clickable-elementor' ); ?></a>
				</p>
			</div>
			<?php
		}


		public function plugin_action_links( $links ) {
			if ( 'js' === $this->get_site_mode() ) {
				$links[] = '<a href="' . esc_url( $this->get_set_mode_url( 'link' ) ) . '">' . esc_html__( 'Use real links', 'make-column-clickable-elementor' ) . '</a>';
			} else {
				$links[] = '<a href="' . esc_url( $this->get_set_mode_url( 'js' ) ) . '">' . esc_html__( 'Use legacy mode', 'make-column-clickable-elementor' ) . '</a>';
			}

			return $links;
		}


		/**
		 * Whether the editor notice about Rishi should be displayed.
		 *
		 * Only for Brazilian sites, and until the user dismisses it.
		 *
		 * @return bool
		 */
		protected function should_show_promo() {
			$is_brazilian = 'pt_BR' === get_locale() || 'pt_BR' === get_user_locale();

			$show = $is_brazilian
				&& current_user_can( 'manage_options' )
				&& ! get_user_meta( get_current_user_id(), 'make_column_clickable_promo_dismissed', true );

			return (bool) apply_filters( 'make_column_clickable_show_promo', $show );
		}


		public function editor_scripts() {
			if ( ! $this->should_show_promo() ) {
				return;
			}

			wp_enqueue_script( 'make-column-clickable-elementor-editor',
				plugins_url( 'assets/js/editor.js', plugin_dir_path( __FILE__ ) ), [ 'jquery' ],
				Make_Column_Clickable_Elementor::VERSION, true );

			wp_localize_script( 'make-column-clickable-elementor-editor', 'makeColumnClickable', [
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'make_column_clickable_dismiss_promo' ),
			] );
		}


		public function dismiss_promo() {
			check_ajax_referer( 'make_column_clickable_dismiss_promo', 'nonce' );

			update_user_meta( get_current_user_id(), 'make_column_clickable_promo_dismissed', 1 );

			wp_send_json_success();
		}


		public function plugin_row_meta( $links, $file ) {
			if ( plugin_basename( Make_Column_Clickable_Elementor::get_main_file() ) === $file && $this->should_show_promo() ) {
				$links[] = '<a href="https://rishi.com.br/whatsapp-elementor" target="_blank">Envie formulários do Elementor pelo WhatsApp</a>';
			}

			return $links;
		}
	}

endif;

new Make_Column_Clickable_Setup();
