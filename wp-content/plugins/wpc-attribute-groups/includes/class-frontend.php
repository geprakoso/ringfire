<?php
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Wpcag_Frontend' ) ) {
	class Wpcag_Frontend {
		protected static $instance = null;

		public static function instance() {
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		public function __construct() {
			add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
			add_filter( 'wc_get_template', [ $this, 'product_attributes_template' ], 99, 2 );
		}

		function enqueue_scripts() {
			wp_enqueue_style( 'wpcag-frontend', WPCAG_URI . 'assets/css/frontend.css', [], WPCAG_VERSION );
		}

		public static function attributes_data( $product, $attributes, $group = null ) {
			$attributes_data = [];

			foreach ( $attributes as $attribute ) {
				$values = [];

				if ( $group && $group->term_id ) {
					$attribute_key = 'attribute_wpcag_' . $group->term_id . '_' . sanitize_title_with_dashes( $attribute->get_name() );
				} else {
					$attribute_key = 'attribute_' . sanitize_title_with_dashes( $attribute->get_name() );
				}

				if ( $attribute->is_taxonomy() ) {
					$attribute_taxonomy = $attribute->get_taxonomy_object();
					$attribute_values   = wc_get_product_terms( $product->get_id(), $attribute->get_name(), [ 'fields' => 'all' ] );

					foreach ( $attribute_values as $attribute_value ) {
						$value_name = esc_html( $attribute_value->name );

						if ( $attribute_taxonomy->attribute_public ) {
							$values[] = '<a href="' . esc_url( get_term_link( $attribute_value->term_id, $attribute->get_name() ) ) . '" rel="tag">' . $value_name . '</a>';
						} else {
							$values[] = $value_name;
						}
					}
				} else {
					$values = $attribute->get_options();

					foreach ( $values as &$value ) {
						$value = make_clickable( esc_html( $value ) );
					}
				}

				$attributes_data[ $attribute_key ] = [
					'label' => apply_filters( 'wpcag_attribute_label', wc_attribute_label( $attribute->get_name() ), $attribute ),
					'value' => apply_filters( 'wpcag_attribute_value', apply_filters( 'woocommerce_attribute', wpautop( wptexturize( implode( ', ', $values ) ) ), $attribute, $values ), $attribute, $values ),
				];
			}

			return apply_filters( 'wpcag_attributes_data', $attributes_data, $product, $attributes, $group );
		}

		public static function attributes_table( $product_attributes ) {
			ob_start();
			?>
            <table class="woocommerce-product-attributes shop_attributes">
				<?php foreach ( $product_attributes as $product_attribute_key => $product_attribute ) { ?>
                    <tr class="woocommerce-product-attributes-item woocommerce-product-attributes-item--<?php echo esc_attr( $product_attribute_key ); ?>">
                        <th class="woocommerce-product-attributes-item__label"><?php echo wp_kses_post( $product_attribute['label'] ); ?></th>
                        <td class="woocommerce-product-attributes-item__value"><?php echo wp_kses_post( $product_attribute['value'] ); ?></td>
                    </tr>
				<?php } ?>
            </table>
			<?php
			return apply_filters( 'wpcag_attributes_table', ob_get_clean(), $product_attributes );
		}

		function product_attributes_template( $located, $template_name ) {
			if ( $template_name === 'single-product/product-attributes.php' ) {
				return WPCAG_DIR . 'templates/product-attributes.php';
			}

			return $located;
		}
	}

	function Wpcag_Frontend() {
		return Wpcag_Frontend::instance();
	}

	Wpcag_Frontend();
}
