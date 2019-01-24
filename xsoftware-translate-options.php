<?php
/**
 * This class control plugin settings.
 */
class xs_translate_options
{
        private $default_options = array(
        'user_backend_configuration' => TRUE,
        'user_frontend_configuration' => TRUE,
        'default_backend_language' => 'en',
        'default_frontend_language' => 'en',
        'backend_language_field_name' => 'uls_backend_language',
        'frontend_language_field_name' => 'uls_frontend_language',
        'activate_tab_language_switch' => TRUE,
        'tab_color_picker_language_switch' => 'rgba(255, 255, 255, 0)',
        'tab_position_language_switch' => 'RM',
        'fixed_position_language_switch' => TRUE,
        'use_google_translate' => TRUE,
        'automatic_redicted_ssl' => TRUE,
        'enable_translation_sidebars_language_switch' => TRUE,
        'languages_filter_enable' => array('post' => 'post', 'page' => 'page'),
        );
        
        private $options = NULL;
        
        private $tab_position = array('TL' => 'Top-Left',
                                         'TC' => 'Top-Center',
                                         'TR' => 'Top-Right',
                                         'BL' => 'Bottom-Left',
                                         'BC' => 'Bottom-Center',
                                         'BR' => 'Bottom-Right',
                                         'LT' => 'Left-Top',
                                         'LM' => 'Left-Middle',
                                         'LB' => 'Left-Bottom',
                                         'RT' => 'Right-Top',
                                         'RM' => 'Right-Middle',
                                         'RB' => 'Right-Bottom'
                                        );

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
    * Register setting fields.
    */
  function init_settings() {
    //register settings
    register_setting('xs_translate_options',
      'xs_translate_options',
      array($this,'validate_settings'));

    //create about section
    add_settings_section('uls_create_section_tabs',
      '',
      'xs_translate_options::ilc_admin_tabs',
      'uls-settings-page');


    // add configuration about the setting depent of the tab
    if( isset($_GET['tab']) && $_GET['tab'] == 'homepage' || !isset($_GET['tab'])  ) {

      //create section for registration
      add_settings_section('uls_general_settings_section',
        __('General Settings','user-language-switch'),
        'xs_translate_options::create_general_settings_section',
        'uls-settings-page');
        
        $options = array( 
                'name' => 'xs_translate_options[automatic_redicted_ssl]', 
                'value' => $this->options['automatic_redicted_ssl'],
                'compare' => TRUE
        );
        add_settings_field($options['name'],
        __('Enable automatic redicted to ssl connection','user-language-switch'),
        'xs_framework::create_input_checkbox',
        'uls-settings-page',
        'uls_general_settings_section',
        $options);

        $options = array( 
                'name' => 'xs_translate_options[activate_tab_language_switch]', 
                'value' => $this->options['activate_tab_language_switch'],
                'compare' => TRUE
        );
      add_settings_field($options['name'],
        __('Enable flags tab','user-language-switch'),
        'xs_framework::create_input_checkbox',
        'uls-settings-page',
        'uls_general_settings_section',
        $options);

        $options = array( 
                'name' => 'xs_translate_options[fixed_position_language_switch]', 
                'value' => $this->options['fixed_position_language_switch'],
                'compare' => TRUE
        );
      add_settings_field($options['name'],
        __('The tab is always visible in the browser window','user-language-switch'),
        'xs_framework::create_input_checkbox',
        'uls-settings-page',
        'uls_general_settings_section',
        $options);
       
        $options = array( 
                'name' => 'xs_translate_options[use_google_translate]', 
                'value' => $this->options['use_google_translate'],
                'compare' => TRUE
        );
        add_settings_field($options['name'],
        __('You want use google translate','user-language-switch'),
        'xs_framework::create_input_checkbox',
        'uls-settings-page',
        'uls_general_settings_section',
        $options);

        $options = array(
                'name' => 'xs_translate_options[tab_position_language_switch]',
                'data' => $this->tab_position,
                'selected' => $this->options['tab_position_language_switch'],
                'compare_key' => TRUE
        );
      add_settings_field($options['name'],
        __('Tab Position','user-language-switch'),
        'xs_framework::create_select',
        'uls-settings-page',
        'uls_general_settings_section',
        $options);

        $options = array( 
                'name' => 'xs_translate_options[tab_color_picker_language_switch]', 
                'value' => $this->options['tab_color_picker_language_switch']
        );
      add_settings_field($options['name'],
        __('Tab Background Color','user-language-switch'),
        'xs_framework::create_input',
        'uls-settings-page',
        'uls_general_settings_section',
        $options);

        $options = array( 
                'name' => 'xs_translate_options[enable_translation_sidebars_language_switch]', 
                'value' => $this->options['enable_translation_sidebars_language_switch'],
                'compare' => TRUE
        );
      add_settings_field($options['name'],
        __('Enable translations for sidebars','user-language-switch'),
        'xs_framework::create_input_checkbox',
        'uls-settings-page',
        'uls_general_settings_section',
        $options);

                $options = array( 
                'name' => 'xs_translate_options[user_frontend_configuration]', 
                'value' => $this->options['user_frontend_configuration'],
                'compare' => TRUE
        );
      add_settings_field($options['name'],
        __('Allow registered users to change the language that user looks the website','user-language-switch'),
        'xs_framework::create_input_checkbox',
        'uls-settings-page',
        'uls_general_settings_section',
        $options);

                $options = array( 
                'name' => 'xs_translate_options[user_backend_configuration]', 
                'value' => $this->options['user_backend_configuration'],
                'compare' => TRUE
        );
       
      add_settings_field($options['name'],
        __('Allow registered users to change the language that user looks the back-end side','user-language-switch'),
        'xs_framework::create_input_checkbox',
        'uls-settings-page',
        'uls_general_settings_section',
        $options);
    }
    else if( isset($_GET['tab']) && $_GET['tab'] == 'menulanguage' ) {
      //create section for tabs description
      $options['input_name'] = 'uls_tabs_menu_language';
      add_settings_section($options['input_name'],
        __('Information','user-language-switch'),
        'xs_translate_options::create_tabs_information_section',
        'uls-settings-page');

      // create menu table configuration
      add_settings_section('table_menu_language',
        '',
        array($this, 'create_table_menu_language'),
        'uls-settings-page',
        'uls_tabs_menu_language'
        );

    }
    else if( isset($_GET['tab']) && $_GET['tab'] == 'languages_filter_enable' ) {
      //create section for tabs description
      $options['input_name'] = 'uls_tabs_language_filter';
      add_settings_section($options['input_name'],
        __('Information','user-language-switch'),
        'xs_translate_options::create_tabs_information_section',
        'uls-settings-page');

      // create menu table configuration
      $options['input_name'] = 'languages_filter_enable';
      add_settings_section('table_language_filter',
        '',
        'xs_translate_options::create_table_language_filter',
        'uls-settings-page',
        'uls_tabs_language_filter',
        $options);
    }
  }

