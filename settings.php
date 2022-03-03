<?php
function osm_settings_init()
{
    register_setting('osm', 'osm_options', [
        'type'              => 'array',
        'sanitize_callback' => 'osm_sanitize_options',
    ]);

    add_settings_section(
        'osm_section_info',
        __('OSM Auth Information.', 'osm'),
        'osm_section_info_callback',
        'osm'
    );

    add_settings_field(
        'osm_field_name',
        __('Name', 'osm'),
        'osm_field_name_cb',
        'osm',
        'osm_section_info',
        array(
            'label_for' => 'osm_field_name',
            'class'     => 'osm_row',
        )
    );

    add_settings_field(
        'osm_field_email',
        __('Email Address', 'osm'),
        'osm_field_email_cb',
        'osm',
        'osm_section_info',
        array(
            'label_for'         => 'osm_field_email',
            'class'             => 'osm_row',
        )
    );
}
add_action('admin_init', 'osm_settings_init');

function osm_section_info_callback($args)
{
?>
    <p id="<?php echo esc_attr($args['id']); ?>"><?php esc_html_e('Please provide your OSM OAuth details', 'osm'); ?></p>
    <p id="<?php echo esc_attr($args['id']); ?>">These can be found by logging into <a href="https://www.onlinescoutmanager.co.uk">OSM</a> and going to settings > My Account Details > Developer Tools.</p>
<?php
}

function osm_field_name_cb($args)
{
    $options = get_option('osm_options');
?>
    <input class="regular-text" placeholder="John Doe" type="text" id="<?php echo esc_attr($args['label_for']); ?>" name="osm_options[<?php echo esc_attr($args['label_for']); ?>]" value="<?php echo esc_attr($options[$args['label_for']]); ?>">
    <p class="description">
        <?php esc_html_e('Please enter your name.', 'osm'); ?>
    </p>
<?php
}

function osm_field_email_cb($args)
{
    $options = get_option('osm_options');
?>
    <input class="regular-text" placeholder="johndoe@example.com" type="text" id="<?php echo esc_attr($args['label_for']); ?>" name="osm_options[<?php echo esc_attr($args['label_for']); ?>]" value="<?php echo esc_attr($options[$args['label_for']]); ?>">
    <p class="description">
        <?php esc_html_e('Please enter your email address.', 'osm'); ?>
    </p>
<?php
}

function osm_options_page()
{
    add_menu_page(
        'Osm',
        'Osm Options',
        'manage_options',
        'osm',
        'osm_options_page_html'
    );
}
add_action('admin_menu', 'osm_options_page');

function osm_sanitize_options($data)
{
    $old_options = get_option('osm_options');
    $has_errors = false;

    if (empty($data['osm_field_name'])) {
        add_settings_error('osm_messages', 'osm_message', __('Name is required', 'osm'), 'error');

        $has_errors = true;
    }

    if (empty($data['osm_field_email'])) {
        add_settings_error('osm_messages', 'osm_message', __('Email address is required', 'osm'), 'error');

        $has_errors = true;
    }

    if (!is_email($data['osm_field_email'])) {
        add_settings_error('osm_messages', 'osm_message', __('Email address is invalid', 'osm'), 'error');

        $has_errors = true;
    }

    if ($has_errors) {
        $data = $old_options;
    }

    return $data;
}

function osm_options_page_html()
{
    if (!current_user_can('manage_options')) {
        return;
    }

    if (isset($_GET['settings-updated']) && empty(get_settings_errors('osm_messages'))) {
        add_settings_error('osm_messages', 'osm_message', __('Settings Saved', 'osm'), 'updated');
    }

    settings_errors('osm_messages');
?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <form action="options.php" method="post">
            <?php
            settings_fields('osm');
            do_settings_sections('osm');
            submit_button('Save Settings');
            ?>
        </form>
    </div>
<?php
}
