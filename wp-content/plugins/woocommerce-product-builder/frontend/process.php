<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

class VI_WPRODUCTBUILDER_FrontEnd_Process {
	protected $data;
	protected $settings;

	/**
	 * Stores chosen attributes
	 * @var array
	 */

	public function __construct() {
		$this->settings = new VI_WPRODUCTBUILDER_Data();

		add_action( 'init', array( $this, 'product_builder_rewrite' ), 10, 0 );
		add_filter( 'query_vars', array( $this, 'wbs_query_var' ) );
		add_action( 'wp_footer', array( $this, 'send_email_friend' ) );

		/*Add to temp cart*/
		add_action( 'template_redirect', array( $this, 'add_to_cart' ) );
		add_action( 'template_redirect', array( $this, 'remove_product' ) );
		add_action( 'template_redirect', array( $this, 'remove_all_product' ) );
		add_action( 'template_redirect', array( $this, 'share_link_callback' ) );
	}

	/**
	 * Process add to cart from step and review
	 */
	public function add_to_cart() {
		if ( ! isset( $_POST['_nonce'] ) || ! isset( $_POST['woopb_id'] ) || ! $_POST['woopb_id'] || isset( $_POST['woopb_save_edit_short_link'] ) ) {
			return;
		}

		$post_id = $_POST['woopb_id'];
		if ( wp_verify_nonce( $_POST['_nonce'], '_woopb_add_to_cart' ) ) {
			$step_id      = filter_input( INPUT_GET, 'step', FILTER_SANITIZE_NUMBER_INT );
			$quantity     = filter_input( INPUT_POST, 'quantity', FILTER_SANITIZE_NUMBER_INT );
			$product_id   = filter_input( INPUT_POST, 'woopb-add-to-cart', FILTER_SANITIZE_NUMBER_INT );
			$variation_id = filter_input( INPUT_POST, 'variation_id', FILTER_SANITIZE_NUMBER_INT );
			$step_id      = $step_id ? $step_id : 1;
			/*Check quantity is only 1*/
			if ( ! $this->get_data( $post_id, 'enable_quantity' ) ) {
				$quantity = 1;
			}
			/*Process add to Session*/
			if ( $product_id ) {
				$product = wc_get_product($variation_id?:$product_id);
				if ($product->get_max_purchase_quantity() > 0 && $quantity > $product->get_max_purchase_quantity()){
					$quantity = $product->get_max_purchase_quantity();
				}elseif ($quantity < $product->get_min_purchase_quantity()){
					$quantity = $product->get_min_purchase_quantity();
				}
				/*Check allow add to cart multi products*/
				$enable_multi_select = $this->get_data( $post_id, 'enable_multi_select' );
				if ( $enable_multi_select ) {
					$products = $this->settings->get_products_added( $post_id, $step_id );
				} else {
					$products = array();
				}
				$data = $_POST;
				if ( ! empty( $data ) && is_array( $data ) ) {
					foreach ( $data as $key => $value ) {
						if ( in_array( $key, [ '_nonce'] )  ) {
							unset( $data[ $key ] );
						}
					}
				}
				$data['quantity'] = $quantity;
//				if ( $variation_id ) {
//					$attributes = array();
//					if ( ! empty( $_POST ) && is_array( $_POST ) ) {
//						foreach ( $_POST as $key => $value ) {
//							if ( strpos( $key, 'attribute_' ) !== false ) {
//								$attributes[ $key ] = $value;
//							}
//						}
//					}
//					$products[ $variation_id ] = array( 'quantity' => $quantity, 'attributes' => $attributes );
//				} else {
//					$products[ $product_id ] = array( 'quantity' => $quantity );
//				}
				$products[ $variation_id ?:$product_id ] = $data;
				$this->settings->set_products_added( $post_id, $products, $step_id );

				if ( ! $enable_multi_select ) {
					$tabs       = $this->get_data( $post_id, 'tab_title' );
					$count_tabs = count( $tabs );

					if ( is_woopb_shortcode() ) {
						global $post;
						$page = get_the_permalink( $post->ID );
					} else {
						$page = get_the_permalink( $post_id );
					}

					if ( $count_tabs > $step_id ) {
						$url = add_query_arg( apply_filters( 'woopb_redirect_url', array( 'step' => $step_id + 1 ) ), $page );
					} else {
						$url = add_query_arg( array( 'woopb_preview' => 1 ), $page );
//						$url = add_query_arg( array(  'step' => $step_id  ), $page );
					}

					$clear_filter = $this->settings->get_param( 'clear_filter' );
					if ( $clear_filter ) {
						$remove = array();
						if ( ! empty( $_GET ) ) {
							foreach ( $_GET as $key => $value ) {
								if ( strpos( $key, 'filter_' ) !== false ) {
									$remove[] = $key;
								}
							}
						}
						$url = remove_query_arg( array_merge( array(
							'min_price',
							'max_price',
							'name_filter',
							'rating_filter'
						), $remove ), $url );
					}

					header( "Location: $url" ); /* Redirect browser */

					/* Make sure that code below does not get executed when we redirect. */
					exit;
				}
			}
		} elseif ( wp_verify_nonce( $_POST['_nonce'], 'woopb_nonce' ) ) { /*Process add to WooCommerce cart*/
			$steps = $this->settings->get_products_added( $post_id );
			$this->check_full_added_for_add_to_cart( $post_id );

			if ( count( $steps ) ) {
				$global_request= $_REQUEST;
				$global_post= $_POST;
				foreach ( $steps as $step ) {
					foreach ( $step as  $product_id=> $detail ) {
						if ( ! $product_id ) {
							continue;
						}
						if (empty($detail['add-to-cart'])){
							$detail['add-to-cart'] = $product_id;
						}
						$_REQUEST = $_POST = $detail;
						WC_Form_Handler::add_to_cart_action();
//						$variation = ! empty( $detail['attributes'] ) ? $detail['attributes'] : '';
//						if ( $variation ) {
//							WC()->cart->add_to_cart( $product_id, $detail['quantity'], $product_id, $variation );
//						} else {
//							WC()->cart->add_to_cart( $product_id, $detail['quantity'] );
//						}
					}
				}
				$_REQUEST = $global_request;
				$_POST = $global_post;
			}
			if ( $this->settings->get_remove_session() ) {
				wc()->session->__unset( 'woopb_' . $post_id );
			}
		}
	}

