<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

class VI_WPRODUCTBUILDER_FrontEnd_Step {
	protected $data;
	protected $total_price;

	public function __construct() {
		$this->settings = new VI_WPRODUCTBUILDER_Data();
		/*Add Script*/
		add_action( 'wp_enqueue_scripts', array( $this, 'init_scripts' ), 20 );
		/*Single template*/
		add_action( 'woocommerce_product_builder_single_content', array(
			$this,
			'product_builder_content_single_page'
		), 11 );

		//STYLE CLASSIC
		add_action( 'woocommerce_product_builder_single_product_content_before', array( $this, 'sort_by' ) );
		add_action( 'woocommerce_product_builder_single_product_content_before', array( $this, 'require_notice' ) );
		add_action( 'woocommerce_product_builder_before_single_top', array( $this, 'description' ), 1 );
		add_action( 'woocommerce_product_builder_before_single_top', array( $this, 'step_title' ), 9 );
		add_action( 'woocommerce_product_builder_before_single_top', array( $this, 'step_html' ), 30 );
		add_action( 'woocommerce_product_builder_before_single_top', array( $this, 'mobile' ), 30 );
		add_action( 'woocommerce_product_builder_single_bottom', array(
			$this,
			'single_product_content_pagination'
		), 10, 2 );

		//STYLE MODERN
		if ( VI_WOOPBTEMPLATE_STYLE === 'modern' ) {
			add_action( 'woocommerce_product_builder_step_title', [ $this, 'step_title' ] );
			add_action( 'woocommerce_product_builder_content_header', [ $this, 'sort_by' ] );
			add_action( 'woocommerce_product_builder_center', [ $this, 'single_product_content_pagination' ], 10, 2 );
			add_action( 'woocommerce_product_builder_right', [ $this, 'step_html' ], 12, 3 );
			add_action( 'woocommerce_product_builder_after', [ $this, 'mobile' ], 12, 3 );
//			add_action( 'woocommerce_product_builder_before_steps_panel', [ $this, 'navigation' ], 12, 3 );
		}

		/*Form send email to friend of review page*/
		if ( $this->settings->enable_email() ) {
			add_action( 'wp_footer', array( $this, 'share_popup_form' ) );
		}

		/*Product html*/
		add_action( 'woocommerce_product_builder_single_product_content', array( $this, 'product_thumb' ), 10 );
		add_action( 'woocommerce_product_builder_single_product_content', array( $this, 'product_title' ), 20 );
		add_action( 'woocommerce_product_builder_single_product_content', array( $this, 'product_price' ), 30, 2 );
		add_action( 'woocommerce_product_builder_single_product_content', array( $this, 'product_description' ), 35 );
		add_action( 'woocommerce_product_builder_single_product_content', array( $this, 'add_to_cart' ), 40 );

		add_action( 'woocommerce_product_builder_simple_add_to_cart', array( $this, 'simple_add_to_cart' ), 40 );
		add_action( 'woocommerce_product_builder_variable_add_to_cart', array( $this, 'variable_add_to_cart' ), 40 );
		add_action( 'woocommerce_product_builder_single_variation', array(
			$this,
			'woocommerce_single_variation'
		), 10 );
		add_action( 'woocommerce_product_builder_single_variation', array(
			$this,
			'woocommerce_product_builder_single_variation'
		), 20 );
		add_action( 'woocommerce_product_builder_quantity_field', array( $this, 'quantity_field' ), 10, 2 );

		/*Add Query var*/
		add_action( 'pre_get_posts', array( $this, 'add_vars' ) );

		/*AJAX Template*/
		add_action( 'woopb_modal_single_product_content_left', [ $this, 'product_thumb' ] );
		add_action( 'woopb_modal_single_product_content_right', [ $this, 'modal_product_title' ] );
		add_action( 'woopb_modal_single_product_content_right', [ $this, 'modal_product_price' ] );
		add_action( 'woopb_modal_single_product_content_right', [ $this, 'product_description' ] );
		add_action( 'woopb_modal_single_product_content_right', [ $this, 'modal_add_to_cart_button' ], 10, 2 );
		add_action( 'woopb_sidebar', [ $this, 'ajax_product_builder_total' ], 10 );
		add_action( 'woopb_sidebar', [ $this, 'ajax_product_builder_add_to_cart' ], 20 );
		add_action( 'woopb_sidebar', [ $this, 'ajax_product_builder_remove_all' ], 30 );
		add_action( 'woopb_sidebar', [ $this, 'ajax_product_builder_buttons_group' ], 30 );

		add_action( 'woopb_load_step_products_modal_right_header', [ $this, 'ajax_product_builder_search_form' ] );
		add_action( 'woopb_load_step_products_modal_right_header', [ $this, 'ajax_product_builder_sort_form' ] );
	}

