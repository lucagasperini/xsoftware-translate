<?php

if(!defined("ABSPATH")) die;

if (!class_exists("xs_translate_options")) :
/**
 * This class control plugin settings.
 */
class xs_translate_options
{
        private $default_options = array(
                'use_google_translate' => TRUE,
                'automatic_redicted_ssl' => TRUE,
                'enable_translation_sidebars' => TRUE,
                'post_type' => array('post', 'page'),
                'native_language' => 'en_GB',
                'frontend_language' => 'en_GB',
                'backend_language' => 'en_GB',
                'menu' => array()
        );
        
        private $options = NULL;
        
        private $languages = NULL;

        /**
        * Save default settings for the plugin.
        */
        function __construct()
        {
                add_action("admin_menu", array($this, "register_menu"));
                add_action("admin_init", array($this, "init_settings"));
                $this->options = get_option('xs_translate_options', $this->default_options);
                $this->languages = xs_framework::get_available_language();
        }
        
        /**
        * Add entries in menu sidebar in back end.
        */
        function register_menu()
        {
                add_submenu_page(
                        'xsoftware', 
                        'Translate', 
                        'Translate', 
                        'manage_options', 
                        'xsoftware_translate', 
                        array($this,'create_settings_page'
                ));
        }
        
        /**
        * Create settings page in back end.
        */
        function create_settings_page()
        {
                echo '<div class="wrap">';
                echo '<h2>User Language Switch</h2>';
                echo '<form method="post" action="options.php" enctype="multipart/form-data">';
                settings_fields( 'xs_translate_options' );
                do_settings_sections( 'uls-settings-page' );
                submit_button( '' );
                echo '</form>';
                echo '</div>';
        }

        /**
        * Register setting fields.
        */
        function init_settings() 
        {
                if ( !current_user_can( 'manage_options' ) )  {
                        wp_die('You do not have sufficient permissions to access this page.');
                }
                //register settings
                register_setting(
                        'xs_translate_options',
                        'xs_translate_options',
                        array($this,'input')
                );

                //create section for registration
                add_settings_section(
                        'xs_general_setting_section',
                        'General Settings',
                        array($this,'show'),
                        'uls-settings-page'
                );
        
        }
        
        function show()
        {
                $tab = xs_framework::create_tabs( array(
                        'href' => '?page=xsoftware_translate',
                        'tabs' => array(
                                'home' => 'Generals',
                                'menu' => 'Menus',
                                'post' => 'Post Types'
                        ),
                        'home' => 'home',
                        'name' => 'main_tab'
                ));
                
                switch($tab) {
                        case 'home':
                                $this->show_general();
                                return;
                        case 'menu':
                                $this->show_menu();
                                return;
                        case 'post':
                                $this->show_post_type();
                                return;
                }
               
        }
        
        function show_general()
        {     
                $options = array(
                        'name' => 'xs_translate_options[frontend_language]',
                        'data' => $this->languages,
                        'selected' => $this->options['frontend_language']
                );
        
                add_settings_field(
                        $options['name'],
                        'Default language',
                        'xs_framework::create_select',
                        'uls-settings-page',
                        'xs_general_setting_section',
                        $options
                );

                $options = array(
                        'name' => 'xs_translate_options[backend_language]',
                        'data' => $this->languages,
                        'selected' => $this->options['backend_language']
                );
                add_settings_field(
                        $options['name'],
                        'Default language for admin side',
                        'xs_framework::create_select',
                        'uls-settings-page',
                        'xs_general_setting_section',
                        $options
                );
                
                $options = array( 
                        'name' => 'xs_translate_options[automatic_redicted_ssl]', 
                        'value' => $this->options['automatic_redicted_ssl'],
                        'compare' => TRUE
                );
                add_settings_field(
                        $options['name'],
                        __('Enable automatic redicted to ssl connection','user-language-switch'),
                        'xs_framework::create_input_checkbox',
                        'uls-settings-page',
                        'xs_general_setting_section',
                        $options
                );
        
                $options = array( 
                        'name' => 'xs_translate_options[use_google_translate]', 
                        'value' => $this->options['use_google_translate'],
                        'compare' => TRUE
                );
                add_settings_field(
                        $options['name'],
                        __('You want use google translate','user-language-switch'),
                        'xs_framework::create_input_checkbox',
                        'uls-settings-page',
                        'xs_general_setting_section',
                        $options
                );

                $options = array( 
                        'name' => 'xs_translate_options[enable_translation_sidebars]', 
                        'value' => $this->options['enable_translation_sidebars'],
                        'compare' => TRUE
                );
                add_settings_field(
                        $options['name'],
                        __('Enable translations for sidebars','user-language-switch'),
                        'xs_framework::create_input_checkbox',
                        'uls-settings-page',
                        'xs_general_setting_section',
                        $options
                );
        }

        /**
        * Validate setting input fields.
        */
        function input($input)
        {
                $current = $this->options;
                
                foreach($input as $key => $value) {
                        if($key == 'post_type')
                                $current[$key] = array_keys($value);
                        else
                                $current[$key] = $value;
                }
                
                return $current;
        }



        /**
        * Create the HTML of a table with languages lits.
        * @param $options array plugin options saved.
        */
        function show_menu() 
        {
                echo '<h2>Translated Menu Navbar</h2>';
                // get the all languages available in the wp
                $languages = xs_framework::get_available_language(array('language' => FALSE, 'english_name' => TRUE));
                $menus = get_terms( 'nav_menu', array( 'hide_empty' => true ) ); // get menues
                foreach ($menus as $menu ) {
                        $data_menu[$menu->slug] = $menu->name;
                }
        
                foreach ($languages as $code => $name ) {
                        $headers[]  = $name;
                        if(isset($this->options['menu'][$code]))
                                $selected = $this->options['menu'][$code];
                        else
                                $selected = reset($data_menu);
                                
                        $data_table[0][] = xs_framework::create_select( array(
                                'name' => 'xs_translate_options[menu]['.$code.']', 
                                'data' => $data_menu, 
                                'selected' => $selected,
                                'return' => true
                        ));
                }
                xs_framework::create_table(array('headers' => $headers, 'data' => $data_table, 'class' => 'widefat fixed'));
        }

        /*
        * create table language filter this is for enable and disable post_type
        */
        function show_post_type() 
        {
                echo '<h2>Filter Post Type</h2>';
                // get the information that actually is in the DB
                $options = isset($this->options['post_type']) ? $this->options['post_type'] : '';
                
                $post_types = get_post_types(['_builtin' => false]); // get all custom post types
                $post_types['post'] = 'post'; // add default post type
                $post_types['page'] = 'page'; // add default post type

                $headers = array('Enable / Disable', 'Post types');
                $data_table = array();
                foreach($post_types as $post_type) {
                        $data_table[$post_type][0] = xs_framework::create_input_checkbox( array(
                                'name' => 'xs_translate_options[post_type]['.$post_type.']',
                                'compare' => in_array($post_type, $options),
                                'return' => TRUE
                                ));
                        $data_table[$post_type][1] = $post_type;
                        
                }
                xs_framework::create_table(array('headers' => $headers, 'data' => $data_table, 'class' => 'widefat fixed'));
        }


}

$xs_translate_options = new xs_translate_options();

endif;
