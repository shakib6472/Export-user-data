<?php
/*
 * Plugin Name:      Export Users
* Plugin URI:        https://github.com/shakib6472/
* Description:        This is the core-helper websites Custom Plugin. All features are came from here.
* Version:           1.0.0
* Requires at least: 5.2
* Requires PHP:      7.2
* Author:            Shakib Shown
* Author URI:        https://github.com/shakib6472/
* License:           GPL v2 or later
* License URI:       https://www.gnu.org/licenses/gpl-2.0.html
* Text Domain:       exp-user-skb
* Domain Path:       /languages
*/
if (!defined('ABSPATH')) {
exit; // Exit if accessed directly.
}



// Add menu page to admin dashboard
add_action('admin_menu', 'export_users_plugin_menu');
function export_users_plugin_menu() {
    add_menu_page(
        'Export Users',
        'Export Users',
        'manage_options',
        'export-users',
        'export_users_page_content',
        'dashicons-download',
        100
    );
}

// Admin page content
function export_users_page_content() {
    // Fetch roles for filter dropdown
    $roles = get_editable_roles();

    echo '<div class="wrap">
            <h1>Export Users</h1>
            <form id="export-users-form">
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="user_role">Filter by Role</label></th>
                        <td>
                            <select name="user_role" id="user_role">
                                <option value="">All Roles</option>';
    foreach ($roles as $role_key => $role_data) {
        echo '<option value="' . esc_attr($role_key) . '">' . esc_html($role_data['name']) . '</option>';
    }
    echo '          </select>
                        </td>
                    </tr>
                </table>
                <button type="button" id="export-users-btn" class="button button-primary">Export Users to CSV</button>
            </form>
            <div id="export-status"></div>
          </div>';
    add_action('admin_footer', 'export_users_ajax_script');
}

// Enqueue JavaScript for AJAX functionality
function export_users_ajax_script() {
    echo '<script type="text/javascript">
        jQuery(document).ready(function($) {
            console.log("Export Users script loaded");

            $("#export-users-btn").on("click", function() {
                console.log("Export button clicked");
                const role = $("#user_role").val();
                console.log("Selected role:", role);

                $("#export-status").html("<p>Processing...</p>");

                $.ajax({
                    url: ajaxurl,
                    method: "POST",
                    data: {
                        action: "export_users_ajax",
                        user_role: role
                    },
                    xhrFields: {
                        responseType: "blob"
                    },
                    success: function(data) {
                        console.log("AJAX success", data);
                        const blob = new Blob([data], { type: "text/csv" });
                        const url = window.URL.createObjectURL(blob);
                        const a = document.createElement("a");
                        a.style.display = "none";
                        a.href = url;
                        a.download = "users_export.csv";
                        document.body.appendChild(a);
                        a.click();
                        window.URL.revokeObjectURL(url);
                        $("#export-status").html("<p>Export completed.</p>");
                    },
                    error: function(xhr, status, error) {
                        console.error("AJAX error", status, error);
                        $("#export-status").html("<p>Error occurred during export.</p>");
                    }
                });
            });
        });
    </script>';
}

// Handle AJAX request
add_action('wp_ajax_export_users_ajax', 'export_users_ajax_handler');
function export_users_ajax_handler() {
    error_log("Export AJAX handler triggered");

    $role_filter = isset($_POST['user_role']) ? sanitize_text_field($_POST['user_role']) : '';
    error_log("Role filter: " . $role_filter);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=users_export.csv');

    $output = fopen('php://output', 'w');

    // Collect all unique meta keys for headers
    $all_meta_keys = [];
    $users = get_users();
    foreach ($users as $user) {
        $meta_keys = array_keys(get_user_meta($user->ID));
        $all_meta_keys = array_unique(array_merge($all_meta_keys, $meta_keys));
    }

    // Base headers
    $headers = ['User ID', 'Username', 'Email', 'First Name', 'Last Name', 'Display Name', 'Role'];
    // Append meta keys to headers
    $headers = array_merge($headers, $all_meta_keys);

    // Write headers to CSV
    fputcsv($output, $headers);

    // Filter users based on role
    $args = ['role' => $role_filter];
    if (empty($role_filter)) {
        unset($args['role']);
    }

    $users = get_users($args);
    error_log("Number of users fetched: " . count($users));

    foreach ($users as $user) {
        $user_data = [
            $user->ID,
            $user->user_login,
            $user->user_email,
            $user->first_name,
            $user->last_name,
            $user->display_name,
            implode(', ', $user->roles),
        ];

        $meta_data = get_user_meta($user->ID);
        foreach ($all_meta_keys as $meta_key) {
            $user_data[] = isset($meta_data[$meta_key]) ? json_encode($meta_data[$meta_key][0]) : '';
        }

        error_log("User data: " . json_encode($user_data));
        fputcsv($output, $user_data);
    }

    fclose($output);
    exit;
}
