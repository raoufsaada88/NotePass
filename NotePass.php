<?php
/*
Plugin Name: Notepass
Description: NotePass is a WordPress plugin designed to help freelancers, agencies, or project managers create, organize, and hand over detailed project notes to clients or team members in a clean, structured, and user-friendly way.
Author: ITRS Consulting
Version: 1.0
Text Domain: notepass
*/

if (!defined('ABSPATH')) exit;

define('FH_LOGO_URL', plugin_dir_url(__FILE__) . 'assets/logo.png');
define('FH_BRAND_COLOR', '#0073aa');

// Sections list WITHOUT Non-Sensitive Credentials section
$sections = [
    'project_overview'   => 'Project Overview',
    'client_info'        => 'Client Information',
    'hosting'            => 'Hosting Info',
    'dns'                => 'DNS Settings',
    'theme'              => 'Theme & Design',
    'plugins'            => 'Plugin List',
    'custom_code'        => 'Custom Code or Features',
    'maintenance'        => 'Maintenance Guidelines',
    'handover_checklist' => 'Handover Checklist',
    'misc'               => 'Other Notes'
];

// Sections that use dynamic blocks
$block_sections = ['project_overview', 'client_info', 'hosting', 'dns'];

// Admin menu
add_action('admin_menu', function() {
    add_menu_page(
        'NotePass',       // Page title (top of admin page)
        'NotePass',       // Menu label in admin sidebar
        'manage_options',
        'freelancer-handover-notes',
        'fh_render_notes_page',
        'dashicons-media-document',
        100
    );
});

// Enqueue admin scripts and styles
add_action('admin_enqueue_scripts', function($hook) use ($block_sections) {
    if ($hook !== 'toplevel_page_freelancer-handover-notes') return;

    wp_enqueue_script('jquery');
    wp_enqueue_script('jquery-ui-tabs');
    wp_enqueue_style('jquery-ui-css', 'https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css');

    wp_enqueue_editor();

    wp_enqueue_style('fh-admin-css', plugin_dir_url(__FILE__) . 'css/admin.css', [], '1.3');
    wp_enqueue_script('jspdf', 'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js', [], '2.5.1', true);
    wp_enqueue_script('fh-admin-js', plugin_dir_url(__FILE__) . 'js/admin.js', ['jquery', 'jquery-ui-tabs', 'jspdf'], '1.3', true);

    wp_localize_script('fh-admin-js', 'fh_vars', [
        'block_sections' => $block_sections,
    ]);
});

// Render admin page with logo and branding footer
function fh_render_notes_page() {
    global $sections, $block_sections;

    if (!current_user_can('manage_options')) {
        wp_die('You do not have permission to view this page.');
    }

    if (isset($_POST['fh_notes_nonce']) && wp_verify_nonce($_POST['fh_notes_nonce'], 'fh_save_notes')) {
        foreach ($sections as $key => $label) {
            if (in_array($key, $block_sections)) {
                $labels = $_POST[$key.'_label'] ?? [];
                $values = $_POST[$key.'_value'] ?? [];
                $notes = $_POST[$key.'_note'] ?? [];
                
                $blocks = [];
                for ($i = 0; $i < count($labels); $i++) {
                    if (trim($labels[$i]) === '' && trim($values[$i]) === '' && trim($notes[$i]) === '') {
                        continue;
                    }
                    $blocks[] = [
                        'label' => sanitize_text_field($labels[$i]),
                        'value' => sanitize_text_field($values[$i]),
                        'note'  => wp_kses_post($notes[$i]),
                    ];
                }
                update_option('fh_note_' . $key, wp_json_encode($blocks));
            } else {
                if (isset($_POST[$key])) {
                    update_option('fh_note_' . $key, wp_kses_post($_POST[$key]));
                }
            }
        }
        echo '<div class="notice notice-success"><p>Notes saved successfully.</p></div>';
    }

    // Page Header with uploaded logo image
    echo '<div class="wrap">';
    echo '<h1><img src="' . esc_url(FH_LOGO_URL) . '" alt="NotePass Logo" style="height:24px; vertical-align:middle; margin-right:8px;"> NotePass</h1>';

    echo '<form method="post" id="fh-notes-form">';
    wp_nonce_field('fh_save_notes', 'fh_notes_nonce');

    echo '<div id="fh-tabs"><ul>';
    foreach ($sections as $key => $label) {
        echo "<li><a href='#tab-$key'>$label</a></li>";
    }
    echo '</ul>';

    foreach ($sections as $key => $label) {
        echo "<div id='tab-$key'>";
        if (in_array($key, $block_sections)) {
            $blocks = json_decode(get_option('fh_note_' . $key, '[]'), true);
            if (!$blocks) $blocks = [['label' => '', 'value' => '', 'note' => '']];
            
            echo '<div class="fh-blocks-container" data-section="'.esc_attr($key).'">';
            foreach ($blocks as $index => $block) {
                echo '<div class="fh-block-row" data-index="'.esc_attr($index).'">';
                echo '<input type="text" name="'.$key.'_label[]" placeholder="Label" value="'.esc_attr($block['label']).'" style="width:30%; margin-right:10px;">';
                echo '<input type="text" name="'.$key.'_value[]" placeholder="Value" value="'.esc_attr($block['value']).'" style="width:30%; margin-right:10px;">';
                echo '<textarea class="fh-block-note" name="'.$key.'_note[]" rows="4" style="width:35%;">'.esc_textarea($block['note']).'</textarea>';
                echo '<button type="button" class="button fh-remove-block" style="margin-left:10px;">Remove</button>';
                echo '</div>';
            }
            echo '</div>';
            echo '<button type="button" class="button fh-add-block" data-section="'.$key.'">Add new block</button>';
        } else {
            $content = get_option('fh_note_' . $key, '');
            wp_editor($content, $key, [
                'textarea_name' => $key,
                'textarea_rows' => 10,
                'media_buttons' => false,
                'teeny' => true,
            ]);
        }
        echo "</div>";
    }

    echo '</div>';

    echo '<p><input type="submit" class="button button-primary" value="Save Notes"></p>';

    echo '<p>
        <a href="#" id="export-html" class="button" style="background-color:' . FH_BRAND_COLOR . '; color:#fff; margin-right:5px;">Export as HTML</a>
        <a href="#" id="export-pdf" class="button" style="background-color:' . FH_BRAND_COLOR . '; color:#fff;">Export as PDF</a>
    </p>';

    echo '</form>';

    // Branding footer below form
    echo '<div style="text-align:center; font-family: \'Source Sans Pro\', -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Oxygen, Ubuntu, Cantarell, \'Helvetica Neue\', sans-serif; font-size:13px; color:#555; margin-top:40px; border-top:1px solid #e1e1e1; padding-top:10px;">';
    echo 'Created with <span style="color:' . FH_BRAND_COLOR . '; font-weight:bold;">&#10084;</span> by <a href="https://itrsconsulting.com" target="_blank" rel="noopener" style="color:' . FH_BRAND_COLOR . '; text-decoration:none;">ITRS Consulting</a>';
    echo '</div>';

    echo '</div>';
}