	public function check_full_added_for_add_to_cart( $post_id ) {
		$param = get_post_meta( $post_id, 'woopb-param', true );
		if ( isset( $param['require_product'] ) && $param['require_product'] ) {
			$session_id  = 'woopb_' . $post_id;
			$miss_step   = array();
			$count       = $sum_step = $target_step = $stop = 0;
			$added_array = WC()->session->get( $session_id );
			$sum_step    = is_array( $param['list_content'] ) ? count( $param['list_content'] ) : 0;

			if ( is_array( $added_array ) ) {
				ksort( $added_array );
			}

			if ( ! empty( $added_array ) ) {
				for ( $i = 1; $i <= $sum_step; $i ++ ) {
					if ( ! empty( $added_array[ $i ] ) ) {
						$count ++;
					} else {
						$miss_step[] = $i;
					}
				}
				$target_step = current( $miss_step );
			} else {
				$target_step = 1;
			}

			if ( $target_step ) {
				$url = add_query_arg( array( 'step' => $target_step, 'notice' => 1 ), get_the_permalink( $post_id ) );
				wp_safe_redirect( $url );
				exit;
			}

			return false;
		}

		return true;
	}

	/**
	 * Get Post Meta
	 *
	 * @param $field
	 *
	 * @return bool
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

	/**
	 * Method remove product ajax
	 */
	public function remove_product() {
		$step_id    = filter_input( INPUT_GET, 'stepp', FILTER_SANITIZE_NUMBER_INT );
		$product_id = filter_input( INPUT_GET, 'product_id', FILTER_SANITIZE_NUMBER_INT );
		$_nonce     = filter_input( INPUT_GET, '_nonce', FILTER_SANITIZE_STRING );
		$post_id    = filter_input( INPUT_GET, 'post_id', FILTER_SANITIZE_STRING );
		if ( wp_verify_nonce( $_nonce, '_woopb_remove_product_step' ) && $step_id && $product_id && $post_id && ( get_post_type() == 'woo_product_builder' || is_woopb_shortcode() ) ) { // && get_post_type() == 'woo_product_builder'
			$count      = $this->settings->remove_product( $post_id, $product_id, $step_id );
			$remove_arg = $count ? array( 'product_id', '_nonce' ) : array( 'product_id', '_nonce', 'woopb_preview' );
			wp_safe_redirect( @remove_query_arg( $remove_arg ) );
//			wp_safe_redirect( site_url( remove_query_arg( $remove_arg ) ) );
			exit;
		}
	}

