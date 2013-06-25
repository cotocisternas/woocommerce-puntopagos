<?php
if ($description = $this->get_description())
                echo wpautop(wptexturize(wp_kses_post($description)));

            echo '<h2>' . __('Our Details', 'woocommerce') . '</h2>';

            echo '<ul class="order_details bacs_details">';

            $fields = apply_filters('woocommerce_bacs_fields', array(
                'account_name' => __('Account Name', 'woocommerce'),
                'account_number' => __('Account Number', 'woocommerce'),
                'sort_code' => __('Sort Code', 'woocommerce'),
                'bank_name' => __('Bank Name', 'woocommerce'),
                'iban' => __('IBAN', 'woocommerce'),
                'bic' => __('BIC', 'woocommerce')
            ));

            foreach ($fields as $key => $value) {
                if (!empty($this->$key)) {
                    echo '<li class="' . esc_attr($key) . '">' . esc_attr($value) . ': <strong>' . wptexturize($this->$key) . '</strong></li>';
                }
            }

            echo '</ul>';
?>
