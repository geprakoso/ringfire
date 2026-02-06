<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

class VI_WPRODUCTBUILDER_Admin_Settings {
	function __construct() {
		add_action( 'admin_menu', array( $this, 'setting_menu' ), 22 );
		add_action( 'admin_init', array( $this, 'save_data' ) );
	}

	public static function set_option_field( $field, $multi = false ) {
		if ( $field ) {
			if ( $multi ) {
				return 'woopb_option-param[' . $field . '][]';
			} else {
				return 'woopb_option-param[' . $field . ']';
			}

		} else {
			return '';
		}
	}

	public static function get_option_field( $field, $default = '' ) {
		$params = get_option( 'woopb_option-param', array() );
		if ( isset( $params[ $field ] ) && $field ) {
			return $params[ $field ];
		} else {
			return $default;
		}
	}

	public function save_data() {
		/**
		 * Check update
		 */
		$setting_url = admin_url( 'edit.php?post_type=woo_product_builder&page=woocommerce-product-builder-setting' );
		$key         = self::get_option_field( 'key' );
		new VillaTheme_Plugin_Check_Update (
			VI_WPRODUCTBUILDER_VERSION,                    // current version
			'https://villatheme.com/wp-json/downloads/v3',  // update path
			'woocommerce-product-builder/woocommerce-product-builder.php',                  // plugin file slug
			'woocommerce-product-builder', '8188', $key, $setting_url
		);
		new VillaTheme_Plugin_Updater( 'woocommerce-product-builder/woocommerce-product-builder.php', 'woocommerce-product-builder', $setting_url );

		/*Save setting options*/
		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		if ( ! isset( $_POST['_opt_woo_product_builder_nonce'] ) || ! wp_verify_nonce( $_POST['_opt_woo_product_builder_nonce'], 'opt_woo_product_builder_action_nonce' ) ) {
			return false;
		}

		$data = wc_clean( $_POST['woopb_option-param'] ?? [] );

		$data['custom_css'] = sanitize_textarea_field( wp_unslash( $data['custom_css'] ) );

		if ( isset( $_POST['message_body'] ) ) {
			$data['message_body'] = wp_kses_post( wp_unslash( $_POST['message_body'] ) );
		}

		if ( isset( $_POST['layout_header'] ) ) {
			$data['layout_header'] = wp_kses_post( wp_unslash( $_POST['layout_header'] ) );
		}

		if ( isset( $_POST['layout_footer'] ) ) {
			$data['layout_footer'] = wp_kses_post( wp_unslash( $_POST['layout_footer'] ) );
		}

		if ( isset( $_POST['woopb_option-param']['check_key'] ) ) {
			unset( $_POST['woopb_option-param']['check_key'] );
			delete_site_transient( 'update_plugins' );
			delete_transient( 'villatheme_item_8188' );
			delete_option( 'woocommerce-product-builder_messages' );
		}
		update_option( 'woopb_option-param', $data );
	}