	/*
	 *
	 */
	public function quantity_field( $product, $post_id ) {
		$enable_quantity = $this->get_data( $post_id, 'enable_quantity' );
		if ( $enable_quantity ) {


			woocommerce_quantity_input( array(
				'min_value'   => apply_filters( 'woocommerce_quantity_input_min', $product->get_min_purchase_quantity(), $product ),
				'max_value'   => apply_filters( 'woocommerce_quantity_input_max', $product->get_max_purchase_quantity(), $product ),
				'input_value' => isset( $_POST['quantity'] ) ? 1 : $product->get_min_purchase_quantity(),
			), $product );
		}
	}

	public function share_popup_form() {
		global $wp_query;
		if ( isset( $wp_query->query_vars['woopb_preview'] ) ) {
			wpb_get_template( 'content-product-builder-preview-popup.php' );
		}
	}

	/**
	 *
	 */
	public function woocommerce_product_builder_single_variation( $post_id ) {
		$button_icon = $this->settings->get_button_icon();
		wpb_get_template( 'single/variation-add-to-cart-button.php', array(
			'post_id'     => $post_id,
			'button_icon' => $button_icon
		) );
	}

	/**
	 *
	 */
	public function woocommerce_single_variation() {
		echo '<div class="woocommerce-product-builder-variation single_variation"></div>';
	}

	public function step_title( $id ) {
		global $post;
		$post_id = is_woopb_shortcode() ? $id : $post->ID;

		/*Process Navigation button*/
		$step_id    = get_query_var( 'step' );
		$tabs       = $this->get_data( $post_id, 'tab_title' );
		$step_descs = $this->get_data( $post_id, 'step_desc' );

		if ( ! is_array( $tabs ) ) {
			return;
		}

		$step_id = $step_id ? $step_id : 1;

		?>
        <div class="woopb-heading-navigation">
            <div class="woopb-heading">
				<?php $step_text = $this->get_data( $post_id, 'text_prefix' );
				if ( $step_text ) {
					echo '<span class="woopb-heading-step-prefix">' . esc_html( str_replace( '{step_number}', $step_id, $step_text ) ) . '</span>';
				}
				echo '<span class="woopb-heading-step-title">' . esc_html( $tabs[ $step_id - 1 ] ?? '' ) . '</span>';
				echo '<div class="woopb-heading-step-desc">' . wp_kses_post( wpautop( $step_descs[ $step_id - 1 ] ?? '' ) ) . '</div>' ?>

            </div>

			<?php $this->navigation( $id ); ?>
        </div>
		<?php
	}

	public function navigation( $id ) {
		global $post;
		$post_id = is_woopb_shortcode() ? $id : $post->ID;

		/*Process Navigation button*/
		$step_id = get_query_var( 'step' );
		$tabs    = $this->get_data( $post_id, 'tab_title' );

		if ( ! is_array( $tabs ) ) {
			return;
		}

		$count_tabs = count( $tabs );

		$step_id   = $step_id ? $step_id : 1;
		$step_prev = $step_next = 0;
		if ( $count_tabs > $step_id ) {
			$step_next = $step_id + 1;
			if ( $step_id > 1 ) {
				$step_prev = $step_id - 1;
			}
		} else {
			if ( $step_id > 1 ) {
				$step_prev = $step_id - 1;
			}
		}
		$review_url   = add_query_arg( array( 'woopb_preview' => 1 ), get_the_permalink() );
		$next_url     = add_query_arg( array( 'step' => $step_next ), get_the_permalink() );
		$previous_url = add_query_arg( array( 'step' => $step_prev ), get_the_permalink() );
		?>
        <div class="woopb-navigation">
			<?php /*Check all steps that producted are added*/

			if ( VI_WOOPBTEMPLATE_STYLE === 'modern' && is_active_sidebar( 'woopb-sidebar' ) ) {
				?>
                <div class="woopb-pc-filters-control "></div>
				<?php
			}

			if ( ! $step_next || $this->get_data( $post_id, 'enable_preview_always' ) ) { ?>
                <div class="woopb-navigation-preview">
                    <a href="<?php echo esc_url( $review_url ) ?>" class="woopb-link"
                       title="<?php esc_html_e( 'Preview', 'woocommerce-product-builder' ); ?>">
                        <span class="woopb-preview-icon"> </span>
                    </a>
                </div>
			<?php } ?>

            <div class="woopb-navigation-previous">
                <a href="<?php echo $step_prev ? esc_url( $previous_url ) : 'javascript:void(0)'; ?>" class="woopb-link"
                   title="<?php esc_html_e( 'Previous', 'woocommerce-product-builder' ); ?>">
                    <span class="woopb-previous-icon <?php echo $step_prev ? '' : 'woopb-blur'; ?>"></span>
                </a>
            </div>
            <div class="woopb-navigation-next">
                <a href="<?php echo $step_next ? esc_url( $next_url ) : 'javascript:void(0)'; ?>" class="woopb-link"
                   title="<?php esc_html_e( 'Next', 'woocommerce-product-builder' ); ?>">
                    <span class="woopb-next-icon <?php echo $step_next ? '' : 'woopb-blur'; ?>"></span>
                </a>
            </div>


        </div>
		<?php

	}

