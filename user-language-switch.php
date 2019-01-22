<?php
/*
Plugin Name: User Language Switch
Description: Build a multilingual and SEO friendly website. Linking translations of content and allow visitors to browse your website in different languages.
Version: 1.7
Author: XSoftware
Author URI: http://www.xsoftware.it
License: GPL3
*/

?>
<?php

define( 'ULS_PLUGIN_URL', plugin_dir_url(__FILE__) );
define( 'ULS_PLUGIN_PATH', plugin_dir_path(__FILE__) );
define( 'ULS_PLUGIN_NAME', plugin_basename(__FILE__) );
define( 'ULS_FILE_PATH', __FILE__ );


/**
 * This function intis the plugin. It check the language in the URL, the language in the browser and the language in the user preferences to redirect the user to the correct page with translations.
 *
 * 1. This function first check the language configured in the user browser and redirects the user to the correct language version of the website.
 */
add_action('init', 'uls_init_plugin');
function uls_init_plugin(){
        include 'uls-options.php';
        include 'codes.php';
        include 'uls-functions.php';
        
        if(is_admin()) return;
        //load translation
        $plugin_dir = dirname( plugin_basename( __FILE__ ) ) . '/languages/';
        load_plugin_textdomain( 'user-language-switch', false, $plugin_dir );
        
        //init flag of permalink convertion to true. When this flag is false, then don't try to get translations when is generating permalinks
        global $uls_permalink_convertion;
        $uls_permalink_convertion = true;

        //init flat for uls link filter function. When this flag is true is because it is running a process to generate a link with translations, then it abort any try to get a translation over a translation, in this way it doesn't do an infinite loop.
        global $uls_link_filter_flag;
        $uls_link_filter_flag = true;
        
        //take language from browser setting
        $language = uls_get_user_language_from_browser();
        if(in_array($language, uls_get_available_languages())) {
                $language = uls_cookie_language($language);
                //redirects the user based on the browser language. It detectes the browser language and redirect the user to the site in that language.
                uls_redirect_by_language($language);
        } else {
                uls_translate_by_google();
        }
        
        //reset flags
        $uls_permalink_convertion = false;
        $uls_link_filter_flag = false;

        //init session to detect if you are in the home page by "first time"
        if(!session_id()) session_start();
}

/**
 * This function gets the language from the current URL.
 *
 * @param $only_lang boolean if it is true, then it returns the 2 letters of the language. It is the language code without location.
 *
 * @return mixed it returns a string containing a language code or false if there isn't any language detected.
 */