        /**
        * Validate setting input fields.
        */
        function validate_settings($input)
        {
                $options = $this->options;
                if( isset($input['menu']) ) {
                        $options['menu'] = $input['menu'];
                }
                else if ( isset($input['languages_filter_enable']) ) {
                        $options['languages_filter_enable'] = $input['uls_language_filter'];
                } else {
                        //get values of checkboxes
                        $options['user_backend_configuration']     =   isset($input['user_backend_configuration']);
                        $options['user_frontend_configuration']    =   isset($input['user_frontend_configuration']);
                        $options['activate_tab_language_switch']   =   isset($input['activate_tab_language_switch']);
                        $options['fixed_position_language_switch'] =   isset($input['fixed_position_language_switch']);
                        $options['use_google_translate']            =   isset($input['use_google_translate']);
                        $options['automatic_redicted_ssl']        = isset($input['automatic_redicted_ssl']);
                        $options['enable_translation_sidebars_language_switch'] = isset($input['enable_translation_sidebars_language_switch']);
                }
                return $options;
        }

  /**
  * Add entries in menu sidebar in back end.
  */
  function register_menu()
  {
        add_submenu_page('xsoftware', __('User Language Switch','user-language-switch'),
        __('User Language Switch','user-language-switch'),
        'manage_options', 'uls-settings-page',
        'xs_translate_options::create_settings_page');
  }


