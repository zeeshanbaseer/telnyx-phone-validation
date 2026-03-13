<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Telnyx_Validator {

    public function __construct() {

        // Admin settings
        add_action( 'admin_menu', [ $this, 'add_settings_page' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );

        // WPForms validation
        add_action( 'wpforms_process', [ $this, 'validate_wpforms' ], 10, 3 );

        // Contact Form 7 validation (tel & tel*)
        add_filter( 'wpcf7_validate_tel',  [ $this, 'validate_cf7' ], 20, 2 );
        add_filter( 'wpcf7_validate_tel*', [ $this, 'validate_cf7' ], 20, 2 );
    }

    // ---------------------------------------------------------------------
    // ADMIN PAGE
    // ---------------------------------------------------------------------

    public function add_settings_page() {
        add_options_page(
            'Telnyx Validation',
            'Telnyx Validation',
            'manage_options',
            'telnyx-validation',
            [ $this, 'settings_page_html' ]
        );
    }

    public function register_settings() {

        register_setting( 'telnyx_settings_group', 'telnyx_api_key' );

        register_setting( 'telnyx_settings_group', 'telnyx_form_mappings', [
            'type'              => 'array',
            'sanitize_callback' => function( $input ) {
                if ( ! is_array( $input ) ) {
                    return [];
                }

                foreach ( $input as &$row ) {
                    $row['plugin']   = isset( $row['plugin'] ) ? sanitize_text_field( $row['plugin'] ) : '';
                    $row['form_id']  = isset( $row['form_id'] ) ? sanitize_text_field( $row['form_id'] ) : '';
                    $row['phone_id'] = isset( $row['phone_id'] ) ? sanitize_text_field( $row['phone_id'] ) : '';
                }

                return $input;
            },
        ] );
    }

    // ---------------------------------------------------------------------
    // SETTINGS PAGE UI
    // ---------------------------------------------------------------------

    public function settings_page_html() {
        $mappings = get_option( 'telnyx_form_mappings', [] );
        if ( ! is_array( $mappings ) ) {
            $mappings = [];
        }

        $plugins = [
            'wpforms'      => 'WPForms',
            'gravityforms' => 'Gravity Forms',
            'cf7'          => 'Contact Form 7',
            'elementor'    => 'Elementor Form',
        ];
        ?>
        <div class="wrap">
            <h1>Telnyx Number Validation Settings</h1>

            <form method="post" action="options.php">
                <?php settings_fields( 'telnyx_settings_group' ); ?>

                <table class="form-table">

                    <!-- API KEY -->
                    <tr valign="top">
                        <th scope="row">Telnyx API Key</th>
                        <td>
                            <input type="text"
                                   name="telnyx_api_key"
                                   value="<?php echo esc_attr( get_option( 'telnyx_api_key' ) ); ?>"
                                   style="width: 400px;" />
                        </td>
                    </tr>

                    <!-- MAPPINGS UI -->
                    <tr valign="top">
                        <th scope="row">Form Mappings</th>
                        <td>

                            <table id="telnyx-mapping-table" class="widefat" style="max-width:700px;">
                                <thead>
                                    <tr>
                                        <th>Plugin</th>
                                        <th>Form ID</th>
                                        <th>Phone Field ID</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ( $mappings as $i => $row ) : ?>
                                    <tr>
                                        <td>
                                            <select name="telnyx_form_mappings[<?php echo esc_attr( $i ); ?>][plugin]">
                                                <?php foreach ( $plugins as $slug => $name ) : ?>
                                                    <option value="<?php echo esc_attr( $slug ); ?>"
                                                        <?php selected( isset( $row['plugin'] ) ? $row['plugin'] : '', $slug ); ?>>
                                                        <?php echo esc_html( $name ); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <td>
                                            <input type="text"
                                                   name="telnyx_form_mappings[<?php echo esc_attr( $i ); ?>][form_id]"
                                                   value="<?php echo esc_attr( isset( $row['form_id'] ) ? $row['form_id'] : '' ); ?>">
                                        </td>
                                        <td>
                                            <input type="text"
                                                   name="telnyx_form_mappings[<?php echo esc_attr( $i ); ?>][phone_id]"
                                                   value="<?php echo esc_attr( isset( $row['phone_id'] ) ? $row['phone_id'] : '' ); ?>">
                                        </td>
                                        <td>
                                            <button type="button" class="button remove-row">X</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>

                            <br>
                            <button type="button" class="button" id="add-mapping-row">Add Mapping</button>

                            <script>
                                (function($) {

                                    let rowIndex = <?php echo (int) count( $mappings ); ?>;

                                    $('#add-mapping-row').on('click', function() {
                                        const row = `
                                            <tr>
                                                <td>
                                                    <select name="telnyx_form_mappings[${rowIndex}][plugin]">
                                                        <option value="wpforms">WPForms</option>
                                                        <option value="gravityforms">Gravity Forms</option>
                                                        <option value="cf7">Contact Form 7</option>
                                                        <option value="elementor">Elementor Form</option>
                                                    </select>
                                                </td>
                                                <td>
                                                    <input type="text" name="telnyx_form_mappings[${rowIndex}][form_id]">
                                                </td>
                                                <td>
                                                    <input type="text" name="telnyx_form_mappings[${rowIndex}][phone_id]">
                                                </td>
                                                <td>
                                                    <button type="button" class="button remove-row">X</button>
                                                </td>
                                            </tr>`;
                                        $('#telnyx-mapping-table tbody').append(row);
                                        rowIndex++;
                                    });

                                    $(document).on('click', '.remove-row', function() {
                                        $(this).closest('tr').remove();
                                    });

                                })(jQuery);
                            </script>

                        </td>
                    </tr>

                </table>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    // ---------------------------------------------------------------------
    // WPForms VALIDATION (multi-mapping compatible)
    // ---------------------------------------------------------------------

    public function validate_wpforms( $fields, $entry, $form_data ) {

        $api_key = trim( get_option( 'telnyx_api_key' ) );
        if ( empty( $api_key ) ) {
            return;
        }

        $mappings = get_option( 'telnyx_form_mappings', [] );
        if ( ! is_array( $mappings ) ) {
            return;
        }

        $form_id = isset( $form_data['id'] ) ? (int) $form_data['id'] : 0;

        foreach ( $mappings as $map ) {

            // Only WPForms mappings
            if ( ! isset( $map['plugin'] ) || $map['plugin'] !== 'wpforms' ) {
                continue;
            }

            if ( (int) $map['form_id'] !== $form_id ) {
                continue;
            }

            $phone_field_id = isset( $map['phone_id'] ) ? (int) $map['phone_id'] : 0;
            if ( ! isset( $fields[ $phone_field_id ] ) ) {
                continue;
            }

            $phone  = isset( $fields[ $phone_field_id ]['value'] ) ? $fields[ $phone_field_id ]['value'] : '';
            $error  = $this->run_telnyx_lookup( $phone );

            if ( $error ) {
                wpforms()->process->errors[ $form_id ][ $phone_field_id ] = $error;
            }
        }
    }

    // ---------------------------------------------------------------------
    // CONTACT FORM 7 VALIDATION (multi-mapping compatible)
    // ---------------------------------------------------------------------

    /**
     * CF7 validation callback for tel & tel* fields.
     *
     * @param WPCF7_Validation $result
     * @param WPCF7_FormTag    $tag
     *
     * @return WPCF7_Validation
     */
    public function validate_cf7( $result, $tag ) {

        $api_key = trim( get_option( 'telnyx_api_key' ) );
        if ( empty( $api_key ) ) {
            return $result;
        }

        $mappings = get_option( 'telnyx_form_mappings', [] );
        if ( ! is_array( $mappings ) || empty( $mappings ) ) {
            return $result;
        }

        if ( ! class_exists( 'WPCF7_Submission' ) ) {
            return $result;
        }

        $submission = \WPCF7_Submission::get_instance();
        if ( ! $submission ) {
            return $result;
        }

        $contact_form = $submission->get_contact_form();
        if ( ! $contact_form ) {
            return $result;
        }

        $current_form_id = (int) $contact_form->id();
        $field_name      = $tag->name;

        foreach ( $mappings as $map ) {

            if ( ! isset( $map['plugin'] ) || $map['plugin'] !== 'cf7' ) {
                continue;
            }

            if ( (int) $map['form_id'] !== $current_form_id ) {
                continue;
            }

            if ( ! isset( $map['phone_id'] ) || $map['phone_id'] !== $field_name ) {
                continue;
            }

            $posted_data = $submission->get_posted_data();
            $phone       = isset( $posted_data[ $field_name ] ) ? $posted_data[ $field_name ] : '';

            $error = $this->run_telnyx_lookup( $phone );

            if ( $error ) {
                $result->invalidate( $tag, $error );
            }

            // Only one mapping per field
            break;
        }

        return $result;
    }

    // ---------------------------------------------------------------------
    // UNIVERSAL TELNYX LOOKUP
    // ---------------------------------------------------------------------

    private function run_telnyx_lookup( $phone ) {

        $api_key = trim( get_option( 'telnyx_api_key' ) );
        if ( empty( $api_key ) ) {
            return null;
        }

        if ( empty( $phone ) ) {
            return 'Please enter your phone number.';
        }

        // Normalize
        $phone = preg_replace( '/\D/', '', $phone );

        if ( strlen( $phone ) === 10 ) {
            $phone = '1' . $phone;
        }

        if ( strpos( $phone, '+' ) !== 0 ) {
            $phone = '+' . $phone;
        }

        $url = 'https://api.telnyx.com/v2/number_lookup/' . urlencode( $phone ) . '?type=carrier&type=caller-name';

        $response = wp_remote_get(
            $url,
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $api_key,
                    'Accept'        => 'application/json',
                ],
                'timeout' => 15,
            ]
        );

        if ( is_wp_error( $response ) ) {
            return 'Could not reach Telnyx. Try again later.';
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( ! empty( $data['errors'] ) ) {
            $msg = isset( $data['errors'][0]['title'] ) ? $data['errors'][0]['title'] : 'Unknown error';
            return 'Telnyx error: ' . $msg;
        }

        if ( empty( $data['data']['valid_number'] ) ) {
            return 'Invalid phone number. Please check again.';
        }

        return null;
    }
}

new Telnyx_Validator();