	/**
	 * Sort by
	 */
	public function sort_by() {
		/*Process sort by*/
		global $post;
		$post_id = is_woopb_shortcode() ? VI_WPRODUCTBUILDER_FrontEnd_Shortcode::$woopb_id : $post->ID;

		$current     = get_query_var( 'sort_by' );
		$current     = $current ? $current : $this->get_data( $post_id, 'sort_default' );
		$step        = get_query_var( 'step' );
		$search_form = $flex_class = '';
		if ( $this->get_data( $post_id, 'search_product_form' ) ) {
			$flex_class = "style='display:flex;'";
			ob_start();
			?>
            <div class="woopb-search-products-form">
                <input type="text" class="woopb-search-products-input" data-step="<?php echo esc_attr( $step ); ?>"
                       data-post="<?php echo esc_attr( $post_id ); ?>"
                       value="<?php echo esc_attr(get_query_var('name_filter')); ?>"
                       placeholder="<?php esc_html_e( 'Search...', 'woocommerce-product-builder' ); ?>">
                <div class="woopb-spinner">
                    <div class="woopb-spinner-inner woopb-hidden">
                    </div>
                </div>
            </div>
			<?php
			$search_form = ob_get_clean();
		}
		?>
        <div class="woopb-sort-by">
			<?php echo $search_form; ?>
            <div class="woopb-sort-by-inner">
				<?php
				$sort_by_events = $this->settings->get_sort_options();
				?>
                <select class="woopb-sort-by-button woopb-button">
					<?php
					foreach ( $sort_by_events as $k => $sort_by_event ) { ?>
                        <option <?php selected( $current, $k ) ?>
                                value="<?php echo remove_query_arg( array('name_filter','ppaged'),add_query_arg( array( 'sort_by' => $k ) )) ?>">
							<?php echo $sort_by_event ?>
                        </option>
					<?php } ?>
                </select>
            </div>
        </div>

	<?php }

	public function require_notice() {
		if ( isset( $_GET['notice'] ) && $_GET['notice'] == 1 ) {
			global $post;
			$post_id     = is_woopb_shortcode() ? VI_WPRODUCTBUILDER_FrontEnd_Shortcode::$woopb_id : $post->ID;
			$step        = get_query_var( 'step' );
			$session_key = 'woopb_' . $post_id;
			$added       = wc()->session->get( $session_key );

			if ( ! empty( $added[ $step ] ) ) {
				return;
			}

			?>
            <div class="woopb-product-require-notice">
                <p><?php esc_html_e( 'Please select product for this step', 'woocommerce-product-builder' ); ?></p>
            </div>
			<?php
		}

	}

	/**
	 * Product Description
	 */
	public function product_description() {
		wpb_get_template( 'single/product-short-description.php' );
	}

