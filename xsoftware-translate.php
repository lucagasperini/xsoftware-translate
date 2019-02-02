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
        * This function intis the plugin. It check the language in the URL, the language in the browser and the language in the user preferences to 
        * redirect the user to the correct page with translations.
        *
        * 1. This function first check the language configured in the user browser and redirects the user to the correct language version of the website.
        */
        function __construct()
        {
                $this->options = get_option('xs_translate_options');
                
                add_action('add_meta_boxes', array($this, 'metaboxes'));
                add_action('save_post', array($this,'metaboxes_save'));
                add_filter('manage_posts_columns', array($this, 'add_columns'));
                add_filter('manage_pages_columns', array($this, 'add_columns'));
                add_action('manage_posts_custom_column',  array($this, 'show_columns'), 10, 2);
                add_action('manage_pages_custom_column',  array($this, 'show_columns'), 10, 2);
                add_action('admin_enqueue_scripts', array($this, 'enqueue_styles'));
                add_filter('locale', array($this, 'set_locale'));
                
                if(is_admin()) return;
                
                if(isset($_COOKIE['xs_framework_user_language'])) {
                        //redirects the user based on the browser language. It detectes the browser language and redirect the user to the site in that language.
                        $this->redirect();
                } else {
                        $this->translate_by_google();
                }
                
                add_action('pre_get_posts', array($this, 'filter_archive'));
                add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));
                add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
                add_filter('wp_nav_menu_items', array($this, 'select_correct_menu_language'), 10, 2);
                
        }
        
        function set_locale() 
        {
                if ( is_admin() ) 
                        return $this->options['backend_language'];
                else
                        return $this->options['frontend_language'];
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

                $languages = xs_framework::get_available_language();
                
                $data[0][0] = 'Post Language:';
                $data[0][1] = xs_framework::create_select( array(
                        'name' => 'xs_translate_language', 
                        'selected' => $lang, 
                        'data' => $languages, 
                        'return' => true,
                        'default' => 'Select a Language'
                ));
                
                if($this->options['native_language'] !== $lang) {
                        $data[1][0] = 'Native Post:';
                        $data[1][1] = xs_framework::create_select( array(
                                'name' => 'xs_translate_native_post', 
                                'selected' => $native,
                                'data' => $post_list, 
                                'return' => true,
                                'default' => 'Select a Post'
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
                if(isset($this->options['menu'][$user_lang]))
                        $menu =  $this->options['menu'][$user_lang];
                else
                        $menu = $this->options['menu'][$this->options['native_language']];
                
                $items = $this->print_select_menu_language($items);
                
                if($menu_name == $menu)
                        return $items;
                else
                        return wp_nav_menu( array( 'menu' => $menu, 'items_wrap' => '%3$s' , 'container' => false, 'echo' => false) );
        }
        
        function print_select_menu_language($items)
        {
                $user_lang = xs_framework::get_user_language();
                $languages = xs_framework::get_available_language(array('iso' => TRUE));
                $offset = '';
                $offset .= '<li>
                <a href=""><span class="flag-icon flag-icon-'.$languages[$user_lang]['iso'].'"></span></a><ul class="sub-menu">';
                
                
                
                foreach($languages as $code => $prop)
                        $offset .= '<li><a onclick="xs_translate_select_language(\'' . $code . '\');" href="">
                        <span class="flag-icon flag-icon-'.$prop['iso'].'"></span>    '.$prop['english_name'].
                        '</a></li>';
                        
                $offset .= '</ul></li>';
                return $items . $offset;
        }

        function translate_by_google()
        {
                if($this->options['use_google_translate'] == false)
                        return;
                        
                wp_enqueue_script('xs_google_translate_script', "https://translate.google.com/translate_a/element.js?cb=googleTranslateElementInit");
                
        }

        /**
        * This function check if the redirection based on the browser language is enabled. If it is add cookies to manage language
        *
        * @return mixed it returns false if the redirection is not possible, due to some of the restriction mentioned above. Otherwise, it just redirects 
        * the user.
        */
        function redirect()
        {
                $url = xs_framework::get_browser_url();
                $url = strtok($url, '?'); //remove query string if there are

                $redirectUrl = $this->get_url_translate($url);
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
        *
        * @return mixed it returns id of translation post as an integer or false if translation doesn't exist.
        */
        function get_post_id_translate($post_id)
        {
                $user_language = xs_framework::get_user_language();

                //get the translation of the post
                $post_language = $this->get_post_meta_language($post_id);

                //if the language of the post is the same language of the translation
                if($post_language == $user_language)
                        return $post_id;
                
                $values = get_post_custom( $post_id );
                $lang = isset( $values['xs_translate_language'][0] ) ? $values['xs_translate_language'][0] : '';
                $native = isset( $values['xs_translate_native_post'][0] ) ? $values['xs_translate_native_post'][0] : '';
                
                if(!empty($native))
                        $native_post = get_post($native);
                else
                        $native_post = get_post($post_id);
                
                if($user_language === $this->options['native_language']) {
                        return $native_post->ID;
                }
                        
                if($post_language !== $user_language)
                {
                        $lang_query = array(
                                array(
                                        'key' => 'xs_translate_language',
                                        'value' => $user_language,
                                        'compare' => '='
                                )
                        );
                        $native_query = array( 
                                array (
                                        'key' => 'xs_translate_native_post',
                                        'value' => $native_post->ID,
                                        'compare'=> '='
                                )
                        );
                        
                        $meta_query = array(
                                'relation' => 'AND',
                                $lang_query,
                                $native_query
                        );
                        
                        $the_posts = get_posts(  array(
                                'post_type' => get_post_type($native_post->ID),
                                'meta_query' => $meta_query,
                                'posts_per_page' => -1,
                        ));
                        if(isset($the_posts) && count($the_posts) == 1)
                                return $the_posts[0]->ID;
                        else
                                return FALSE;
                }
                
                return FALSE;
        }

        /**
        * Get the page of traslated url.
        */
        function get_url_translate($url)
        {
                $offset = NULL;
                if(empty($url))
                        return $offset;
                        
                $page_id = url_to_postid($url);
                if($page_id == 0)
                        return $url;
                $id = $this->get_post_id_translate($page_id);
                $offset = get_permalink($id);
                        
                return $offset;
        }

        /**
        * Get the language of a post.
        *
        * @param $id integer id of the post.
        *
        * @return string the code of the language or an empty string if the post doesn't have a language.
        */
        function get_post_meta_language($id)
        {
                $lang = get_post_meta($id, 'xs_translate_language', true);

                return $lang;
        }

        function get_post_meta_native($id)
        {
                $native = get_post_meta($id, 'xs_translate_native_post', true);

                return $native;
        }


        function add_columns($columns) 
        {
                $columns['language'] = 'Language';
                $columns['native'] = 'Native';
                return $columns;
        }

        function show_columns($column, $post_id) 
        {
                switch ( $column ) {
                        case 'language':
                                $code = $this->get_post_meta_language($post_id);
                                $iso = xs_framework::get_available_language(array('english_name' => FALSE, 'iso' => TRUE));
                                if(isset($iso[$code]))
                                        echo '<span class="flag-icon flag-icon-'.$iso[$code].'"></span>';
                                        
                                return;
                        case 'native': 
                                $native = intval($this->get_post_meta_native($post_id));
                                if($native === 0)
                                        return;
                                $native_post = get_post($native);

                                if(isset($native_post->post_title) && $native !== $post_id)
                                        echo '<span>'.$native_post->post_title.'</span>';
                                return;
                }
        }
        /**
        * Add queries to filter posts by languages. If a post doesn't have language.
        *
        * @param $query object WordPress query object where language query will be added.
        */
        function meta_query(&$query)
        {
                //get language displayed
                $language_displayed = xs_framework::get_user_language();

                $language_query = array(
                                array(
                                'key' => 'xs_translate_language',
                                'value' => $language_displayed,
                                'compare' => '='
                                ),
                        );

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
        }

        /**
        * Filter posts in archives by language.
        *
        * @param $query object WordPress query object used to create the archive of posts.
        */
        
        function filter_archive($query)
        {
                //this flag indicates if we should filter posts by language
                $modify_query = !$query->is_page() && !$query->is_single() && !$query->is_preview() && 'nav_menu_item' != $query->get('post_type');

                //filter posts by language loaded in the page
                if($modify_query){
                        $this->meta_query($query);
                }
        }
}
endif;
