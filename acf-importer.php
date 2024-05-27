<?php
/*
Plugin Name:  ACF Import Exporter
Plugin URI:   https://github.com/mrbinarie
Description:  ACF Repeater field's import exporter
Version:      1.0
Author:       mrbinarie
Author URI:   https://github.com/mrbinarie
License:      GPL2
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

function generate_acf_repeater_blocks() {
    // Check if we are in the admin area and editing a post or a page
    if (is_admin() && isset($_GET['post'])) {
        $post_id = intval($_GET['post']); // Get the post ID from the query string
        
        // Fetch ACF fields for the specified post ID
        $fields = get_fields($post_id);

        if ($fields) {
            foreach ($fields as $field_key => $field_value) {
                $field = get_field_object($field_key, $post_id);
                // Check if the field type is 'repeater'
                if ($field['type'] == 'repeater') {
                    // var_dump($field);
                    // Add an action to render the block content in the admin footer
                    add_action('admin_footer', function() use ($field) {
                        render_repeater_field_block($field); // Pass $field to the rendering function
                    });
                    
                }
            }
        }
    }
}

function render_repeater_field_block($field) { 
    ?>
    <script>
        jQuery(document).ready(function($) { // Pass jQuery as $ inside the function
            let field_name = '<?= $field['name'] ?>';
            $('div[data-name="'+field_name+'"]').append(`<hr>
                <textarea class="acf-importer" rows="5" placeholder="input excel data" 
                    style="margin-bottom: 5px; resize: none"
                    data-import-textarea="${field_name}"></textarea>
            <button class="button button-primary" type="button" data-import-button="${field_name}">Import</button>`);

            $('[data-import-button="'+field_name+'"]').on('click', function() {
                let import_textare_val = $('[data-import-textarea="'+field_name+'"]').val();
                
                let list = [];

                let textarea_rows = import_textare_val.split("\n");
                $.each(textarea_rows, function(key, value) {
                    let columns = value.split("\t");
                    list.push(columns);
                });
                
                // add empty rows
                let add_row_button = $('div[data-name="'+field_name+'').find('[data-event="add-row"]');
                let rows_table = $('div[data-name="'+field_name+'').find('table > tbody');
                let rows_table_trs = rows_table.find('tr');
                console.log(textarea_rows.length, rows_table_trs.length)
                if(textarea_rows.length > rows_table_trs.length) {
                    for(let i=0; i!=(textarea_rows.length - rows_table_trs.length); i++) {
                        add_row_button[0].click();
                    }
                }
                // $.each(textarea_rows, function(key, value) {
                //     add_row_button.click();
                // });

                // fill empty rows
                rows_table = $('div[data-name="'+field_name+'').find('table > tbody');
                rows_table_trs = rows_table.find('tr');
                $.each(rows_table_trs, function(table_tr_key, table_tr) {
                    let row_inputs = $(table_tr).find('input');
                    $.each(row_inputs, function(input_key, input_element) {
                        $(input_element).attr('value', list[table_tr_key][input_key])
                    });
                });
            })
        });
    </script>
    <?php
}

// Enqueue jQuery library
function enqueue_jquery() {
    wp_enqueue_script('jquery');
}
add_action('admin_enqueue_scripts', 'enqueue_jquery');

// Hook into WordPress initialization
add_action('init', 'generate_acf_repeater_blocks');