	public function remove_all_product() {
		$_nonce  = isset( $_GET['_nonce'] ) ? sanitize_text_field( $_GET['_nonce'] ) : '';
		$remove  = isset( $_GET['remove'] ) ? sanitize_text_field( $_GET['remove'] ) : '';
		$post_id = isset( $_GET['post_id'] ) ? sanitize_text_field( $_GET['post_id'] ) : '';
		if ( wp_verify_nonce( $_nonce, '_woopb_remove_all_product_step' ) && $remove == 'all' && $post_id && ( get_post_type() == 'woo_product_builder' || is_woopb_shortcode() ) ) {
			$session_id = 'woopb_' . $post_id;
			WC()->session->set( $session_id, array() );
			wp_safe_redirect( @remove_query_arg( array( 'remove', '_nonce' ) ) );
//			wp_safe_redirect( site_url( remove_query_arg( array( 'remove', '_nonce' ) ) ) );
			exit;
		}
	}

	/**
	 * Method rewrite url
	 */
	public function product_builder_rewrite() {
		/*Check customer has not session*/
		if ( class_exists( 'WC_Session_Handler' ) ) {
			$session = new WC_Session_Handler();
			if ( ! $session->has_session() ) {
				$session->init();
				$session->set_customer_session_cookie( true );
			}
		}

		if ( trim( get_option( 'wpb2205_cpt_base' ) ) != '' ) {
			$main_struct_link = get_option( 'wpb2205_cpt_base' );
			add_rewrite_rule( "$main_struct_link/([^/]*)/step/([0-9]*)/?$", 'index.php?post_type=woo_product_builder&name=$matches[1]&step_builder=$matches[2]', 'top' );
		} else {
			add_rewrite_rule( "woo_product_builder/([^/]*)/step/([0-9]*)/?$", 'index.php?post_type=woo_product_builder&name=$matches[1]&step_builder=$matches[2]', 'top' );
		}

	}

	/**
	 * Method add query_var
	 */
	function wbs_query_var( $query_vars ) {
		$query_vars[] = 'step';
		$query_vars[] = 'ppaged';
		$query_vars[] = 'max_page';
		$query_vars[] = 'min_price';
		$query_vars[] = 'max_price';
		$query_vars[] = 'sort_by';
		$query_vars[] = 'rating_filter';
		$query_vars[] = 'woopb_preview';
		$query_vars[] = 'name_filter';

		return $query_vars;
	}

