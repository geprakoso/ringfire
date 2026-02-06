<?php
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Wpcag_Backend' ) ) {
	class Wpcag_Backend {
		protected static $settings = [];
		protected static $instance = null;

		public static function instance() {
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		public function __construct() {
			self::$settings = (array) get_option( 'wpcag_settings', [] );

			add_action( 'init', [ $this, 'init' ] );
			add_action( 'admin_init', [ $this, 'register_settings' ] );
			add_action( 'admin_menu', [ $this, 'admin_menu' ] );
			add_filter( 'plugin_action_links', [ $this, 'action_links' ], 10, 2 );
			add_filter( 'plugin_row_meta', [ $this, 'row_meta' ], 10, 2 );
			add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
			add_action( 'woocommerce_init', [ $this, 'woo_init' ] );
			add_action( 'wpc-attribute-group_add_form_fields', [ $this, 'add_form_fields' ] );
			add_action( 'wpc-attribute-group_edit_form_fields', [ $this, 'edit_form_fields' ] );
			add_action( 'edit_wpc-attribute-group', [ $this, 'save_form_fields' ] );
			add_action( 'create_wpc-attribute-group', [ $this, 'save_form_fields' ] );
			add_filter( 'manage_edit-wpc-attribute-group_columns', [ $this, 'group_columns' ] );
			add_filter( 'manage_wpc-attribute-group_custom_column', [ $this, 'group_columns_content' ], 10, 3 );
			add_filter( 'parse_term_query', [ $this, 'parse_term_query' ], 99 );
			add_action( 'woocommerce_product_options_attributes', [ $this, 'add_tools' ] );

			// ajax
			add_action( 'wp_ajax_wpcag_order', [ $this, 'ajax_order' ] );
			add_action( 'wp_ajax_wpcag_order_attrs', [ $this, 'ajax_order_attrs' ] );
			add_action( 'wp_ajax_wpcag_add_group_attributes', [ $this, 'ajax_add_group_attributes' ] );
			add_action( 'wp_ajax_wpcag_search_term', [ $this, 'ajax_search_term' ] );
		}

		function init() {
			// load text-domain
			load_plugin_textdomain( 'wpc-attribute-groups', false, basename( WPCAG_DIR ) . '/languages/' );
		}

		public static function get_settings() {
			return apply_filters( 'wpcag_get_settings', self::$settings );
		}

		public static function get_setting( $name, $default = false ) {
			if ( ! empty( self::$settings ) && isset( self::$settings[ $name ] ) ) {
				$setting = self::$settings[ $name ];
			} else {
				$setting = get_option( 'wpcag_' . $name, $default );
			}

			return apply_filters( 'wpcag_get_setting', $setting, $name, $default );
		}

		public static function get_groups() {
			return get_terms( [
				'taxonomy'   => 'wpc-attribute-group',
				'hide_empty' => false
			] );
		}

		function register_settings() {
			register_setting( 'wpcag_settings', 'wpcag_settings' );
		}

		function admin_menu() {
			add_submenu_page( 'wpclever', esc_html__( 'WPC Smart Attribute Groups', 'wpc-attribute-groups' ), esc_html__( 'Smart Attribute Groups', 'wpc-attribute-groups' ), 'manage_options', 'wpclever-wpcag', [
				$this,
				'admin_menu_content'
			] );
		}

		function admin_menu_content() {
			$active_tab = sanitize_key( $_GET['tab'] ?? 'settings' );
			?>
            <div class="wpclever_settings_page wrap">
                <h1 class="wpclever_settings_page_title"><?php echo esc_html__( 'WPC Smart Attribute Groups', 'wpc-attribute-groups' ) . ' ' . esc_html( WPCAG_VERSION ) . ' ' . ( defined( 'WPCAG_PREMIUM' ) ? '<span class="premium" style="display: none">' . esc_html__( 'Premium', 'wpc-attribute-groups' ) . '</span>' : '' ); ?></h1>
                <div class="wpclever_settings_page_desc about-text">
                    <p>
						<?php printf( /* translators: stars */ esc_html__( 'Thank you for using our plugin! If you are satisfied, please reward it a full five-star %s rating.', 'wpc-attribute-groups' ), '<span style="color:#ffb900">&#9733;&#9733;&#9733;&#9733;&#9733;</span>' ); ?>
                        <br/>
                        <a href="<?php echo esc_url( WPCAG_REVIEWS ); ?>"
                           target="_blank"><?php esc_html_e( 'Reviews', 'wpc-attribute-groups' ); ?></a> |
                        <a href="<?php echo esc_url( WPCAG_CHANGELOG ); ?>"
                           target="_blank"><?php esc_html_e( 'Changelog', 'wpc-attribute-groups' ); ?></a> |
                        <a href="<?php echo esc_url( WPCAG_DISCUSSION ); ?>"
                           target="_blank"><?php esc_html_e( 'Discussion', 'wpc-attribute-groups' ); ?></a>
                    </p>
                </div>
				<?php if ( isset( $_GET['settings-updated'] ) && $_GET['settings-updated'] ) { ?>
                    <div class="notice notice-success is-dismissible">
                        <p><?php esc_html_e( 'Settings updated.', 'wpc-attribute-groups' ); ?></p>
                    </div>
				<?php } ?>
                <div class="wpclever_settings_page_nav">
                    <h2 class="nav-tab-wrapper">
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpclever-wpcag&tab=settings' ) ); ?>"
                           class="<?php echo esc_attr( $active_tab === 'settings' ? 'nav-tab nav-tab-active' : 'nav-tab' ); ?>">
							<?php esc_html_e( 'Settings', 'wpc-attribute-groups' ); ?>
                        </a>
                        <a href="<?php echo esc_url( admin_url( 'edit-tags.php?taxonomy=wpc-attribute-group&post_type=product' ) ); ?>"
                           class="nav-tab">
							<?php esc_html_e( 'Attribute Groups', 'wpc-attribute-groups' ); ?>
                        </a>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpclever-wpcag&tab=premium' ) ); ?>"
                           class="<?php echo esc_attr( $active_tab === 'premium' ? 'nav-tab nav-tab-active' : 'nav-tab' ); ?>"
                           style="color: #c9356e">
							<?php esc_html_e( 'Premium Version', 'wpc-attribute-groups' ); ?>
                        </a>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpclever-kit' ) ); ?>" class="nav-tab">
							<?php esc_html_e( 'Essential Kit', 'wpc-attribute-groups' ); ?>
                        </a>
                    </h2>
                </div>
                <div class="wpclever_settings_page_content">
					<?php if ( $active_tab === 'settings' ) {
						$single_attributes_position    = self::get_setting( 'single_attributes_position', 'below' );
						$single_attributes_title       = self::get_setting( 'single_attributes_title', esc_html__( 'Other', 'wpc-attribute-groups' ) );
						$single_attributes_description = self::get_setting( 'single_attributes_description' );
						$single_attributes_weight      = self::get_setting( 'single_attributes_weight', 'yes' );
						$single_attributes_dimensions  = self::get_setting( 'single_attributes_dimensions', 'yes' );
						$layout                        = self::get_setting( 'layout', '01' );
						?>
                        <form method="post" action="options.php">
                            <table class="form-table">
                                <tr class="heading">
                                    <th>
										<?php esc_html_e( 'Single Attributes', 'wpc-attribute-groups' ); ?>
                                    </th>
                                    <td>
										<?php esc_html_e( 'Configure for single attributes - which were not assigned for any groups.', 'wpc-attribute-groups' ); ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e( 'Position', 'wpc-attribute-groups' ); ?></th>
                                    <td>
                                        <label> <select name="wpcag_settings[single_attributes_position]">
                                                <option value="above" <?php selected( $single_attributes_position, 'above' ); ?>><?php esc_html_e( 'Above Attribute Groups', 'wpc-attribute-groups' ); ?></option>
                                                <option value="below" <?php selected( $single_attributes_position, 'below' ); ?>><?php esc_html_e( 'Below Attribute Groups', 'wpc-attribute-groups' ); ?></option>
                                            </select> </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e( 'Title', 'wpc-attribute-groups' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="text" class="text regular-text"
                                                   name="wpcag_settings[single_attributes_title]"
                                                   value="<?php echo esc_attr( $single_attributes_title ); ?>"/>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e( 'Description', 'wpc-attribute-groups' ); ?></th>
                                    <td>
                                        <label>
                                            <textarea name="wpcag_settings[single_attributes_description]" cols="50"
                                                      rows="3"
                                                      style="width: 100%"><?php echo esc_html( $single_attributes_description ); ?></textarea>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e( 'Show Weight', 'wpc-attribute-groups' ); ?></th>
                                    <td>
                                        <label> <select name="wpcag_settings[single_attributes_weight]">
                                                <option value="yes" <?php selected( $single_attributes_weight, 'yes' ); ?>><?php esc_html_e( 'Yes', 'wpc-attribute-groups' ); ?></option>
                                                <option value="no" <?php selected( $single_attributes_weight, 'no' ); ?>><?php esc_html_e( 'No', 'wpc-attribute-groups' ); ?></option>
                                            </select> </label>
                                        <span class="description"><?php esc_html_e( 'Show/hide the product weight in "Single Attributes".', 'wpc-attribute-groups' ); ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e( 'Show Dimensions', 'wpc-attribute-groups' ); ?></th>
                                    <td>
                                        <label> <select name="wpcag_settings[single_attributes_dimensions]">
                                                <option value="yes" <?php selected( $single_attributes_dimensions, 'yes' ); ?>><?php esc_html_e( 'Yes', 'wpc-attribute-groups' ); ?></option>
                                                <option value="no" <?php selected( $single_attributes_dimensions, 'no' ); ?>><?php esc_html_e( 'No', 'wpc-attribute-groups' ); ?></option>
                                            </select> </label>
                                        <span class="description"><?php esc_html_e( 'Show/hide the product dimensions in "Single Attributes".', 'wpc-attribute-groups' ); ?></span>
                                    </td>
                                </tr>
                                <tr class="heading">
                                    <th colspan="2">
										<?php esc_html_e( 'Style', 'wpc-attribute-groups' ); ?>
                                    </th>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e( 'Layout', 'wpc-attribute-groups' ); ?></th>
                                    <td>
                                        <label> <select name="wpcag_settings[layout]">
                                                <option value="01" <?php selected( $layout, '01' ); ?>><?php esc_html_e( 'Layout 01', 'wpc-attribute-groups' ); ?></option>
                                                <option value="02" <?php selected( $layout, '02' ); ?>><?php esc_html_e( 'Layout 02', 'wpc-attribute-groups' ); ?></option>
                                            </select> </label>
                                    </td>
                                </tr>
                                <tr class="submit">
                                    <th colspan="2">
										<?php settings_fields( 'wpcag_settings' ); ?><?php submit_button(); ?>
                                    </th>
                                </tr>
                            </table>
                        </form>
					<?php } elseif ( $active_tab === 'premium' ) { ?>
                        <div class="wpclever_settings_page_content_text">
                            <p>Get the Premium Version just $29!
                                <a href="https://wpclever.net/downloads/wpc-attribute-groups/?utm_source=pro&utm_medium=wpcag&utm_campaign=wporg"
                                   target="_blank">https://wpclever.net/downloads/wpc-attribute-groups/</a>
                            </p>
                            <p><strong>Extra features for Premium Version:</strong></p>
                            <ul style="margin-bottom: 0">
                                <li>- Use shortcode to place attribute group where you want.</li>
                                <li>- Get the lifetime update & premium support.</li>
                            </ul>
                        </div>
					<?php } ?>
                </div><!-- /.wpclever_settings_page_content -->
                <div class="wpclever_settings_page_suggestion">
                    <div class="wpclever_settings_page_suggestion_label">
                        <span class="dashicons dashicons-yes-alt"></span> Suggestion
                    </div>
                    <div class="wpclever_settings_page_suggestion_content">
                        <div>
                            To display custom engaging real-time messages on any wished positions, please install
                            <a href="https://wordpress.org/plugins/wpc-smart-messages/" target="_blank">WPC Smart
                                Messages</a> plugin. It's free!
                        </div>
                        <div>
                            Wanna save your precious time working on variations? Try our brand-new free plugin
                            <a href="https://wordpress.org/plugins/wpc-variation-bulk-editor/" target="_blank">WPC
                                Variation Bulk Editor</a> and
                            <a href="https://wordpress.org/plugins/wpc-variation-duplicator/" target="_blank">WPC
                                Variation Duplicator</a>.
                        </div>
                    </div>
                </div>
            </div>
			<?php
		}

		function action_links( $links, $file ) {
			static $plugin;

			if ( ! isset( $plugin ) ) {
				$plugin = plugin_basename( WPCAG_FILE );
			}

			if ( $plugin === $file ) {
				$settings             = '<a href="' . esc_url( admin_url( 'admin.php?page=wpclever-wpcag&tab=settings' ) ) . '">' . esc_html__( 'Settings', 'wpc-attribute-groups' ) . '</a>';
				$groups               = '<a href="' . esc_url( admin_url( 'edit-tags.php?taxonomy=wpc-attribute-group&post_type=product' ) ) . '">' . esc_html__( 'Attribute Groups', 'wpc-attribute-groups' ) . '</a>';
				$links['wpc-premium'] = '<a href="' . esc_url( admin_url( 'admin.php?page=wpclever-wpcag&tab=premium' ) ) . '">' . esc_html__( 'Premium Version', 'wpc-attribute-groups' ) . '</a>';
				array_unshift( $links, $settings, $groups );
			}

			return (array) $links;
		}

		function row_meta( $links, $file ) {
			static $plugin;

			if ( ! isset( $plugin ) ) {
				$plugin = plugin_basename( WPCAG_FILE );
			}

			if ( $plugin === $file ) {
				$row_meta = [
					'support' => '<a href="' . esc_url( WPCAG_DISCUSSION ) . '" target="_blank">' . esc_html__( 'Community support', 'wpc-attribute-groups' ) . '</a>',
				];

				return array_merge( $links, $row_meta );
			}

			return (array) $links;
		}

		function parse_term_query( $query ) {
			if ( isset( $query->query_vars['taxonomy'] ) && is_array( $query->query_vars['taxonomy'] ) ) {
				if ( isset( $query->query_vars['taxonomy'][0] ) && ( $query->query_vars['taxonomy'][0] === 'wpc-attribute-group' ) ) {
					$query->query_vars['order']      = 'ASC';
					$query->query_vars['orderby']    = 'meta_value_num';
					$query->query_vars['meta_query'] = [
						'relation' => 'OR',
						[
							'key'     => 'wpcag_order',
							'type'    => 'NUMERIC',
							'compare' => 'NOT EXISTS'
						],
						[
							'key'     => 'wpcag_order',
							'type'    => 'NUMERIC',
							'value'   => 0,
							'compare' => '>'
						]
					];
				}
			}
		}

		function ajax_order() {
			if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['nonce'] ), 'wpcag_order' ) ) {
				die( 'Permissions check failed!' );
			}

			if ( isset( $_POST['ids'] ) ) {
				$ids = array_map( 'absint', explode( ',', sanitize_text_field( $_POST['ids'] ) ) );

				if ( is_array( $ids ) && ! empty( $ids ) ) {
					foreach ( $ids as $k => $id ) {
						if ( $id ) {
							update_term_meta( $id, 'wpcag_order', $k + 1 );
						}
					}
				}
			}

			wp_die();
		}

		function ajax_order_attrs() {
			if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['nonce'] ), 'wpcag_order' ) ) {
				die( 'Permissions check failed!' );
			}

			if ( isset( $_POST['group'] ) && isset( $_POST['attrs'] ) ) {
				$attrs = array_filter( explode( ',', sanitize_text_field( $_POST['attrs'] ) ) );
				update_term_meta( absint( $_POST['group'] ), 'wpcag_attributes', $attrs );
			}

			wp_die();
		}

		function ajax_add_group_attributes() {
			if ( ! isset( $_POST['security'] ) || ! wp_verify_nonce( sanitize_key( $_POST['security'] ), 'add-attribute' ) ) {
				die( 'Permissions check failed!' );
			}

			if ( ! current_user_can( 'edit_products' ) || ! isset( $_POST['group_id'], $_POST['i'] ) ) {
				wp_die( - 1 );
			}

			$group_id = ( isset( $_POST['group_id'] ) && (int) $_POST['group_id'] ? (int) $_POST['group_id'] : null );
			$i        = absint( $_POST['i'] );
			parse_str( wp_unslash( $_POST['data'] ), $data );

			if ( $group_id ) {
				$group_attributes = get_term_meta( $group_id, 'wpcag_attributes', true ) ?: [];

				if ( ! empty( $data ) ) {
					$data   = array_values( $data['attribute_names'] );
					$result = array_intersect( $data, $group_attributes );

					foreach ( $result as $item ) {
						$key = array_search( $item, $group_attributes );
						unset( $group_attributes[ $key ] );
					}
				}

				foreach ( $group_attributes as $group_attribute ) {
					$metabox_class = [];
					$attribute     = new WC_Product_Attribute();
					$attribute->set_id( wc_attribute_taxonomy_id_by_name( sanitize_text_field( wp_unslash( $group_attribute ) ) ) );
					$attribute->set_name( sanitize_text_field( wp_unslash( $group_attribute ) ) );
					$attribute->set_visible( apply_filters( 'woocommerce_attribute_default_visibility', 1 ) );
					$attribute->set_variation( apply_filters( 'woocommerce_attribute_default_is_variation', 0 ) );

					if ( $attribute->is_taxonomy() ) {
						$metabox_class[] = 'taxonomy';
						$metabox_class[] = 'wpcag-taxonomy';
						$metabox_class[] = $attribute->get_name();
					}

					include WP_PLUGIN_DIR . '/woocommerce/includes/admin/meta-boxes/views/html-product-attribute.php';
					$i ++;
				}
			}

			wp_die();
		}

		function ajax_search_term() {
			$return = [];

			$args = [
				'taxonomy'   => sanitize_text_field( $_REQUEST['taxonomy'] ),
				'orderby'    => 'id',
				'order'      => 'ASC',
				'hide_empty' => false,
				'fields'     => 'all',
				'name__like' => sanitize_text_field( $_REQUEST['q'] ),
			];

			$terms = get_terms( $args );

			if ( count( $terms ) ) {
				foreach ( $terms as $term ) {
					$return[] = [ $term->slug, $term->name ];
				}
			}

			wp_send_json( $return );
		}

		function enqueue_scripts() {
			wp_enqueue_style( 'hint', WPCAG_URI . 'assets/css/hint.css' );
			wp_enqueue_style( 'wpcag-backend', WPCAG_URI . 'assets/css/backend.css', [ 'woocommerce_admin_styles' ], WPCAG_VERSION );
			wp_enqueue_script( 'wpcag-backend', WPCAG_URI . 'assets/js/backend.js', [
				'jquery',
				'jquery-ui-core',
				'jquery-ui-sortable',
				'selectWoo'
			], WPCAG_VERSION, true );
			wp_localize_script( 'wpcag-backend', 'wpcag_vars', [
					'nonce' => wp_create_nonce( 'wpcag_order' ),
				]
			);
		}

		function woo_init() {
			$labels = [
				'name'                       => esc_html__( 'Attribute Groups', 'wpc-attribute-groups' ),
				'singular_name'              => esc_html__( 'Attribute Group', 'wpc-attribute-groups' ),
				'menu_name'                  => esc_html__( 'Attribute Groups', 'wpc-attribute-groups' ),
				'all_items'                  => esc_html__( 'All Attribute Groups', 'wpc-attribute-groups' ),
				'edit_item'                  => esc_html__( 'Edit Attribute Group', 'wpc-attribute-groups' ),
				'view_item'                  => esc_html__( 'View Attribute Group', 'wpc-attribute-groups' ),
				'update_item'                => esc_html__( 'Update Attribute Group', 'wpc-attribute-groups' ),
				'add_new_item'               => esc_html__( 'Add New Attribute Group', 'wpc-attribute-groups' ),
				'new_item_name'              => esc_html__( 'New Attribute Group Name', 'wpc-attribute-groups' ),
				'parent_item'                => esc_html__( 'Parent Attribute Group', 'wpc-attribute-groups' ),
				'parent_item_colon'          => esc_html__( 'Parent Attribute Group:', 'wpc-attribute-groups' ),
				'search_items'               => esc_html__( 'Search Attribute Groups', 'wpc-attribute-groups' ),
				'popular_items'              => esc_html__( 'Popular Attribute Groups', 'wpc-attribute-groups' ),
				'back_to_items'              => esc_html__( '&larr; Go to Attribute Groups', 'wpc-attribute-groups' ),
				'separate_items_with_commas' => esc_html__( 'Separate groups with commas', 'wpc-attribute-groups' ),
				'add_or_remove_items'        => esc_html__( 'Add or remove groups', 'wpc-attribute-groups' ),
				'choose_from_most_used'      => esc_html__( 'Choose from the most used groups', 'wpc-attribute-groups' ),
				'not_found'                  => esc_html__( 'No groups found', 'wpc-attribute-groups' )
			];

			$args = [
				'hierarchical'       => false,
				'labels'             => apply_filters( 'wpcag_taxonomy_labels', $labels ),
				'show_ui'            => true,
				'query_var'          => true,
				'public'             => false,
				'publicly_queryable' => false,
				'show_in_menu'       => true,
				'show_in_rest'       => true,
				'show_admin_column'  => true,
			];

			register_taxonomy( 'wpc-attribute-group', [ 'product' ], apply_filters( 'wpcag_taxonomy_args', $args ) );
		}

		function add_form_fields() {
			self::form_fields();
		}

		function edit_form_fields( $term ) {
			self::form_fields( $term );
		}

		function form_fields( $term = null ) {
			if ( $term ) {
				$apply       = get_term_meta( $term->term_id, 'wpcag_apply', true ) ?: 'all';
				$apply_val   = get_term_meta( $term->term_id, 'wpcag_apply_val', true ) ?: [];
				$attributes  = get_term_meta( $term->term_id, 'wpcag_attributes', true ) ?: [];
				$exclude     = get_term_meta( $term->term_id, 'wpcag_exclude', true ) ?: 'no';
				$table_start = '<table class="form-table">';
				$table_end   = '</table>';
				$tr_start    = '<tr class="form-field">';
				$tr_end      = '</tr>';
				$th_start    = '<th scope="row">';
				$th_end      = '</th>';
				$td_start    = '<td>';
				$td_end      = '</td>';
			} else {
				// add new
				$apply       = 'all';
				$apply_val   = [];
				$attributes  = [];
				$exclude     = 'no';
				$table_start = '';
				$table_end   = '';
				$tr_start    = '<div class="form-field">';
				$tr_end      = '</div>';
				$th_start    = '';
				$th_end      = '';
				$td_start    = '';
				$td_end      = '';
			}

			$allowed_html = [
				'table' => [
					'class' => [],
				],
				'tr'    => [
					'class' => [],
				],
				'th'    => [
					'class' => [],
					'scope' => [],
				],
				'td'    => [
					'class' => [],
				],
				'div'   => [
					'class' => [],
				],
			];

			echo wp_kses( $table_start, $allowed_html );

			echo wp_kses( $tr_start . $th_start, $allowed_html );
			echo '<label>' . esc_html__( 'Apply for', 'wpc-attribute-groups' ) . '</label>';
			echo wp_kses( $th_end . $td_start, $allowed_html ); ?>
            <label> <select class="wpcag_apply" name="wpcag_apply">
                    <option value="all" <?php selected( $apply, 'all' ); ?>><?php esc_attr_e( 'All products', 'wpc-attribute-groups' ); ?></option>
					<?php
					$taxonomies = get_object_taxonomies( 'product', 'objects' );

					foreach ( $taxonomies as $taxonomy ) {
						if ( $taxonomy->name !== 'wpc-attribute-group' ) {
							echo '<option value="' . esc_attr( $taxonomy->name ) . '" ' . selected( $apply, $taxonomy->name, false ) . '>' . esc_html( $taxonomy->label ) . '</option>';
						}
					}
					?>
                </select> </label>
            <div class="wpcag_apply_val_wrapper hide_if_apply_all">
                <label>
                    <select class="wpcag_terms wpcag_apply_val" multiple="multiple" name="wpcag_apply_val[]"
                            data-<?php echo esc_attr( $apply ); ?>="<?php echo esc_attr( implode( ',', (array) $apply_val ) ); ?>">
						<?php if ( is_array( $apply_val ) && ! empty( $apply_val ) ) {
							foreach ( $apply_val as $t ) {
								if ( $term = get_term_by( 'slug', $t, $apply ) ) {
									echo '<option value="' . esc_attr( $t ) . '" selected>' . esc_html( $term->name ) . '</option>';
								}
							}
						} ?>
                    </select> </label>
            </div>
			<?php echo wp_kses( $td_end . $tr_end, $allowed_html );

			echo wp_kses( $tr_start . $th_start, $allowed_html );
			echo '<label>' . esc_html__( 'Attributes', 'wpc-attribute-groups' ) . '</label>';
			echo wp_kses( $th_end . $td_start, $allowed_html ); ?>
            <div class="wpcag_attributes_wrapper">
                <label for="wpcag_attributes_selector"></label><select name="wpcag_attributes[]"
                                                                       id="wpcag_attributes_selector"
                                                                       class="wpcag_attributes_selector"
                                                                       multiple="multiple">
					<?php
					$product_attributes = [];

					if ( $taxonomies = get_object_taxonomies( 'product', 'objects' ) ) {
						foreach ( $taxonomies as $taxonomy ) {
							if ( str_starts_with( $taxonomy->name, 'pa_' ) ) {
								$product_attributes[] = $taxonomy->name;

							}
						}
					}

					$merge_attributes = array_unique( array_merge( $attributes, $product_attributes ) );

					foreach ( $merge_attributes as $attr ) {
						echo '<option value="' . esc_attr( $attr ) . '" ' . esc_attr( in_array( $attr, $attributes ) ? 'selected' : '' ) . '>' . esc_html( wc_attribute_label( $attr ) ) . '</option>';
					}
					?>
                </select>
            </div>
			<?php
			echo wp_kses( $td_end . $tr_end, $allowed_html );

			echo wp_kses( $tr_start . $th_start, $allowed_html );
			echo '<label>' . esc_html__( 'Exclude from Additional Information', 'wpc-attribute-groups' ) . '</label>';
			echo wp_kses( $th_end . $td_start, $allowed_html ); ?>
            <label> <select class="wpcag_exclude" name="wpcag_exclude">
                    <option value="yes" <?php selected( $exclude, 'yes' ); ?>><?php esc_attr_e( 'Yes', 'wpc-attribute-groups' ); ?></option>
                    <option value="no" <?php selected( $exclude, 'no' ); ?>><?php esc_attr_e( 'No', 'wpc-attribute-groups' ); ?></option>
                </select> </label>
            <p class="description"><?php esc_html_e( 'Exclude this group from Additional Information. You still can place it in a new tab or elsewhere by using a shortcode.', 'wpc-attribute-groups' ); ?></p>
			<?php echo wp_kses( $td_end . $tr_end, $allowed_html );

			echo wp_kses( $table_end, $allowed_html );
		}

		function save_form_fields( $term_id ) {
			if ( isset( $_POST['wpcag_apply'] ) ) {
				update_term_meta( $term_id, 'wpcag_apply', sanitize_text_field( $_POST['wpcag_apply'] ) );
			}

			if ( isset( $_POST['wpcag_apply_val'] ) ) {
				update_term_meta( $term_id, 'wpcag_apply_val', self::sanitize_array( $_POST['wpcag_apply_val'] ) );
			}

			if ( isset( $_POST['wpcag_attributes'] ) ) {
				update_term_meta( $term_id, 'wpcag_attributes', self::sanitize_array( $_POST['wpcag_attributes'] ) );
			}

			if ( isset( $_POST['wpcag_exclude'] ) ) {
				update_term_meta( $term_id, 'wpcag_exclude', sanitize_text_field( $_POST['wpcag_exclude'] ) );
			}
		}

		function group_columns( $columns ) {
			return [
				'cb'          => $columns['cb'] ?? 'cb',
				'name'        => esc_html__( 'Name', 'wpc-attribute-groups' ),
				'description' => esc_html__( 'Description', 'wpc-attribute-groups' ),
				'apply'       => esc_html__( 'Apply for', 'wpc-attribute-groups' ),
				'attributes'  => esc_html__( 'Attributes', 'wpc-attribute-groups' ),
				'shortcode'   => esc_html__( 'Shortcode', 'wpc-attribute-groups' ),
				'handle'      => '',
			];
		}

		function group_columns_content( $column, $column_name, $term_id ) {
			if ( $column_name === 'attributes' ) {
				$attributes_html = '';
				$attributes      = get_term_meta( $term_id, 'wpcag_attributes', true ) ?: [];

				if ( is_array( $attributes ) && ! empty( $attributes ) ) {
					$attributes_html .= '<ul class="wpcag_attributes_list" data-group="' . esc_attr( $term_id ) . '">';

					foreach ( $attributes as $attribute ) {
						if ( $attribute_obj = get_taxonomy( $attribute ) ) {
							$attributes_html .= '<li data-attr="' . esc_attr( $attribute ) . '"><span class="wpcag_attribute_name">' . esc_html( wc_attribute_label( $attribute_obj->name ) ) . '</span> <a href="' . esc_url( admin_url( 'edit-tags.php?taxonomy=' . $attribute . '&post_type=product' ) ) . '" target="_blank" class="hint--right" aria-label="' . esc_attr__( 'Configure terms', 'wpc-attribute-groups' ) . '"><span class="dashicons dashicons-edit"></span></a></li>';
						}
					}

					$attributes_html .= '<ul>';
				}

				return $attributes_html;
			}

			if ( $column_name === 'apply' ) {
				$apply = get_term_meta( $term_id, 'wpcag_apply', true ) ?: 'all';

				if ( $apply === 'all' ) {
					return esc_html__( 'All products', 'wpc-attribute-groups' );
				} else {
					// attribute
					if ( $attribute_obj = get_taxonomy( $apply ) ) {
						$apply_html  = $attribute_obj->label;
						$apply_val   = (array) get_term_meta( $term_id, 'wpcag_apply_val', true ) ?: [];
						$apply_terms = [];

						if ( ! empty( $apply_val ) ) {
							foreach ( $apply_val as $val ) {
								if ( $term = get_term_by( 'slug', $val, $apply ) ) {
									$apply_terms[] = $term->name;
								}
							}

							if ( ! empty( $apply_terms ) ) {
								$apply_html .= ': ' . implode( ', ', $apply_terms );
							}
						}

						return $apply_html;
					}
				}

				return '';
			}

			if ( $column_name === 'shortcode' ) {
				$shortcode = '';
				$term      = get_term( $term_id, 'wpc-attribute-group' );
				$exclude   = get_term_meta( $term_id, 'wpcag_exclude', true ) ?: 'no';

				if ( $exclude === 'yes' ) {
					$shortcode .= '<p>' . esc_html__( 'Excluded from Additional Information', 'wpc-attribute-groups' ) . '</p>';
				}

				$shortcode .= '<div class="wpcag_shortcode_wrapper">';
				$shortcode .= '<input type="text" class="wpcag_shortcode_input" readonly value="[wpcag slug=&quot;' . esc_attr( $term->slug ) . '&quot; id=&quot;' . $term->term_id . '&quot; name=&quot;' . esc_attr( $term->name ) . '&quot; show_heading=&quot;yes&quot; show_description=&quot;yes&quot;]" disabled/>';
				$shortcode .= '<p class="wpcag_shortcode_premium">' . esc_html__( '* Premium Version only', 'wpc-attribute-groups' ) . '</p>';
				$shortcode .= '</div>';

				return $shortcode;
			}

			if ( $column_name === 'handle' ) {
				return '<input type="hidden" class="wpcag_term_order" value="' . esc_attr( $term_id ) . '" />';
			}

			return $column;
		}

		function add_tools() {
			$groups = self::get_groups();

			if ( is_array( $groups ) && ! empty( $groups ) ) {
				?>
                <div class="toolbar wpcag_toolbar" style="text-align: right">
                    <span><?php esc_html_e( 'Add attributes from group', 'wpc-attribute-groups' ); ?></span> <label>
                        <select class="wpcag_group_attributes_select">
                            <option value=""><?php esc_html_e( 'Attribute group', 'wpc-attribute-groups' ); ?></option>
							<?php foreach ( $groups as $group ) {
								$group_attributes = get_term_meta( $group->term_id, 'wpcag_attributes', true ) ?: [];
								echo '<option value="' . esc_attr( $group->term_id ) . '">' . esc_html( $group->name ) . ' (' . count( $group_attributes ) . ')</option>';
							} ?>
                        </select> </label>
                    <button type="button"
                            class="button wpcag_group_attributes_add"><?php esc_html_e( 'Add', 'wpc-attribute-groups' ); ?></button>
                </div>
				<?php
			}
		}

		function sanitize_array( $arr ) {
			foreach ( (array) $arr as $k => $v ) {
				if ( is_array( $v ) ) {
					$arr[ $k ] = self::sanitize_array( $v );
				} else {
					$arr[ $k ] = sanitize_text_field( $v );
				}
			}

			return $arr;
		}
	}

	function Wpcag_Backend() {
		return Wpcag_Backend::instance();
	}

	Wpcag_Backend();
}
