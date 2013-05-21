<?php
/*
Plugin Name: Simple Watermark
Plugin URI: http://MyWebsiteAdvisor.com/tools/wordpress-plugins/simple-watermark/
Description: Adds watermark to images as they are viewed.
Version: 1.0
Author: MyWebsiteAdvisor
Author URI: http://MyWebsiteAdvisor.com
*/

register_activation_hook(__FILE__, 'simple_watermark_activate');

register_deactivation_hook(__FILE__, 'simple_watermark_deactivate');

register_uninstall_hook(__FILE__, "simple_watermark_uninstall");



function simple_watermark_uninstall(){
	
	delete_option('simple-watermark-settings');
	delete_option('mywebsiteadvisor_pluigin_installer_menu_disable');

}


function simple_watermark_deactivate() {
		
	global $wp_rewrite;
	
	if(isset($wp_rewrite->non_wp_rules['(.*)wp-content/uploads/(.*).(jpe?g)'])){
		unset($wp_rewrite->non_wp_rules['(.*)wp-content/uploads/(.*).(jpe?g)'] );
	}
	if(isset($wp_rewrite->non_wp_rules['(.*)wp-content/uploads/(.*).(gif)'])){
		unset($wp_rewrite->non_wp_rules['(.*)wp-content/uploads/(.*).(gif)'] );
	}
	if(isset($wp_rewrite->non_wp_rules['(.*)wp-content/uploads/(.*).(png)'])){
		unset($wp_rewrite->non_wp_rules['(.*)wp-content/uploads/(.*).(png)'] );
	}

	flush_rewrite_rules();
	
	
	$cache_dir_path = ABSPATH . "wp-content/watermark-cache/";
	
	clear_simple_watermark_cache($cache_dir_path);
		
}


function clear_simple_watermark_cache($path){

    $path = rtrim($path, '/').'/';
    $handle = @opendir($path);
	if($handle){
		while(false !== ($file = readdir($handle))) {
			if($file != '.' and $file != '..' ) {
				$fullpath = $path.$file;
				if(is_dir($fullpath)) clear_simple_watermark_cache($fullpath); else unlink($fullpath);
			}
		}
   	 	closedir($handle);
	}
    @rmdir($path);

}






function simple_watermark_activate() {

	// display error message to users
	if ($_GET['action'] == 'error_scrape') {
		die("Sorry, Simple Watermark Plugin requires PHP 5.0 or higher. Please deactivate Simple Watermark Plugin.");
	}

	if ( version_compare( phpversion(), '5.0', '<' ) ) {
		trigger_error('', E_USER_ERROR);
	}
	
	
}


// require Simple Watermark Plugin if PHP 5 installed
if ( version_compare( phpversion(), '5.0', '>=') ) {

	define('SMW_LOADER', __FILE__);

	include_once(dirname(__FILE__) . '/simple-watermark-plugin-installer.php');
	
	require_once(dirname(__FILE__) . '/simple-watermark-settings-page.php');
	require_once(dirname(__FILE__) . '/simple-watermark-tools.php');
	require_once(dirname(__FILE__) . '/simple-watermark-plugin.php');

	$simple_watermark = new Simple_Watermark_Plugin();

}

?>