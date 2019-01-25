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
        function __construct(){
                if(is_admin()) return;
                //load translation
                $plugin_dir = dirname( plugin_basename( __FILE__ ) ) . '/languages/';
                load_plugin_textdomain( 'user-language-switch', false, $plugin_dir );
                
                $this->options = get_option('xs_translate_options');
                
                if(isset($_COOKIE['xs_framework_user_language'])) {
                        //redirects the user based on the browser language. It detectes the browser language and redirect the user to the site in that language.
                        $this->uls_redirect_by_language($_COOKIE['xs_framework_user_language']);
                } else {
                        $this->uls_translate_by_google();
                }
                add_action( 'wp_enqueue_scripts', array($this, 'enqueue_scripts'));  
                add_action( 'save_post', array($this, 'uls_save_association'));
                //add_filter( 'cmb_meta_boxes', array($this, 'uls_language_metaboxes'));
                add_action('wp_ajax_test_response', array($this, 'uls_text_ajax_process_request'));
                add_filter('manage_posts_columns', array($this, 'uls_add_columns'));
                add_filter('manage_pages_columns', array($this, 'uls_add_columns'));
                add_action('manage_posts_custom_column',  array($this, 'uls_show_columns'));
                add_action('manage_pages_custom_column',  array($this, 'uls_show_columns'));
                add_action('pre_get_posts', array($this, 'uls_filter_archive_by_language'));
                add_action('wp_head',  array($this, 'head_reference_translation'));
        }
        
        function enqueue_scripts()
        {
                wp_enqueue_script('xs_translate_scripts', plugins_url('js/functions.js', __FILE__));
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

        /**
        * Add meta boxes to select the language an traductions of a post.
        *
        * @return array
        */
/*        function uls_language_metaboxes( $meta_boxes ) {
        if(isset($_GET['post'])){
        $post_type = get_post_type($_GET['post']);
        }else{
        if(isset($_GET['post_type'])){
                $post_type = $_GET['post_type'];
        }else{
                $post_type = 'post';
        }
        }
        $prefix = 'uls_'; // Prefix for all fields
        $languages = xs_framework::get_available_language();
        $options = array(array('name'=>'Select one option', 'value'=>''));
        $fields = array();
        foreach ( $languages as $lang ){
        $language_name = $lang;

        $new = array('name' => $language_name, 'value' => $lang);
        array_push($options, $new);
        $t1 = get_posts(array(
                'post_type' => $post_type,
                'meta_query' => array(
                array (
                        'key' => 'uls_language',
                        'value'=>array($lang),
                )
                ),
                'posts_per_page' => -1,
        ));
        $t2 = get_posts(array(
                'post_type' => $post_type,
                'meta_query' => array(
                array (
                        'key' => 'uls_language',
                        'compare'=> 'NOT EXISTS',
                )
                ),
                'posts_per_page' => -1,
        ));
        $the_posts = array_merge( $t1, $t2 );

        $posts = array(array('name'=>'Select the translated post', 'value'=>''));
        foreach ($the_posts as $post):
                $post = array('name'=>$post->post_title, 'value'=>$post->ID);
                array_push($posts, $post);
        endforeach;
        wp_reset_query();
        $field = array(
                'name' => 'Select the version in '. $language_name,
                'id' => $prefix.'translation_'.strtolower($lang),
                'type' => 'select',
                'options' => $posts
        );
        array_push($fields, $field);
        }

        array_unshift($fields, array('name' => 'Select a language',
                                        'id' => $prefix . 'language',
                                        'type' => 'select',
                                        'options' => $options));
        //   $fields[] = array(
        //             'name' => 'Select a language',
        //             'id' => $prefix . 'language',
        //             'type' => 'select',
        //             'options' => $options,
        //          );

        $args=array(
        'public'   => true,
        '_builtin' => false
        );
        $output = 'names'; // names or objects, note names is the default
        $operator = 'and'; // 'and' or 'or'
        $custom_post_types = get_post_types($args,$output,$operator);
        $add_to_posts = array('page','post');
        if(!empty($custom_post_types)):
        foreach ($custom_post_types as $custom):
        array_push($add_to_posts, $custom);
        endforeach;
        endif;
        $meta_boxes[] = array(
        'id' => 'language',
        'title' => 'Language',
        'pages' => $add_to_posts, // post type
        'context' => 'normal',
        'priority' => 'high',
        'show_names' => true, // Show field names on the left
        'fields' => $fields
        );
        return $meta_boxes;
        }
        */

        /**
        * Save language associations
        */
        function uls_save_association( $post_id ) {
        //verify post is a revision
        $parent_id = wp_is_post_revision( $post_id );
        if($parent_id === false)
        $parent_id = $post_id;

        $languages = xs_framework::get_available_language();
        $selected_language = isset($_POST['uls_language']) ? $_POST['uls_language'] : null;

        // get array post metas because we need the uls_language and uls_translation
        $this_post_metas = get_post_meta( $parent_id );
        $this_uls_translation = !empty($this_post_metas) ?  isset($this_post_metas['uls_language']) ? 
        'uls_translation_'.strtolower($this_post_metas['uls_language'][0]) : '' : '';
        // if the language of this page change so change the all pages that have this like a traduction
        if ($selected_language != $this_uls_translation) {
        // get post that have this traduction
        $args =  array('post_type' => get_post_type($parent_id),
                        'meta_key' => $this_uls_translation,
                        'meta_value' => $parent_id,
                        'meta_compare' => '=');
        $query = new WP_Query($args);

        // if the query return the post that have assocciate the translation this page,
        // delete the old post_meta uls_translation_#_#
        if ( !empty($query->posts) ) {
        // we need only the IDs of the post query
        foreach ($query->posts as $key) {
                // delete the old post_meta uls_translation_#_#
                delete_post_meta ($key->ID, $this_uls_translation);
                // if selected_language is not empty so add the new traduction
                if (!empty($selected_language)) {
                // get the new post meta if this exits does update the uls_translation
                $page_post_meta = get_post_meta ($key->ID, 'uls_translation_'.strtolower($selected_language), true);
                // ask if the new post_meta uls_translation_#_# exits
                if ( empty($page_post_meta) )
                update_post_meta ( $key->ID, 'uls_translation_'.strtolower($selected_language), $parent_id );
                }
        }
        }
        }
        if (!empty($selected_language)) {
        // if the language change so change the traduction
        foreach ($languages as $lang) {
        $related_post = isset($_POST['uls_translation_'.strtolower($lang)]) ? $_POST['uls_translation_'.strtolower($lang)] : null;
        if( !empty( $related_post ) ) {
                // add traduction to the page that was selected like a translation
                $related_post_meta_translation = get_post_meta( $related_post, 'uls_translation_'.strtolower($selected_language), true );
                if ( empty ( $related_post_meta_translation ) )
                update_post_meta ( $related_post, 'uls_translation_'.strtolower($selected_language), $parent_id );
                // add language to the page that was selected like a tranlation. If the page doesn't has associated a languages
                $related_post_get_language = get_post_meta( $related_post, 'uls_language', true );
                if ( empty ( $related_post_get_language) )
                update_post_meta ( $related_post, 'uls_language', $lang );

        }
        }
        }

        }
        

        /**
        * Remove associations
        */
        function uls_text_ajax_process_request() {
        // first check if data is being sent and that it is the data we want
        $relation_id = $_POST['pid'];
        $lang = $_POST['lang'];
        $post_id = $_POST['post'];
        $meta = $_POST['meta'];
        if ( isset( $_POST["pid"] ) ) {
        // now set our response var equal to that of the POST varif(isset($relation_id)){
        delete_post_meta( $relation_id, 'uls_translation_'.$lang );
        delete_post_meta( $post_id, $meta );
        // send the response back to the front end
        echo $relation_id.'-'.$lang.'-'.$post_id.'-'.$meta;
        die();
        }
        }

        function uls_add_columns($columns) {
        unset($columns['date']);
        $columns['language'] = 'Language';
        $columns['date'] = 'Date';
        return $columns;
        }

        function uls_show_columns($name) {
        global $post;
        $string = "";
        $views = get_post_meta($post->ID, 'uls_language', true);
        $printFlag = '<img src="'.plugins_url("css/blank.gif", __FILE__).'"';
        $printFlag .= 'style="margin-right:5px;"';
        $printFlag .= 'class="flag_16x11 flag-'.Codes::languageCode2CountryCode($views).'"';
        $printFlag .= 'alt="'.$views.'" title="'.$views.'" />';
        echo $printFlag;
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
