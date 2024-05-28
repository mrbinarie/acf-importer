<?php
/*
Plugin Name:  ACF Importer
Plugin URI:   https://github.com/mrbinarie/acf-importer
Description:  ACF Repeater Field Import Tool
Version:      1.1
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
            $('div[data-name="'+field_name+'"]').append(`
                <input type="file" data-import-file="${field_name}" accept=".csv" style="display: none;">
                <button class="button button-primary" type="button" data-import-button="${field_name}">Import from CSV</button>`);

            let input_import = $('[data-import-file="'+field_name+'"]');
            let button_import = $('[data-import-button="'+field_name+'"]');

            button_import.click(function () {
                input_import.click();
            });

            input_import.change(function (event) {
                const file = event.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function (e) {
                        const csvContent = e.target.result;
                        displayCSVContent(csvContent);
                    };
                    reader.readAsText(file);
                }
            });

            function displayCSVContent(input_import_content)
            {
                let list = parseCSV(input_import_content);
console.log(list)
                // let textarea_rows = input_import_content.split("\n");
                // $.each(textarea_rows, function(key, value) {
                //     let columns = value.match(/(".*?"|[^",\s]+)(?=\s*,|\s*$)/g);
                //     list.push(columns);
                // });
                
                // add empty rows
                let add_row_button = $('div[data-name="'+field_name+'').find('[data-event="add-row"]');
                let rows_table = $('div[data-name="'+field_name+'').find('table > tbody');
                let rows_table_trs = rows_table.find('tr');
                console.log(list.length, rows_table_trs.length)
                if(list.length > rows_table_trs.length) {
                    for(let i=0; i!=(list.length - rows_table_trs.length); i++) {
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
            }

            function parseCSV(csv)
            {
                var lines = csv.split(/\r?\n/); // Split by new line
                var result = [];

                // Start from the second line (index 1) to skip headers
                for (var i = 0; i < lines.length; i++) {
                    var obj = {};
                    var currentLine = lines[i].split(',');

                    // Loop through each column in the current line
                    for (var j = 0; j < currentLine.length; j++) {
                        // Check if the value exists and starts with a quote
                        if (currentLine[j] && currentLine[j].startsWith('"')) {
                            // Join values that contain a comma until we have a balanced number of quotes
                            while (!currentLine[j].endsWith('"') && j < currentLine.length - 1) {
                                currentLine[j] += ',' + currentLine[j + 1];
                                currentLine.splice(j + 1, 1);
                            }
                            currentLine[j] = currentLine[j].substring(1, currentLine[j].length - 1);
                        }
                        // Assuming no headers, just use index as keys
                        obj[j] = currentLine[j];
                    }
                    result.push(obj);
                }
                return result;
            }

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