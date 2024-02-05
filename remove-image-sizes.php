<?php
/*
 * Plugin Name: Remove Image Sizes
 * Description: Remove specific image sizes for specific images.
 * Version: 1.0
 * Author: Marius Hasan
 */

// Add a custom column header
function add_custom_media_column($columns) {
    $columns['custom-actions'] = 'Sizes';
    return $columns;
}

function add_custom_media_button($column_name, $post_id) {
    if ($column_name === 'custom-actions') {
        if (get_post_mime_type($post_id) === 'image/svg+xml') {
            return;
        }

        $meta = wp_get_attachment_metadata($post_id);

        if ( !$meta ) {
            $response['errors'] = "No metadata found for image ID $image_id.";
            wp_send_json_error($response);
            return;
        }

        $upload_dir = wp_upload_dir();
        $base_dir = trailingslashit($upload_dir['basedir']);
        $sizes = $meta['sizes'];
        $original_filepath = $base_dir . $meta['file'];
        $original_size = file_exists($original_filepath) ? format_file_size(filesize($original_filepath)) : 'N/A';
        $original_dimensions = isset($meta['width']) && isset($meta['height']) ? "{$meta['width']}x{$meta['height']}" : 'N/A';

        echo "<div>original ({$original_dimensions}) - {$original_size}</div>";

        $checkboxDivs = "";

        foreach ($sizes as $size => $size_info) {
            $filepath = $base_dir . trailingslashit(dirname($meta['file'])) . $size_info['file'];
            $file_size = file_exists($filepath) ? format_file_size(filesize($filepath)) : 'N/A';
            $dimensions = isset($size_info['width']) && isset($size_info['height']) ? "{$size_info['width']}x{$size_info['height']}" : 'N/A';
            $checkboxDivs .= "<div><label><input type='checkbox' class='size-checkbox' data-id='{$post_id}' data-size='{$size}'> {$size} ({$dimensions}) - {$file_size}</label></div>";
        }
        $actions = "<div class='imc-checkboxes'>$checkboxDivs</div>";
        $actions .= count($sizes) !== 0 ? "<button class='remove-sizes button button-small button-primary' data-id='{$post_id}'>Remove Selected</button>" : "";
        echo "<div class='imc-actions'>{$actions}</div>";
        echo "<div class='imc-message'></div>";
    }
}

function format_file_size($bytes) {
    if ($bytes >= 1073741824) {
        $bytes = number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        $bytes = number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        $bytes = number_format($bytes / 1024, 2) . ' KB';
    } elseif ($bytes > 1) {
        $bytes = $bytes . ' bytes';
    } elseif ($bytes == 1) {
        $bytes = $bytes . ' byte';
    } else {
        $bytes = '0 bytes';
    }
    return $bytes;
}


function enqueue_custom_js() {
    wp_enqueue_script('remove-sizes-button', plugins_url('remove-image-sizes/button.js'), array('jquery'), '1.0', true);
    $nonce = wp_create_nonce('image_sizes_cleaner_nonce');
    wp_localize_script('remove-sizes-button', 'imageSizesCleaner', array(
        'nonce' => $nonce,
        'ajaxurl' => admin_url('admin-ajax.php')
    ));
}

function image_sizes_cleaner_ajax() {
    $response = array('errors' => array(), 'removedSizes' => array());

    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'image_sizes_cleaner_nonce')) {
        $response['errors'][] = 's';
    }

    if (!isset($_POST['image_id']) || !is_numeric($_POST['image_id'])) {
        $response['errors'][] = "s";
    }

    if (!isset($_POST['sizes']) || !is_array($_POST['sizes'])) {
        $response['errors'][] = "s";
    }

    $sizes = ['thumbnail', 'medium', 'medium_large', 'large', '1536x1536', '2048x2048'];
    foreach ($_POST['sizes'] as $size) {
        if (!in_array($size, $sizes)) {
            error_log($_POST['sizes']);
            $response['errors'][] = "s";
        }
    }

    if (!empty($response['errors'])) {
        wp_send_json($response);
        return;
    }

    $image_id = (int) $_POST['image_id'];
    $meta = wp_get_attachment_metadata( $image_id );

    if ( !$meta ) {
        $response['errors'] = "No metadata found for image ID $image_id.";
        wp_send_json_error($response);
        return;
    }

    $sizes_to_remove = $_POST['sizes'];
    $upload_dir = wp_upload_dir();
    $base_dir = trailingslashit($upload_dir['basedir']);
    $original_file_path = $base_dir . $meta['file'];
    $file_dirname = dirname($meta['file']) === '.' ? '' : trailingslashit(dirname($meta['file']));

    $removed_sizes = [];
    if ( isset( $meta['sizes'] ) ) {
        foreach ( $sizes_to_remove as $size ) {
            if ( isset( $meta['sizes'][$size] ) ) {
                $file_name = $meta['sizes'][$size]['file'];
                $file_path = $base_dir . $file_dirname . $file_name;
                $slices = explode('.', $file_name);
                $file_name_end = $slices[count($slices) - 2];
                
                if ( file_exists($file_path) && strpos($file_path, wp_upload_dir()['basedir']) === 0 && !empty($file_path) && $file_path !== $original_file_path && is_numeric($file_name_end[strlen($file_name_end) - 1]) ) {
                    unlink($file_path);
                } else {
                    $response['errors'][] = "File not found: $file_path.";
                }

                $webp_path = $file_path . '.webp';
                if (file_exists($webp_path)) {
                    unlink($webp_path);
                }

                unset( $meta['sizes'][$size] );
                $removed_sizes[] = $size;
            }
        }
    }

    if ( wp_update_attachment_metadata( $image_id, $meta ) ) {
        $size_list = implode(', ', $removed_sizes);
        $response['success'] = "Sizes ($size_list) were successfully removed for image ID $image_id.";
        $response['removedSizes'] = $removed_sizes;
    } else {
        $response['error'] = "Error updating metadata.";
    }
    
    wp_send_json($response);
}

function enqueue_styles() {
    global $pagenow;
    if ($pagenow == 'upload.php' && ( ! isset($_GET['mode']) || $_GET['mode'] !== 'grid' )) {
        wp_enqueue_style('imc-style', plugins_url('style.css', __FILE__));
    }
}

add_filter('manage_media_columns', 'add_custom_media_column');
add_action('manage_media_custom_column', 'add_custom_media_button', 10, 2);
add_action('admin_enqueue_scripts', 'enqueue_custom_js');
add_action('wp_ajax_image_sizes_cleaner', 'image_sizes_cleaner_ajax');
add_action('admin_enqueue_scripts', 'enqueue_styles');