	/**
	 * Add Query Var
	 *
	 * @param $wp_query
	 */
	function add_vars( &$wp_query ) {
		$step_id                               = filter_input( INPUT_GET, 'step', FILTER_SANITIZE_NUMBER_INT );
		$wp_query->query_vars['step']          = $step_id ? $step_id : 1;
		$page                                  = filter_input( INPUT_GET, 'ppaged', FILTER_SANITIZE_NUMBER_INT );
		$wp_query->query_vars['ppaged']        = $page ? $page : 1;
		$wp_query->query_vars['max_page']      = $step_id ? $step_id : 1;
		$wp_query->query_vars['rating_filter'] = filter_input( INPUT_GET, 'rating_filter', FILTER_SANITIZE_STRING );
		$wp_query->query_vars['sort_by']       = filter_input( INPUT_GET, 'sort_by', FILTER_SANITIZE_STRING );
		$wp_query->query_vars['name_filter']   = filter_input( INPUT_GET, 'name_filter', FILTER_SANITIZE_STRING );
	}

	/**
	 * Show step
	 */
	public function step_html( $id ) {
		global $post;
//		$post_id = $post->ID;
		$post_id = is_woopb_shortcode() ? $id : $post->ID;
		/*Get current step*/
		$step_titles = $this->get_data( $post_id, 'tab_title', array() );
		$step_id     = get_query_var( 'step' );
		$step_id     = $step_id ? $step_id : 1;
		$total_price = 0;
		?>
        <div class="woopb-steps">
			<?php
			do_action( 'woocommerce_product_builder_before_steps_panel' );
			if ( count( $step_titles ) ) {

			foreach ( $step_titles as $k => $step_title ) {
				$arg = array( 'step' => $k + 1 );
				foreach ( array( 'min_price', 'max_price', 'name_filter', 'rating_filter' ) as $item ) {
					if ( $value = get_query_var( $item ) ) {
						$arg[ $item ] = $value;
					}
				}

				$products_added = $this->settings->get_products_added( $post_id, $k + 1 );
				$current        = $k == ( $step_id - 1 ) ? 1 : 0;
				?>
                <div class="woopb-step-heading <?php echo $current ? 'woopb-step-heading-active' : ''; ?>">
                    <a href="<?php echo add_query_arg( $arg, get_the_permalink() ) ?>" class="woopb-step-link">
						<?php echo esc_html( $step_title ) ?>
                    </a>
                </div>
				<?php if ( count( $products_added ) ) {
					if ( in_array( 'no_product_found', $products_added ) ) {
						continue;
					}
					?>
                    <div class="woopb-step woopb-step-<?php echo esc_attr( $k ) ?> <?php echo $current ? 'woopb-step-active' : ''; ?>">
                        <div class="woopb-step-products-added">
							<?php foreach ( $products_added as $p_id => $value ) {
								if ( ! $p_id ) {
									continue;
								}
								$product = wc_get_product( $p_id );
								if ( ! $product ) {
									continue;
								}
								?>
                                <div class="woopb-step-products-added-wrapper">
									<?php
									$sub_price     = wc_get_price_to_display( $product );
									$sub_price     = $sub_price ? $sub_price * $value['quantity'] : 0;
									$total_price   += $sub_price;
									$pd_img        = has_post_thumbnail( $p_id ) ? get_the_post_thumbnail( $p_id, 'shop_catalog' ) : ( $product->is_type( 'variation' ) ? get_the_post_thumbnail( $product->get_parent_id(), 'shop_catalog' ) : wc_placeholder_img( 'woocommerce_gallery_thumbnail' ) );
									$product_title = VI_WPRODUCTBUILDER_Data::get_product_name( $product, $value );
									if ( $pd_img ) {
										?>
                                        <div class="woopb-step-product-thumb">
											<?php echo $pd_img; ?>
                                        </div>
									<?php } ?>

                                    <div class="woopb-step-product-added">
										<?php printf( '<span class="woopb-step-product-added-title"><a href="%s" target="_blank">%s</a></span>',
											$product->get_permalink(), $product_title ); ?>
                                        <div class="woopb-step-product-added-price">
											<?php echo $value['quantity'] . ' x ' . apply_filters( 'woopb_added_price', wc_price( $sub_price ) ) ?>
                                        </div>
										<?php $arg_remove = array(
											'stepp'      => ( $k + 1 ),
											'product_id' => $p_id,
											'post_id'    => $post_id
										); ?>

                                    </div>

                                    <a class="woopb-step-product-added-remove"
                                       href="<?php echo wp_nonce_url( add_query_arg( $arg_remove ), '_woopb_remove_product_step', '_nonce' ) ?>">
                                        <span class="woopb-close"></span>
                                    </a>
                                </div>
							<?php } ?>
                        </div>
                    </div>
				<?php } ?>
			<?php } ?>
            <div class="woopb-step woopb-step-total">
				<?php echo esc_html__( 'Total:', 'woocommerce-product-builder' ) . ' ' . wc_price( $total_price ); ?>
            </div>

            <div class="woopb-added-footer">
				<?php if ( $this->get_data( $post_id, 'remove_all_button' ) ) { ?>
                    <div class="woopb-step-remove ">
                        <a class="woopb-step-product-added-remove-all woopb-button" href="<?php
						echo wp_nonce_url( add_query_arg( array(
							'remove'  => 'all',
							'step'    => 1,
							'post_id' => $post_id
						) ), '_woopb_remove_all_product_step', '_nonce' ) ?>">
                            <span class="woopb-bin-icon"></span>
                            <span>
							<?php echo esc_html__( 'Remove all', 'woocommerce-product-builder' ) ?>
                            </span>
                        </a>
                    </div>
				<?php }

				if ( $this->settings->has_step_added( $post_id ) || $this->get_data( $post_id, 'add_to_cart_always_show' ) ) {
					?>
                    <form method="POST" action="<?php echo wc_get_cart_url() ?>" class="woopb-form-cart-now">
						<?php
						do_action( 'litespeed_nonce', 'woopb_nonce' );
                        wp_nonce_field( 'woopb_nonce', '_nonce' )
                        ?>
                        <input type="hidden" name="woopb_id" value="<?php echo esc_attr( $post_id ) ?>"/>
						<?php
						$btn = " <button class='woopb-button woopb-button-primary woopb-add-to-cart-button'><span class='woopb-cart-icon'></span>" . __( 'Add to cart', 'woocommerce-product-builder' ) . "</button>";
						printf( apply_filters( 'woopb_add_to_cart_button', $btn ) );
						?>
                    </form>
				<?php }
				} ?>
            </div>
        </div>

		<?php
		$this->total_price = $total_price;
	}

