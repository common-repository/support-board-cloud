<?php

/*
 *
 * Plugin Name: Support Board Cloud
 * Plugin URI: https://cloud.board.support/
 * Description: Smart chat for better support and marketing
 * Version: 1.0.4
 * Author: Support Board
 * Author URI: https://board.support/
 * © 2017-2023 board.support. All rights reserved.
 *
 */

function sbcloud_set_admin_menu() {
    add_submenu_page('options-general.php', 'Support Board Cloud', 'Support Board Cloud', 'administrator', 'support-board-cloud', 'sbcloud_admin');
}

function sbcloud_enqueue_admin() {
    if (key_exists('page', $_GET) && $_GET['page'] == 'support-board-cloud') {
        wp_enqueue_style('sb-cloud-admin-css', plugin_dir_url( __FILE__ ) . '/assets/style.css', [], '1.0', 'all');
    }
}

function sbcloud_enqueue() {
    $settings = json_decode(get_option('sbcloud-settings'), true);
    if (!$settings || empty($settings['chat-id'])) return false;
    $inline_code = '';
    $page_id = get_the_ID();
    $exclusions = [$settings['visibility-ids'], $settings['visibility-post-types'], $settings['visibility-type']];
    $exclusions = [$exclusions[0] ? array_map('trim', explode(',', $exclusions[0])) : [], $exclusions[1] ? array_map('trim', explode(',', $exclusions[1])) : [], $exclusions[2]];

    // Selective chat loading
    if ($exclusions[2] != false && (count($exclusions[0]) && (($exclusions[2] == 'show' && !in_array($page_id, $exclusions[0])) || ($exclusions[2] == 'hide' && in_array($page_id, $exclusions[0]))))) {
        return false;
    }
    if (count($exclusions[1])) {
        $post_type = get_post_type($page_id);
        if ((($exclusions[2] == 'show' && !in_array($post_type, $exclusions[1])) || ($exclusions[2] == 'hide' && in_array($post_type, $exclusions[1])))) {
            return false;
        }
    }

    // Multisite routing
    if (is_multisite() && $settings['multisite-routing']) {
        $inline_code .= 'var SB_DEFAULT_DEPARTMENT = ' . esc_html(get_current_blog_id()) . ';';
    }

    // WordPress users synchronization
    if ($settings['synch-wp-users']) {
        $current_user = wp_get_current_user();
        if ($current_user) {
            $profile_image = get_avatar_url($current_user->ID, ['size' => '500']);
            if (empty($profile_image) || !(strpos($profile_image, '.jpg') || strpos($profile_image, '.png'))) {
                $profile_image = '';
            }
            $inline_code .= 'var SB_DEFAULT_USER = { first_name: "' . esc_html($current_user->user_firstname ? $current_user->user_firstname : $current_user->nickname) . '", last_name: "' . esc_html($current_user->user_lastname) . '", email: "' . esc_html($current_user->user_email) . '", profile_image: "' . esc_html($profile_image) . '", password: "' . esc_html($current_user->user_pass) . '", extra: { "wp-id": [' . esc_html($current_user->ID) . ', "WordPress ID"] }};';
        }
    }

    // Force language
    $language = sbcloud_isset($settings, 'force-language');
    if ($language) $language = '&lang=' . esc_html($language);

    wp_enqueue_script('chat-init', 'https://cloud.board.support/account/js/init.js?id=' . esc_html($settings['chat-id']) . $language, ['jquery'], '1.0', true);
    if ($inline_code) wp_add_inline_script('jquery', $inline_code);
}

function sbcloud_tickets_shortcode() {
    wp_register_script('sbcloud-tickets', '');
    wp_enqueue_script('sbcloud-tickets');
    wp_add_inline_script('sbcloud-tickets', 'var SB_TICKETS = true;');
    return '<div id="sb-tickets"></div>';
}

function sbcloud_articles_shortcode() {
    return '<script>var SB_ARTICLES_PAGE = true</script><div id="sb-articles"></div>';
}

function sbcloud_isset($array, $key, $default = '') {
    return !empty($array) && isset($array[$key]) && $array[$key] !== '' ? $array[$key] : $default;
}