// Enqueue frontend styles
add_action('wp_enqueue_scripts', function() {
    wp_enqueue_style('fh-frontend-css', plugin_dir_url(__FILE__) . 'css/frontend.css', [], '1.3');
});

// Client portal shortcode
add_shortcode('freelancer_handover_notes', function() {
    global $sections;

    if (!is_user_logged_in()) {
        return '<p>Please <a href="' . wp_login_url(get_permalink()) . '">login</a> to view your handover notes.</p>';
    }

    $current_user = wp_get_current_user();

    if (!array_intersect($current_user->roles, ['subscriber', 'administrator', 'editor'])) {
        return '<p>You do not have permission to view this content.</p>';
    }

    $block_sections = ['project_overview', 'client_info', 'hosting', 'dns'];

    $output = '<div class="fh-client-notes">';
    $output .= '<h2>Project Handover Notes</h2>';

    foreach ($sections as $key => $label) {
        $output .= '<h3>' . esc_html($label) . '</h3>';

        if (in_array($key, $block_sections)) {
            $blocks = json_decode(get_option('fh_note_' . $key, '[]'), true);
            if ($blocks && is_array($blocks)) {
                $output .= '<ul>';
                foreach ($blocks as $block) {
                    $output .= '<li><strong>' . esc_html($block['label']) . ':</strong> ' . esc_html($block['value']) . '<br>' . wp_kses_post($block['note']) . '</li>';
                }
                $output .= '</ul>';
            } else {
                $output .= '<p><em>No details provided.</em></p>';
            }
        } else {
            $content = get_option('fh_note_' . $key, '');
            $output .= wp_kses_post(wpautop($content));
        }
    }

    $output .= '<button id="fh-confirm-receipt" class="button button-primary" style="margin-top:20px;">Confirm Receipt</button>';
    $output .= '<p id="fh-confirm-message" style="color:green; margin-top:10px; display:none;">Thank you for confirming receipt.</p>';
    $output .= '</div>';

    $output .= "<script>
        document.getElementById('fh-confirm-receipt').addEventListener('click', function() {
            fetch('" . admin_url('admin-ajax.php') . "?action=fh_confirm_receipt', {method: 'POST', credentials: 'same-origin'})
                .then(response => response.json())
                .then(data => {
                    if(data.success) {
                        document.getElementById('fh-confirm-message').style.display = 'block';
                        document.getElementById('fh-confirm-receipt').disabled = true;
                    }
                });
        });
    </script>";

    return $output;
});

// AJAX Confirm Receipt
add_action('wp_ajax_nopriv_fh_confirm_receipt', 'fh_ajax_confirm_receipt');
add_action('wp_ajax_fh_confirm_receipt', 'fh_ajax_confirm_receipt');
function fh_ajax_confirm_receipt() {
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Not logged in']);
    }
    $user_id = get_current_user_id();
    update_user_meta($user_id, 'fh_handover_confirmed', current_time('mysql'));
    wp_send_json_success(['message' => 'Receipt confirmed']);
}