	public function mobile() {
		?>
        <div class="woopb-close-modal">
            <span class="woopb-close"> </span>
        </div>

        <div class="woopb-overlay"></div>

        <div class="woopb-mobile-control-bar">

            <div class="woopb-mobile-steps-control">

                <div class="woopb-steps-detail-btn">

                </div>

                <div class="woopb-mobile-view-total">
					<?php echo esc_html__( 'Total:', 'woocommerce-product-builder' ) . ' ' . wc_price( $this->total_price ); ?>
                </div>

            </div>

            <div class="woopb-mobile-filters-control">
            </div>
        </div>
		<?php
	}

	/*
	 * Pagination
	 */
	public function single_product_content_pagination( $products, $max_page ) {

		$step         = get_query_var( 'step' );
		$current_page = get_query_var( 'ppaged' );
		$paged        = $current_page ? $current_page : 1;
		if ( $max_page > 1 ) {
			?>
            <div class="woopb-products-pagination">
				<?php
				if ( $paged > 2 ) {
					$i   = 1;
					$arg = array(
						'ppaged' => $i,
						'step'   => $step
					);
					?>
                    <div class="woopb-page">
                        <a href="<?php echo add_query_arg( $arg ) ?>"><?php echo esc_html( $i ) ?></a>
                    </div>
					<?php
					if ( $paged - 2 > 1 ) {
						?>
                        <div class="woopb-page">
                            <span>...</span>
                        </div>
						<?php
					}
				}
				if ( $paged - 1 > 0 ) {
					$i   = $paged - 1;
					$arg = array(
						'ppaged' => $i,
						'step'   => $step
					);
					?>
                    <div class="woopb-page">
                        <a href="<?php echo add_query_arg( $arg ) ?>"><?php echo esc_html( $i ) ?></a>
                    </div>
					<?php
				}
				?>
                <div class="woopb-page woopb-active">
                    <span><?php echo esc_html( $paged ) ?></span>
                </div>
				<?php
				if ( $paged + 1 < $max_page ) {
					$i   = $paged + 1;
					$arg = array(
						'ppaged' => $i,
						'step'   => $step
					);
					?>
                    <div class="woopb-page">
                        <a href="<?php echo add_query_arg( $arg ) ?>"><?php echo esc_html( $i ) ?></a>
                    </div>
					<?php
				}
				if ( $paged < $max_page ) {
					if ( $paged < ( $max_page - 2 ) ) {
						?>
                        <div class="woopb-page">
                            <span>...</span>
                        </div>
						<?php
					}
					$i   = $max_page;
					$arg = array(
						'ppaged' => $i,
						'step'   => $step
					);
					?>
                    <div class="woopb-page">
                        <a href="<?php echo add_query_arg( $arg ) ?>"><?php echo esc_html( $i ) ?></a>
                    </div>
					<?php
				}
				?>
            </div>
            <div class="woopb-search-pagination"></div>
			<?php
		}
	}