	/**
	 * Method send chosen product for friend
	 */
	public function send_email_friend() {

		if ( ! $this->settings->enable_email() ) {
			return;
		}
		if ( ! isset( $_POST['_wpnonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( $_POST['_wpnonce'], 'woocommerce_product_builder_send_email' ) ) {
			return;
		}
		$emailto = filter_input( INPUT_POST, 'woopb_emailto_field', FILTER_SANITIZE_EMAIL );

		if ( ! $emailto ) {
			return;
		}

		$subject = filter_input( INPUT_POST, 'woopb_subject_field', FILTER_SANITIZE_STRING );
		$content = filter_input( INPUT_POST, 'woopb_content_field', FILTER_SANITIZE_STRING );
		global $post;
		if ( empty( $post ) ) {
			return;
		}

		$post_id           = is_woopb_shortcode() ? VI_WPRODUCTBUILDER_FrontEnd_Shortcode::$woopb_id : $post->ID;
		$setting           = new VI_WPRODUCTBUILDER_Admin_Settings();
		$pre_subject       = $setting->get_option_field( 'email_subject' );
		$container_content = $setting->get_option_field( 'message_body' );
		$email_from        = $setting->get_option_field( 'email_from' );
		$email_header      = $setting->get_option_field( 'email_header' );

		if ( trim( $pre_subject ) ) {
			$subject = $pre_subject . ' - ' . $subject;
		}
		$products = $this->settings->get_products_added( $post_id );

		if ( is_array( $products ) && count( array_filter( $products ) ) ) {

			$content = '<p>' . strip_tags( $content ) . '</p>';
			$content .= '<table border="0" cellpadding="10" cellspacing="0" width="100%"><thead style="background: #eee;"><tr><th width="20%" align="left"></th><th width="30%" align="center">' . esc_html__( 'Product', 'woocommerce-product-builder' ) . '</th><th width="20%" align="left">' . esc_html__( 'Price', 'woocommerce-product-builder' ) . '</th><th width="10%" align="left">' . esc_html__( 'Quantity', 'woocommerce-product-builder' ) . '</th><th width="20%" align="left">' . esc_html__( 'Total', 'woocommerce-product-builder' ) . '</th></tr></thead><tbody>';
			$index   = 1;
			$total   = 0;
			foreach ( $products as $step_id => $items ) {
				foreach ( $items as $key_id => $data ) {
					$quantity = $data['quantity'] ?? '';
					if ( ! $quantity ) {
						continue;
					}
					$get_prd = wc_get_product( $key_id );
					if ( ! $get_prd ) {
						continue;
					}
					$prd_name = '<a href="' . esc_url( $get_prd->get_permalink() ) . '">' . VI_WPRODUCTBUILDER_Data::get_product_name( $get_prd, $data ) . '</a>';
					if ( ! empty( get_the_post_thumbnail( $key_id ) ) ) {
						$prd_thumbnail = get_the_post_thumbnail( $key_id, 'thumbnail' );
					} else {
						$prd_thumbnail = '<img src="' . wc_placeholder_img_src() . '" width="150px" height="150px" />';
					}
					$prd_price     = $get_prd->get_price();
					$format_pridce = wc_price( $prd_price );
					$content       .= '<tr><td align="left">' . $prd_thumbnail . '</td><td align="center">' . $prd_name . '</td><td align="left">' . $format_pridce . '</td><td align="left">' . $quantity . '</td><td align="left">' . wc_price( $quantity * $prd_price ) . '</td></tr>';
					$index ++;
					$total = $total + $quantity * $prd_price;
				}
			}
			$content      .= '</tbody>';
			$total_format = wc_price( $total );
			$content      .= '<tfoot style="background: #eee;"><tr><td></td><td></td><td></td><td>' . esc_html( 'Total:' ) . '</td><td>' . $total_format . '</td></tr></tfoot></table>';

			$arr_rpl       = array( $email_from, $subject, $content, $this->settings->get_share_link() );
			$content_admin = nl2br( $container_content );

			if ( $container_content ) {
				$keya    = array(
					'{email}',
					'{subject}',
					'{message_content}',
					'{callback_link}',
				);
				$content = str_replace( $keya, $arr_rpl, $content_admin );
			}

			$headers = array( 'Content-Type: text/html; charset=UTF-8', "From: {$email_header} <{$email_from}>" );
			wp_mail( $emailto, $subject, $content, $headers );
		}
	}

	public function share_link_callback() {
		if ( is_admin() ) {
			return;
		}

		if ( isset( $_GET['woopb_share'] ) ) {
			$link = sanitize_text_field( $_GET['woopb_share'] );
			$link = strtr( $link, '-_,', '+/=' );
			$data = json_decode( base64_decode( $link ), true );

			$pid = empty( $data['page_id'] ) ? ( $data['woopb_id'] ?? '' ) : $data['page_id'];

			if ( isset( $data['page_id'], $data['woopb_id'] ) ) {
				wc()->session->set( 'woopb_' . $data['woopb_id'], $data['data'] );
				wc()->session->set_customer_session_cookie( true );
				wp_safe_redirect( site_url( "?page_id={$pid}&woopb_preview=1" ) );
				exit;
			}
		}

		if ( isset( $_GET['woopbUrl'] ) ) {
			$link = sanitize_text_field( $_GET['woopbUrl'] );

			$posts = get_posts( array( 'post_type' => 'woo_pb_share_link', 's' => $link ) );
			if ( ! count( $posts ) ) {
				return;
			}

			$post    = current( $posts );
			$post_id = $post->ID;

			if ( empty( $_GET['woopb_edit_short_link'] ) ) {
				$clicked = get_post_meta( $post_id, '_clicked', true ) ?: 0;
				update_post_meta( $post_id, '_clicked', $clicked + 1 );
			}

			$data = get_post_meta( $post_id, '_product_array', true );

			$pid = empty( $data['woopb_id'] ) ? ( $data['page_id'] ?? '' ) : $data['woopb_id'];

			if ( isset( $data['data'] ) && $pid ) {
				wc()->session->set_customer_session_cookie( true );
				wc()->session->set( 'woopb_' . $pid, $data['data'] );

				if ( ! empty( $_GET['woopb_edit_short_link'] ) ) {
					$id = sanitize_text_field( $_GET['woopb_edit_short_link'] );
					wc()->session->set( 'woopb_edit_short_link', $id );
				}

				wp_safe_redirect( site_url( "?page_id={$pid}&woopb_preview=1" ) );
				exit;
			}
		}
	}
}