function uls_get_user_language_from_url($only_lang = false){
        if(!isset($_SERVER['HTTP_HOST']) || !isset($_SERVER['REQUEST_URI'])) {
                return false;
        }
        //get language from URL
        $language = null;
        if(is_null($language)) {
                //activate flag to avoid translations and get the real URL of the blog
                global $uls_permalink_convertion;
                $uls_permalink_convertion = true;

                //get the langauge from the URL
                $url = str_replace(get_bloginfo('url'), '', 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
                if( isset($url[0]) && $url[0] == '/') $url = substr($url, 1);
                        $parts = explode('/', $url);
                if(count($parts) > 0)
                        $language = $parts[0];

                //reset the flag
                $uls_permalink_convertion = true;
        }

        return uls_valid_language($language) ? $language : false;
}


/**
 * This function retrieves the user language selected in the admin side.
 *
 * @param $only_lang boolean if it is true, then it returns the 2 letters of the language. It is the language code without location.
 * @param $type string (backend|frontend) specify which language it will return.
 *
 * @return mixed it returns a string containing a language code. If user don't have permissions to change languages or user hasn't configured a language, then the default language of the website is returned. If user isn't logged in, then the default language of the website is returned.
 */
function uls_get_user_saved_language($only_lang = false, $type = null){
  //get the options of the plugin
  $options = uls_get_options();
  $language = false;

  //detect if the user is in backend or frontend
  if($type == null){
    $type = 'frontend';
    if( is_admin() )
      $type = 'backend';
  }

  //if the user is logged in
  if( is_user_logged_in() ){
    //if the user can modify the language
    if($options["user_{$type}_configuration"])
      $language = get_user_meta(get_current_user_id(), "uls_{$type}_language", true);
  }

  //set the default language if the user doesn't have a preference
  if(empty($language))
    $language = $options["default_{$type}_language"];

  //remove the location
  if(false != $language && $only_lang){
    $pos = strpos($language, '_');
    if(false !== $pos)
      return substr($language, 0, $pos);
  }

  return $language;
}

/**
 * This function retrieves the user language from the browser. It reads the headers sent by the browser about language preferences.
 *
 * @return mixed it returns a string containing a language code or false if there isn't any language detected.
 */
function uls_get_user_language_from_browser(){
  if(!isset($_SERVER["HTTP_ACCEPT_LANGUAGE"])){
        return false;
  }
    //split the header languages
    $browserLanguages = explode(',', $_SERVER["HTTP_ACCEPT_LANGUAGE"]);
    
    

    //parse each language
    $parsedLanguages = array();
    foreach($browserLanguages as $bLang){
      //check for q-value and create associative array. No q-value means 1 by rule
      if(preg_match("/(.*);q=([0-1]{0,1}\.\d{0,4})/i",$bLang,$matches)){
        $matches[1] = strtolower(str_replace('-', '_', $matches[1]));
        $parsedLanguages []= array(
          'code' => (false !== strpos($matches[1] , '_')) ? $matches[1] : false,
          'l' => $matches[1],
          'q' => (float)$matches[2],
        );
      }
      else{
        $bLang = strtolower(str_replace('-', '_', $bLang));
        $parsedLanguages []= array(
          'code' => (false !== strpos($bLang , '_')) ? $bLang : false,
          'l' => $bLang,
          'q' => 1.0,
        );
      }
    }
    //get the languages activated in the site
    $validLanguages = uls_get_available_languages();
    
    //validate the languages
    $max = 0.0;
    $maxLang = false;
    foreach($parsedLanguages as $k => &$v){
      if(false !== $v['code']){
        //search the language in the installed languages using the language and location
        foreach($validLanguages as $vLang){
          if(strtolower($vLang) == $v['code']){
            //replace the preferred language
            if($v['q'] > $max){
              $max = $v['q'];
              $maxLang = $vLang;
            }
          }
        }//check for the complete code
      }
    }

    //if language hasn't been detected
    if(false == $maxLang){
      foreach($parsedLanguages as $k => &$v){
        //search only for the language
        foreach($validLanguages as $vLang){
          if(substr($vLang, 0, 2) == substr($v['l'], 0, 2)){
            //replace the preferred language
            if($v['q'] > $max){
              $max = $v['q'];
              $maxLang = $vLang;
            }
          }
        }//search only for the language
      }
    }

    return $maxLang;
}



/**
 * This function gets the language from the URL, if there is no language in the URL, then it gets language from settings saved by the user in the back-end side. If there isn't a language in the URL or user hasn't set it, then default language of the website is used.
 *
 * @param $only_lang boolean if it is true, then it returns the 2 letters of the language. It is the language code without location.
 *
 * @return string language code. If there isn't a language in the URL or user hasn't set it, then default language is returned.
 */
function uls_get_user_language(){

  return isset($_COOKIE['uls_language']) ? $_COOKIE['uls_language'] :  uls_get_site_language();
}

/**
 * Get the default language of the website.
 *
 * @param $side string (frontend | backend) if it is frontend, then it returns the default language for the front-end side, otherwise it returns the language for the back-end side. If there is not languages configured, then it returns false.
 *
 * @return mixed it returns an string with language code or false if there is not languages configured.
 */
function uls_get_site_language($side = 'frontend'){
   $options = uls_get_options();
   return isset($options["default_{$side}_language"]) ? $options["default_{$side}_language"] : false;
}

function uls_translate_by_google()
{
        $options = uls_get_options();
        if($options['use_google_translate'] == false)
                return;
                
        wp_enqueue_script('uls_google_translate_script', "https://translate.google.com/translate_a/element.js?cb=googleTranslateElementInit");
        
}

function uls_cookie_language($language)
{
        if($language == NULL || $language == false)  
                return NULL;
                
        if(!isset($_COOKIE['uls_language'])){
                setcookie('uls_language', $language, time()+2*60*60, "/"); //set a cookie for 2 hour
                return $language;
        } else {
                return $_COOKIE['uls_language'];
        }
        
}

/**
 * This function check if the redirection based on the browser language is enabled. If it is add cookies to manage language
 *
 * @return mixed it returns false if the redirection is not possible, due to some of the restriction mentioned above. Otherwise, it just redirects the user.
 */
function uls_redirect_by_language($language)
{
        $url = uls_get_browser_url();
        $url = strtok($url, '?'); //remove query string if there are

        $redirectUrl = uls_get_url_translated($url, $language);
        if($redirectUrl == false)
                return NULL;
        
        if ($url != $redirectUrl) {
                wp_redirect($redirectUrl);
                exit;
        }
        return NULL;
}

/**
 * This function is attached to the WP hook "locale" and it sets the language to see the current page. The function get the language of the user, it uses the first language found in these options: URL, browser configuration, user settings, default language.
 */
function uls_language_loading($lang){
   global $uls_locale;
   //if this method is already called, then it remove the action to avoid recursion
   if($uls_locale)
    remove_filter('locale', 'uls_language_loading');
   else
     $uls_locale = true;

   if ( !isset($_SESSION) )
     session_start();

   if ( is_admin() )
     $res = isset($_SESSION["ULS_USER_BACKEND_LOCALE"]) ? $_SESSION["ULS_USER_BACKEND_LOCALE"] : $lang;
   else
     $res = isset($_SESSION["ULS_USER_FRONTEND_LOCALE"]) ? $_SESSION["ULS_USER_FRONTEND_LOCALE"] : $lang;

   $uls_locale = false;
   return $res;
}
add_filter('locale', 'uls_language_loading');


/*
 * This function create the new value on sessions vars, it save code language, it is necesary because many function need this value and it is not a good way save this value in db.
 */

function uls_language_loading_in_session ( $username ) {

  // check if the user exits
  if ( ! username_exists( $username ) )
    return;

  // get current user
  $user = get_user_by( 'login', $username );

  // if user is empty return nothing
  if (empty($user))
    return;

  //get the options of the plugin
  $options = uls_get_options();
  $language = '';

  //if the user can modify the language
  if($options["user_frontend_configuration"])
    $language_ftd = get_user_meta($user->ID, "uls_frontend_language", true);

  if($options["user_backend_configuration"])
    $language_bkd = get_user_meta($user->ID, "uls_backend_language", true);

  // Save language
  $_SESSION["ULS_USER_FRONTEND_LOCALE"] = $language_ftd;
  $_SESSION["ULS_USER_BACKEND_LOCALE"] = $language_bkd;
}
add_action( 'wp_authenticate' , 'uls_language_loading_in_session' );


/**
 * It returns the configured or default code language for a language abbreviation. The code language is the pair of language and country (i.e: en_US, es_ES)-
 *
 * @param $language code language.
 *
 * @return mixed it returns an string with the complete code or null if the language is not available.
 */
function uls_get_location_by_language($language){
  //get available languages activated in the website
  $available_languages = uls_get_available_languages();
  //for each code language, search for the language
  foreach($available_languages as $code)
    if(substr($language, 0, 2) == $language)
      return $code;

  return null;
}

/**
 * Validate if language is valid and active.
 *
 * @param $language string language to validate.
 *
 * @return boolean true if language is valid, otherwise it returns false.
 */
function uls_valid_language($language){
   //TO-DO: validate with registered languages in the site
   //get language names
   require 'uls-languages.php';
   return !empty($country_languages[$language]) || in_array($language, $language_codes);
}

/**
 * Return the id of the translation of a post.
 *
 * @param $post_id integer id of post to translate.
 * @param $language string language of translation. If it is null or invalid, current language loaded in the page is used.
 *
 * @return mixed it returns id of translation post as an integer or false if translation doesn't exist.
 */
function uls_get_post_translation_id($post_id, $language = null){
  //get language
  if(!uls_valid_language($language))
    $language = uls_get_user_language();

  //get the translation of the post
  $post_language = uls_get_post_language($post_id);

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
        $translation_id = uls_get_post_translation_id($page_id, $language);
        $offset = get_permalink($translation_id);
                
        return $offset;
}


/**
 * This function creates an HTML select input with the available languages for the site.
 * @param $id string id of the HTML element.
 * @param $name string name of the HTML element.
 * @param $default_value string value of the default selected option.
 * @param $class string CSS classes for the HTML element.
 * @param $available_language boolean "true" to return only the available lagunage "false" return all language in the wp.
 *
 * @return string HTML code of the language selector input.
 */
function uls_language_selector_input($id, $name, $default_value = '', $class = '', $available_languages = true){ //FIXME: remove function!
   //get available languages
   $available_languages = uls_get_available_languages($available_languages);

   //get language names
   require 'uls-languages.php';

   //create HTML input
   ob_start();
   ?>
   <select id="<?php echo $id; ?>" name="<?php echo $name; ?>" class="<?php echo $class; ?>" >
      <?php foreach($available_languages as $lang):
      $language_name = $lang;
      if(!empty($country_languages[$lang]))
        $language_name = $country_languages[$lang];
      else{
        $aux_name = array_search($lang, $language_codes);
        if(false !== $aux_name)
          $language_name = $aux_name;
      } ?>
      <option value="<?php echo $lang; ?>" <?php selected($lang, $default_value); ?>><?php _e($language_name,'user-language-switch'); ?></option>
      <?php endforeach; ?>
   </select>
   <?php
   $res = ob_get_contents();
   ob_end_clean();
   return $res;
}


/**
 * Get the available languages on the system.
 *
 * @return array associative array with the available languages in the system. The keys are the language names and the values are the language codes.
 */
function uls_get_available_languages( $available_languages = true ){
  if ($available_languages) {
    $options = get_option('uls_settings'); // get information from DB
    // if the user does not have available the languages so the plugin avilable all languages
    $available_language = isset($options['available_language']) ? $options['available_language'] : uls_get_available_languages(false);
    return $available_language;
  }
   $theme_root = get_template_directory();
   $lang_array = get_available_languages( $theme_root.'/languages/' );
   $wp_lang = get_available_languages(WP_CONTENT_DIR.'/languages/');
   if(!empty($wp_lang)) $lang_array = array_merge((array)$lang_array, (array)$wp_lang);
   if (!in_array('en_US',$lang_array)) array_push($lang_array, 'en_US');
   $lang_array = array_unique($lang_array);
   require 'uls-languages.php';
   $final_array= array();
   foreach($lang_array as $lang):
     if(!empty($country_languages[$lang]))
       $final_array[$country_languages[$lang]] = $lang;
     else
       $final_array[$lang] = $lang;
   endforeach;
   return $final_array;

}

/**
 * Get the language of a post.
 *
 * @param $id integer id of the post.
 *
 * @return string the code of the language or an empty string if the post doesn't have a language.
 */
function uls_get_post_language($id){
  $postLanguage = get_post_meta($id, 'uls_language', true);
  if("" == $postLanguage) return "";

  //format the language code
  $p = strpos($postLanguage, "_");
  if($p !== false){
    $postLanguage = substr($postLanguage, 0, $p) . strtoupper(substr($postLanguage, $p));
  }

  //validate the language
  if (uls_valid_language($postLanguage)) {
    return $postLanguage;
  }

  return "";
}

/**
 * Add meta boxes to select the language an traductions of a post.
 *
 * @return array
 */
function uls_language_metaboxes( $meta_boxes ) {
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
   $languages = uls_get_available_languages();
   $options = array(array('name'=>'Select one option', 'value'=>''));
   require 'uls-languages.php';
   $fields = array();
   foreach ( $languages as $lang ){
      $language_name = $lang;
      if(!empty($country_languages[$lang]))
        $language_name = $country_languages[$lang];
      else{
        $aux_name = array_search($lang, $language_codes);
        if(false !== $aux_name)
          $language_name = $aux_name;
      }

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
add_filter( 'cmb_meta_boxes', 'uls_language_metaboxes' );

/**
 * Save language associations
 */
function uls_save_association( $post_id ) {
  //verify post is a revision
  $parent_id = wp_is_post_revision( $post_id );
  if($parent_id === false)
   $parent_id = $post_id;

  $languages = uls_get_available_languages();
  $selected_language = isset($_POST['uls_language']) ? $_POST['uls_language'] : null;

  // get array post metas because we need the uls_language and uls_translation
  $this_post_metas = get_post_meta( $parent_id );
  $this_uls_translation = !empty($this_post_metas) ?  isset($this_post_metas['uls_language']) ? 'uls_translation_'.strtolower($this_post_metas['uls_language'][0]) : '' : '';
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
add_action( 'save_post', 'uls_save_association' );

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
add_action('wp_ajax_test_response', 'uls_text_ajax_process_request');

/**
* Enqueue plugin style-file
*/
function uls_add_styles() {
  // Respects SSL, Style.css is relative to the current file
  wp_register_style( 'html-style', plugins_url('css/styles.css', __FILE__) );
  wp_enqueue_style( 'html-style' );
  wp_enqueue_style( 'webilop-flags_16x11-style', plugins_url('css/flags/flags_16x11.css', __FILE__) );
}
add_action( 'admin_enqueue_scripts', 'uls_add_styles' );

/**
 * Register javascript file
 */
function uls_add_scripts() {
    wp_register_script( 'add-bx-js',   WP_CONTENT_URL . '/plugins/user-language-switch/js/js_script.js', array('jquery') );
    wp_enqueue_script( 'add-bx-js' );
    wp_enqueue_script( 'add_alert_select_js',   WP_CONTENT_URL . '/plugins/user-language-switch/js/event_select.js', array('jquery') );
    // make the ajaxurl var available to the above script
    wp_localize_script( 'add-bx-js', 'the_ajax_script', array( 'ajaxurl' => admin_url( 'admin-ajax.php' ) ) );
    wp_enqueue_style( 'webilop-flags_32x32-style', plugins_url('css/flags/flags_32x32.css', __FILE__) );
    wp_enqueue_style( 'uls-public-css', plugins_url('css/public.css', __FILE__) );
}
add_action( 'wp_enqueue_scripts', 'uls_add_scripts' );


add_filter('manage_posts_columns', 'uls_add_columns');
add_filter('manage_pages_columns', 'uls_add_columns');
function uls_add_columns($columns) {
    unset($columns['date']);
    $columns['language'] = 'Language';
    $columns['date'] = 'Date';
    return $columns;
}
add_action('manage_posts_custom_column',  'uls_show_columns');
add_action('manage_pages_custom_column',  'uls_show_columns');
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
  $language_displayed = uls_get_user_language();

  //get the default language of the website
  $default_website_language = uls_get_site_language();

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
add_action('pre_get_posts', 'uls_filter_archive_by_language', 1);
function uls_filter_archive_by_language($query){
  //check if it in the admin dashboard
  if(is_admin())
    return;

  // get values configuration uls_settings to applic filter translation to the post_types
  // if the information in languages_filter_disable are true apply filter
  $settings = get_option('uls_settings');

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
    uls_add_language_meta_query($query);
  }
}

add_action('wp_head','head_reference_translation');
function head_reference_translation() {

  //get the id of the current page
  $url =(isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"]=="on") ? "https://" : "http://";
  $url .= $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"];
  $post_id = url_to_postid($url);

  // get all available languages
  $languages = uls_get_available_languages();
  $curren_code = uls_get_user_language(); // get current language
  // delete the current language site
  $code_value = array_search($curren_code, $languages);
  unset($languages[$code_value]);

  // build the url to be tranlation
  $url = '';
  // get url from where it's using
  if ( is_home() )
    $url = get_home_url(); // get home url
  else if ( is_archive() || is_search() || is_author() || is_category() || is_tag() || is_date() )
    $url = uls_get_browser_url(); // get browser url

  // if exits the url so, translate this
  if (!empty($url) ) {
    // use all available languages and get the url translation
    foreach ($languages as $language => $code) {
      $translation_url = uls_get_url_translated($url, $code);
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
      $translation_id = uls_get_post_translation_id($post_id, $code);
      if ( !empty($translation_id) ) {
        $translation_url = uls_get_url_translated(get_permalink($translation_id), $code);
        echo '<link rel="alternate" hreflang="'.substr($code, 0, 2).'" href="'.$translation_url.'" />';
      }
    }
    // leave the global car like it was before
    $uls_permalink_convertion = true;
  }
}


// desactivate the tab flags
function update_db_after_update() {

  $options = get_option('uls_settings');
  !isset( $options['activate_tab_language_switch'] ) ?  $options['activate_tab_language_switch'] = false : '' ;
  update_option('uls_settings',$options);
}
register_activation_hook( __FILE__, 'update_db_after_update' );