function sbcloud_admin() { 
    if (isset($_POST['sbcloud_submit'])) {
        $chat_id = $_POST['sbcloud-chat-id'];
        if (!isset($_POST['sb_nonce']) || !wp_verify_nonce($_POST['sb_nonce'], 'sb-nonce')) die('nonce-check-failed'); 
        if (strpos($chat_id, 'script')) {
            $chat_id = str_replace('\"', '"', $chat_id);
            $chat_id = substr($chat_id, strpos($chat_id, 'js?id=') + 6);
            $chat_id = substr($chat_id, 0, strpos($chat_id, '"'));
        } 
        sanitize_text_field($chat_id);
        $settings = [
            'chat-id' => sanitize_text_field($chat_id),
            'multisite-routing' => sanitize_text_field(sbcloud_isset($_POST, 'sbcloud-multisite-routing', false)),
            'visibility-type' => sanitize_text_field($_POST['sbcloud-visibility-type']),
            'visibility-ids' => sanitize_text_field($_POST['sbcloud-visibility-ids']),
            'visibility-post-types' => sanitize_text_field($_POST['sbcloud-visibility-post-types']),
            'synch-wp-users' => sanitize_text_field(sbcloud_isset($_POST, 'sbcloud-synch-wp-users', false)),
            'force-language' => sanitize_text_field($_POST['sbcloud-force-language'])
        ];
        update_option('sbcloud-settings', json_encode($settings));
    }
    $settings = json_decode(get_option('sbcloud-settings'), true);
    $force_language = sbcloud_isset($settings, 'force-language');
?>
<form method="post" action="">
<div class="wrap">
    <h1>Support Board Cloud</h1>
    <div class="postbox-container">
        <table class="form-table sbcloud-table">
            <tbody>
                <tr valign="top">
                    <th scope="row">
                        <label for="name">Chat ID</label>
                    </th>
                    <td>
                        <input type="text" id="sbcloud-chat-id" name="sbcloud-chat-id" value="<?php echo esc_html(sbcloud_isset($settings, 'chat-id')) ?>" />
                        <br />
                        <p class="description">Enter the embed code or the ID attribute. Get it from <a target="_blank" href="https://cloud.board.support/account/?tab=installation">here</a>. Pricing <a target="_blank" href="https://board.support/cloud/wordpress">here</a>.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">
                        <label for="name">Multisite routing</label>
                    </th>
                    <td>
                        <input type="checkbox" id="sbcloud-multisite-routing" name="sbcloud-multisite-routing" <?php if (sbcloud_isset($settings, 'multisite-routing')) echo 'checked' ?> " />
                        <br />
                        <p class="description">
                            Automatically route the conversations of each website to the department with the same ID of the WordPress website.
                            This setting requires a WordPress Multisite installation and Support Board departments with the same IDs of the WordPress websites.
                        </p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">
                        <label for="name">Visibility</label>
                    </th>
                    <td>
                        <label>Type</label>
                        <select id="sbcloud-visibility-type" name="sbcloud-visibility-type">
                            <option value=""></option>
                            <option value="show" <?php if (sbcloud_isset($settings, 'visibility-type') == 'show') echo 'selected' ?>>Show</option>
                            <option value="hide" <?php if (sbcloud_isset($settings, 'visibility-type') == 'hide') echo 'selected' ?>>Hide</option>
                        </select>
                        <br />
                        <label>Page IDs</label>
                        <input type="text" id="sbcloud-visibility-ids" name="sbcloud-visibility-ids" value="<?php echo esc_html(sbcloud_isset($settings, 'visibility-ids')) ?>" />
                        <br />
                        <label>Post Type slugs</label>
                        <input type="text" id="sbcloud-visibility-post-types" name="sbcloud-visibility-post-types" value="<?php echo esc_html(sbcloud_isset($settings, 'visibility-post-types')) ?>" />
                        <br />
                        <p class="description">
                            Choose where to display the chat. Insert the values separated by commas.
                        </p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">
                        <label for="name">Synchronize WordPress users</label>
                    </th>
                    <td>
                        <input type="checkbox" id="sbcloud-synch-wp-users" name="sbcloud-synch-wp-users" <?php if (sbcloud_isset($settings, 'synch-wp-users')) echo 'checked' ?> />
                        <br />
                        <p class="description">
                          Sync logged in WordPress users with Support Board.
                        </p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">
                        <label for="name">Force language</label>
                    </th>
                    <td>
                        <select id="sbcloud-force-language" name="sbcloud-force-language">
                            <option value="" <?php if (empty($force_language)) echo 'selected' ?>>Disabled</option>
                            <option value="ar" <?php if ($force_language == 'ar') echo 'selected' ?>>Arabic</option>
                            <option value="bg" <?php if ($force_language == 'bg') echo 'selected' ?>>Bulgarian</option>
                            <option value="cs" <?php if ($force_language == 'cs') echo 'selected' ?>>Czech</option>
                            <option value="da" <?php if ($force_language == 'da') echo 'selected' ?>>Danish</option>
                            <option value="de" <?php if ($force_language == 'de') echo 'selected' ?>>German</option>
                            <option value="el" <?php if ($force_language == 'el') echo 'selected' ?>>Greek</option>
                            <option value="es" <?php if ($force_language == 'es') echo 'selected' ?>>Spanish</option>
                            <option value="et" <?php if ($force_language == 'et') echo 'selected' ?>>Estonian</option>
                            <option value="fa" <?php if ($force_language == 'fa') echo 'selected' ?>>Persian</option>
                            <option value="fi" <?php if ($force_language == 'fi') echo 'selected' ?>>Finnish</option>
                            <option value="fr" <?php if ($force_language == 'fr') echo 'selected' ?>>French</option>
                            <option value="he" <?php if ($force_language == 'he') echo 'selected' ?>>Hebrew</option>
                            <option value="hi" <?php if ($force_language == 'hi') echo 'selected' ?>>Hindi</option>
                            <option value="hr" <?php if ($force_language == 'hr') echo 'selected' ?>>Croatian</option>
                            <option value="hu" <?php if ($force_language == 'hu') echo 'selected' ?>>Hungarian</option>
                            <option value="am" <?php if ($force_language == 'am') echo 'selected' ?>>Armenian</option>
                            <option value="id" <?php if ($force_language == 'id') echo 'selected' ?>>Indonesian</option>
                            <option value="it" <?php if ($force_language == 'it') echo 'selected' ?>>Italian</option>
                            <option value="ja" <?php if ($force_language == 'ja') echo 'selected' ?>>Japanese</option>
                            <option value="ka" <?php if ($force_language == 'ka') echo 'selected' ?>>Georgian</option>
                            <option value="ko" <?php if ($force_language == 'ko') echo 'selected' ?>>Korean</option>
                            <option value="mk" <?php if ($force_language == 'mk') echo 'selected' ?>>Macedonian</option>
                            <option value="mn" <?php if ($force_language == 'mn') echo 'selected' ?>>Mongolian</option>
                            <option value="my" <?php if ($force_language == 'my') echo 'selected' ?>>Burmese</option>
                            <option value="nl" <?php if ($force_language == 'nl') echo 'selected' ?>>Dutch</option>
                            <option value="no" <?php if ($force_language == 'no') echo 'selected' ?>>Norwegian</option>
                            <option value="pl" <?php if ($force_language == 'pl') echo 'selected' ?>>Polish</option>
                            <option value="pt" <?php if ($force_language == 'pt') echo 'selected' ?>>Portuguese</option>
                            <option value="ro" <?php if ($force_language == 'ro') echo 'selected' ?>>Romanian</option>
                            <option value="ru" <?php if ($force_language == 'ru') echo 'selected' ?>>Russian</option>
                            <option value="sk" <?php if ($force_language == 'sk') echo 'selected' ?>>Slovak</option>
                            <option value="sl" <?php if ($force_language == 'sl') echo 'selected' ?>>Slovenian</option>
                            <option value="sq" <?php if ($force_language == 'sq') echo 'selected' ?>>Albanian</option>
                            <option value="sr" <?php if ($force_language == 'sr') echo 'selected' ?>>Serbian</option>
                            <option value="su" <?php if ($force_language == 'su') echo 'selected' ?>>Sundanese</option>
                            <option value="sv" <?php if ($force_language == 'sv') echo 'selected' ?>>Swedish</option>
                            <option value="th" <?php if ($force_language == 'th') echo 'selected' ?>>Thai</option>
                            <option value="tr" <?php if ($force_language == 'tr') echo 'selected' ?>>Turkish</option>
                            <option value="uk" <?php if ($force_language == 'uk') echo 'selected' ?>>Ukrainian</option>
                            <option value="vi" <?php if ($force_language == 'vi') echo 'selected' ?>>Vietnamese</option>
                            <option value="zh" <?php if ($force_language == 'zh') echo 'selected' ?>>Chinese</option>
                        </select>
                        <br />
                        <p class="description">
                            Force the chat to ignore the language preferences, and to use always the same language.
                        </p>
                    </td>
                </tr>
            </tbody>
        </table>
        <p class="submit">
            <input type="hidden" name="sb_nonce" id="sb_nonce" value="<?php echo wp_create_nonce('sb-nonce') ?>">
            <input type="submit" class="button-primary" name="sbcloud_submit" value="Save changes" />
        </p>
    </div>
</div>
</form>
<?php }

function sbcloud_script_id_fix($tag, $handle, $src) {
    if ('chat-init' === $handle) {
        $tag = '<script id="chat-init" src="' . esc_url(str_replace(['%3F', '%3D'], ['?', '='], $src)) . '"></script>';
    }
    return $tag;
}

add_action('admin_menu', 'sbcloud_set_admin_menu');
add_action('network_admin_menu', 'sbcloud_set_admin_menu');
add_action('admin_enqueue_scripts', 'sbcloud_enqueue_admin');
add_action('wp_enqueue_scripts', 'sbcloud_enqueue');
add_filter('script_loader_tag', 'sbcloud_script_id_fix', 10, 3 );
add_shortcode('sb-tickets', 'sbcloud_tickets_shortcode');
add_shortcode('sb-articles', 'sbcloud_articles_shortcode');

?>