	/**
	 * Product variable
	 */
	public function variable_add_to_cart( $post_id ) {
		global $product;

		// Enqueue variation scripts.
		wp_enqueue_script( 'wc-add-to-cart-variation' );

		// Get Available variations?
		$get_variations = count( $product->get_children() ) <= apply_filters( 'woocommerce_ajax_variation_threshold', 30, $product );

		// Load the template.
		wpb_get_template( 'single/add-to-cart-variable.php', array(
			'available_variations' => $get_variations ? $product->get_available_variations() : false,
			'attributes'           => $product->get_variation_attributes(),
			'selected_attributes'  => $product->get_default_attributes(),
			'post_id'              => $post_id
		) );
	}

	public function simple_add_to_cart( $post_id ) {
		$button_icon = $this->settings->get_button_icon();
		wpb_get_template( 'single/add-to-cart-simple.php', array(
			'post_id'     => $post_id,
			'button_icon' => $button_icon
		) );
	}

	public function add_to_cart( $post_id ) {
		$enable_multi_select = $this->get_data( $post_id, 'enable_multi_select' );
		$step_id             = get_query_var( 'step' );
		$step_id             = $step_id ? $step_id : 1;

		if ( wp_doing_ajax() ) {
			$step_id = $_POST['step'] ? sanitize_text_field( $_POST['step'] ) : 1;
		}

		$product_added = $this->settings->get_products_added( $post_id, $step_id );

		if ( $enable_multi_select || count( $product_added ) < 1 ) {
			$allow_add_to_cart = 1;
		} else {
			$allow_add_to_cart = 0;
		}
		if ( $allow_add_to_cart ) {
			global $product;
			do_action( 'woocommerce_product_builder_' . $product->get_type() . '_add_to_cart', $post_id );
		}

		/*Create close div of right content*/
		echo '</div>';
	}

	public function modal_add_to_cart_button( $post_id, $step_id ) {
		$enable_multi_select = $this->get_data( $post_id, 'enable_multi_select' );

		$product_added = $this->settings->get_products_added( $post_id, $step_id );

//		if ( $enable_multi_select || count( $product_added ) < 1 ) {
		global $product;
		do_action( 'woocommerce_product_builder_' . $product->get_type() . '_add_to_cart', $post_id );
//		}
	}

	/**
	 * Init Script
	 */
	public function init_scripts() {

		if ( $this->settings->get_button_icon() ) {
			wp_register_style( 'woocommerce-product-builder-icon', VI_WPRODUCTBUILDER_CSS . 'woocommerce-product-builder-icon.css', array(), VI_WPRODUCTBUILDER_VERSION );
		}

		$style_file = VI_WOOPBTEMPLATE_STYLE == 'modern' ? 'woocommerce-product-builder-2' : ( VI_WOOPBTEMPLATE_STYLE == 'ajax' ? 'woocommerce-product-builder-ajax' : 'woocommerce-product-builder' );
		$suffix     = WP_DEBUG ? '.css' : '.min.css';

		wp_register_style( 'woocommerce-product-builder', VI_WPRODUCTBUILDER_CSS . $style_file . $suffix, array(), VI_WPRODUCTBUILDER_VERSION );

		if ( is_rtl() ) {
			wp_register_style( 'woocommerce-product-builder-rtl', VI_WPRODUCTBUILDER_CSS . 'woocommerce-product-builder-rtl.css', array(), VI_WPRODUCTBUILDER_VERSION );
		}

		/*Add script*/
		$suffix = WP_DEBUG ? '.js' : '.min.js';

		wp_register_script( 'woocommerce-product-builder', VI_WPRODUCTBUILDER_JS . 'woocommerce-product-builder' . $suffix, array(
			'jquery',
			'photoswipe',
			'wc-single-product'
		), VI_WPRODUCTBUILDER_VERSION );

		global $post;
		if ( $post && $post->post_type == 'woo_product_builder' ) {
			add_filter( 'woocommerce_single_product_zoom_enabled', '__return_true' );

			$this->settings->enqueue_scripts();
			add_action( 'wp_footer', array( $this, 'load_photoswipe_template' ) );
		}
	}

