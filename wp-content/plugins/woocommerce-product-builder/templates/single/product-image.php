<?php
defined( 'ABSPATH' ) || exit;
global $product;
?>
<div class="woopb-product-left">
    <div class="woopb-product-image">
		<?php
		remove_all_actions( 'woocommerce_product_thumbnails' );
		woocommerce_show_product_images();
		?>
    </div>
</div>
