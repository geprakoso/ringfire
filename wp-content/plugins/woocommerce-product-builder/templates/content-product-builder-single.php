<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


do_action( 'woocommerce_product_builder_before_single_top', $id );
do_action( 'woocommerce_product_builder_single_top', $products, $max_page );
?>
    <div class="woocommerce-product-builder-wrapper">
        <div class="woocommerce-product-builder-content">
			<?php
			do_action( 'woocommerce_product_builder_single_product_content_before', $products, $max_page );
			?>
            <div class="woopb-products">

				<?php
				wpb_get_template( 'product-template.php', array( 'id' => $id, 'products' => $products, 'max_page' => $max_page, 'step_error' => $step_error ) );
				?>

            </div>
            <div class="woopb-products-searched"></div>
			<?php do_action( 'woocommerce_product_builder_single_product_content_after', $products, $max_page ); ?>
        </div>
    </div>
<?php do_action( 'woocommerce_product_builder_single_bottom', $products, $max_page );
