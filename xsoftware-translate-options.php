<?php

if(!defined("ABSPATH")) exit;

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
                'languages_filter_enable' => array('post' => 'post', 'page' => 'page'),
                'native_language' => 'en_GB',
                'frontend_language' => 'en_GB',
                'backend_language' => 'en_GB',
        );
        
        private $options = NULL;

        /**
        * Save default settings for the plugin.
        */
        function __construct()
        {
                add_action("admin_menu", array($this, "register_menu"));
                add_action("admin_init", array($this, "init_settings"));
                $this->options = get_option('xs_translate_options', $this->default_options);
                $this->options['available_languages'] = xs_framework::get_available_language();
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
                        'xsoftware-translate', 
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
                        array($this,'validate_settings')
                );

                //create section for registration
                add_settings_section(
                        'xs_general_setting_section',
                        'General Settings',
                        array($this,'show_settings'),
                        'uls-settings-page'
                );
                
                //register settings
                register_setting(
                        'xs_translate_options',
                        'xs_translate_options',
                        array($this,'validate_advsettings')
                );

                //create section for registration
                add_settings_section(
                        'xs_advance_setting_section',
                        '',
                        array($this,'show_advsettings'),
                        'uls-settings-page'
                );
        
        }
        
        function show_settings()
        {
                $options = array( 
                        'name' => 'xs_translate_options[native_language]', 
                        'selected' => $this->options['native_language'],
                        'data' => xs_framework::get_available_language()
                );
                
                add_settings_field(
                        $options['name'],
                        __('Select a native language','user-language-switch'),
                        'xs_framework::create_select',
                        'uls-settings-page',
                        'xs_general_setting_section',
                $options);
                
                                
                $options = array(
                        'name' => 'xs_translate_options[frontend_language]',
                        'data' => xs_framework::get_available_language(),
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
                        'data' => xs_framework::get_available_language(),
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
        
        function show_advsettings()
        {
                $this->create_table_menu_language();
                $this->create_table_language_filter();
        }

        /**
        * Validate setting input fields.
        */
        function validate_settings($input)
        {
                $options = $this->options;
                
                $options['menu'] = $input['menu'];
                $options['languages_filter_enable'] = $input['uls_language_filter'];
                $options['use_google_translate']            =   isset($input['use_google_translate']);
                $options['automatic_redicted_ssl']        = isset($input['automatic_redicted_ssl']);
                $options['enable_translation_sidebars'] = isset($input['enable_translation_sidebars']);
                $options['native_language'] = $input['native_language'];
                $options['frontend_language'] = $input['frontend_language'];
                $options['backend_language'] = $input['backend_language'];
                
                return $options;
        }



        /**
        * Create the HTML of a table with languages lits.
        * @param $options array plugin options saved.
        */
        function create_table_menu_language() 
        {
                echo '<h2>Translated Menu Navbar<h2>';
                // get the all languages available in the wp
                $languages = xs_framework::get_available_language(array('language' => FALSE, 'english_name' => TRUE));
                $menus = get_terms( 'nav_menu', array( 'hide_empty' => true ) ); // get menues
                foreach ($menus as $menu ) {
                        $data_menu[$menu->slug] = $menu->name;
                }
        
                foreach ($languages as $code => $name ) {
                        $headers[]  = $name;
                        $data_table[0][] = xs_framework::create_select( array(
                                'name' => 'xs_translate_options[menu]['.$code.']', 
                                'data' => $data_menu, 
                                'selected' => $this->options['menu'][$code],
                                'return' => true
                        ));
                }
                xs_framework::create_table(array('headers' => $headers, 'data' => $data_table, 'class' => 'widefat fixed'));
        }

        /*
        * create table language filter this is for enable and disable post_type
        */
        function create_table_language_filter() 
        {
                echo '<h2>Filter Post Type<h2>';
                // get the information that actually is in the DB
                $languages_filter = isset($this->options['languages_filter_enable']) ? $this->options['languages_filter_enable'] : '';

                $args = array( '_builtin' => false);// values for do the query
                $post_types = get_post_types($args); // get all custom post types
                $post_types['post'] = 'post'; // add default post type
                $post_types['page'] = 'page'; // add default post type

                $headers = array('Enable / Disable', 'Post types');
                $data_table = array();
                foreach($post_types as $post_type => $name) {
                        $data_table[$post_type][0] = xs_framework::create_input_checkbox( array(
                                'name' => 'uls_language_filter['.$post_type.']',
                                'value' => $name,
                                'compare' => isset($languages_filter[$post_type]),
                                'return' => TRUE
                                ));
                        $data_table[$post_type][1] = $name;
                        
                }
                xs_framework::create_table(array('headers' => $headers, 'data' => $data_table, 'class' => 'widefat fixed'));
        }


}

$xs_translate_options = new xs_translate_options();

endif;