	public function load_photoswipe_template() {
		$this->settings->load_photoswipe_template();
	}

	/**
	 * Product Title
	 */
	public function product_price( $pb_id, $min_id ) {
		ob_start();
		wpb_get_template( 'single/product-price.php' );
		$price = apply_filters( 'woopb_price_each_step', ob_get_clean(), $pb_id, $min_id );

		echo $price;
	}

	public function modal_product_price() {
		wpb_get_template( 'single/product-price.php' );
	}


	/**
	 * Product Title
	 */
	public function product_thumb() {
		add_filter('woocommerce_locate_template',function ($template, $template_name){
			if ($template_name === 'single-product/product-image.php' && ( strpos($template,'themes/woodmart') || strpos($template,'themes/flatsome'))){
				$template =  WC()->plugin_path() . '/templates/'.$template_name;
			}
			return $template;
		},PHP_INT_MAX,2);
		wpb_get_template( 'single/product-image.php' );
	}

	/**
	 * Product Title
	 */
	public function product_title( $post_id ) {
		/*Create div before title*/
		$remove_link = $this->settings->get_data( $post_id, 'remove_product_link' );
		echo '<div class="woopb-product-right">';
		wpb_get_template( 'single/product-title.php', [ 'remove_link' => $remove_link ] );
	}

	/**
	 * Get Product Ids
	 */
	public function product_builder_content_single_page( $id ) {
		global $post, $wp_query;
		$post_id = is_woopb_shortcode() ? $id : $post->ID;

		if ( VI_WOOPBTEMPLATE_STYLE === 'ajax' ) {

			wpb_get_template( 'content-product-builder-single-ajax.php',
				[ 'id' => $post_id, 'settings' => $this->settings, 'self' => $this ] );

			wpb_get_template( 'content-product-builder-preview-popup.php' );

			return;
		}
		$data = $this->settings->get_product_filters( $post_id );

		$max_page    = 1;
		$products    = array();
		$load_single = true;

		if ( isset( $wp_query->query_vars['woopb_preview'] ) ) {
			$products = $this->settings->get_products_added( $post_id );
			$settings = $this->settings;
			if ( is_array( $products ) && count( $products ) ) {
				wpb_get_template( 'content-product-builder-preview.php', array(
					'id'       => $id,
					'products' => $products,
					'settings' => $settings
				) );

				$load_single = false;
			}
		}

		if ( $load_single ) {
			if ( $data ) {
				$products = $data->posts;
				$max_page = $data->max_num_pages;
			}

			$step_id = get_query_var( 'step' );
			if ( empty( $products ) ) {
				$this->settings->set_products_added( $post_id, [ 'no_product_found' ], $step_id );
			} else {
				$this->settings->remove_no_product_found( $post_id, $step_id );
			}

			$step_error_descs = $this->settings->get_data( $post_id, 'step_error_desc' );
			$step_error       = $step_error_descs[ $step_id - 1 ] ?? '';

			$single_template = VI_WOOPBTEMPLATE_STYLE === 'modern' ? 'content-product-builder-single-2.php' : 'content-product-builder-single.php';
			wpb_get_template( $single_template, array(
				'id'         => $id,
				'products'   => $products,
				'max_page'   => $max_page,
				'step_error' => $step_error
			) );
		}
	}

	/**
	 * Get Post Meta
	 *
	 * @param $post_id
	 * @param $field
	 * @param string $default
	 *
	 * @return string
	 */
	private function get_data( $post_id, $field, $default = '' ) {
		if ( isset( $this->data[ $post_id ] ) && $this->data[ $post_id ] ) {
			$params = $this->data[ $post_id ];
		} else {
			$this->data[ $post_id ] = get_post_meta( $post_id, 'woopb-param', true );
			$params                 = $this->data[ $post_id ];
		}

		if ( isset( $params[ $field ] ) && $field ) {
			return $params[ $field ];
		} else {
			return $default;
		}
	}

