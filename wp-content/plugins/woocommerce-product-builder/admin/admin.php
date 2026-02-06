<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

class VI_WPRODUCTBUILDER_Admin_Admin {
	public function __construct() {
		add_filter( 'plugin_action_links_woocommerce-product-builder/woocommerce-product-builder.php', array(
			$this,
			'settings_link'
		) );
		add_action( 'load-options-permalink.php', array( $this, 'woo_product_builder_load_permalinks' ), 11 );

		add_action( 'init', array( $this, 'init' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_product_builder_metaboxes' ) );

		add_action( 'save_post', array( $this, 'save_post_metadata' ) );

		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_script' ) );

		/*Get list product or categories in edit page*/
		add_action( 'wp_ajax_woopb_get_data', array( $this, 'get_data' ) );

		add_filter( 'manage_woo_product_builder_posts_columns', array( $this, 'define_shortcode_columns' ) );
		add_action( 'manage_woo_product_builder_posts_custom_column', array( $this, 'shortcode_columns' ), 10, 2 );

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_style' ) );

		add_action( 'edit_form_after_editor', array( $this, 'show_shortcode' ) );
		add_filter( 'post_row_actions', array( $this, 'dupe_link' ), 10, 2 );
		add_action( 'admin_action_duplicate_woo_product_builder', array( $this, 'duplicate_action' ) );
	}
    public function duplicate_action(){
	    if ( empty( $_REQUEST['post'] ) ) {
		    wp_die( esc_html__( 'No product builder to duplicate has been supplied!', 'woocommerce-product-builder' ) );
	    }

	    $post_id = isset( $_REQUEST['post'] ) ? absint( $_REQUEST['post'] ) : '';

	    check_admin_referer( 'woo_product_builder-duplicate-' . $post_id );

	    $post = get_post( $post_id );

	    if ( false === $post ) {
		    /* translators: %s: product id */
		    wp_die( sprintf( esc_html__( 'Product builder creation failed, could not find original product builder: %s', 'woocommerce' ), esc_html( $post_id ) ) );
	    }

	    $duplicate = wp_insert_post(array(
		    'post_excerpt' => '',
		    'post_content' => '',
		    'post_title'   => sprintf( esc_html__( '%s (Copy)', 'woocommerce' ), $post->post_title ),
		    'post_status'  => 'draft',
		    'post_type'    => 'woo_product_builder',
	    ));
	    if ( is_wp_error( $duplicate ) ) {
		    wp_die( $duplicate->get_error_message());
	    } elseif ( ! $duplicate ) {
		    wp_die( esc_html__( 'Can not duplicate product builder!', 'woocommerce-product-builder' ) );
	    }
	    $duplicate = get_post($duplicate);
	    update_post_meta( $duplicate->ID, 'woopb-param', get_post_meta($post_id,'woopb-param', true) );
	    // Hook rename to match other woocommerce_product_* hooks, and to move away from depending on a response from the wp_posts table.
	    do_action( 'woo_product_builder_duplicate', $duplicate, $post );
	    // Redirect to the edit screen for the new draft page.
	    wp_safe_redirect( admin_url( 'post.php?action=edit&post=' . $duplicate->ID ) );
	    exit;
    }
	public function dupe_link( $actions, $post ) {
		global $post;

		if ( ! current_user_can( 'manage_options' ) ) {
			return $actions;
		}

		if ( 'woo_product_builder' !== $post->post_type ) {
			return $actions;
		}
		$actions['duplicate'] = sprintf('<a href="%s" rel="permalink" aria-label="%s">%s</a>',
			wp_nonce_url( admin_url( 'edit.php?post_type=woo_product_builder&action=duplicate_woo_product_builder&amp;post=' . $post->ID ), 'woo_product_builder-duplicate-' . $post->ID ),
			esc_attr__( 'Make a duplicate from this product', 'woocommerce-product-builder' ),
			esc_html__( 'Duplicate', 'woocommerce-product-builder' ));

		return $actions;
	}

	/**
	 * Get Product via ajax
	 */
	public function get_data() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$keyword = isset( $_POST['keyword'] ) ? sanitize_text_field( $_POST['keyword'] ) : '';
		$type    = isset( $_POST['type'] ) ? (int) sanitize_text_field( $_POST['type'] ) : '';
		$results = array();
		switch ( $type ) {
			case 1:
				$args      = array(
					'post_status'    => 'publish',
					'post_type'      => 'product',
					'posts_per_page' => 100,
					's'              => $keyword,
					'tax_query'      => array(
						array(
							'taxonomy' => 'product_type',
							'field'    => 'slug',
							'terms'    => apply_filters( 'woopb_product_type', array( 'simple', 'variable' ) ),
							//custom work
							'operator' => 'IN'
						),
					)
				);
				$the_query = new WP_Query( $args );
				// The Loop
				if ( $the_query->have_posts() ) {
					while ( $the_query->have_posts() ) {
						$the_query->the_post();
						$data          = array();
						$data['id']    = get_the_ID();
						$data['title'] = get_the_title();
						if ( has_post_thumbnail() ) {
							$data['thumb_url'] = get_the_post_thumbnail_url();
						} else {
							$data['thumb_url'] = '';
						}
						$results[] = $data;
					}
				}
				// Reset Post Data
				wp_reset_postdata();
				break;
			default:
				$args  = array(
					'taxonomy'   => 'product_cat',
					'orderby'    => 'name',
					'hide_empty' => true,
					'number'     => 200,
					'search'     => $keyword
				);
				$cates = get_terms( $args );
				if ( count( $cates ) ) {
					foreach ( $cates as $cat ) {
						$data              = array();
						$data['id']        = $cat->term_id;
						$data['title']     = $cat->name;
						$data['thumb_url'] = '';
						$results[]         = $data;
					}
				}
		}
		wp_send_json( $results );
		die;
	}

	/**
	 * Register post type
	 */
	public function init() {
		load_plugin_textdomain( 'woocommerce-product-builder' );
		$this->load_plugin_textdomain();
		register_post_type( 'woo_product_builder', array(
			'labels' => array(
				'name'               => __( 'Product Builders', 'woocommerce-product-builder' ),
				'singular_name'      => __( 'Product Builders', 'woocommerce-product-builder' ),
				'add_new'            => __( 'Add New', 'woocommerce-product-builder' ),
				'add_new_item'       => __( 'Add New Product Builder', 'woocommerce-product-builder' ),
				'edit'               => __( 'Edit', 'woocommerce-product-builder' ),
				'edit_item'          => __( 'Edit Product Builder', 'woocommerce-product-builder' ),
				'new_item'           => __( 'New Product Builder', 'woocommerce-product-builder' ),
				'view'               => __( 'View', 'woocommerce-product-builder' ),
				'view_item'          => __( 'View Product Builder', 'woocommerce-product-builder' ),
				'search_items'       => __( 'Search Product Builders', 'woocommerce-product-builder' ),
				'not_found'          => __( 'No Product Builders found', 'woocommerce-product-builder' ),
				'not_found_in_trash' => __( 'No Product Builders found in Trash', 'woocommerce-product-builder' )
			),

			'public'               => true,
			'menu_position'        => 2,
			'supports'             => array( 'title', 'thumbnail', 'revisions' ),
			'taxonomies'           => array( '' ),
			'menu_icon'            => 'dashicons-feedback',
			'has_archive'          => true,
			'register_meta_box_cb' => array( $this, 'add_product_builder_metaboxes' ),
			'rewrite'              => array( 'slug' => get_option( 'wpb2205_cpt_base' ), "with_front" => false )
		) );

		register_post_type( 'woo_pb_share_link', array(
			'labels' => array(
				'name'               => __( 'Share link', 'woocommerce-product-builder' ),
				'singular_name'      => __( 'Share link', 'woocommerce-product-builder' ),
				'add_new'            => __( 'Add New', 'woocommerce-product-builder' ),
				'add_new_item'       => __( 'Add New Share link', 'woocommerce-product-builder' ),
				'edit'               => __( 'Edit', 'woocommerce-product-builder' ),
				'edit_item'          => __( 'Edit Share link', 'woocommerce-product-builder' ),
				'new_item'           => __( 'New Share link', 'woocommerce-product-builder' ),
				'view'               => __( 'View', 'woocommerce-product-builder' ),
				'view_item'          => __( 'View Share link', 'woocommerce-product-builder' ),
				'search_items'       => __( 'Search Share link', 'woocommerce-product-builder' ),
				'not_found'          => __( 'No Share link found', 'woocommerce-product-builder' ),
				'not_found_in_trash' => __( 'No Share link found in Trash', 'woocommerce-product-builder' )
			),

			'public'              => false,
			'show_ui'             => true,
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
			'hierarchical'        => false,
			'menu_position'       => 2,
			'supports'            => array( 'title' ),
			'has_archive'         => true,
			'rewrite'             => true,
			'show_in_menu'        => 'edit.php?post_type=woo_product_builder',
			'show_in_nav_menus'   => false,
			'show_in_admin_bar'   => false,
			'map_meta_cap'        => true,
//			'capability_type'     => 'woo_pb_share_link',
//			'capabilities'      => array(
////				'create_posts' => 'do_not_allow',
//				'edit_post'   => 'edit_woo_pb_share_link',
//				'edit_posts'   => 'edit_woo_pb_share_links',
//			),
		) );
		flush_rewrite_rules();
	}

	/**
	 * load Language translate
	 */
	public function load_plugin_textdomain() {
		$locale = apply_filters( 'plugin_locale', get_locale(), 'woocommerce-product-builder' );
		// Admin Locale
		if ( is_admin() ) {
			load_textdomain( 'woocommerce-product-builder', VI_WPRODUCTBUILDER_LANGUAGES . "woocommerce-product-builder-$locale.mo" );
		}

		// Global + Frontend Locale
		load_textdomain( 'woocommerce-product-builder', VI_WPRODUCTBUILDER_LANGUAGES . "woocommerce-product-builder-$locale.mo" );
		load_plugin_textdomain( 'woocommerce-product-builder', false, VI_WPRODUCTBUILDER_LANGUAGES );
	}

	public function woo_product_builder_load_permalinks() {
		if ( isset( $_POST['wpb2205_cpt_base'] ) ) {
			update_option( 'wpb2205_cpt_base', sanitize_title_with_dashes( $_POST['wpb2205_cpt_base'] ) );
		}

		// Add a settings field to the permalink page
		add_settings_field( 'wpb2205_cpt_base', __( 'Product builders' ), array(
			$this,
			'woo_product_builder_field_callback'
		), 'permalink', 'optional' );
	}

	public function woo_product_builder_field_callback() {
		$value = get_option( 'wpb2205_cpt_base' );
		echo '<input type="text" value="' . esc_attr( $value ) . '" name="wpb2205_cpt_base" id="wpb2205_cpt_base" class="regular-text" placeholder="product-builder" />';

	}

	/**
	 * Link to Settings
	 *
	 * @param $links
	 *
	 * @return mixed
	 */
	public function settings_link( $links ) {
		$settings_link = '<a href="edit.php?post_type=woo_product_builder&page=woocommerce-product-builder-setting" title="' . __( 'Settings', 'woocommerce-product-builder' ) . '">' . __( 'Settings', 'woocommerce-product-builder' ) . '</a>';
		array_unshift( $links, $settings_link );

		return $links;
	}

	/**
	 * Enqueue scripts admin page
	 */
	public function admin_enqueue_script() {
		global $pagenow, $typenow;
		$page = isset( $_REQUEST['page'] ) ? $_REQUEST['page'] : '';

		if ( is_admin() && $pagenow == 'post-new.php' && $typenow == 'woo_product_builder' or $pagenow == 'post.php' && $typenow == 'woo_product_builder' or $typenow == 'woo_product_builder' && $page == 'woocommerce-product-builder-setting' || $typenow == 'woo_pb_share_link' ) {
			global $wp_scripts;
			$scripts = $wp_scripts->registered;
			foreach ( $scripts as $k => $script ) {
				preg_match( '/^\/wp-/i', $script->src, $result );
				if ( count( array_filter( $result ) ) < 1 ) {
					if ( $script->handle != 'query-monitor' ) {
						wp_dequeue_script( $script->handle );
					}
				}
			}

			wp_enqueue_style( 'woocommerce-product-builder-form', VI_WPRODUCTBUILDER_CSS . 'form.min.css' );
			wp_enqueue_style( 'woocommerce-product-builder-table', VI_WPRODUCTBUILDER_CSS . 'table.min.css' );
			wp_enqueue_style( 'woocommerce-product-builder-dropdown', VI_WPRODUCTBUILDER_CSS . 'dropdown.min.css' );
			wp_enqueue_style( 'woocommerce-product-builder-checkbox', VI_WPRODUCTBUILDER_CSS . 'checkbox.min.css' );
			wp_enqueue_style( 'woocommerce-product-builder-menu', VI_WPRODUCTBUILDER_CSS . 'menu.min.css' );
			wp_enqueue_style( 'woocommerce-product-builder-segment', VI_WPRODUCTBUILDER_CSS . 'segment.min.css' );
			wp_enqueue_style( 'woocommerce-product-builder-button', VI_WPRODUCTBUILDER_CSS . 'button.min.css' );
			wp_enqueue_style( 'woocommerce-product-builder-transition', VI_WPRODUCTBUILDER_CSS . 'transition.min.css' );
			wp_enqueue_style( 'woocommerce-product-builder-tab', VI_WPRODUCTBUILDER_CSS . 'tab.css' );
			wp_enqueue_style( 'woocommerce-product-builder-input', VI_WPRODUCTBUILDER_CSS . 'input.min.css' );
			wp_enqueue_style( 'woocommerce-product-builder-icon', VI_WPRODUCTBUILDER_CSS . 'icon.min.css' );
			wp_enqueue_style( 'woocommerce-product-builder', VI_WPRODUCTBUILDER_CSS . 'woocommerce-product-builder-admin-product.css' );
			wp_enqueue_style( 'woocommerce-product-builder-select2', VI_WPRODUCTBUILDER_CSS . 'select2.min.css' );

			wp_enqueue_script( 'woocommerce-product-builder-transition', VI_WPRODUCTBUILDER_JS . 'transition.min.js', array( 'jquery' ) );
			wp_enqueue_script( 'woocommerce-product-builder-checkbox', VI_WPRODUCTBUILDER_JS . 'checkbox.js', array( 'jquery' ) );
			wp_enqueue_script( 'woocommerce-product-builder-dropdown', VI_WPRODUCTBUILDER_JS . 'dropdown.min.js', array( 'jquery' ) );
			wp_enqueue_script( 'woocommerce-product-builder-address', VI_WPRODUCTBUILDER_JS . 'jquery.address-1.6.min.js', array( 'jquery' ) );
			wp_enqueue_script( 'woocommerce-product-builder-tab', VI_WPRODUCTBUILDER_JS . 'tab.js', array( 'jquery' ) );
			wp_enqueue_script( 'woocommerce-product-builder-select2', VI_WPRODUCTBUILDER_JS . 'select2.js', array( 'jquery' ) );
			/*Color picker*/
			wp_enqueue_script( 'iris', admin_url( 'js/iris.min.js' ), array(
				'jquery-ui-draggable',
				'jquery-ui-slider',
				'jquery-touch-punch'
			), false, 1 );

			if ( $page == 'woocommerce-product-builder-setting' || $pagenow == 'edit.php' ) {
				wp_enqueue_script( 'woocommerce-product-builder-admin-product', VI_WPRODUCTBUILDER_JS . 'woocommerce-product-builder-admin.js', array( 'jquery' ) );

			} else {
				wp_enqueue_script( 'woocommerce-product-builder-admin-product', VI_WPRODUCTBUILDER_JS . 'woocommerce-product-builder-admin-product.js', array(
					'jquery',
					'jquery-ui-sortable'
				) );
			}
			$arg_scripts = array(
				'tab_title'                => esc_html__( 'Please fill your step title', 'woocommerce-product-builder' ),
				'tab_title_change'         => esc_html__( 'Please fill your tab title that you want to change.', 'woocommerce-product-builder' ),
				'tab_notice_remove'        => esc_html__( 'Do you want to remove this tab?', 'woocommerce-product-builder' ),
				'compatible_notice_remove' => esc_html__( 'Do you want to remove all compatible?', 'woocommerce-product-builder' ),
				'ajax_url'                 => esc_url( admin_url( 'admin-ajax.php' ) ),
				'nonce'                    => wp_create_nonce( 'woopb_admin_ajax' )
			);

			wp_localize_script( 'woocommerce-product-builder-admin-product', '_woopb_params', $arg_scripts );
		}
	}


	/**
	 * Register metaboxes
	 */
	public function add_product_builder_metaboxes() {
		add_meta_box( 'vi_wpb_select_product', __( 'Products Configuration', 'woocommerce-product-builder' ), array(
			$this,
			'select_products_html'
		), 'woo_product_builder', 'normal', 'default' );
		add_meta_box( 'vi_wpb_side_bar', __( 'General', 'woocommerce-product-builder' ), array(
			$this,
			'general_setting_html'
		), 'woo_product_builder', 'normal', 'default' );
		add_meta_box( 'vi_wpb_product_per_page', __( 'Products', 'woocommerce-product-builder' ), array(
			$this,
			'products_per_page_html'
		), 'woo_product_builder', 'normal', 'default' );
	}

	/**
	 * Register select product metaboxes
	 */
	public function select_products_html( $post ) {
		wp_nonce_field( 'woocommerce-product-builder_save', '_woopb_field_nonce' );
		?>
        <!--		Form search-->
        <div class="vi-ui form woopb-search-form">

            <div class="inline fields">
                <div class="three wide field">
                    <label for="<?php echo self::set_field( 'select_product' ) ?>"><?php esc_html_e( 'Select products', 'woocommerce-product-builder' ) ?></label>
                </div>
                <div class="three wide field">
                    <select class="vi-ui  dropdown woopb-type">
                        <option value="0"><?php esc_html_e( 'Categories', 'woocommerce-product-builder' ) ?></option>
                        <option value="1"><?php esc_html_e( 'Products', 'woocommerce-product-builder' ) ?></option>
                    </select>
                </div>
                <div class="one wide field">
                </div>
                <div class="eight wide field">
                    <div class="vi-ui action input">
                        <input class="wpb-search-field" type="text"
                               placeholder="<?php esc_attr_e( 'Fill your product title or category title', 'woocommerce-product-builder' ) ?>"/>
                        <span class="vi-ui button blue woopb-search-button"><?php esc_html_e( 'Search', 'woocommerce-product-builder' ) ?></span>
                    </div>
                </div>
            </div>

			<?php do_action( 'woopb_after_woopb_search_form', $post ) ?>

            <script type="text/html" id="tmpl-woopb-item-template">
                <div class="woopb-item woopb-item-{{{data.item_class}}}" data-id="{{{data.id}}}">
                    <div class="woopb-item-top">{{{data.thumb}}}</div>
                    <div class="woopb-item-bottom">{{{data.name}}}</div>
                </div>
            </script>
            <div class="woopb-product-select">
                <div class="woopb-items">
					<?php
					$args  = array(
						'taxonomy'   => 'product_cat',
						'orderby'    => 'name',
						'hide_empty' => true,
						'number'     => 20
					);
					$cates = get_terms( $args );
					if ( count( $cates ) ) {
						foreach ( $cates as $cat ) { ?>
                            <div class="woopb-item woopb-item-category"
                                 data-id="<?php echo esc_attr( $cat->term_id ) ?>">
                                <div class="woopb-item-top"></div>
                                <div class="woopb-item-bottom"><?php echo esc_html( $cat->name ) ?></div>
                            </div>
						<?php }
					}
					?>
                </div>
            </div>
        </div>
		<?php
		$list_contents    = self::get_field( 'list_content', array() );
		$tab_titles       = self::get_field( 'tab_title', array() );
		$step_icons       = self::get_field( 'step_icon', array() );
		$step_descs       = self::get_field( 'step_desc', array() );
		$step_error_descs = self::get_field( 'step_error_desc', array() );
		?>
        <div class="vi-ui form woopb-items-added small">

            <div class="inline fields">
                <div class="four wide field woopb-tabs">
                    <div class="vi-ui vertical tabular menu woopb-sortable">
						<?php if ( count( $tab_titles ) ) {
							foreach ( $tab_titles as $k => $tab_title ) {
								?>
                                <a class="item <?php echo $k ? '' : 'active' ?>"
                                   data-tab="<?php echo esc_attr( $k ) ?>">
                                    <span class="woopb-remove"></span>
                                    <span class="woopb-edit"></span>
                                    <span class="woopb-tab-title"><?php echo esc_html( $tab_title ) ?></span>
                                    <input type="hidden" class="woopb-save-name" name="woopb-param[tab_title][<?php echo esc_attr( $k ) ?>]"
                                           value="<?php echo esc_attr( $tab_title ) ?>">
                                </a>
							<?php }
						} else { ?>
                            <a class="active item" data-tab="first">
                                <span class="woopb-tab-title"><?php esc_html_e( 'First step', 'woocommerce-product-builder' ) ?></span>
                                <span class="woopb-edit"></span>
                                <span class="woopb-remove"></span>
                                <input type="hidden" class="woopb-save-name" name="woopb-param[tab_title][first]" value="first">
                            </a>
						<?php } ?>
                    </div>
                </div>
                <div class="twelve wide field woopb-tabs-content">
					<?php
					if ( count( $list_contents ) ) {
						foreach ( $list_contents as $k => $list_content ) {
							$step_desc       = $step_descs[ $k ] ?? '';
							$step_error_desc = $step_error_descs[ $k ] ?? '';
							$step_icon_id    = $step_icons[ $k ] ?? '';
							$step_icon       = $step_icon_id ? wp_get_attachment_url( $step_icon_id ) : '';
							?>
                            <div class="vi-ui tab <?php echo $k ? '' : 'active' ?>"
                                 data-tab="<?php echo esc_attr( $k ) ?>">

                                <div class="woopb-tab-inner">
									<?php
									if ( is_array( $list_content ) && count( $list_content ) ) {
										foreach ( $list_content as $item ) {

											$item_data     = array();
											$check_product = 0;
											if ( strpos( trim( $item ), 'cate_' ) === false ) {

												$item_data['title'] = get_post_field( 'post_title', $item );
												$item_data['id']    = get_post_field( 'ID', $item );

												$check_product = 1;
											} else {
												$term_id            = str_replace( 'cate_', '', trim( $item ) );
												$term_data          = get_term_by( 'id', $term_id, 'product_cat' );
												$item_data['title'] = $term_data->name;
												$item_data['id']    = $term_data->term_id;

											}

											?>
                                            <div class="woopb-item woopb-item-<?php echo $check_product ? 'product' : 'category' ?> <?php echo has_post_thumbnail( $item_data['id'] ) && $check_product ? 'woopb-img' : '' ?>"
                                                 data-id="<?php echo esc_attr( $item_data['id'] ) ?>">
                                                <div class="woopb-item-top">
													<?php if ( $check_product ) {
														echo get_the_post_thumbnail( $item_data['id'] );
													} ?>
                                                </div>
                                                <div class="woopb-item-bottom"><?php echo esc_attr( $item_data['title'] ) ?></div>
                                                <input type="hidden"
                                                       name="woopb-param[list_content][<?php echo esc_attr( $k ) ?>][]"
                                                       value="<?php echo $check_product ? esc_attr( $item_data['id'] ) : 'cate_' . esc_attr( $item_data['id'] ) ?>">
                                            </div>
											<?php
										}
									}
									?>
                                </div>

                                <div class="vi-ui segment">
                                    <strong><?php esc_html_e( 'Step options', 'woocommerce-product-builder' ); ?></strong>
                                    <table width="100%">
                                        <tr>
                                            <td><?php esc_html_e( 'Step icon:', 'woocommerce-product-builder' ); ?></td>
                                            <td>
                                                <div class="woopb-step-icon">
                                                    <span class="woopb-remove-step-icon">&times;</span>
													<?php
													if ( $step_icon ) {
														printf( '<img class="woopb-select-step-icon" src="%s">', esc_url( $step_icon ) );
													} else {
														?>
                                                        <img class="woopb-select-step-icon"
                                                             src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAGQAAABkCAQAAADa613fAAAAaUlEQVR42u3PQREAAAgDINc/9Izg34MGpJ0XIiIiIiIiIiIiIiIiIiIiIiIiIiIiIiIiIiIiIiIiIiIiIiIiIiIiIiIiIiIiIiIiIiIiIiIiIiIiIiIiIiIiIiIiIiIiIiIiIiIiIiJyWYprx532021aAAAAAElFTkSuQmCC">
														<?php
													}
													?>
                                                    <input type="hidden" class="woopb-step-icon-id" name="woopb-param[step_icon][<?php echo esc_attr( $k ) ?>]"
                                                           value="<?php echo esc_attr( $step_icon_id ) ?>">
                                                </div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><?php esc_html_e( 'Description:', 'woocommerce-product-builder' ); ?></td>
                                            <td>
                                                <textarea name="woopb-param[step_desc][<?php echo esc_attr( $k ) ?>]" rows="3"><?php echo wp_kses_post( $step_desc ) ?></textarea>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><?php esc_html_e( 'Error description:', 'woocommerce-product-builder' ); ?></td>
                                            <td>
                                                <textarea name="woopb-param[step_error_desc][<?php echo esc_attr( $k ) ?>]" rows="3"
                                                          placeholder="<?php esc_html_e( 'Description when no product found', 'woocommerce-product-builder' ); ?>"><?php echo wp_kses_post( $step_error_desc ) ?></textarea>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
						<?php }
					} else { ?>
                        <div class="vi-ui active tab" data-tab="first">
                            <div class="woopb-tab-inner">

                            </div>

                            <div class="vi-ui segment">
                                <strong><?php esc_html_e( 'Step options', 'woocommerce-product-builder' ); ?></strong>
                                <table width="100%">
                                    <tr>
                                        <td><?php esc_html_e( 'Step icon:', 'woocommerce-product-builder' ); ?></td>
                                        <td>
                                            <div class="woopb-step-icon">
                                                <span class="woopb-remove-step-icon">&times;</span>
                                                <img class="woopb-select-step-icon"
                                                     src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAGQAAABkCAQAAADa613fAAAAaUlEQVR42u3PQREAAAgDINc/9Izg34MGpJ0XIiIiIiIiIiIiIiIiIiIiIiIiIiIiIiIiIiIiIiIiIiIiIiIiIiIiIiIiIiIiIiIiIiIiIiIiIiIiIiIiIiIiIiIiIiIiIiIiIiIiIiJyWYprx532021aAAAAAElFTkSuQmCC">

                                                <input type="hidden" class="woopb-step-icon-id" name="woopb-param[step_icon][0]" value="">
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><?php esc_html_e( 'Description:', 'woocommerce-product-builder' ); ?></td>
                                        <td>
                                            <textarea name="woopb-param[step_desc][0]" rows="3"></textarea>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><?php esc_html_e( 'Error description:', 'woocommerce-product-builder' ); ?></td>
                                        <td>
                                            <textarea name="woopb-param[step_error_desc][0]" rows="3"
                                                      placeholder="<?php esc_html_e( 'Description when no product found', 'woocommerce-product-builder' ); ?>"></textarea>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>

					<?php } ?>
                </div>
            </div>
        </div>
        <p class="woopb-controls">
            <span class="vi-ui button green woopb-add-tab"><?php esc_html_e( 'Add New Step', 'woocommerce-product-builder' ) ?></span>
        </p>
		<?php
	}

	/**
	 * Set fields post meta
	 */
	public static function set_field( $field, $multi = false ) {
		if ( $field ) {
			if ( $multi ) {
				return 'woopb-param[' . $field . '][]';
			} else {
				return 'woopb-param[' . $field . ']';
			}

		} else {
			return '';
		}
	}

	/**
	 * Get fields post meta
	 */
	public static function get_field( $field, $default = '' ) {
		global $post;
		$params = get_post_meta( $post->ID, 'woopb-param', true );
		if ( isset( $params[ $field ] ) && $field ) {
			return $params[ $field ];
		} else {
			return $default;
		}
	}

	/**
	 * Register products per page metaboxes
	 */
	public function products_per_page_html() { ?>
        <table class="form-table vi-ui form">
            <tr valign="top">
                <th scope="row">
                    <label for="<?php echo self::set_field( 'product_per_page' ) ?>"><?php esc_html_e( 'Product per page', 'woocommerce-product-builder' ) ?></label>
                </th>
                <td>
                    <input type="number" id="<?php echo self::set_field( 'product_per_page' ) ?>"
                           name="<?php echo self::set_field( 'product_per_page' ) ?>"
                           value="<?php echo self::get_field( 'product_per_page', 10 ) ?>" min="1"/>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">
                    <label for="<?php echo self::set_field( 'enable_compatible' ) ?>"><?php esc_html_e( 'Depend', 'woocommerce-product-builder' ) ?></label>
                </th>
                <td>
                    <div class="vi-ui checkbox toggle">
                        <input <?php checked( self::get_field( 'enable_compatible' ), 1 ) ?>
                                type="checkbox"
                                id="<?php echo self::set_field( 'enable_compatible' ) ?>"
                                name="<?php echo self::set_field( 'enable_compatible' ) ?>"
                                value="1"/>
                        <label for="<?php echo self::set_field( 'enable_compatible' ) ?>"><?php esc_html_e( 'Enable', 'woocommerce-product-builder' ) ?></label>
                    </div>
                    <p class="description"><?php esc_html_e( 'Please save first to load all steps.', 'woocommerce-product-builder' ) ?></p>
                    <table class="vi-ui single line table green">
                        <thead>
                        <tr>
                            <th><?php esc_html_e( 'STEP', 'woocommerce-product-builder' ) ?></th>
                            <th><?php esc_html_e( 'DEPENDING ON', 'woocommerce-product-builder' ) ?></th>
                        </tr>
                        </thead>
                        <tbody>
		                <?php $tabs = self::get_field( 'tab_title' );
		                $compatible = self::get_field( 'product_compatible', array() );
		                if ( is_array( $tabs ) && ! empty( $tabs ) ) {
			                foreach ( $tabs as $key => $title ) {
				                if ( ! $key ) {
					                continue;
				                }
				                $step_compatible = isset( $compatible[ $key ] ) ? $compatible[ $key ] : array();
				                ?>
                                <tr>
                                    <td><?php echo esc_html( $title ) ?></td>
                                    <td>
                                        <select class="woopb-compatible-field" multiple="multiple"
                                                name="<?php echo self::set_field( 'product_compatible' ) ?>[<?php echo esc_attr( $key ) ?>][]">
							                <?php foreach ( $tabs as $key_2 => $title_2 ) {
								                if ( $key <= $key_2 ) {
									                break;
								                }
								                ?>
                                                <option <?php selected( in_array( $key_2, $step_compatible ), 1 ) ?>
                                                        value="<?php echo esc_attr( $key_2 ) ?>"><?php echo esc_html( $title_2 ) ?></option>
							                <?php } ?>
                                        </select>
						                <?php do_action( 'woopb_product_options_setting', $key ); ?>
                                    </td>

                                </tr>
			                <?php }
		                } ?>
                        </tbody>
                        <tfoot>
                        <tr>
                            <td colspan="2">
								<span class="vi-ui button woopb-compatible-clear-all red">
								<?php esc_html_e( 'Clear all', 'woocommerce-product-builder' ) ?>
								</span>
                            </td>
                        </tr>
                        </tfoot>
                    </table>
                </td>
            </tr>
        </table>
	<?php }

	/**
	 * General setting metaboxes
	 */
	public function general_setting_html() { ?>
        <table class="form-table vi-ui form">
            <tr valign="top">
                <th scope="row">
                    <label for="<?php echo self::set_field( 'text_prefix' ); ?>"><?php esc_html_e( 'Text prefix each step', 'woocommerce-product-builder' ) ?></label>
                </th>
                <td>
                    <input type="text" name="<?php echo self::set_field( 'text_prefix' ); ?>"
                           id="<?php echo self::set_field( 'text_prefix' ); ?>"
                           value="<?php echo self::get_field( 'text_prefix', 'Step {step_number}' ); ?>">
                    <p class="description"><?php esc_html_e( '{step_number} - Number of current step', 'woocommerce-product-builder' ) ?></p>
                </td>
            </tr>

            <tr valign="top">
                <th scope="row">
                    <label for="<?php echo self::set_field( 'description' ); ?>"><?php esc_html_e( 'Description', 'woocommerce-product-builder' ) ?></label>
                </th>
                <td>
                    <input type="text" name="<?php echo self::set_field( 'description' ); ?>"
                           id="<?php echo self::set_field( 'description' ); ?>"
                           value="<?php echo self::get_field( 'description' ); ?>">
                    <!--                    <p class="description">-->
					<?php //esc_html_e( '{step_number} - Number of current step', 'woocommerce-product-builder' ) ?><!--</p>-->
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">
                    <label for="<?php echo self::set_field( 'sort_default' ); ?>">
						<?php esc_html_e( 'Sort default', 'woocommerce-product-builder' ) ?>
                    </label>
                </th>
                <td>
                    <div class="">
						<?php
						$data     = new VI_WPRODUCTBUILDER_Data();
						$options  = $data->get_sort_options();
						$selected = self::get_field( 'sort_default' );
						?>
                        <select name="<?php echo self::set_field( 'sort_default' ); ?>">
							<?php
							foreach ( $options as $key => $option ) {
								$selectedd = $selected == $key ? 'selected' : '';
								echo sprintf( "<option value='%1s' %2s>%3s</option>", esc_attr( $key ), esc_attr( $selectedd ), esc_html( $option ) );
							}
							?>
                        </select>
                    </div>
                    <p class="description"><?php esc_html_e( '', 'woocommerce-product-builder' ) ?></p>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">
                    <label for="<?php echo self::set_field( 'child_cat' ); ?>"><?php esc_html_e( 'Child categories', 'woocommerce-product-builder' ) ?></label>
                </th>
                <td>
                    <div class="vi-ui toggle checkbox checked">
                        <input type="checkbox" name="<?php echo self::set_field( 'child_cat' ); ?>"
                               id="<?php echo self::set_field( 'child_cat' ); ?>" <?php checked( self::get_field( 'child_cat' ), 1 ); ?>
                               value="1">
                        <label for="<?php echo self::set_field( 'child_cat' ); ?>"><?php esc_html_e( 'Enable', 'woocommerce-product-builder' ) ?></label>
                    </div>
                    <p class="description"><?php esc_html_e( 'Get all products in child categories', 'woocommerce-product-builder' ) ?></p>
                </td>
            </tr>

            <tr valign="top">
                <th scope="row">
                    <label for="<?php echo self::set_field( 'enable_multi_select' ); ?>"><?php esc_html_e( 'Add many products in a step', 'woocommerce-product-builder' ) ?></label>
                </th>
                <td>
                    <div class="vi-ui toggle checkbox checked">
                        <input type="checkbox" name="<?php echo self::set_field( 'enable_multi_select' ); ?>"
                               id="<?php echo self::set_field( 'enable_multi_select' ); ?>" <?php checked( self::get_field( 'enable_multi_select' ), 1 ); ?>
                               value="1">
                        <label for="<?php echo self::set_field( 'enable_multi_select' ); ?>"><?php esc_html_e( 'Enable', 'woocommerce-product-builder' ) ?></label>
                    </div>
                    <p class="description"><?php esc_html_e( 'Select multiple products in a step', 'woocommerce-product-builder' ) ?></p>
                </td>
            </tr>

            <tr valign="top">
                <th scope="row">
                    <label for="<?php echo self::set_field( 'enable_quantity' ); ?>"><?php esc_html_e( 'Quantity field', 'woocommerce-product-builder' ) ?></label>
                </th>
                <td>
                    <div class="vi-ui toggle checkbox checked">
                        <input type="checkbox" name="<?php echo self::set_field( 'enable_quantity' ); ?>"
                               id="<?php echo self::set_field( 'enable_quantity' ); ?>" <?php checked( self::get_field( 'enable_quantity' ), 1 ); ?>
                               value="1">
                        <label for="<?php echo self::set_field( 'enable_quantity' ); ?>"><?php esc_html_e( 'Enable', 'woocommerce-product-builder' ) ?></label>
                    </div>
                    <p class="description"><?php esc_html_e( 'Default quantity is 1. Enable it to add more.', 'woocommerce-product-builder' ) ?></p>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">
                    <label for="<?php echo self::set_field( 'enable_preview_always' ); ?>"><?php esc_html_e( 'Preview button always show ', 'woocommerce-product-builder' ) ?></label>
                </th>
                <td>
                    <div class="vi-ui toggle checkbox checked">
                        <input type="checkbox" name="<?php echo self::set_field( 'enable_preview_always' ); ?>"
                               id="<?php echo self::set_field( 'enable_preview_always' ); ?>" <?php checked( self::get_field( 'enable_preview_always' ), 1 ); ?>
                               value="1">
                        <label for="<?php echo self::set_field( 'enable_preview_always' ); ?>"><?php esc_html_e( 'Enable', 'woocommerce-product-builder' ) ?></label>
                    </div>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">
                    <label for="<?php echo self::set_field( 'add_to_cart_always_show' ); ?>"><?php esc_html_e( 'Add to cart button always show ', 'woocommerce-product-builder' ) ?></label>
                </th>
                <td>
                    <div class="vi-ui toggle checkbox checked">
                        <input type="checkbox" name="<?php echo self::set_field( 'add_to_cart_always_show' ); ?>"
                               id="<?php echo self::set_field( 'add_to_cart_always_show' ); ?>" <?php checked( self::get_field( 'add_to_cart_always_show' ), 1 ); ?>
                               value="1">
                        <label for="<?php echo self::set_field( 'add_to_cart_always_show' ); ?>"><?php esc_html_e( 'Enable', 'woocommerce-product-builder' ) ?></label>
                    </div>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">
                    <label for="<?php echo self::set_field( 'remove_all_button' ); ?>"><?php esc_html_e( 'Remove all button ', 'woocommerce-product-builder' ) ?></label>
                </th>
                <td>
                    <div class="vi-ui toggle checkbox checked">
                        <input type="checkbox" name="<?php echo self::set_field( 'remove_all_button' ); ?>"
                               id="<?php echo self::set_field( 'remove_all_button' ); ?>" <?php checked( self::get_field( 'remove_all_button' ), 1 ); ?>
                               value="1">
                        <label for="<?php echo self::set_field( 'remove_all_button' ); ?>"><?php esc_html_e( 'Enable', 'woocommerce-product-builder' ) ?></label>
                    </div>
                    <p class="description"><?php esc_html_e( 'Display "Remove all" button on product builder page', 'woocommerce-product-builder' ) ?></p>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">
                    <label for="<?php echo self::set_field( 'search_product_form' ); ?>"><?php esc_html_e( 'Search product form', 'woocommerce-product-builder' ) ?></label>
                </th>
                <td>
                    <div class="vi-ui toggle checkbox checked">
                        <input type="checkbox" name="<?php echo self::set_field( 'search_product_form' ); ?>"
                               id="<?php echo self::set_field( 'search_product_form' ); ?>" <?php checked( self::get_field( 'search_product_form' ), 1 ); ?>
                               value="1">
                        <label for="<?php echo self::set_field( 'search_product_form' ); ?>"><?php esc_html_e( 'Enable', 'woocommerce-product-builder' ) ?></label>
                    </div>
                    <p class="description"><?php esc_html_e( 'Display search products form by ajax', 'woocommerce-product-builder' ) ?></p>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">
                    <label for="<?php echo self::set_field( 'require_product' ); ?>"><?php esc_html_e( 'Product is required each step', 'woocommerce-product-builder' ) ?></label>
                </th>
                <td>
                    <div class="vi-ui toggle checkbox checked">
                        <input type="checkbox" name="<?php echo self::set_field( 'require_product' ); ?>"
                               id="<?php echo self::set_field( 'require_product' ); ?>" <?php checked( self::get_field( 'require_product' ), 1 ); ?>
                               value="1">
                        <label for="<?php echo self::set_field( 'require_product' ); ?>"><?php esc_html_e( 'Enable', 'woocommerce-product-builder' ) ?></label>
                    </div>
                    <p class="description"><?php esc_html_e( '', 'woocommerce-product-builder' ) ?></p>
                </td>
            </tr>

            <tr valign="top">
                <th scope="row">
                    <label for="<?php echo self::set_field( 'out_of_stock_product' ); ?>">
						<?php esc_html_e( 'Out of stock products', 'woocommerce-product-builder' ) ?>
                    </label>
                </th>
                <td>
                    <div class="vi-ui toggle checkbox checked">
                        <input type="checkbox" name="<?php echo self::set_field( 'out_of_stock_product' ); ?>"
                               id="<?php echo self::set_field( 'out_of_stock_product' ); ?>" <?php checked( self::get_field( 'out_of_stock_product' ), 1 ); ?>
                               value="1">
                        <label for="<?php echo self::set_field( 'out_of_stock_product' ); ?>"><?php esc_html_e( 'Enable', 'woocommerce-product-builder' ) ?></label>
                    </div>
                    <p class="description"><?php esc_html_e( 'Enable it to display out of stock products on product builder page', 'woocommerce-product-builder' ) ?></p>
                </td>
            </tr>

            <tr valign="top">
                <th scope="row">
                    <label for="<?php echo self::set_field( 'zero_price_product' ); ?>">
						<?php esc_html_e( 'Hide zero price product', 'woocommerce-product-builder' ) ?>
                    </label>
                </th>
                <td>
                    <div class="vi-ui toggle checkbox checked">
                        <input type="checkbox" name="<?php echo self::set_field( 'zero_price_product' ); ?>"
                               id="<?php echo self::set_field( 'zero_price_product' ); ?>" <?php checked( self::get_field( 'zero_price_product' ), 1 ); ?>
                               value="1">
                        <label for="<?php echo self::set_field( 'zero_price_product' ); ?>"><?php esc_html_e( 'Enable', 'woocommerce-product-builder' ) ?></label>
                    </div>
                    <p class="description"><?php esc_html_e( 'Enable it to hide the products which have zero prices.', 'woocommerce-product-builder' ) ?></p>
                </td>
            </tr>

            <tr valign="top">
                <th scope="row">
                    <label for="<?php echo self::set_field( 'remove_product_link' ); ?>">
						<?php esc_html_e( 'Remove product title link', 'woocommerce-product-builder' ) ?>
                    </label>
                </th>
                <td>
                    <div class="vi-ui toggle checkbox checked">
                        <input type="checkbox" name="<?php echo self::set_field( 'remove_product_link' ); ?>"
                               id="<?php echo self::set_field( 'remove_product_link' ); ?>" <?php checked( self::get_field( 'remove_product_link' ), 1 ); ?>
                               value="1">
                        <label for="<?php echo self::set_field( 'remove_product_link' ); ?>"><?php esc_html_e( 'Enable', 'woocommerce-product-builder' ) ?></label>
                    </div>
                    <p class="description"><?php esc_html_e( 'Enable it to disable the link to single product pages from the title of products.', 'woocommerce-product-builder' ) ?></p>
                </td>
            </tr>

        </table>
	<?php }


	/**
	 * Save metaboxes
	 */
	public function save_post_metadata( $post_id ) {
		// verify nonce
		if ( ! isset( $_POST['_woopb_field_nonce'] ) || ! isset( $_POST['woopb-param'] ) ) {
			return false;
		}

		if ( ! current_user_can( 'edit_page', $post_id ) ) {
			return esc_html__( 'Cannot edit page', 'woocommerce-product-builder' );
		}

		$data = $_POST['woopb-param'];
		array_walk_recursive( $data, 'sanitize_text_field' );
		$temp = [
			'list_content' => [],
			'step_icon'    => [],
			'step_desc'    => [],
		];

		if ( is_array( $data['tab_title'] ) && ! empty( $data['tab_title'] ) ) {

			foreach ( $data['tab_title'] as $key => $title ) {
				if ( ! empty( $data['list_content'][ $key ] ) ) {
					$temp['list_content'][ $key ] = $data['list_content'][ $key ];
					$temp['step_icon'][ $key ]    = $data['step_icon'][ $key ];
					$temp['step_desc'][ $key ]    = $data['step_desc'][ $key ];

				} else {
					unset( $data['tab_title'][ $key ] );
				}
			}

			$data['tab_title']    = array_values( $data['tab_title'] );
			$data['list_content'] = array_values( $temp['list_content'] );
			$data['step_icon']    = array_values( $temp['step_icon'] );
			$data['step_desc']    = array_values( $temp['step_desc'] );
		}

		update_post_meta( $post_id, 'woopb-param', $data );
	}

	public function define_shortcode_columns( $columns ) {
		unset( $columns['date'] );
		$columns['shortcode'] = esc_html__( ' Shortcode', 'woocommerce-product-builder' );
		$columns['date']      = esc_html__( ' Date', 'woocommerce-product-builder' );

		return $columns;
	}

	public function shortcode_columns( $column, $id ) {
		if ( $column == 'shortcode' ) {
			echo "<input class='woopb-shortcode' type='text' value='[woocommerce_product_builder id=\"{$id}\"]' readonly onclick='this.select();document.execCommand(\"copy\");'>";
		}
	}

	public function enqueue_style() {
		if ( get_current_screen()->id == 'edit-woo_product_builder' ) {
			wp_register_style( 'woopb-inline-style', false );
			wp_enqueue_style( 'woopb-inline-style' );
			$css = ".woopb-shortcode{width:300px;}";
			wp_add_inline_style( 'woopb-inline-style', $css );
		}
	}

	public function show_shortcode() {
		global $post;

		if ( get_current_screen()->id !== 'woo_product_builder' ) {
			return;
		}
		?>
        <div class="woopb-shortcode-group">
            <strong>
				<?php esc_html_e( 'Shortcode:', 'woocommerce-product-builder' ); ?>
            </strong>
            <input class='woopb-shortcode' type='text' readonly
                   value='[woocommerce_product_builder id=<?php echo esc_attr( $post->ID ) ?>]' onclick='this.select();document.execCommand("copy");'>
            <span>
                <?php esc_html_e( '(Note: Use one shortcode per page only)', 'woocommerce-product-builder' ); ?>
            </span>
        </div>
		<?php
	}
}