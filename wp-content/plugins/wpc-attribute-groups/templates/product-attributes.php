<?php
/**
 * Product attributes
 * @see https://docs.woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 9.3.0
 * @var $product_attributes
 */

defined( 'ABSPATH' ) || exit;

if ( ! $product_attributes ) {
	return;
}

global $product;

$attributes            = array_filter( $product->get_attributes(), 'wc_attributes_array_filter_visible' );
$orphaned_attributes   = $attributes;
$attributes_keys       = array_keys( $attributes );
$attribute_groups      = $single_attributes = [];
$attribute_groups_html = $single_attributes_html = '';

foreach ( $attributes_keys as $index => $attribute_key ) {
	if ( ! str_starts_with( $attribute_key, 'pa_' ) ) {
		unset( $attributes_keys[ $index ] );
	}
}

if ( $groups = Wpcag_Backend::get_groups() ) {
	foreach ( $groups as $group ) {
		$group_apply      = get_term_meta( $group->term_id, 'wpcag_apply', true ) ?: 'all';
		$group_apply_val  = (array) get_term_meta( $group->term_id, 'wpcag_apply_val', true ) ?: [];
		$group_attributes = (array) get_term_meta( $group->term_id, 'wpcag_attributes', true ) ?: [];

		// check apply
		if ( ( $group_apply !== 'all' ) && ! empty( $group_apply ) && ! empty( $group_apply_val ) ) {
			if ( ! has_term( $group_apply_val, $group_apply, $product->get_id() ) ) {
				continue;
			}
		}

		foreach ( $attributes_keys as $attributes_key ) {
			if ( in_array( $attributes_key, $group_attributes ) ) {
				$attribute_groups[] = $group;
				break;
			}
		}
	}
}

// find orphaned attributes
if ( ! empty( $attribute_groups ) ) {
	foreach ( $attribute_groups as $attribute_group ) {
		$group_attributes = get_term_meta( $attribute_group->term_id, 'wpcag_attributes', true ) ?: [];

		foreach ( $group_attributes as $group_attribute ) {
			if ( array_key_exists( $group_attribute, $attributes ) ) {
				unset( $orphaned_attributes[ $group_attribute ] );
			}
		}
	}
}

// attribute groups
if ( ! empty( $attribute_groups ) ) {
	foreach ( $attribute_groups as $attribute_group ) {
		$group_attributes         = get_term_meta( $attribute_group->term_id, 'wpcag_attributes', true ) ?: [];
		$group_exclude            = get_term_meta( $attribute_group->term_id, 'wpcag_exclude', true ) ?: 'no';
		$product_group_attributes = [];
		$group_attributes_arr     = [];

		foreach ( $group_attributes as $group_attribute ) {
			if ( array_key_exists( $group_attribute, $attributes ) ) {
				$product_group_attributes[ $group_attribute ] = $attributes[ $group_attribute ];
			}
		}

		if ( $group_exclude === 'yes' ) {
			continue;
		}

		$group_attributes_arr = Wpcag_Frontend::attributes_data( $product, $product_group_attributes, $attribute_group );

		if ( ! empty( $group_attributes_arr ) ) {
			$attribute_groups_html .= '<div class="' . esc_attr( 'wpcag_group wpcag_group_' . $attribute_group->term_id . ' wpcag_group_' . sanitize_title_with_dashes( $attribute_group->name ) ) . '">';
			$attribute_groups_html .= '<div class="wpcag_group_info">';
			$attribute_groups_html .= '<div class="wpcag_group_heading">' . esc_html( $attribute_group->name ) . '</div>';
			$attribute_groups_html .= '<div class="wpcag_group_description">' . esc_html( $attribute_group->description ) . '</div>';
			$attribute_groups_html .= '</div><!-- /wpcag_group_info -->';
			$attribute_groups_html .= '<div class="wpcag_group_attributes">';
			$attribute_groups_html .= Wpcag_Frontend::attributes_table( $group_attributes_arr );
			$attribute_groups_html .= '</div><!-- /wpcag_group_attributes -->';
			$attribute_groups_html .= '</div><!-- /wpcag_group -->';
		}
	}
}

// single attributes
$single_attributes = Wpcag_Frontend::attributes_data( $product, $orphaned_attributes );

// dimension and weight
$display_dimensions           = apply_filters( 'wc_product_enable_dimensions_display', $product->has_weight() || $product->has_dimensions() );
$single_attributes_weight     = Wpcag_Backend::get_setting( 'single_attributes_weight', 'yes' ) === 'yes';
$single_attributes_dimensions = Wpcag_Backend::get_setting( 'single_attributes_dimensions', 'yes' ) === 'yes';

if ( $display_dimensions && $single_attributes_weight && $product->has_weight() ) {
	$single_attributes['weight'] = [
		'label' => esc_html__( 'Weight', 'wpc-attribute-groups' ),
		'value' => wc_format_weight( $product->get_weight() ),
	];
}

if ( $display_dimensions && $single_attributes_dimensions && $product->has_dimensions() ) {
	$single_attributes['dimensions'] = [
		'label' => esc_html__( 'Dimensions', 'wpc-attribute-groups' ),
		'value' => wc_format_dimensions( $product->get_dimensions( false ) ),
	];
}

if ( ! empty( $single_attributes ) ) {
	$single_attributes_title       = Wpcag_Backend::get_setting( 'single_attributes_title', esc_html__( 'Other', 'wpc-attribute-groups' ) );
	$single_attributes_description = Wpcag_Backend::get_setting( 'single_attributes_description', '' );

	$single_attributes_html .= '<div class="wpcag_group wpcag_other">';

	if ( ! empty( $single_attributes_title ) || ! empty( $single_attributes_description ) ) {
		$single_attributes_html .= '<div class="wpcag_group_info">';

		if ( ! empty( $single_attributes_title ) ) {
			$single_attributes_html .= '<div class="wpcag_group_heading">' . esc_html( Wpcag_Backend::get_setting( 'single_attributes_title', esc_html__( 'Other', 'wpc-attribute-groups' ) ) ) . '</div>';
		}

		if ( ! empty( $single_attributes_description ) ) {
			$single_attributes_html .= '<div class="wpcag_group_description">' . esc_html( Wpcag_Backend::get_setting( 'single_attributes_description', '' ) ) . '</div>';
		}

		$single_attributes_html .= '</div><!-- /wpcag_group_info -->';
	}

	$single_attributes_html .= '<div class="wpcag_group_attributes">';
	$single_attributes_html .= Wpcag_Frontend::attributes_table( $single_attributes );
	$single_attributes_html .= '</div><!-- /wpcag_group_attributes -->';
	$single_attributes_html .= '</div><!-- /wpcag_group -->';
}

// layout
$layout = Wpcag_Backend::get_setting( 'layout', '01' );

echo '<div class="' . esc_attr( 'wpcag_groups wpcag_groups_layout_' . $layout ) . '">';

if ( Wpcag_Backend::get_setting( 'single_attributes_position', 'below' ) === 'below' ) {
	echo $attribute_groups_html . $single_attributes_html;
} else {
	echo $single_attributes_html . $attribute_groups_html;
}

echo '</div>';