        /**
        * Create the HTML of a table with languages lits.
        * @param $options array plugin options saved.
        */
        static function create_table_menu_language($option) 
        {
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

  static function sort_translations_callback($a, $b) {
    return strnatcasecmp($a['english_name'], $b['english_name']);
  }

   /*
    * create table language filter this is for enable and disable post_type
     */
  static function create_table_language_filter($options) {
    $options = get_option('xs_translate_options'); // get information from DB
    // get the information that actually is in the DB
    $languages_filter = isset($options['languages_filter_enable']) ? $options['languages_filter_enable'] : '';

    $args = array( '_builtin' => false);// values for do the query
    $post_types = get_post_types($args); // get all custom post types
    $post_types['post'] = 'post'; // add default post type
    $post_types['page'] = 'page'; // add default post type
  ?>
    <table id="menu-locations-table" class="">
      <thead>
        <tr>
          <th>Enable / Disable </th>
          <th>Post types</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($post_types as $post_type => $name): ?>
          <tr>
            <?php $checked = isset($languages_filter[$post_type]) ? 'checked' : ''; ?>
            <td>
              <input type="checkbox" name="uls_language_filter[<?=$post_type?>]" value="<?=$name?>" <?=$checked?> />
            </td>
            <td>
              <label for="<?=$post_type?>_label"><?=$name?></label>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <input type="hidden" name="languages_filter_enable" value="languages_filter_enable" >
  <?php
  }
   /**
    * Create register form displayed on back end.
    */
   static function create_general_settings_section(){
    ?>
      <p><?php _e('Configure settings for the language tab that contains flags to change between languages in your website, set default languages for your 
website, create custom menu translations and activate translations of sidebars to create different sidebars for each language.', 'user-language-switch'); ?></p>
    <?php
   }

   /**
    * Create the section tabs information.
    */
   static function create_tabs_information_section($options){
     switch($options['id']){
       case 'uls_tabs_menu_language':
         $description = __("Assign menus as translations to other menus, first you need to create your menus in Appearance - Menus. If you don't assign a 
translation for a menu, then pages in the menu are translated individually if they have translations assigned.", 'user-language-switch');
         break;
       case 'uls_tabs_available_language':
         $description = '';
         __('You can install more languages in your site following the instructions in 
         <a href="http://codex.wordpress.org/WordPress_in_Your_Language" target="_blank">WordPress in Your Language</a>.', 'user-language-switch');
         break;
       case 'uls_tabs_language_filter':
         $description = __("Select which post types should be filtered automatically by language. If a post, page or custom post doesn't match the language you 
are looking in the website, then it is not displayed. If a post, page or custom post doesn't have language, then it is matched with the default language of the 
website.", 'user-language-switch');
         break;
     }
      ?>
      <div><p><?php echo $description; ?></p></div>
      <?php
   }

   /**
    * Create settings page in back end.
    */
   static function create_settings_page(){
      if ( !current_user_can( 'manage_options' ) )  {
         wp_die( __( 'You do not have sufficient permissions to access this page.', 'user-language-switch' ) );
      }
   ?>
   <div class="wrap">
      <h2><?php _e('User Language Switch','user-language-switch'); ?></h2>
      <form method="post" action="options.php" enctype="multipart/form-data">
         <?php settings_fields( 'xs_translate_options' ); ?>
         <?php do_settings_sections( 'uls-settings-page' ); ?>
         <?php submit_button( __( 'Save', 'user-language-switch') ); ?>
      </form>
   </div>
   <?php
   }

   static function ilc_admin_tabs() {
    // get the current tab or default tab
    $current = isset($_GET['tab']) ? $_GET['tab'] : 'homepage';
    // add the tabs that you want to use in the plugin
    $tabs = array('homepage' => __('General', 'user-language-switch'),
                  'menulanguage' => __('Menu Languages', 'user-language-switch'),
                  'languages_filter_enable' => __('Filter Post Types', 'user-language-switch') );

    echo '<div id="icon-themes" class="icon32"><br></div>';
    echo '<h2 class="nav-tab-wrapper">';
    // configurate the url with your personal_url and add the class for the activate tab
    foreach( $tabs as $tab => $name ){
        $class = ( $tab == $current ) ? ' nav-tab-active' : '';
        echo "<a class='nav-tab$class' href='?page=uls-settings-page&tab=$tab'>$name</a>";
    }
    echo '</h2>';
   }

        static function select_correct_menu_language($items, $args) 
        {
                $options = get_option('xs_translate_options');
                $menu_name = $args->menu;
                $user_lang = xs_framework::get_user_language();
                $menu = isset($options['menu'][$user_lang]) ? $options['menu'][$user_lang] : '';
                
                $items = xs_translate_options::print_select_menu_language($items);
                
                if($menu_name == $menu)
                        return $items;
                else
                        return wp_nav_menu( array( 'menu' => $menu, 'items_wrap' => '%3$s' , 'container' => false, 'echo' => false) );
        }
        
        static function print_select_menu_language($items)
        {
                $offset = '';
                $offset .= '<li>';
                $offset .= '<select id="xs_translate_select_language" onchange="xs_translate_select_language()">';
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
}

$xs_translate_options = new xs_translate_options();
/**
 * Add ajax action to save user language preferences.
 */
add_filter('wp_nav_menu_items', 'xs_translate_options::select_correct_menu_language', 10, 2);
