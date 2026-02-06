<?php
/**
 * Enqueue script and styles for child theme
 */
function woodmart_child_enqueue_styles() {
	wp_enqueue_style( 'child-style', get_stylesheet_directory_uri() . '/style.css', array( 'woodmart-style' ), woodmart_get_theme_info( 'Version' ) );
}
add_action( 'wp_enqueue_scripts', 'woodmart_child_enqueue_styles', 10010 );
/*
add_action( 'wpo_wcpdf_after_item_meta', 'wpo_wcpdf_show_product_attributes', 10, 3 );
function wpo_wcpdf_show_product_attributes ( $template_type, $item, $order ) {
    if(empty($item['product'])) return;
    $document = wcpdf_get_document( $template_type, $order );
    printf('<div class="product-attribute">Procie: %s</div>', $document->get_product_attribute('processor', $item['product']));
	printf('<div class="product-attribute">Cooler: %s</div>', $document->get_product_attribute('cooler', $item['product']));
	printf('<div class="product-attribute">Mobo: %s</div>', $document->get_product_attribute('motherboard', $item['product']));
	printf('<div class="product-attribute">RAM: %s</div>', $document->get_product_attribute('ram', $item['product']));
	printf('<div class="product-attribute">VGA Card: %s</div>', $document->get_product_attribute('vga-card	', $item['product']));
	printf('<div class="product-attribute">Storage: %s</div>', $document->get_product_attribute('penyimpanan', $item['product']));
	printf('<div class="product-attribute">PSU: %s</div>', $document->get_product_attribute('power-supply', $item['product']));
	printf('<div class="product-attribute">Casing: %s</div>', $document->get_product_attribute('casing', $item['product']));
	printf('<div class="product-attribute">Fan: %s</div>', $document->get_product_attribute('fan', $item['product']));
	printf('<div class="product-attribute">Monitor: %s</div>', $document->get_product_attribute('monitor', $item['product']));
	printf('<div class="product-attribute">Accesories: %s</div>', $document->get_product_attribute('accesories', $item['product']));
}
*/
add_filter( 'product_type_selector', 'remove_product_types' );

function remove_product_types( $types ){
    unset( $types['grouped'] );
    unset( $types['external'] );
    unset( $types['variable'] );

    return $types;
}

// function disable_woodmart_dark_mode_scripts() {
//     // Example: Remove Woodmart dark mode script if present
//     wp_dequeue_script('woodmart-theme-dark');
//     wp_dequeue_style('woodmart-theme-dark');
// }
// add_action('wp_enqueue_scripts', 'disable_woodmart_dark_mode_scripts', 100);