	public function description() {
		global $post;
		echo sprintf( "<div class='woopb-description'>%s</div>", esc_html( $this->settings->get_data( $post->ID, 'description' ) ) );
	}


//	AJAX Template style
	public function modal_product_title( $post_id ) {
		$remove_link = $this->settings->get_data( $post_id, 'remove_product_link' );
		wpb_get_template( 'single/product-title.php', [ 'remove_link' => $remove_link ] );
	}

	public function ajax_product_builder_total() {
		?>
        <div class="woopb-added-products-total">
            <span class="woopb-added-products-label">
                <?php esc_html_e( 'Total:', 'woocommerce-product-builder' ); ?>
            </span>
            <span class="woopb-added-products-value">
				<?php echo wc_price( 0 ); ?>
            </span>
        </div>
		<?php
	}

	public function ajax_product_builder_add_to_cart() {
		?>
        <div class="woopb-add-products-to-cart woopb-button-primary woopb-button">
			<?php esc_html_e( 'Add to cart', 'woocommerce-product-builder' ); ?>
        </div>
		<?php
	}

	public function ajax_product_builder_remove_all( $post_id ) {
		if ( $this->get_data( $post_id, 'remove_all_button' ) ) {
			?>
            <div class="woopb-remove-all woopb-button">
				<?php esc_html_e( 'Remove all', 'woocommerce-product-builder' ); ?>
            </div>
			<?php
		}

	}

	public function ajax_product_builder_buttons_group() {
		$use_icon            = $this->settings->get_param( 'button_icon' );
		$use_only_icon_class = $use_icon ? 'woopb-icons-flex' : '';
		?>
        <div class="woopb-tool-buttons <?php echo esc_attr( $use_only_icon_class ) ?>">
			<?php
			if ( $this->settings->get_param( 'get_short_share_link' ) || $this->settings->get_param( 'share_link' ) ) {
				?>
                <div class="woopb-get-share-link woopb-button">
					<?php
					if ( $use_icon ) {
						?>
                        <span class="woopb-icon woopb-icon-share2"> </span>
						<?php
					} else {
						esc_html_e( 'Get share link', 'woocommerce-product-builder' );
					}
					?>
                </div>
				<?php
			}

			if ( $this->settings->enable_email() ) {
				?>
                <div class="woopb-send-to-friend woopb-button">
					<?php
					if ( $use_icon ) {
						?>
                        <span class="woopb-icon woopb-icon-envelope"> </span>
						<?php
					} else {
						esc_html_e( 'Send to your friends', 'woocommerce-product-builder' );
					}
					?>
                </div>
				<?php
			}

			if ( $this->settings->get_param( 'print_button' ) ) {
				?>
                <div class="woopb-button woopb-print-button">
					<?php
					if ( $use_icon ) {
						?>
                        <span class="woopb-icon woopb-icon-print"> </span>
						<?php
					} else {
						esc_html_e( 'Print', 'woocommerce-product-builder' );
					}
					?>
                </div>
				<?php
			}

			if ( $this->settings->get_param( 'download_pdf' ) ) {
				?>
                <div class="woopb-button woopb-download-pdf-button">
					<?php
					if ( $use_icon ) {
						?>
                        <span class="woopb-icon woopb-icon-file-pdf"> </span>
						<?php
					} else {
						esc_html_e( 'Download PDF', 'woocommerce-product-builder' );
					}
					?>
                </div>
				<?php
			}
			?>
        </div>
		<?php
	}


	public function ajax_product_builder_search_form( $post_id ) {
		if ( $this->get_data( $post_id, 'search_product_form' ) ) {
			?>
            <input type="text" class="woopb-search"
                   placeholder="<?php esc_html_e( 'Search...', 'woocommerce-product-builder' ); ?>">
			<?php
		}
	}

	public function ajax_product_builder_sort_form( $post_id ) {
		$sort_default = $this->get_data( $post_id, 'sort_default' );
		?>
        <select class="woopb-sort">
			<?php
			$sort_options = $this->settings->get_sort_options();
			foreach ( $sort_options as $value => $option ) {
				printf( '<option value="%s" %s>%s</option>', esc_attr( $value ), selected( $sort_default, $value, false ), esc_html( $option ) );
			}
			?>
        </select>
		<?php
	}

}