	public function page_callback() { ?>
        <div class="wrap woocommerce-product-builder">
            <h2><?php esc_html_e( 'WooCommerce Product Builder Settings', 'woocommerce-product-builder' ) ?></h2>

            <form class="vi-ui form" method="post" action="">
				<?php
				wp_nonce_field( 'opt_woo_product_builder_action_nonce', '_opt_woo_product_builder_nonce' );
				settings_fields( 'woocommerce-product-builder' );
				do_settings_sections( 'woocommerce-product-builder' );
				?>
                <div class="vi-ui top attached tabular menu">
                    <a class="item active"
                       data-tab="design"><?php esc_html_e( 'Design', 'woocommerce-product-builder' ) ?></a>
                    <a class="item " data-tab="email"><?php esc_html_e( 'Email', 'woocommerce-product-builder' ) ?></a>
                    <a class="item " data-tab="print"><?php esc_html_e( 'Print & PDF', 'woocommerce-product-builder' ) ?></a>
                    <a class="item " data-tab="update"><?php esc_html_e( 'Update', 'woocommerce-product-builder' ) ?></a>
                </div>

                <!--				Design-->
                <div class="vi-ui bottom attached tab segment active" data-tab="design">
                    <!--                    <h3>--><?php //esc_html_e( 'Template', 'woocommerce-product-builder' ); ?><!--</h3>-->
                    <table class="form-table vi-ui form">
                        <tr valign="top">
                            <th scope="row">
                                <label for="<?php echo self::set_option_field( 'button_text_color' ) ?>">
									<?php esc_html_e( 'Template', 'woocommerce-product-builder' ) ?>
                                </label>
                            </th>
                            <td>
                                <select name="<?php echo self::set_option_field( 'template' ); ?>" class="vi-ui dropdown">
									<?php
									$selected = self::get_option_field( 'template', 'ajax' );
									$options  = [
										'classic' => esc_html__( 'Classic', 'woocommerce-product-builder' ),
										'modern'  => esc_html__( 'Modern', 'woocommerce-product-builder' ),
										'ajax'    => esc_html__( 'AJAX', 'woocommerce-product-builder' ),
									];
									foreach ( $options as $value => $text ) {
										printf( "<option value='%s' %s>%s</option>", esc_attr( $value ), selected( $selected, $value ), esc_html( $text ) );
									}
									?>
                                </select>

                            </td>
                        </tr>


                    </table>

                    <h3><?php esc_html_e( 'Button', 'woocommerce-product-builder' ); ?></h3>
                    <p class="description"><?php esc_html_e( 'Set color and background color for WooCommerce Product Builder buttons.', 'woocommerce-product-builder' ); ?></p>
                    <table class="form-table vi-ui form">
                        <tr valign="top">
                            <th scope="row">
                                <label for="<?php echo self::set_option_field( 'button_text_color' ) ?>"><?php esc_html_e( 'Text color', 'woocommerce-product-builder' ) ?></label>
                            </th>
                            <td>
                                <input class="color-picker" type="text"
                                       name="<?php echo self::set_option_field( 'button_text_color' ); ?>"
                                       value="<?php echo self::get_option_field( 'button_text_color', '#fff' ); ?>"
                                       style="background-color: <?php echo self::get_option_field( 'button_text_color', '#fff' ); ?>">
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">
                                <label for="<?php echo self::set_option_field( 'button_bg_color' ) ?>"><?php esc_html_e( 'Background color', 'woocommerce-product-builder' ) ?></label>
                            </th>
                            <td>
                                <input class="color-picker" type="text"
                                       name="<?php echo self::set_option_field( 'button_bg_color' ); ?>"
                                       value="<?php echo self::get_option_field( 'button_bg_color', '#04747a' ); ?>"
                                       style="background-color: <?php echo self::get_option_field( 'button_bg_color', '#04747a' ); ?>">
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">
                                <label for="<?php echo self::set_option_field( 'button_main_text_color' ) ?>"><?php esc_html_e( 'Primary text color', 'woocommerce-product-builder' ); ?></label>
                            </th>
                            <td>
                                <input class="color-picker" type="text"
                                       name="<?php echo self::set_option_field( 'button_main_text_color' ); ?>"
                                       value="<?php echo self::get_option_field( 'button_main_text_color', '#fff' ); ?>"
                                       style="background-color: <?php echo self::get_option_field( 'button_main_text_color', '#fff' ); ?>">
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">
                                <label for="<?php echo self::set_option_field( 'button_main_bg_color' ) ?>"><?php esc_html_e( 'Primary background color', 'woocommerce-product-builder' ); ?></label>
                            </th>
                            <td>
                                <input class="color-picker" type="text"
                                       name="<?php echo self::set_option_field( 'button_main_bg_color' ); ?>"
                                       value="<?php echo self::get_option_field( 'button_main_bg_color', '#4b9989' ); ?>"
                                       style="background-color: <?php echo self::get_option_field( 'button_main_bg_color', '#4b9989' ); ?>">
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">
                                <label for="<?php echo self::set_option_field( 'button_icon' ) ?>"><?php esc_html_e( 'Button Icon', 'woocommerce-product-builder' ); ?></label>
                            </th>
                            <td>
                                <select class="vi-ui dropdown"
                                        name="<?php echo self::set_option_field( 'button_icon' ) ?>">
                                    <option value="0" <?php selected( self::get_option_field( 'button_icon' ), 0 ) ?>><?php esc_html_e( 'Text', 'woocommerce-product-builder' ); ?></option>
                                    <option value="1" <?php selected( self::get_option_field( 'button_icon' ), 1 ) ?>><?php esc_html_e( 'Icon', 'woocommerce-product-builder' ); ?></option>
                                </select>
                                <p class="description"><?php esc_html_e( 'If you use AJAX template and icon option is selected: send email, get share link, print, pdf button will use icon instead', 'woocommerce-product-builder' ); ?></p>

                            </td>
                        </tr>

                    </table>

                    <h3><?php esc_html_e( 'Mobile', 'woocommerce-product-builder' ); ?></h3>
                    <table class="form-table vi-ui form">
                        <tr valign="top">
                            <th scope="row">
                                <label for="<?php echo self::set_option_field( 'mobile_bar_position' ) ?>">
									<?php esc_html_e( 'Distance from bottom', 'woocommerce-product-builder' ) ?>
                                </label>
                            </th>
                            <td>
                                <input class="" type="number" min="0" step="1"
                                       name="<?php echo self::set_option_field( 'mobile_bar_position' ); ?>"
                                       value="<?php echo self::get_option_field( 'mobile_bar_position', 0 ); ?>">
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">
                                <label for="<?php echo self::set_option_field( 'mobile_bar_text_color' ) ?>">
									<?php esc_html_e( 'Control bar text color', 'woocommerce-product-builder' ) ?>
                                </label>
                            </th>
                            <td>
                                <input class="color-picker" type="text"
                                       name="<?php echo self::set_option_field( 'mobile_bar_text_color' ); ?>"
                                       value="<?php echo self::get_option_field( 'mobile_bar_text_color', '#000' ); ?>"
                                       style="background-color: <?php echo self::get_option_field( 'mobile_bar_text_color', '#000' ); ?>">
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">
                                <label for="<?php echo self::set_option_field( 'mobile_bar_bg_color' ) ?>">
									<?php esc_html_e( 'Control bar background color', 'woocommerce-product-builder' ) ?>
                                </label>
                            </th>
                            <td>
                                <input class="color-picker" type="text"
                                       name="<?php echo self::set_option_field( 'mobile_bar_bg_color' ); ?>"
                                       value="<?php echo self::get_option_field( 'mobile_bar_bg_color', '#fff' ); ?>"
                                       style="background-color: <?php echo self::get_option_field( 'mobile_bar_bg_color', '#fff' ); ?>">
                            </td>
                        </tr>
                    </table>

                    <h3><?php esc_html_e( 'Advanced', 'woocommerce-product-builder' ); ?></h3>
                    <table class="form-table vi-ui form">
                        <tr valign="top">
                            <th scope="row">
                                <label for="<?php echo self::set_option_field( 'share_link' ) ?>">
									<?php esc_html_e( 'Display share link', 'woocommerce-product-builder' ); ?>
                                </label>
                            </th>
                            <td>
                                <div class="vi-ui checkbox toggle">
                                    <input type="checkbox" id="<?php echo self::set_option_field( 'share_link' ) ?>"
                                           name="<?php echo self::set_option_field( 'share_link' ) ?>"
                                           value="1" <?php checked( self::get_option_field( 'share_link' ), 1 ) ?>>
                                </div>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">
                                <label for="<?php echo self::set_option_field( 'get_short_share_link' ) ?>">
									<?php esc_html_e( 'Display get short share link for customer', 'woocommerce-product-builder' ); ?>
                                </label>
                                <p class="description"><?php esc_html_e( 'Default: Display for admin', 'woocommerce-product-builder' ); ?></p>
                            </th>
                            <td>
                                <div class="vi-ui checkbox toggle">
                                    <input type="checkbox" id="<?php echo self::set_option_field( 'get_short_share_link' ) ?>"
                                           name="<?php echo self::set_option_field( 'get_short_share_link' ) ?>"
                                           value="1" <?php checked( self::get_option_field( 'get_short_share_link' ), 1 ) ?>>
                                </div>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">
                                <label for="<?php echo self::set_option_field( 'time_to_remove_short_share_link' ) ?>">
									<?php esc_html_e( 'Remove short share link records after x day(s)', 'woocommerce-product-builder' ); ?>
                                </label>
                            </th>
                            <td>
                                <input type="text" name="<?php echo self::set_option_field( 'time_to_remove_short_share_link' ); ?>"
                                       value="<?php echo self::get_option_field( 'time_to_remove_short_share_link', 30 ); ?>">
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">
                                <label for="<?php echo self::set_option_field( 'remove_session' ) ?>">
									<?php esc_html_e( 'Clear session', 'woocommerce-product-builder' ); ?>
                                </label>
                            </th>
                            <td>
                                <div class="vi-ui checkbox toggle">
                                    <input type="checkbox" id="<?php echo self::set_option_field( 'remove_session' ) ?>"
                                           name="<?php echo self::set_option_field( 'remove_session' ) ?>"
                                           value="1" <?php checked( self::get_option_field( 'remove_session' ), 1 ) ?>>
                                </div>
                                <p class="description"><?php esc_html_e( 'Clear session after add to cart', 'woocommerce-product-builder' ); ?></p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">
                                <label for="<?php echo self::set_option_field( 'clear_filter' ) ?>">
									<?php esc_html_e( 'Clear filter', 'woocommerce-product-builder' ); ?>
                                </label>
                            </th>
                            <td>
                                <div class="vi-ui checkbox toggle">
                                    <input type="checkbox" id="<?php echo self::set_option_field( 'clear_filter' ) ?>"
                                           name="<?php echo self::set_option_field( 'clear_filter' ) ?>"
                                           value="1" <?php checked( self::get_option_field( 'clear_filter' ), 1 ) ?>>
                                </div>
                                <p class="description"><?php esc_html_e( 'Clear filter after select', 'woocommerce-product-builder' ); ?></p>
                            </td>
                        </tr>

                        <tr valign="top">
                            <th scope="row">
                                <label for="<?php echo self::set_option_field( 'custom_css' ) ?>"><?php esc_html_e( 'Custom CSS', 'woocommerce-product-builder' ); ?></label>
                            </th>
                            <td>
                                <textarea
                                        name="<?php echo self::set_option_field( 'custom_css' ) ?>"><?php echo self::get_option_field( 'custom_css' ) ?></textarea>
                            </td>
                        </tr>
                    </table>

                </div>

                <!--				Email Design-->
                <div class="vi-ui bottom attached tab segment " data-tab="email">
                    <table class="form-table">
                        <tbody>
                        <tr valign="top">
                            <th scope="row">
                                <label for="<?php echo self::set_option_field( 'enable_email' ) ?>"><?php esc_html_e( 'Enable', 'woocommerce-product-builder' ) ?></label>
                            </th>
                            <td>
                                <div class="vi-ui checkbox toggle">
                                    <input type="checkbox" id="<?php echo self::set_option_field( 'enable_email' ) ?>"
                                           name="<?php echo self::set_option_field( 'enable_email' ) ?>"
                                           value="1" <?php checked( self::get_option_field( 'enable_email' ), 1 ) ?>>
                                </div>
                                <p class="description"><?php esc_html_e( 'Allow customers to send an email to friends on the preview page.', 'woocommerce-product-builder' ) ?></p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">
                                <label for="<?php echo self::set_option_field( 'email_header' ) ?>"><?php esc_html_e( 'Header', 'woocommerce-product-builder' ) ?></label>
                            </th>
                            <td>
                                <div class="field">
                                    <input type="text" id="<?php echo self::set_option_field( 'email_header' ) ?>"
                                           name="<?php echo self::set_option_field( 'email_header' ) ?>"
                                           placeholder="<?php esc_html_e( 'WordPress', 'woocommerce-product-builder' ) ?>"
                                           value="<?php echo self::get_option_field( 'email_header', '' ) ?>">
                                </div>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">
                                <label for="<?php echo self::set_option_field( 'email_from' ) ?>"><?php esc_html_e( 'From', 'woocommerce-product-builder' ) ?></label>
                            </th>
                            <td>
                                <div class="field">
									<?php $admin_email = get_option( 'admin_email' ); ?>
                                    <input type="email" id="<?php echo self::set_option_field( 'email_from' ) ?>"
                                           name="<?php echo self::set_option_field( 'email_from' ) ?>"
                                           placeholder="<?php esc_html_e( '<admin@yoursite.com>', 'woocommerce-product-builder' ) ?>"
                                           value="<?php echo self::get_option_field( 'email_from', $admin_email ) ?>"
                                           required>
                                </div>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">
                                <label for="<?php echo self::set_option_field( 'email_subject' ) ?>"><?php esc_html_e( 'Subject', 'woocommerce-product-builder' ) ?></label>
                            </th>
                            <td>
                                <div class="field">
                                    <input type="text" id="<?php echo self::set_option_field( 'email_subject' ) ?>"
                                           name="<?php echo self::set_option_field( 'email_subject' ) ?>"
                                           placeholder="<?php esc_html_e( '[Subject email]', 'woocommerce-product-builder' ) ?>"
                                           value="<?php echo self::get_option_field( 'email_subject' ) ?>">
                                </div>
                                <p class="description"><?php esc_html_e( 'The first text display on subject field of email.', 'woocommerce-product-builder' ) ?></p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">
                                <label for="<?php echo self::set_option_field( 'message_body' ) ?>"><?php esc_html_e( 'Message Body', 'woocommerce-product-builder' ) ?></label>
                            </th>
                            <td>
                                <div class="field">
									<?php
									$default_content = "From: {email} \nSubject: {subject} \nMessage body: \n{message_content} \n{callback_link} \n\n-- \nThis e-mail was sent from a contact form on anonymous website (http://yoursite.com)";
									$content         = self::get_option_field( 'message_body', $default_content );
									$editor_id       = 'message_body';

									wp_editor( $content, $editor_id );
									?>
                                </div>
                                <p class="description"><?php esc_html_e( 'The content of message.', 'woocommerce-product-builder' ) ?></p>
                                <ul class="description" style="list-style: none">
                                    <li>
                                        <span>{email}</span>
                                        - <?php esc_html_e( 'Your email.', 'woocommerce-product-builder' ) ?>
                                    </li>
                                    <li>
                                        <span>{subject}</span>
                                        - <?php esc_html_e( 'The subject of email.', 'woocommerce-product-builder' ) ?>
                                    </li>
                                    <li>
                                        <span>{message_content}</span>
                                        - <?php esc_html_e( 'The content of message body.', 'woocommerce-product-builder' ) ?>
                                    </li>
                                    <li>
                                        <span>{callback_link}</span>
                                        - <?php esc_html_e( 'Auto add product list when click link.', 'woocommerce-product-builder' ) ?>
                                    </li>
                                </ul>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">
                                <label for="<?php echo self::set_option_field( 'message_success' ) ?>"><?php esc_html_e( 'Message thank you', 'woocommerce-product-builder' ) ?></label>
                            </th>
                            <td>
                                <div class="field">
                                    <input type="text" id="<?php echo self::set_option_field( 'message_success' ) ?>"
                                           name="<?php echo self::set_option_field( 'message_success' ) ?>"
                                           value="<?php echo self::get_option_field( 'message_success', 'Thank you! Your email has sent to your friend!' ) ?>"/>
                                </div>
                                <p class="description"><?php esc_html_e( 'The messages display after sent email.', 'woocommerce-product-builder' ) ?></p>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </div>

                <!--				Print & PDF-->
                <div class="vi-ui bottom attached tab segment " data-tab="print">
                    <table class="form-table">
                        <tbody>
                        <tr valign="top">
                            <th scope="row">
                                <label for="<?php echo self::set_option_field( 'print_button' ) ?>">
									<?php esc_html_e( 'Print button', 'woocommerce-product-builder' ); ?></label>
                            </th>
                            <td>
                                <div class="vi-ui checkbox toggle">
                                    <input type="checkbox" id="<?php echo self::set_option_field( 'print_button' ) ?>"
                                           name="<?php echo self::set_option_field( 'print_button' ) ?>"
                                           value="1" <?php checked( self::get_option_field( 'print_button' ), 1 ) ?>>
                                </div>
                            </td>
                        </tr>

                        <tr valign="top">
                            <th scope="row">
                                <label for="<?php echo self::set_option_field( 'download_pdf' ) ?>">
									<?php esc_html_e( 'Download PDF button', 'woocommerce-product-builder' ); ?></label>
                            </th>
                            <td>
                                <div class="vi-ui checkbox toggle">
                                    <input type="checkbox" id="<?php echo self::set_option_field( 'download_pdf' ) ?>"
                                           name="<?php echo self::set_option_field( 'download_pdf' ) ?>"
                                           value="1" <?php checked( self::get_option_field( 'download_pdf' ), 1 ) ?>>
                                </div>
                            </td>
                        </tr>

                        <tr valign="top">
                            <th scope="row">
                                <label for="<?php echo self::set_option_field( 'show_short_desc' ) ?>">
									<?php esc_html_e( 'Show short description', 'woocommerce-product-builder' ); ?></label>
                            </th>
                            <td>
                                <div class="vi-ui checkbox toggle">
                                    <input type="checkbox" id="<?php echo self::set_option_field( 'show_short_desc' ) ?>"
                                           name="<?php echo self::set_option_field( 'show_short_desc' ) ?>"
                                           value="1" <?php checked( self::get_option_field( 'show_short_desc' ), 1 ) ?>>
                                </div>
                            </td>
                        </tr>

                        <tr valign="top">
                            <th scope="row">
                                <label for="<?php echo self::set_option_field( 'layout_header' ) ?>">
									<?php esc_html_e( 'Header of layout', 'woocommerce-product-builder' ) ?>
                                </label>
                            </th>
                            <td>
                                <div class="field">
									<?php
									$default_content = "<table style=\"border: none; border-collapse: collapse; line-height: 1.5;\" width=\"100%\">
                                                        <tbody>
                                                        <tr>
                                                        <td style=\"padding: 5px; border: none; vertical-align: top;\" width=\"120\">Logo</td>
                                                        <td style=\"vertical-align: top; border: none; padding: 10px;\"><strong style=\"font-size: 20px;\">{site_title}
                                                        </strong><strong>Email: </strong>{admin_email}
                                                        <strong>Address:</strong> {store_address}
                                                        <strong>Website:</strong> {site_url}</td>
                                                        </tr>
                                                        </tbody>
                                                        </table>
                                                        <h1 style=\"text-align: center;\"><strong>Product builder</strong></h1>
                                                        &nbsp;";

									$content   = self::get_option_field( 'layout_header', $default_content );
									$editor_id = 'layout_header';

									wp_editor( $content, $editor_id );
									?>
                                </div>
                                <p class="description"><?php esc_html_e( 'Shortcode:', 'woocommerce-product-builder' ) ?></p>
                                <ul class="description" style="list-style: none">
                                    <li>
                                        <span>{admin_email}</span>
                                        - <?php esc_html_e( 'Your admin email.', 'woocommerce-product-builder' ) ?>
                                    </li>
                                    <li>
                                        <span>{store_address}</span>
                                        - <?php esc_html_e( 'Your store address', 'woocommerce-product-builder' ) ?>
                                    </li>
                                    <li>
                                        <span>{site_url}</span>
                                        - <?php esc_html_e( 'Your site url', 'woocommerce-product-builder' ) ?>
                                    </li>
                                </ul>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">
                                <label for="<?php echo self::set_option_field( 'layout_footer' ) ?>"><?php esc_html_e( 'Footer of layout', 'woocommerce-product-builder' ) ?></label>
                            </th>
                            <td>
                                <div class="field">
									<?php
									$default_content = "";
									$content         = self::get_option_field( 'layout_footer', $default_content );
									$editor_id       = 'layout_footer';

									wp_editor( $content, $editor_id );
									?>
                                </div>
                                <p class="description"><?php esc_html_e( 'Shortcode:', 'woocommerce-product-builder' ) ?></p>
                                <ul class="description" style="list-style: none">
                                    <li>
                                        <span>{admin_email}</span>
                                        - <?php esc_html_e( 'Your admin email.', 'woocommerce-product-builder' ) ?>
                                    </li>
                                    <li>
                                        <span>{store_address}</span>
                                        - <?php esc_html_e( 'Your store address', 'woocommerce-product-builder' ) ?>
                                    </li>
                                    <li>
                                        <span>{site_url}</span>
                                        - <?php esc_html_e( 'Your site url', 'woocommerce-product-builder' ) ?>
                                    </li>
                                </ul>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </div>

                <!--Update-->
                <div class="vi-ui bottom attached tab segment " data-tab="update">
                    <table class="form-table">
                        <tbody>
                        <tr valign="top">
                            <th scope="row">
                                <label for="auto-update-key"><?php esc_html_e( 'Auto Update Key', 'woocommerce-product-builder' ) ?></label>
                            </th>
                            <td>
                                <div class="fields">
                                    <div class="ten wide field">
                                        <input type="text" name="<?php echo self::set_option_field( 'key' ) ?>"
                                               id="auto-update-key"
                                               class="villatheme-autoupdate-key-field"
                                               value="<?php echo self::get_option_field( 'key' ) ?>">
                                    </div>
                                    <div class="six wide field">
                                        <span class="vi-ui button green villatheme-get-key-button"
                                              data-href="https://api.envato.com/authorization?response_type=code&client_id=villatheme-download-keys-6wzzaeue&redirect_uri=https://villatheme.com/update-key"
                                              data-id="19934326"><?php echo esc_html__( 'Get Key', 'woocommerce-product-builder' ) ?></span>
                                    </div>
                                </div>
								<?php do_action( 'woocommerce-product-builder_key' ) ?>
                                <p class="description"><?php echo __( 'Please fill your key what you get from <a target="_blank" href="https://villatheme.com/my-download">Villatheme</a>. You can automatically update WooCommerce Product Builder plugin. See guide <a target="_blank" href="https://villatheme.com/knowledge-base/how-to-use-auto-update-feature/">here</a>', 'woocommerce-product-builder' ) ?></p>

                            </td>
                        </tr>
                        </tbody>
                    </table>
                </div>
                <p>
                    <button class="vi-ui button primary woopb-button-save">
						<?php esc_html_e( 'Save', 'woocommerce-product-builder' ); ?>
                    </button>
                    <button class="vi-ui button woopb-button-save"
                            name="<?php echo self::set_option_field( 'check_key' ) ?>">
						<?php esc_html_e( 'Save & Check Key', 'woocommerce-product-builder' ); ?>
                    </button>
                </p>

            </form>
			<?php do_action( 'villatheme_support_woocommerce-product-builder' ) ?>
        </div>
	<?php }

	function setting_menu() {
		add_submenu_page(
			'edit.php?post_type=woo_product_builder',
			esc_html__( 'WooCommerce Product Builder Setting', 'woocommerce-product-builder' ),
			esc_html__( 'Settings', 'woocommerce-product-builder' ),
			'manage_options',
			'woocommerce-product-builder-setting',
			array( $this, 'page_callback' )
		);
	}
}