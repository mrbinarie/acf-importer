<?php
/*
Plugin Name:  ACF Importer
Plugin URI:   https://github.com/mrbinarie/acf-importer
Description:  ACF Repeater Field Import Tool
Version:      1.3
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
                    add_action('admin_footer', function() use ($field, $post_id) {
                        render_repeater_field_block($field, $post_id); // Pass $field to the rendering function
                    });
                    
                }
            }
        }
    }
}

function render_repeater_field_block($field, $post_id) { 
    ?>
    <script>
        jQuery(document).ready(function($) { // Pass jQuery as $ inside the function
            let field_name = '<?= $field['name'] ?>';
            let upload_csv_url = '<?= admin_url("admin.php?page=wp-acf-import-form&post_id=$post_id&acf_field_name=$field[name]") ?>';
            $('div[data-name="'+field_name+'"]').append(`
                <input type="file" data-import-file="${field_name}" accept=".csv" style="display: none;">
                <button class="button" type="button" data-import-button="${field_name}">Import CSV file</button>
                <a class="button" target="_blank" href="${upload_csv_url}">Import large CSV file</a>`);

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

// ================================================== //
// Register a custom page
function wp_simple_form_menu() {
    add_menu_page(
        'ACF Repeater Import', // Page title
        'ACF Import', // Menu title
        'manage_options', // Capability
        'wp-acf-import-form', // Menu slug
        'wp_simple_form_page' // Callback function
    );
}
add_action('admin_menu', 'wp_simple_form_menu');

// Display the form page
function wp_simple_form_page() {

    $post_id = '';

    if(isset($_GET['post_id'])) 
        $post_id = intval($_GET['post_id']);
    if(isset($_GET['acf_field_name'])) 
        $acf_field_name = $_GET['acf_field_name'];
    ?>
    <div class="wrap">
        <h1>ACF Import</h1>
        <?php if(isset($_GET['status']) && $_GET['status'] == 'error' && isset($_GET['message'])): ?>
            <?php 
                $messages = urldecode($_GET['message']);
            ?>
            <?php foreach(explode("|", $messages) as $message): ?>
                <div class="notice notice-error is-dismissible">
                        <p><?= esc_html($message) ?></p>
                </div>
            <?php endforeach ?>
        <?php elseif (isset($_GET['status']) && $_GET['status'] === 'success'): ?>
            <div class="notice notice-success is-dismissible">
                <p>Form submitted successfully!</p>
            </div>
        <?php endif; ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
            <input type="hidden" name="action" value="wp_simple_form_handle">
            <p>
                <label for="post_id">Post ID:</label>
                <input type="text" id="post_id" name="post_id" value="<?= $post_id ?>">
            </p>
            <p>
                <label for="post_id">ACF field(repeater) name:</label>
                <input type="text" id="acf_field_name" name="acf_field_name" value="<?= $acf_field_name ?>">
            </p>
            <p>
                <label for="file">Upload CSV File:</label>
                <input type="file" id="file" name="file" accept=".csv">
            </p>
            <p>
                <input type="submit" value="Submit">
            </p>
        </form>
    </div>
    <?php
}

// Handle form submission
function wp_simple_form_handle() {
    $errors = [];
    if (isset($_POST['post_id']) && isset($_POST['post_id'])) {
        $post_id = sanitize_text_field($_POST['post_id']);
        $field_name = sanitize_text_field($_POST['acf_field_name']);
        
        // Check if the post exists
        if (!get_post($post_id)) {
            $errors[] = "Post does not exist.";
        }

        // Check if the ACF field exists
        if (!get_field_object($field_name, $post_id)) {
            $errors[] = "ACF field does not exist.";
        }

        // Process file upload if no errors so far
        if (isset($_FILES['file']) && isset($_FILES['file']['tmp_name']) && !empty($_FILES['file']['tmp_name'])) {
            $fileType = mime_content_type($_FILES['file']['tmp_name']);
            if ($fileType == 'text/csv' || $fileType == 'application/vnd.ms-excel') {
                $fileContents = file_get_contents($_FILES['file']['tmp_name']);
                if ($fileContents !== FALSE) {
                    $sub_fields = get_acf_repeater_data_dynamic($post_id, $field_name);
                    $data = parse_csv($fileContents, $sub_fields);
                    import_acf_repeater_data($post_id, $field_name, $data);
                } else {
                    $errors[] = "Error opening the file.";
                }
            } else {
                $errors[] = "Invalid file type.";
            }
        } else {
            $errors[] = "File not provided.";
        }
    } else {
        $errors[] = "Unknown error.";
    }

    // Redirect based on the error status
    if(!empty($errors)) {
        $message = implode("|", $errors);
        wp_redirect(admin_url('admin.php?page=wp-acf-import-form&status=error&message=' . urlencode($message)));
    } else {
        wp_redirect(admin_url('admin.php?page=wp-acf-import-form&status=success'));
    }
    exit;
}

function parse_csv($csv, $sub_fields)
{
    $lines = preg_split('/\r\n|\r|\n/', $csv); // Split by new line
    $result = [];

    // Loop through each line
    foreach ($lines as $line) {
        $obj = [];
        $currentLine = explode(',', $line);

        // Loop through each column in the current line
        for ($j = 0; $j < count($currentLine); $j++) {
            // Check if the value exists and starts with a quote
            if (!empty($currentLine[$j]) && $currentLine[$j][0] == '"') {
                // Join values that contain a comma until we have a balanced number of quotes
                while ($j < count($currentLine) - 1 && substr($currentLine[$j], -1) != '"') {
                    $currentLine[$j] .= ',' . $currentLine[$j + 1];
                    array_splice($currentLine, $j + 1, 1);
                }
                $currentLine[$j] = substr($currentLine[$j], 1, -1);
            }
            // Assuming no headers, just use index as keys
            $obj[$sub_fields[$j]] = $currentLine[$j];
        }
        if(count($obj) == count($sub_fields))
            $result[] = $obj;
    }

    return $result;
}

function import_acf_repeater_data($post_id, $field_name, $data) {
    if( function_exists('have_rows') && function_exists('add_row') ) {
        // Clear existing rows
        delete_field($field_name, $post_id);
        
        // Loop through the data and add rows
        foreach( $data as $row ) {
            add_row($field_name, $row, $post_id);
        }
    }
}

function get_acf_repeater_data_dynamic($post_id, $field_name) {
    $repeater_data = [];
    $field = get_field_object($field_name, $post_id);
    // echo "<pre>"; print_r($field['sub_fields']);
    if (isset($field['sub_fields']) && is_array($field['sub_fields'])) {
        foreach ($field['sub_fields'] as $row) {
            $repeater_data[] = $row['name'];
        }
    }

    return $repeater_data;
}

add_action('admin_post_wp_simple_form_handle', 'wp_simple_form_handle');
add_action('admin_post_nopriv_wp_simple_form_handle', 'wp_simple_form_handle');