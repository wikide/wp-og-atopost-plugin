<?php
/*
Plugin Name: Open Graph Plugin
Description: Простой плагин для добавления OG тегов и авторепостинга постов
Version: 1.0
Author: kc
*/

function og_plugin_meta_load_og() {
    $og_meta = '';
    if ( is_page() || is_single() ) {
        $post = get_post(get_queried_object_id());
        $tags = get_tags_to_message($post->ID);
        if (!empty($text)) {
            $tags .= PHP_EOL.$text.PHP_EOL;
        }

        if (has_post_thumbnail($post->ID)) {
            $image = wp_get_attachment_image_src( get_post_thumbnail_id( $post->ID ), 'single-post-thumbnail' );
        }

        $text = $tags . preg_replace('/[\n\r]+/s', "\n\n", strip_tags($post->post_content));
        if (strlen($text) > 500) {
            $text = mb_substr($text, 0, 500) . '...';
        }
        $og_meta .= '<meta property="og:title" content="' . $post->post_title. '" />' . PHP_EOL;
        $og_meta .= '<meta property="og:description" content="' . $text . '" />' . PHP_EOL;
        $og_meta .= '<meta property="og:url" content="' . get_permalink($post->ID) . '" />' . PHP_EOL;
        $og_meta .= '<meta property="og:type" content="URL" />' . PHP_EOL;
        $og_meta .= '<meta property="og:site_name" content="' . get_bloginfo('name') . '" />' . PHP_EOL;
        $og_meta .= '<meta property="og:locale" content="' . get_bloginfo('language') . '" />' . PHP_EOL;
        if (isset($image[0])) {
            $og_meta .= '<meta property="og:image" content="' . $image[0] . '" />' . PHP_EOL;
        }
    }
    echo $og_meta;
}

function og_plugin_add_submenu_page() {
    add_submenu_page(
        'options-general.php',
        'Настройки автопостинга',
        'Open Graph Plugin',
        'manage_options',
        'my-plugin-settings',
        'og_plugin_settings_html'
    );
}

function og_plugin_clear_cache_link_vk($url, $token)
{
    $api_url = 'https://api.vk.com/method/utils.resolveScreenName';
    $params = [
        'screen_name' => $url,
        'access_token' => $token,
        'v' => '5.131'
    ];
    $response = file_get_contents($api_url . '?' . http_build_query($params));

    return json_decode($response, true);
}

function og_plugin_settings_html() {
    if (!current_user_can('manage_options')) {
        return;
    }

    if (isset($_POST['og_plugin_option_clear_cache_vk'])) {
        $response = og_plugin_clear_cache_link_vk(
            $_POST['og_plugin_clear_vk_link'],
            get_option('og_plugin_option_token_vk')
        );
        if (isset($response['error'])) {
            $message = sprintf('<div class="notice notice-error"><p>При обновлении кэша VK произошла ошибка %s</p></div>', $response['error']['error_msg']);
        } else {
            $message = '<div class="notice notice-success"><p>Кэш для ссылки обновлен успешно!</p></div>';
        }
        echo $message;
    }

    if (isset($_POST['og_plugin_option_vk'])) {
        update_option('og_plugin_option_token_vk', sanitize_text_field($_POST['og_plugin_option_token_vk']));
        update_option('og_plugin_option_group_vk', sanitize_text_field($_POST['og_plugin_option_group_vk']));
        echo '<div class="notice notice-success"><p>Настройки сохранены!</p></div>';
    }

    $og_plugin_option_token_vk = get_option('og_plugin_option_token_vk', get_option('og_plugin_option_token_user_vk'));
    $og_plugin_option_group_vk = get_option('og_plugin_option_group_vk', get_option('og_plugin_option_group_vk'));

    ?>
    <div class="wrap">
        <h1>Настройки автопостинга</h1>
        <form method="post" action="">
            <input type="hidden" name="og_plugin_option_vk" value="1" />
            <?php wp_nonce_field('og_plugin_settings_action', 'og_plugin_nonce'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="og_plugin_option_token_vk">Токен VK</label></th>
                    <td>
                        <input name="og_plugin_option_token_vk" type="text" id="og_plugin_option_token_vk" value="<?php echo esc_attr($og_plugin_option_token_vk); ?>" class="regular-text">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="og_plugin_option_group_vk">Группа VK</label></th>
                    <td>
                        <input name="og_plugin_option_group_vk" type="text" id="og_plugin_option_group_vk" value="<?php echo esc_attr($og_plugin_option_group_vk); ?>" class="regular-text">
                    </td>
                </tr>
            </table>
            <?php submit_button('Сохранить'); ?>
        </form>
    </div>
    <div>
        <h2>Очистка OG кэша в VK</h2>
        <form method="post" action="">
            <input type="hidden" name="og_plugin_option_clear_cache_vk" value="1" />
            <?php wp_nonce_field('og_plugin_settings_action', 'og_plugin_nonce'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="og_plugin_clear_vk_link">Ссылка</label></th>
                    <td>
                        <input name="og_plugin_clear_vk_link" type="text" id="og_plugin_clear_vk_link" value="" class="regular-text">
                        <p class="description">Ссылка, VK кэш для которой нужно очистить</p>
                    </td>
                </tr>
            </table>
            <?php submit_button('Очистить'); ?>
        </form>
    </div>
    <?php
}

function og_plugin_vk_post($post_id)
{
    $post = get_post($post_id);
    $text = get_tags_to_message($post->ID);
    if (!empty($text)) {
        $text = PHP_EOL.$text.PHP_EOL;
    }
    $text .= preg_replace('/[\n\r]+/s', "\n\n", strip_tags($post->post_content));
    $text = strip_tags($text);
    if (strlen($text) > 500) {
        $text = mb_substr($text, 0, 500) . '...';
    }
    $text .= PHP_EOL.get_permalink($post->ID).PHP_EOL;
    $data = [
        'message' => $text,
        'link' => get_permalink($post->ID)
    ];

    $url = 'https://api.vk.com/method/wall.post';
    $params = [
        'owner_id' => get_option('og_plugin_option_group_vk'),
        'message' => $data['message'],
        'attachments' =>  $data['link'],
        'access_token' => get_option('og_plugin_option_token_vk'),
        'from_group' => 1,
        'v' => '5.131'
    ];
    curl_sender_exec($url, $params);
}

// Добавляет на страницу OG теги
add_action( 'wp_head', 'og_plugin_meta_load_og' );

// Добавляет подпункт меню в настройки WP
add_action('admin_menu', 'og_plugin_add_submenu_page');

// Автопост записи в группу VK при публикации записи
add_action('auto-draft_to_publish', 'og_plugin_vk_post', 20, 1);
add_action('future_to_publish', 'og_plugin_vk_post', 20, 1);
add_action('draft_to_publish', 'og_plugin_vk_post', 20, 1);