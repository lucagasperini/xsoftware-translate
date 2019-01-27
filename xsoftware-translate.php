<?php
/*
Plugin Name: XSoftware Translate
Description: Build a multilingual website. Linking translations of content and allow visitors to browse your website in different languages.
Version: 1.7
Author: XSoftware
Author URI: http://www.xsoftware.it
License: GPL3
*/

if(!defined("ABSPATH")) exit;

if (!class_exists("xs_translate")) :

add_action( 'init', 'load_xs_translate');

function load_xs_translate()
{
        $xs_translate_plugin = new xs_translate();
}

include 'xsoftware-translate-options.php';

class xs_translate
{
        private $options = NULL;
        /**
        * This function intis the plugin. It check the language in the URL, the language in the browser and the language in the user preferences to redirect the 
user to the correct page with translations.
        *
        * 1. This function first check the language configured in the user browser and redirects the user to the correct language version of the website.
        */
        function __construct()
        {
                add_action('add_meta_boxes', array($this, 'metaboxes'));
                add_action('save_post', array($this,'metaboxes_save'));
                add_filter('manage_posts_columns', array($this, 'add_columns'));
                add_filter('manage_pages_columns', array($this, 'add_columns'));
                add_action('manage_posts_custom_column',  array($this, 'show_columns'));
                add_action('manage_pages_custom_column',  array($this, 'show_columns'));
                add_action('admin_enqueue_scripts', array($this, 'enqueue_styles'));
                add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));
                add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
                //add_filter('wp_nav_menu_items', array($this, 'select_correct_menu_language'));
                
                $this->options = get_option('xs_translate_options');
                
                if(is_admin()) return;
                //load translation
                $plugin_dir = dirname( plugin_basename( __FILE__ ) ) . '/languages/';
                load_plugin_textdomain( 'user-language-switch', false, $plugin_dir );
                
                
                if(isset($_COOKIE['xs_framework_user_language'])) {
                        //redirects the user based on the browser language. It detectes the browser language and redirect the user to the site in that language.
                        $this->uls_redirect_by_language($_COOKIE['xs_framework_user_language']);
                } else {
                        $this->uls_translate_by_google();
                }
                add_action('pre_get_posts', array($this, 'uls_filter_archive_by_language'));
                add_action('wp_head',  array($this, 'head_reference_translation'));
                
        }
        
        function metaboxes()
        {
                add_meta_box( 'xs_translate_metaboxes', 'XSoftware Translate', array($this,'metaboxes_print'), array('post', 'page'),'advanced','high');
        }
        
        function metaboxes_print()
        {
                global $post;
                $values = get_post_custom( $post->ID );
                $lang = isset( $values['xs_translate_language'][0] ) ? $values['xs_translate_language'][0] : '';
                $native = isset( $values['xs_translate_native_post'][0] ) ? intval($values['xs_translate_native_post'][0]) : '';

                $the_posts = get_posts(  array(
                        'post_type' => get_post_type($post->ID),
                        'meta_query' => array( array (
                                        'key' => 'xs_translate_language',
                                        'value' => $this->options['native_language'],
                                        'compare'=> '='
                                        )
                        ),
                        'posts_per_page' => -1,
                ));
               
                $post_list = array();
                foreach ($the_posts as $post) {
                        $post_list[$post->ID] = $post->post_title;
                }
                array_unshift($post_list, 'Select a Post');

                $languages = xs_framework::get_available_language();
                array_unshift($languages, 'Select a Language');
                
                $data[0][0] = 'Post Language:';
                $data[0][1] = xs_framework::create_select( array(
                        'name' => 'xs_translate_language', 
                        'selected' => $lang, 
                        'data' => $languages, 
                        'return' => true
                ));
                
                if($this->options['native_language'] !== $lang) {
                        $data[1][0] = 'Native Post:';
                        $data[1][1] = xs_framework::create_select( array(
                                'name' => 'xs_translate_native_post', 
                                'selected' => $native,
                                'data' => $post_list, 
                                'return' => true
                        ));
                }
                
                xs_framework::create_table(array('data' => $data)); //FIXME: 100% width
        }
        
        function metaboxes_save($post_id)
        {
                if( isset( $_POST['xs_translate_language'] ) )
                        update_post_meta( $post_id, 'xs_translate_language', $_POST['xs_translate_language'] );
                if( isset( $_POST['xs_translate_native_post'] ) )
                        update_post_meta( $post_id, 'xs_translate_native_post', $_POST['xs_translate_native_post'] );
        }
        
        function enqueue_styles()
        {
                wp_enqueue_style('xs_translate_style_flag', plugins_url('flag-icon-css/css/flag-icon.min.css', __FILE__));
                wp_enqueue_style('xs_translate_style', plugins_url('css/style.css', __FILE__));
        }
        
        function enqueue_scripts()
        {
                wp_enqueue_script('xs_translate_scripts', plugins_url('js/functions.js', __FILE__));
        }
        
        function select_correct_menu_language($items, $args) 
        {
                $menu_name = $args->menu;
                $user_lang = xs_framework::get_user_language();
                $menu = isset($this->options['menu'][$user_lang]) ? $this->options['menu'][$user_lang] : '';
                
                $items = $this->print_select_menu_language($items);
                
                if($menu_name == $menu)
                        return $items;
                else
                        return wp_nav_menu( array( 'menu' => $menu, 'items_wrap' => '%3$s' , 'container' => false, 'echo' => false) );
        }
        
        function print_select_menu_language($items)
        {
                $offset = '';
                $offset .= '<li>';
                $offset .= '<select class="languagepicker" id="xs_translate_select_language" onchange="xs_translate_select_language()">';
                $languages = xs_framework::get_available_language();
                $current_lang = xs_framework::get_user_language();
                foreach($languages as $code => $name)
                        if($current_lang != $code)
                                $offset .= '<option value="'.$code.'">'.$name.'</option>';
                        else
                                $offset .= '<option value="'.$code.'" selected>'.$name.'</option>';
                        
                $offset .= '</select></li>';
                return $items . $offset;
        }


        /**
        * Return the permalink of the translation link of a post.
        *
        * @param $post_id integer id of post.
        * @param $language string language of translation. If it is null or invalid, current language loaded in the page is used.
        *
        * @return string the permalink of the translation link of a post.
        */
        function uls_get_permalink($post_id, $language = null){
        $translation_id = $this->uls_get_post_translation_id($post_id, $language);
        return empty($translation_id) ? get_permalink($post_id) : get_permalink($translation_id);
        }


        /**
        * This function gets the language from the URL, if there is no language in the URL, then it gets language from settings saved by the user in the 
back-end side. 
        If there isn't a language in the URL or user hasn't set it, then default language of the website is used.
        *
        * @param $only_lang boolean if it is true, then it returns the 2 letters of the language. It is the language code without location.
        *
        * @return string language code. If there isn't a language in the URL or user hasn't set it, then default language is returned.
        */

        function uls_translate_by_google()
        {
                $options = $this->options;
                if($options['use_google_translate'] == false)
                        return;
                        
                wp_enqueue_script('uls_google_translate_script', "https://translate.google.com/translate_a/element.js?cb=googleTranslateElementInit");
                
        }

        /**
        * This function check if the redirection based on the browser language is enabled. If it is add cookies to manage language
        *
        * @return mixed it returns false if the redirection is not possible, due to some of the restriction mentioned above. Otherwise, it just redirects the 
user.
        */
        function uls_redirect_by_language($language)
        {
                $url = xs_framework::get_browser_url();
                $url = strtok($url, '?'); //remove query string if there are

                $redirectUrl = $this->uls_get_url_translated($url, $language);
                if($redirectUrl == false)
                        return NULL;
                
                if ($url != $redirectUrl) {
                        wp_redirect($redirectUrl);
                        exit;
                }
                return NULL;
        }

        /**
        * Return the id of the translation of a post.
        *
        * @param $post_id integer id of post to translate.
        * @param $language string language of translation. If it is null or invalid, current language loaded in the page is used.
        *
        * @return mixed it returns id of translation post as an integer or false if translation doesn't exist.
        */
        function uls_get_post_translation_id($post_id, $language = null)
        {
                $language = xs_framework::get_user_language();

                //get the translation of the post
                $post_language = $this->uls_get_post_language($post_id);

                //if the language of the post is the same language of the translation
                if($post_language == $language)
                return $post_id;

                //get the translation
                $translation = get_post_meta($post_id, 'uls_translation_' . $language, true);
                if("" == $translation)
                        $translation = get_post_meta($post_id, 'uls_translation_' . strtolower($language), true);

                return empty($translation) ? false : $translation;
        }

        /**
        * Get the page of traslated url.
        */
        function uls_get_url_translated($url, $language = NULL)
        {
                $offset = NULL;
                if(empty($url))
                        return $offset;
                        
                $page_id = url_to_postid($url);
                if($page_id == 0)
                        return $url;
                $translation_id = $this->uls_get_post_translation_id($page_id, $language);
                $offset = get_permalink($translation_id);
                        
                return $offset;
        }

        /**
        * Get the language of a post.
        *
        * @param $id integer id of the post.
        *
        * @return string the code of the language or an empty string if the post doesn't have a language.
        */
        function uls_get_post_language($id)
        {
                $postLanguage = get_post_meta($id, 'uls_language', true);
                if("" == $postLanguage) 
                        return "";

                //format the language code
                $p = strpos($postLanguage, "_");
                if($p !== false){
                        $postLanguage = substr($postLanguage, 0, $p) . strtoupper(substr($postLanguage, $p));
                }

                return $postLanguage;
        }


        function add_columns($columns) 
        {
                $columns['language'] = 'Language';
                return $columns;
        }

        function show_columns($name) 
        {
                global $post;
                $code = get_post_meta($post->ID, 'xs_translate_language', true);
                $iso = xs_framework::get_available_language(array('english_name' => FALSE, 'iso' => TRUE));
                if(isset($iso[$code]))
                        echo '<span class="flag-icon flag-icon-'.$iso[$code].'"></span>';
        }

        /**
        * Add queries to filter posts by languages. If a post doesn't have language.
        *
        * @param $query object WordPress query object where language query will be added.
        */
        function uls_add_language_meta_query(&$query){
        //set permalink convertion to true, to get real URLs
        global $uls_permalink_convertion;
        $uls_permalink_convertion = true;

        //get language displayed
        $language_displayed = xs_framework::get_user_language();

        //get the default language of the website
        $default_website_language = xs_framework::get_option('frontend_language');

        //if the language displayed is the same to the default language, then it includes posts without language
        $language_query = null;
        if($language_displayed == $default_website_language){
        //build query for languages
        $language_query = array(
        'relation' => 'OR',
        array(
                'key' => 'uls_language',
                'value' => 'bug #23268',
                'compare' => 'NOT EXISTS'
        ),
        array(
                'key' => 'uls_language',
                'value' => $language_displayed,
                'compare' => '='
        )
        );
        }
        //filter posts by language displayed
        else{
        $language_query = array(
        array(
                'key' => 'uls_language',
                'value' => $language_displayed,
                'compare' => '='
        ),
        );
        }

        //get current meta query
        $meta_query = $query->get('meta_query');

        //add language query to the meta query
        if(empty($meta_query))
        $meta_query = $language_query;
        else
        $meta_query = array(
        'relation' => 'AND',
        $language_query,
        $meta_query
        );

        //set the new meta query
        $query->set('meta_query', $meta_query);

        //reset flag
        $uls_permalink_convertion = false;
        }

        /**
        * Filter posts in archives by language.
        *
        * @param $query object WordPress query object used to create the archive of posts.
        */
        
        function uls_filter_archive_by_language($query){
        //check if it in the admin dashboard
        if(is_admin())
        return;

        // get values configuration uls_settings to applic filter translation to the post_types
        // if the information in languages_filter_disable are true apply filter
        $settings = $this->options;

        // Check post type in query, if post type is empty , Wordpress uses 'post' by default
        $postType = 'post';
        if(property_exists($query, 'query') && array_key_exists('post_type', $query->query)) {
        $postType = $query->query['post_type'];
        }

        if (is_array($settings)) {
        if (array_key_exists('languages_filter_enable', $settings)) {
        if (is_string($postType) || is_numeric($postType)) {
                if (is_array($settings['languages_filter_enable'])) {
                if (!array_key_exists($postType, $settings['languages_filter_enable'])) {
                return;
                }
                }
        }
        }
        }

        //this flag indicates if we should filter posts by language
        $modify_query = !$query->is_page() && !$query->is_single() && !$query->is_preview();

        //if it is displaying the home page and the home page is the list of posts
        //$modify_query = 'posts' == get_option( 'show_on_front' ) && is_front_page();

        //if it is an archive
        //$modify_query = $modify_query || $query->is_archive() || $query->is_post_type_archive();

        //if this is not a query for a menu(menus are handled by the plugin too)
        $modify_query = $modify_query && 'nav_menu_item' != $query->get('post_type');

        //filter posts by language loaded in the page
        if($modify_query){
        $this->uls_add_language_meta_query($query);
        }
        }

        
        function head_reference_translation() {

        //get the id of the current page
        $url =(isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"]=="on") ? "https://" : "http://";
        $url .= $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"];
        $post_id = url_to_postid($url);

        // get all available languages
        $languages = xs_framework::get_available_language();
        $curren_code = xs_framework::get_user_language(); // get current language
        // delete the current language site
        $code_value = array_search($curren_code, $languages);
        unset($languages[$code_value]);

        // build the url to be tranlation
        $url = '';
        // get url from where it's using
        if ( is_home() )
        $url = get_home_url(); // get home url
        else if ( is_archive() || is_search() || is_author() || is_category() || is_tag() || is_date() )
        $url = xs_framework::get_browser_url(); // get browser url

        // if exits the url so, translate this
        if (!empty($url) ) {
        // use all available languages and get the url translation
        foreach ($languages as $language => $code) {
        $translation_url = $this->uls_get_url_translated($url, $code);
        echo '<link rel="alternate" hreflang="'.substr($code, 0, 2).'" href="'.$translation_url.'" />';
        }
        }

        // build url to the home
        if ( !empty($post_id) && empty($url) ) {

        // change the filter
        global $uls_permalink_convertion;
        $uls_permalink_convertion = false;

        // use all available languages and get the url translation
        foreach ($languages as $language => $code) {
        // get the post_id translation if the current page has translation
        $translation_id = $this->uls_get_post_translation_id($post_id, $code);
        if ( !empty($translation_id) ) {
                $translation_url = $this->uls_get_url_translated(get_permalink($translation_id), $code);
                echo '<link rel="alternate" hreflang="'.substr($code, 0, 2).'" href="'.$translation_url.'" />';
        }
        }
        // leave the global car like it was before
        $uls_permalink_convertion = true;
        }
        }
}
endif;
