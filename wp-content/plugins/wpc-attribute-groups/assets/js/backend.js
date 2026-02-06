(function ($) {
    'use strict';

    $(function () {
        $('.wpcag_attributes_selector').selectWoo().on('select2:select', function (e) {
            var element = e.params.data.element;
            var $element = $(element);

            $(this).append($element);
            $(this).trigger('change');
        });

        $('.wpcag_attributes_list').sortable({
            cursor: 'move',
            handle: '.wpcag_attribute_name',
            axis: 'y',
            forcePlaceholderSize: true,
            helper: 'clone',
            opacity: 0.65,
            placeholder: 'product-cat-placeholder',
            scrollSensitivity: 40,
            update: function (event, ui) {
                var attrs = '';
                var group = $(event['target']).attr('data-group');

                $(event['target']).find('li').each(function () {
                    attrs += $(this).attr('data-attr') + ',';
                });

                var data = {
                    action: 'wpcag_order_attrs',
                    nonce: wpcag_vars.nonce,
                    group: group,
                    attrs: attrs,
                };

                $.post(ajaxurl, data, function (response) {
                    //console.log(response);
                });
            },
        });

        $('body.taxonomy-wpc-attribute-group .wp-list-table').sortable({
            items: 'tbody tr:not(.inline-edit-row)',
            cursor: 'move',
            handle: '.column-handle',
            axis: 'y',
            forcePlaceholderSize: true,
            helper: 'clone',
            opacity: 0.65,
            placeholder: 'product-cat-placeholder',
            scrollSensitivity: 40,
            update: function (event, ui) {
                var ids = '';

                $(document).find('.wpcag_term_order').each(function () {
                    ids += $(this).val() + ',';
                });

                var data = {
                    action: 'wpcag_order', nonce: wpcag_vars.nonce, ids: ids,
                };

                $.post(ajaxurl, data, function (response) {
                    //console.log(response);
                });
            },
        });

        wpcag_apply();
    });

    $(document).on('change', '.wpcag_apply', function () {
        wpcag_apply();
    });

    $(document).on('click touch', '.wpcag_shortcode_input', function () {
        $(this).select();
    });

    // add group attributes
    $(document).on('click', '.wpcag_group_attributes_add', function () {
        var $wrapper = $(this).closest('#product_attributes');
        var $attributes = $wrapper.find('.product_attributes');
        var size = $('.product_attributes .woocommerce_attribute').length;
        var group_id = $('.wpcag_group_attributes_select').val();
        var attrs_data = $('.product_attributes').find('input, select, textarea');
        var product_type = $('select#product-type').val();
        var data = {
            action: 'wpcag_add_group_attributes',
            i: size,
            group_id: group_id,
            data: attrs_data.serialize(),
            security: woocommerce_admin_meta_boxes.add_attribute_nonce,
        };

        $wrapper.block({
            message: null, overlayCSS: {
                background: '#fff', opacity: 0.6,
            },
        });

        $.post(ajaxurl, data, function (response) {
            $attributes.append(response);

            if ('variable' !== product_type) {
                $attributes.find('.enable_variation').hide();
            }

            $(document.body).trigger('wc-enhanced-select-init');

            $('.product_attributes .woocommerce_attribute').each(function (index, el) {
                $('.attribute_position', el).val(parseInt(
                    $(el).index('.product_attributes .woocommerce_attribute'), 10));

            });

            $attributes.find('.wpcag-taxonomy').removeClass('closed wpcag-taxonomy').addClass('open').find('.woocommerce_attribute_data').show();

            $wrapper.unblock();

            $(document.body).trigger('woocommerce_added_attribute');
        });

        $('.wpcag_group_attributes_select').val('');

        return false;
    });

    function wpcag_apply() {
        $('.wpcag_apply').each(function () {
            var $this = $(this);
            var apply = $this.val();
            var $terms = $this.closest('.form-field').find('.wpcag_terms');

            if (apply === 'all') {
                $terms.closest('.form-field').find('.hide_if_apply_all').hide();
            } else {
                $terms.closest('.form-field').find('.hide_if_apply_all').show();
            }

            $terms.selectWoo({
                ajax: {
                    url: ajaxurl, dataType: 'json', delay: 250, data: function (params) {
                        return {
                            q: params.term, action: 'wpcag_search_term', taxonomy: apply,
                        };
                    }, processResults: function (data) {
                        var options = [];

                        if (data) {
                            $.each(data, function (index, text) {
                                options.push({id: text[0], text: text[1]});
                            });
                        }

                        return {
                            results: options,
                        };
                    }, cache: true,
                }, minimumInputLength: 1,
            });

            if ((typeof $terms.data(apply) === 'string' ||
                $terms.data(apply) instanceof String) && $terms.data(apply) !== '') {
                $terms.val($terms.data(apply).split(',')).change();
            } else {
                $terms.val([]).change();
            }
        });
    }
})(jQuery);
