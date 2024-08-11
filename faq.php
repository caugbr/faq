<?php
/**
 * Plugin name: FAQ
 * Description: Simple 'Frequently Asked Questions' system
 * Version: 1.0
 * Author: Cau Guanabara
 * Author URI: mailto:cauguanabara@gmail.com
 * Text Domain: faq
 * Domain Path: /langs
 * License: Wordpress
 */

if (!defined('ABSPATH')) {
    exit;
}

define('FAQ_PATH', str_replace("\\", "/", dirname(__FILE__)));
define('FAQ_URL', str_replace("\\", "/", plugin_dir_url(__FILE__)));

class FAQ {
    public function __construct() {
        global $wp_helper;
        global $require_zip_plugin;
        if ($require_zip_plugin) {
            $require_zip_plugin->require(
                'FAQ', 
                'WP Helper', 
                'https://github.com/caugbr/wp-helper/archive/refs/heads/main.zip', 
                'wp-helper/wp-helper.php'
            );
        }
        if ($wp_helper) {
            $wp_helper->add_textdomain('faq', dirname(plugin_basename(__FILE__)) . '/langs');
            $wp_helper->add_script('faq-js', FAQ_URL . 'assets/faq.js');
            $wp_helper->add_style('faq-css', FAQ_URL . 'assets/faq.css');
            $wp_helper->disable_gutenberg('faq');
            $wp_helper->set_unique_term('faq', 'faq_section');
    
            add_action('init', [$this, 'create_post_type']);
            add_shortcode('faq', [$this, 'faq_shortcode']);
            add_action('save_post', [$this, 'save_metabox'], 10, 2);
            add_action('add_meta_boxes', [$this, 'add_metabox']);
        }
    }

    public function create_post_type() {
        global $wp_helper;
        $wp_helper->add_taxonomy(
            'faq_section', 
            __('FAQ section', 'faq'), 
            __('FAQ sections', 'faq'), 
            ['faq'], 
            ["hierarchical" => true]
        );
        $wp_helper->add_post_type(
            'faq', 
            __('FAQ Item', 'faq'), 
            __('FAQ Items', 'faq'), 
            [
                'hierarchical' => false, 'menu_icon' => 'dashicons-editor-help', 
                'supports' => ['title', 'editor', 'thumbnail'],
                'show_in_menu' => true
            ]
        );
    }

    public function faq_shortcode($atts) {
        $atts = shortcode_atts(['section' => 'all', 'style' => 'flat'], $atts);
        $args = [
            "post_type" => "faq",
            "post_status" => "publish",
            "numberposts" => -1
        ];
        if ($atts['section'] != 'all') {
            $args['tax_query'] = [
                [
                    'taxonomy' => 'faq_section',
                    'field' => 'name',
                    'terms' => $atts['section']
                ]
            ];
        }
        $posts = get_posts($args);
        $posts = array_map(function($post) {
            $order = get_post_meta($post->ID, 'faq_order', true);
            $post->order = $order ? $order : 1000;
            return $post;
        }, $posts);
        usort($posts, function($e1, $e2) {
            return $e1->order > $e2->order;
        });

        $questions = [];
        foreach ($posts as $post) {
            
            $item = [
                "id" => "tab_{$post->ID}",
                "question" => $post->post_title,
                "answer" => $post->post_content
            ];
            $section = get_the_terms($post, 'faq_section');
            if (is_wp_error($section) || !$section) {
                $questions["default"] = $questions["default"] ?? [];
                $questions["default"][] = $item;
            } else {
                $sec = $section[0]->name;
                $questions[$sec] = $questions[$sec] ?? [];
                $questions[$sec][] = $item;
            }
        }
        $sections = array_keys($questions);
        ob_start();
        if ($atts['style'] == 'sections') {
            ?>
            <div class="faq tabs" data-tab="<?php print $sections[0]; ?>">
                <div class="tab-links">
                    <?php foreach ($sections as $sec) { $id = sanitize_title($sec); ?>
                    <a class="tab" href="#" data-tab="<?php print $id; ?>">
                        <?php print $sec; ?>
                    </a>
                    <?php } ?>
                </div>
                <div class="tab-stage">
                    <?php foreach ($sections as $sec) { $id = sanitize_title($sec); ?>
                        <div class="tab-content" data-tab="<?php print $id; ?>">
                        <?php foreach ($questions[$sec] as $item) { ?>
                            <div class="faq-item">
                                <div class="question">
                                    <?php print $item['question']; ?>
                                </div>
                                <div class="answer">
                                    <?php print $item['answer']; ?>
                                </div>
                            </div>
                        <?php } ?>
                        </div>
                    <?php } ?>
                </div>
            </div>
            <?php
            $this->tabs_css($sections);
        } else {
            ?>
            <div class="faq">
                <?php foreach (array_keys($questions) as $sec) { ?>
                    <?php foreach ($questions[$sec] as $item) { ?>
                        <div class="faq-item">
                            <div class="question">
                                <?php print $item['question']; ?>
                            </div>
                            <div class="answer">
                                <?php print $item['answer']; ?>
                            </div>
                        </div>
                    <?php } ?>
                <?php } ?>
            </div>
            <?php
        }
        return ob_get_clean();
    }

    /**
     * Add metaboxes.
     *
     * @since    1.0.0
     */
    public function add_metabox() {
        add_meta_box(
            'faq_box',
            __('Questions order', 'faq'),
            [$this, 'metabox_html'],
            'faq',
            'side'
        );
    }

    /**
     * Add metaboxes.
     *
     * @since    1.0.0
     */
    public function metabox_html($post) {
        $order = get_post_meta($post->ID, 'faq_order', true);
        ?>
        <label for="faq_order"><?php _e('Order', 'faq'); ?></label>
        <input type="number" name="faq_order" id="faq_order" value="<?php print $order; ?>">
        <?php
    }

    /**
     * Save data for the event metabox.
     *
     * @since    1.0.0
     */
    public function save_metabox($post_id, $post) {
        if ($post->post_type == 'faq') {
            if (!empty($_POST['faq_order'])) {
                update_post_meta($post_id, 'faq_order', $_POST['faq_order']);
            }
        }
    }

    private function tabs_css($ids) {
        ?>
        <style>
            <?php foreach ($ids as $id) { $id = sanitize_title($id); ?>
            .tabs[data-tab="<?php print $id; ?>"] .tab-links a.tab[data-tab="<?php print $id; ?>"],
            <?php } ?>
            .tabs[data-tab="settings"] .tab-links a.tab[data-tab="settings"] {
                background-color: #efefef;
                border-bottom-color: #efefef;
                font-weight: bold;
                cursor: default;
                padding-bottom: 0.5rem;
            }
            <?php foreach ($ids as $id) { $id = sanitize_title($id); ?>
            .tabs[data-tab="<?php print $id; ?>"] .tab-stage .tab-content[data-tab="<?php print $id; ?>"],
            <?php } ?>
            .tabs[data-tab="settings"] .tab-stage .tab-content[data-tab="settings"] {
                display: block;
            }
        </style>
        <?php
    }
}

global $faq;
$faq = new FAQ();