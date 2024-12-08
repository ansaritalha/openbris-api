<?php
/**
 * Plugin Name: OpenBRIS Company Search for Quform
 * Description: Integrate OpenBRIS company autocomplete with Quform fields.
 * Version: 1.1
 * Author: Talha Ansari
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class OpenBRIS_Quform_Autocomplete {
    private $api_key;

    public function __construct() {
        // Retrieve API key from settings
        $this->api_key = get_option('openbris_api_key', 'default-api-key');

        // Enqueue scripts
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);

        // AJAX handlers
        add_action('wp_ajax_fetch_company_suggestions', [$this, 'fetch_company_suggestions']);
        add_action('wp_ajax_nopriv_fetch_company_suggestions', [$this, 'fetch_company_suggestions']);

        // Admin settings page
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);
    }

    public function enqueue_scripts() {
        wp_enqueue_script(
            'openbris-quform-autocomplete',
            plugin_dir_url(__FILE__) . 'openbris-quform-autocomplete.js',
            ['jquery'],
            filemtime(plugin_dir_path(__FILE__) . 'openbris-quform-autocomplete.js'),
            true
        );

        // Retrieve field classes and companyIdClasses from settings
       
        $company_id_classes = get_option('company_id_classes', '');
         $company_id_classes_array = array_map('trim', explode(',', $company_id_classes));
        $search_classes = get_option('company_search_classes', '');
        $search_classes_array = array_map('trim', explode(',', $search_classes));
       
        $vat_classes = get_option('vat_field_classes', '');
        $vat_classes_array = array_map('trim', explode(',', $vat_classes));
        $state_classes = get_option('state_field_classes', '');
        $state_classes_array = array_map('trim', explode(',', $state_classes));
$IČ_DPH_classes = get_option('dph_field_classes', '');
        $IČ_DPH_classes_array = array_map('trim', explode(',', $IČ_DPH_classes));
        wp_localize_script('openbris-quform-autocomplete', 'openbrisConfig', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'company_name_search_classes' => $search_classes_array,
            'company_id_classes' => $company_id_classes_array, 
            'vat_field_classes' => $vat_classes_array, 
            'dph_field_classes' => $IČ_DPH_classes_array, 
            'state_field_classes' => $state_classes_array,
        ]);
    }

    public function fetch_company_suggestions() {
        $query = sanitize_text_field($_POST['openquery']);
        $country_input = isset($_POST['briscountry']) ? sanitize_text_field($_POST['briscountry']) : '';

// Default country code
$country_code = 'SVK';

// Use a switch-case to map country names to codes
switch ($country_input) {
    case 'Slovensko':
        $country_code = 'SVK';
        break;
    case 'Česká republika':
        $country_code = 'CZE';
        break;
    default:
        $country_code = 'SVK';
}


        if (strlen($query) < 3) {
            wp_send_json_error(['message' => 'Please enter at least 3 characters.']);
        }

        $api_url = "https://api.openbris.eu/v1/autocomplete/$country_code/$query";
        $response = wp_remote_get($api_url, [
            'headers' => [
                'api-key' => $this->api_key,
            ],
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error(['message' => 'API request failed.']);
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            wp_send_json_error(['message' => 'Failed to fetch data.']);
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (isset($data['error'])) {
            wp_send_json_error(['message' => $data['error']]);
        }

        $suggestions = array_map(function ($item) {
    $vat_number = isset($item['vatNumber']) ? $item['vatNumber'] : 'No VAT number available';  // Add this line
    return [
        'name' => $item['name'],
        'address' => $item['street'] ?? 'No address provided',
        'city' => $item['city'] ?? 'No city provided',
        'id' => $item['businessId'],
        'vat' => $vat_number  // Use the modified VAT number

    ];
}, $data);

        wp_send_json_success($suggestions);
    }

    public function add_settings_page() {
        add_menu_page(
            'OpenBRIS Settings',
            'OpenBRIS',
            'manage_options',
            'openbris-settings',
            [$this, 'render_settings_page'],
            'dashicons-admin-settings',
            100
        );
    }

    public function register_settings() {
        register_setting('openbris_settings', 'company_search_classes');
        register_setting('openbris_settings', 'openbris_api_key');
        register_setting('openbris_settings', 'company_id_classes'); 
         register_setting('openbris_settings', 'vat_field_classes');
register_setting('openbris_settings', 'dph_field_classes');

 register_setting('openbris_settings', 'state_field_classes'); 
    }

    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1>OpenBRIS Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('openbris_settings');
                do_settings_sections('openbris_settings');
                ?>
                <table class="form-table">
                        <tr valign="top">
                        <th scope="row">API Key</th>
                        <td>
                            <textarea rows="5" cols="50" type="text" name="openbris_api_key"><?php echo esc_textarea(get_option('openbris_api_key', '')); ?></textarea>
                            <p class="description">Your OpenBRIS API key.</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Názov spoločnosti classes:</th>
                        <td>
                            <textarea name="company_search_classes" rows="5" cols="50"><?php echo esc_textarea(get_option('company_search_classes', '')); ?></textarea>
                            <p class="description">Add Classes for the Názov spoločnosti (comma-separated).</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">IČO Classes:</th>
                        <td>
                            <textarea name="company_id_classes" rows="5" cols="50"><?php echo esc_textarea(get_option('company_id_classes', '')); ?></textarea>
                            <p class="description">Add Classes where the IČO should be set (comma-separated).</p>
                        </td>
                    </tr>
                    <tr valign="top">
    <th scope="row">
Štát Field Classes</th>
    <td>
        <textarea name="state_field_classes" rows="5" cols="50"><?php echo esc_textarea(get_option('state_field_classes', '')); ?></textarea>
        <p class="description">Classes where the Štát classes should be set (comma-separated).</p>
    </td>
</tr>
                    <tr valign="top">
    <th scope="row">Je firma platcom DPH Field Classes</th>
    <td>
        <textarea name="vat_field_classes" rows="5" cols="50"><?php echo esc_textarea(get_option('vat_field_classes', '')); ?></textarea>
        <p class="description">Classes where the Je firma platcom DPH? classes should be set (comma-separated).</p>
    </td>
</tr>
<tr valign="top">
    <th scope="row">
IČ DPH Field Classes</th>
    <td>
        <textarea name="dph_field_classes" rows="5" cols="50"><?php echo esc_textarea(get_option('dph_field_classes', '')); ?></textarea>
        <p class="description">Classes where the 
IČ DPH classes should be set (comma-separated).</p>
    </td>
</tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}

new OpenBRIS_Quform_Autocomplete();