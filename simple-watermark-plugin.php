<?php


 
class Simple_Watermark_Plugin{

	//plugin version number
	private $version = "1.0";
	
	private $debug = false;
	
	
	
	//holds settings page class
	private $settings_page;
	
	//holds a link to the plugin settings menu
	private $page_menu;
	
	//holds watermark tools
	private $tools;


	
	///options are: edit, upload, link-manager, pages, comments, themes, plugins, users, tools, options-general
	private $page_icon = "options-general"; 	
	
	//settings page title, to be displayed in menu and page headline
	private $plugin_title = "Simple Watermark";
	
	//page name, also will be used as option name to save all options
	private $plugin_name = "simple-watermark";
	
	//will be used as option name to save all options
	private $setting_name = "simple-watermark-settings";	
	
	
	
	//holds plugin options
	private $opt = array();
	
	public $plugin_path;
	public $plugin_dir;
	public $plugin_url;
	
	
	//initialize the plugin class
	public function __construct() {
		
		$this->plugin_path = DIRECTORY_SEPARATOR . str_replace(basename(__FILE__), null, plugin_basename(__FILE__));
		$this->plugin_dir = WP_PLUGIN_DIR . $this->plugin_path;
		$this->plugin_url = WP_PLUGIN_URL . $this->plugin_path;
		
		$this->opt = get_option($this->setting_name);
		
		$this->tools = new Simple_Watermark_Tools;
		$this->tools->opt = $this->opt;
		
		
		if(isset($_GET['action']) && isset($_GET['page']) && "watermark_preview" == $_GET['action'] && "simple-watermark-settings" == $_GET['page'] ){
			$this->tools->do_watermark_preview();
			die();
		}
		
		//always check to see if the request is to watermark an image, the worker function checks for a proper request.
		add_action('init', array($this->tools, 'do_simple_watermark'));


		//hook the new watermark rewrite rule to get setup with wp init
		add_action( 'admin_init', array($this, 'watermark_add_rewrite_rules') );  
	
		//check to see if the cache is disabled, and if it is clear it out
		add_action( 'admin_init', array($this, 'watermark_check_cache_dir') );  

		
	
		//check pluign settings and display alert to configure and save plugin settings
		add_action( 'admin_init', array(&$this, 'check_plugin_settings') );
		
		//initialize plugin settings
        add_action( 'admin_init', array(&$this, 'settings_page_init') );
		
		//create menu in wp admin menu
        add_action( 'admin_menu', array(&$this, 'admin_menu') );
		

		
		// add plugin "Settings" action on plugin list
		add_action('plugin_action_links_' . plugin_basename(SMW_LOADER), array(&$this, 'add_plugin_actions'));
		
		// add links for plugin help, donations,...
		add_filter('plugin_row_meta', array(&$this, 'add_plugin_links'), 10, 2);
	
	}
	

	//check to see if the settings were updated, and if the cache should be disabled, and if so, disable it!
	public function watermark_check_cache_dir(){
		if(isset($_GET['page']) && isset($_GET['settings-updated']) && $_GET['page'] == 'simple-watermark-settings' && $_GET['settings-updated'] == 'true' ){
			$watermark_cache = $this->opt['watermark_settings']['image_cache'];
			
			if($watermark_cache == "disabled"){
				$cache_dir_path = ABSPATH . "wp-content/watermark-cache/";
						
				if (file_exists($cache_dir_path)) {
					clear_simple_watermark_cache($cache_dir_path);
				}
			}
		}
	}

	
	
	//rewrite everything in the wp-content/uploads/ directory to our plugin to process the watermarks when necessary
	public function watermark_add_rewrite_rules(){
	
		global $wp_rewrite;
		
		if(isset($this->opt['watermark_settings']['image_types'])){
			foreach($this->opt['watermark_settings']['image_types'] as $key => $value){
				switch($key){
					
					case "jpg":
						//rewrite jpg images
						$rule = '(.*)wp-content/uploads/(.*).(jpe?g)'; 
						$rewrite = '$1/wp-admin/options-general.php?page=simple-watermark-settings&action=add_simple_watermark&src=$2.$3';
						$position = "top";
							
						add_rewrite_rule($rule, $rewrite, $position);
						break;
						
					case "gif":
						//rewrite gif images
						$rule = '(.*)wp-content/uploads/(.*).(gif)'; 
						$rewrite = '$1/wp-admin/options-general.php?page=simple-watermark-settings&action=add_simple_watermark&src=$2.$3';
						$position = "top";
							
						add_rewrite_rule($rule, $rewrite, $position);
						break;
						
					
					case "png":	
						//rewrite png images
						$rule = '(.*)wp-content/uploads/(.*).(png)'; 
						$rewrite = '$1/wp-admin/options-general.php?page=simple-watermark-settings&action=add_simple_watermark&src=$2.$3';
						$position = "top";
							
						add_rewrite_rule($rule, $rewrite, $position);
						break;
						
					
								
				}
			}
		}
		
			
		if(isset($_GET['activate']) && $_GET['activate'] == 'true')			
			$wp_rewrite->flush_rules();
		
		
		if(isset($_GET['page']) && isset($_GET['settings-updated']) && $_GET['page'] == 'simple-watermark-settings' && $_GET['settings-updated'] == 'true' )			
			$wp_rewrite->flush_rules();
		
		
	}
	

	
	
	
	public function settings_page_init() {

		 $this->settings_page  = new Simple_Watermark_Settings_Page( $this->setting_name );
		 
        //set the settings
        $this->settings_page->set_sections( $this->get_settings_sections() );
        $this->settings_page->set_fields( $this->get_settings_fields() );
		$this->settings_page->set_sidebar( $this->get_settings_sidebar() );

		$this->build_optional_tabs();
		
        //initialize settings
        $this->settings_page->init();
    }
	
	
	
	
	public function check_plugin_settings(){
		if( isset($_GET['page']) ){
			if ($_GET['page'] == "simple-watermark"  ){
				if(false === get_option($this->setting_name)){
					
					$link = admin_url()."options-general.php?page=simple-watermark-settings&tab=watermark_settings";
					$message = '<div class="error"><p>Welcome!<br>This plugin needs to be configured before you watermark your images.';
					$message .= '<br>Please Configure and Save the <a href="%1$s">Plugin Settings</a> before you continue!!</p></div>';
					echo sprintf($message, $link);
					
				}
			}
		}
	}

	
	

    /**
     * Returns all of the settings sections
     *
     * @return array settings sections
     */
    function get_settings_sections() {
	
		$settings_sections = array(
			array(
				'id' => 'watermark_settings',
				'title' => __( 'Watermark Settings', $this->plugin_name )
			)
		);
		
		
		$text_watermark_section = array(
				'id' => 'text_watermark_settings',
				'title' => __( 'Text Watermark', $this->plugin_name )
			);

		$image_watermark_section = array(
				'id' => 'image_watermark_settings',
				'title' => __( 'Image Watermark', $this->plugin_name )
			);
			


		if(isset($this->opt['watermark_settings']['watermark_type'])){
			switch( $this->opt['watermark_settings']['watermark_type']){
				
				case "text-only":
					$settings_sections[] = $text_watermark_section;
					break;
				case "image-only":
					$settings_sections[] = $image_watermark_section;
					break;	
				
			}
		}

								
        return $settings_sections;
    }


	
	

    /**
     * Returns all of the settings fields
     *
     * @return array settings fields
     */
    function get_settings_fields() {
		
		$pwd = getcwd()."/";
		$target = $this->plugin_dir."watermark-logo.png";
		$default_watermark_path  =   $this->tools->get_relative_path($pwd, $target);
		
		$image_watermark_fields = array(
			array(
				'name' => 'watermark_image_url',
				'label' => __( 'Watermark Image URL', $this->plugin_name ),
				'type' => 'url',
				'default' => $default_watermark_path,
				'desc' => 'Configure the Watermark Image URL or Relative Path.<p>If you have <b>"allow_url_fopen" : disabled</b>, you can use a relative path to the watermark image location such as: <br><b>' . $default_watermark_path . '</b></p>',
			),
			array(
				'name' => 'watermark_image_width',
				'label' => __( 'Watermark Image Width', $this->plugin_name ),
				'desc' => 'Configure the Watermark Image Width (Percentage)',
				'type' => 'percentage',
				'default' => "50"
			),
			array(
				'name' => 'watermark_image_v_pos',
				'label' => __( 'Watermark Image Vertical Position', $this->plugin_name ),
			 	'desc' => __( "Enable Image Watermark Vertical Position Adjustnment.<br>(Feature Available in Ultra Version Only, <a href='http://mywebsiteadvisor.com/tools/wordpress-plugins/simple-watermark/' target='_blank'>Click Here for More Information!</a>)", $this->plugin_name ),
                'action' => 'Enable',
				'type' => 'checkbox',
                'enabled' => 'false'
			),
			array(
				'name' => 'watermark_image_h_pos',
				'label' => __( 'Watermark Image Horizontal Position', $this->plugin_name ),
			 	'desc' => __( "Enable Image Watermark Horizontal Position Adjustnment.<br>(Feature Available in Ultra Version Only, <a href='http://mywebsiteadvisor.com/tools/wordpress-plugins/simple-watermark/' target='_blank'>Click Here for More Information!</a>)", $this->plugin_name ),
                'action' => 'Enable',
				'type' => 'checkbox',
                'enabled' => 'false'
			),
			array(
				'name' => 'enable_hq_watermarks',
				'label' => __( 'High Quality Watermarks', $this->plugin_name ),
				'desc' => __( "Enable Watermark Resampling which will result in Higher Quality watermarks.<br>(Feature Available in Ultra Version Only, <a href='http://mywebsiteadvisor.com/tools/wordpress-plugins/simple-watermark/' target='_blank'>Click Here for More Information!</a>)", $this->plugin_name ),
				'action' => 'Enable',
				'type' => 'checkbox',
				'enabled' => 'false'
			)
			
		);
			
			
			
		$fonts = $this->get_font_list();
		
		
		
		$fonts_select = array(
			'name' => 'watermark_font',
			'label' => __( 'Watermark Font', $this->plugin_name ),
			'desc' => 'Select a Watermark Text Font',
			'type' => 'select',
			'options' => $fonts
		);
		
			
		$text_watermark_fields = array(
			
			array(
				'name' => 'watermark_text',
				'label' => __( 'Watermark Text', $this->plugin_name ),
				'desc' => 'Configure the Watermark Text',
				'type' => 'text',
				'default' => "&copy; MyWebsiteAdvisor.com"
			),
			$fonts_select,
			array(
				'name' => 'watermark_text_width',
				'label' => __( 'Watermark Text Width', $this->plugin_name ),
				'desc' => 'Configure the Watermark Text Width (Percentage)',
				'type' => 'percentage',
				'default' => "50"
			),
			array(
				'name' => 'watermark_text_color',
				'label' => __( 'Watermark Text Color', $this->plugin_name ),
				'desc' => 'Configure the Watermark Text Color (FFFFFF is White)',
				'type' => 'text',
				'default' => "FFFFFF"
			),
			array(
				'name' => 'watermark_text_transparency',
				'label' => __( 'Watermark Text Transparency', $this->plugin_name ),
				'desc' => 'Configure the Watermark Text Transparency (Percentage)',
				'type' => 'percentage',
				'default' => "70"
			), 
			array(
				'name' => 'watermark_text_v_pos',
				'label' => __( 'Watermark Text Vertical Position', $this->plugin_name ),
				'desc' => 'Configure the Watermark Text Vertical Position (Percentage)',
			 	'desc' => __( "Enable Text Watermark Vertical Position Adjustnment.<br>(Feature Available in Ultra Version Only, <a href='http://mywebsiteadvisor.com/tools/wordpress-plugins/simple-watermark/' target='_blank'>Click Here for More Information!</a>)", $this->plugin_name ),
                'action' => 'Enable',
				'type' => 'checkbox',
                'enabled' => 'false'
			),
			array(
				'name' => 'watermark_text_h_pos',
				'label' => __( 'Watermark Text Horizontal Position', $this->plugin_name ),
			 	'desc' => __( "Enable Text Watermark Horizontal Position Adjustnment.<br>(Feature Available in Ultra Version Only, <a href='http://mywebsiteadvisor.com/tools/wordpress-plugins/simple-watermark/' target='_blank'>Click Here for More Information!</a>)", $this->plugin_name ),
                'action' => 'Enable',
				'type' => 'checkbox',
                'enabled' => 'false'
			)
			
			
		);






		$settings_fields = array(
			'watermark_settings' => array(
			array(
                    'name' 		=> 'watermark_type',
                    'label' 		=> __( 'Watermark Type', $this->plugin_name ),
					'desc' 		=> __( "Select a Watermark Type.<br>(Upgrade to <a href='http://mywebsiteadvisor.com/tools/wordpress-plugins/simple-watermark/' target='_blank'>Simple Watermark Ultra</a> for <b>Text and Image Watermarks!</b>)", $this->plugin_name ),
                    'type'			=> 'radio',
					'default'		=> 'image-only',
                    'options'	 	=> array(
						'image-only' 	=> 'Image Only',
                        'text-only' 		=> 'Text Only' 
                    )
                ),
				array(
                    'name' 		=> 'image_types',
                    'label' 		=> __( 'Image Types', $this->plugin_name ),
                    'desc' 		=> __( 'Enable Automatic Watermarks for the selected Image Types', $this->plugin_name ),
                    'type' 		=> 'multicheck',
                    'options' 	=> array(
						'jpg' 			=> '.JPG',
                        'png'			=> '.PNG',
                        'gif' 			=> '.GIF'
                    )
                ),
				array(
					'name' 		=> 'image_cache',
					'label' 		=> __( 'Watermark Cache', $this->plugin_name ),
					'desc' 		=> __( "Enable the Watermark Image Cache System for Enhanced Performance", $this->plugin_name ),
					'type' 		=> 'radio',
					'default' 	=> 'basic',
					'options' 	=> array(
						'disabled' 	=> 'Disable Cache<br>(Save Disk Space, Reduced Performance)',
                        'basic' 	=> 'Basic Cache<br>(Cache Watermarked Images, Improved Performance)' 
                    )
				),
				array(
					'name' 		=> 'jpeg_quality',
					'label' 		=> __( 'JPEG Quality Adjustment', $this->plugin_name ),
					'desc'		 	=> __( "Adjustable JPEG image output quality can adjust the size and quality of the finished images.<br>(Feature Available in Ultra Version Only, <a href='http://mywebsiteadvisor.com/tools/wordpress-plugins/simple-watermark/' target='_blank'>Click Here for More Information!</a>)", $this->plugin_name ),
					'type' 		=> 'checkbox',
					'action' 		=> 'Enable',
					'enabled' 	=> 'false'
				)
			)
		);
		
		
		if(isset($this->opt['watermark_settings']['watermark_type'])){
			switch( $this->opt['watermark_settings']['watermark_type']){

				case "text-only":
					$settings_fields['text_watermark_settings'] = $text_watermark_fields;
					break;
					
				case "image-only":
					$settings_fields['image_watermark_settings'] = $image_watermark_fields;
					break;	
				
			}
		}
			
			
        return $settings_fields;
    }




	private function do_diagnostic_sidebar(){
	
		ob_start();
		
			echo "<p>Plugin Version: $this->version</p>";
				
			echo "<p>Server OS: ".PHP_OS." (" . strlen(decbin(~0)) . " bit)</p>";
			
			echo "<p>Required PHP Version: 5.0+<br>";
			echo "Current PHP Version: " . phpversion() . "</p>";
			

			$gdinfo = gd_info();
		
			if($gdinfo){
				echo '<p>GD Support Enabled!<br>';
				if($gdinfo['FreeType Support']){
					 echo 'FreeType Support Enabled!</p>';
				}else{
					echo "Please Configure FreeType!</p>";
				}
			}else{
				echo "<p>Please Configure GD!</p>";
			}
			
			
			if( ini_get('safe_mode') ){
				echo "<p><font color='red'>PHP Safe Mode is enabled!<br><b>Disable Safe Mode in php.ini!</b></font></p>";
			}else{
				echo "<p>PHP Safe Mode: is disabled!</p>";
			}
			
			if( ini_get('allow_url_fopen')){
				echo "<p>PHP allow_url_fopen: is enabled!</p>";
			}else{
				echo "<p><font color='red'>PHP allow_url_fopen: is disabled!<br><b>Enable allow_url_fopen in php.ini!</b></font></p>";
			}
			
			
			echo "<p>Memory Use: " . number_format(memory_get_usage()/1024/1024, 1) . " / " . ini_get('memory_limit') . "</p>";
			
			echo "<p>Peak Memory Use: " . number_format(memory_get_peak_usage()/1024/1024, 1) . " / " . ini_get('memory_limit') . "</p>";
			
			if(function_exists('sys_getloadavg')){
				$lav = sys_getloadavg();
				echo "<p>Server Load Average: ".$lav[0].", ".$lav[1].", ".$lav[2]."</p>";
			}	
		
			
	
		return ob_get_clean();
				
	}
	
	


	
	private function get_settings_sidebar(){
	
		$plugin_resources = "<p><a href='http://mywebsiteadvisor.com/tools/wordpress-plugins/simple-watermark/' target='_blank'>Plugin Homepage</a></p>
			<p><a href='http://mywebsiteadvisor.com/support/'  target='_blank'>Plugin Support</a></p>
			<p><b><a href='http://wordpress.org/support/view/plugin-reviews/simple-watermark?rate=5#postform'  target='_blank'>Rate and Review This Plugin</a></b></p>";
	
	
		$enabled = get_option('mywebsiteadvisor_pluigin_installer_menu_disable');
		if(!isset($enabled) || $enabled == 'true'){
			$more_plugins = "<p><b><a href='".admin_url()."plugins.php?page=MyWebsiteAdvisor' target='_blank' title='Install More Free Plugins from MyWebsiteAdvisor.com!'>Install More Free Plugins!</a></b></p>";
		}else{
			$more_plugins = "<p><b><a href='".admin_url()."plugin-install.php?tab=search&type=author&s=MyWebsiteAdvisor' target='_blank' title='Install More Free Plugins from MyWebsiteAdvisor.com!'>Install More Free Plugins!</a></b></p>";
		}
			
		$more_plugins .= "<p><a href='http://mywebsiteadvisor.com/tools/premium-wordpress-plugins/'  target='_blank'>Premium WordPress Plugins!</a></p>
			<p><a href='http://mywebsiteadvisor.com/products-page/developer-wordpress-plugins/'  target='_blank'>Developer WordPress Plugins!</a></p>
			<p><a href='http://profiles.wordpress.org/MyWebsiteAdvisor/'  target='_blank'>Free Plugins on Wordpress.org!</a></p>
			<p><a href='http://mywebsiteadvisor.com/tools/wordpress-plugins/'  target='_blank'>Free Plugins on MyWebsiteAdvisor.com!</a></p>";
					
							
		$follow_us = "<p><a href='http://facebook.com/MyWebsiteAdvisor/'  target='_blank'>Follow us on Facebook!</a></p>
			<p><a href='http://twitter.com/MWebsiteAdvisor/'  target='_blank'>Follow us on Twitter!</a></p>
			<p><a href='http://www.youtube.com/mywebsiteadvisor'  target='_blank'>Watch us on YouTube!</a></p>
			<p><a href='http://MyWebsiteAdvisor.com/'  target='_blank'>Visit our Website!</a></p>";
	
		$upgrade = "	<p>
			<b><a href='http://mywebsiteadvisor.com/tools/wordpress-plugins/simple-watermark/'  target='_blank'>Upgrade to Simple Watermark Ultra!</a></b><br />
			<br />
			<b>Features:</b><br />
				-Manually Add Watermarks<br />
	 			-Change Watermark Position<br />
	 			-Add High Quality Watermarks<br />
	 			-And Much More!<br />
			</p>";
	
		$sidebar_info = array(
			array(
				'id' => 'diagnostic',
				'title' => 'Plugin Diagnostic Check',
				'content' => $this->do_diagnostic_sidebar()		
			),
			array(
				'id' => 'resources',
				'title' => 'Plugin Resources',
				'content' => $plugin_resources	
			),
			array(
				'id' => 'upgrade',
				'title' => 'Plugin Upgrades',
				'content' => $upgrade	
			),
			array(
				'id' => 'more_plugins',
				'title' => 'More Plugins',
				'content' => $more_plugins	
			),
			array(
				'id' => 'follow_us',
				'title' => 'Follow MyWebsiteAdvisor',
				'content' => $follow_us	
			)
		);
		
		return $sidebar_info;

	}



	//plugin settings page template
    function plugin_settings_page(){
	
		echo "<style> 
		.form-table{ clear:left; } 
		.nav-tab-wrapper{ margin-bottom:0px; }
		</style>";
		
		echo $this->display_social_media(); 
		
        echo '<div class="wrap" >';
		
			echo '<div id="icon-'.$this->page_icon.'" class="icon32"><br /></div>';
			
			echo "<h2>".$this->plugin_title." Plugin Settings</h2>";
			
			$this->settings_page->show_tab_nav();
			
			echo '<div id="poststuff" class="metabox-holder has-right-sidebar">';
			
				echo '<div class="inner-sidebar">';
					echo '<div id="side-sortables" class="meta-box-sortabless ui-sortable" style="position:relative;">';
					
						$this->settings_page->show_sidebar();
					
					echo '</div>';
				echo '</div>';
			
				echo '<div class="has-sidebar" >';			
					echo '<div id="post-body-content" class="has-sidebar-content">';
						
						$this->settings_page->show_settings_forms();
						
					echo '</div>';
				echo '</div>';
				
			echo '</div>';
			
        echo '</div>';
		
    }










   	public function admin_menu() {
		$this->page_menu = add_options_page( $this->plugin_title, $this->plugin_title, 'manage_options',  $this->setting_name, array($this, 'plugin_settings_page') );
 	
		global $wp_version;

   		if($this->page_menu && version_compare($wp_version, '3.3', '>=')){
			add_action("load-". $this->page_menu, array($this, 'admin_help'));	
		}
    }




	//public function admin_help($contextual_help, $screen_id, $screen){
	public function admin_help(){
			
		 $screen = get_current_screen();
		 
		//if ($screen_id == $this->page_menu) {
				
			$support_the_dev = $this->display_support_us();
			$screen->add_help_tab(array(
				'id' => 'developer-support',
				'title' => "Support the Developer",
				'content' => "<h2>Support the Developer</h2><p>".$support_the_dev."</p>"
			));
				
				
		$video_code = "<style>
		.videoWrapper {
			position: relative;
			padding-bottom: 56.25%; /* 16:9 */
			padding-top: 25px;
			height: 0;
		}
		.videoWrapper iframe {
			position: absolute;
			top: 0;
			left: 0;
			width: 100%;
			height: 100%;
		}
		</style>";
		
			$video_id = $this->youtube_id;
			$video_code .= '<div class="videoWrapper"><iframe width="640" height="360" src="http://www.youtube.com/embed/'.$video_id.'?rel=0&vq=hd720" frameborder="0" allowfullscreen></iframe></div>';

			$screen->add_help_tab(array(
				'id' => 'tutorial-video',
				'title' => "Tutorial Video",
				'content' => "<h2>{$this->plugin_title} Tutorial Video</h2><p>$video_code</p>"
			));
			
			
			

						
			$faqs = "<p><b>How do I generate the Highest Quality Watermarks?</b><br>";
			$faqs .= "We recommend that your watermark image be roughly the same width as the largest images you plan to watermark.<br>";
			$faqs .= "That way the watermark image will be scaled down, which will work better than making the watermark image larger in order to fit.<br>";
			$faqs .= "We also have a premium version of this plugin that adds the capability to Re-Sample the watermark image, rather than simply Re-Size it, which results in significantly better looking watermarks!<br>";
			$faqs .= "<b><a href='http://MyWebsiteAdvisor.com/tools/wordpress-plugins/simple-watermark/' target='_blank'>Upgrade to Simple Watermark Ultra</a></b>";
			$faqs .= "</p>";
			
			$faqs .= "<p><b>How can I Adjust the Location of the Watermarks?</b><br>";
			$faqs .= "We have a premium version of this plugin that adds the capability to adjust the location of the watermarks.<br>";
			$faqs .= "The position can be adjusted both vertically and horizontally.<br>";
			$faqs .= "<b><a href='http://MyWebsiteAdvisor.com/tools/wordpress-plugins/simple-watermark/' target='_blank'>Upgrade to Simple Watermark Ultra</a></b>";
			$faqs .= "</p>";
			



			$screen->add_help_tab(array(
				'id' => 'plugin-faq',
				'title' => "Plugin FAQ's",
				'content' => "<h2>Frequently Asked Questions</h2>".$faqs
			));
					
					
			$screen->add_help_tab(array(
				'id' => 'plugin-support',
				'title' => "Plugin Support",
				'content' => "<h2>Support</h2><p>For Plugin Support please visit <a href='http://mywebsiteadvisor.com/support/' target='_blank'>MyWebsiteAdvisor.com</a></p>"
			));
			
			
			$screen->add_help_tab(array(
				'id' => 'upgrade_plugin',
				'title' => __( 'Plugin Upgrades', $this->plugin_name ),
				'content' => $this->get_plugin_upgrades()		
			));		
			
				
					
			
			$disable_plugin_installer_nonce = wp_create_nonce("mywebsiteadvisor-plugin-installer-menu-disable");	
		
			$plugin_installer_ajax = " <script>
				function update_mwa_display_plugin_installer_options(){
					  
						jQuery('#display_mwa_plugin_installer_label').text('Updating...');
						
						var option_checked = jQuery('#display_mywebsiteadvisor_plugin_installer_menu:checked').length > 0;
					  
						var ajax_data = {
							'checked': option_checked,
							'action': 'update_mwa_plugin_installer_menu_option', 
							'security': '$disable_plugin_installer_nonce'
						};
						  
						jQuery.ajax({
							type: 'POST',
							url:  ajaxurl,
							data: ajax_data,
							success: function(data){
								if(data == 'true'){
									jQuery('#display_mwa_plugin_installer_label').text(' MyWebsiteAdvisor Plugin Installer Menu Enabled!');
								}
								if(data == 'false'){
									jQuery('#display_mwa_plugin_installer_label').text(' MyWebsiteAdvisor Plugin Installer Menu Disabled!');
								}
								//alert(data);
								//location.reload();
							}
						});  
				  }</script>";



			$checked = "";
			$enabled = get_option('mywebsiteadvisor_pluigin_installer_menu_disable');
			if(!isset($enabled) || $enabled == 'true'){
				$checked = "checked='checked'";
				$content = "<h2>More Free Plugins from MyWebsiteAdvisor.com</h2><p>Install More Free Plugins from MyWebsiteAdvisor.com <a href='".admin_url()."plugins.php?page=MyWebsiteAdvisor' target='_blank'>Click here</a></p>";
			}else{
					$checked = "";
				$content = "<h2>More Free Plugins from MyWebsiteAdvisor.com</h2><p>Install More Free Plugins from MyWebsiteAdvisor.com  <a href='".admin_url()."plugin-install.php?tab=search&type=author&s=MyWebsiteAdvisor' target='_blank'>Click here</a></p>";
			}
			
			$content .=  $plugin_installer_ajax . "
       	<p><input type='checkbox' $checked id='display_mywebsiteadvisor_plugin_installer_menu' name='display_mywebsiteadvisor_plugin_installer_menu' onclick='update_mwa_display_plugin_installer_options()' /> <label id='display_mwa_plugin_installer_label' for='display_mywebsiteadvisor_plugin_installer_menu' > Check here to display the MyWebsiteAdvisor Plugin Installer page in the Plugins menu.</label></p>";
			
			$screen->add_help_tab(array(
				'id' => 'more-free-plugins',
				'title' => "More Free Plugins",
				'content' => $content
			));
			
			
			
			
			$help_sidebar = "<p>Please Visit us online for more Free WordPress Plugins!</p>";
			$help_sidebar .= "<p><a href='http://mywebsiteadvisor.com/tools/wordpress-plugins/' target='_blank'>MyWebsiteAdvisor.com</a></p>";
			$help_sidebar .= "<br>";
			$help_sidebar .= "<p>Install more FREE WordPress Plugins from MyWebsiteAdvisor.com </p>";
			
			$enabled = get_option('mywebsiteadvisor_pluigin_installer_menu_disable');
			if(!isset($enabled) || $enabled == 'true'){
				$help_sidebar .= "<p><a href='".admin_url()."plugins.php?page=MyWebsiteAdvisor' target='_blank'>Click here</a></p>";
			}else{
				$help_sidebar .= "<p><a href='".admin_url()."plugin-install.php?tab=search&type=author&s=MyWebsiteAdvisor' target='_blank'>Click here</a></p>";
			}
			
			$screen->set_help_sidebar($help_sidebar);
		//}
	}
	
	
	






	private function get_image_sizes(){
	
		$default_image_sizes = array('fullsize');
		$tmp_image_sizes = array_unique(array_merge(get_intermediate_image_sizes(), $default_image_sizes));
		$image_sizes = array();
		
		foreach($tmp_image_sizes as $image_size){
			$image_sizes[$image_size] = ucfirst($image_size);
		}	
		
		return $image_sizes;
				
	}

  

	/**
	 * Add "Settings" action on installed plugin list
	 */
	public function add_plugin_actions($links) {
		array_unshift($links, '<a href="options-general.php?page=' . $this->setting_name . '">' . __('Settings') . '</a>');
		
		return $links;
	}
	
	
	/**
	 * Add links on installed plugin list
	 */
	public function add_plugin_links($links, $file) {
		if($file == plugin_basename(SMW_LOADER)) {
			$upgrade_url = 'http://mywebsiteadvisor.com/tools/wordpress-plugins/simple-watermark/';
			$links[] = '<a href="'.$upgrade_url.'" target="_blank" title="Click Here to Upgrade this Plugin!">Upgrade Plugin</a>';
			
			$install_url = admin_url()."plugins.php?page=MyWebsiteAdvisor";
			$links[] = '<a href="'.$install_url.'" target="_blank" title="Click Here to Install More Free Plugins!">More Plugins</a>';
			
			//$tutorial_url = 'http://mywebsiteadvisor.com/learning/video-tutorials/simple-watermark-tutorial/';
			//$links[] = '<a href="'.$tutorial_url.'" target="_blank" title="Click Here to View the Plugin Video Tutorial!">Tutorial Video</a>';
			
			$rate_url = 'http://wordpress.org/support/view/plugin-reviews/' . basename(dirname(__FILE__)) . '?rate=5#postform';
			$links[] = '<a href="'.$rate_url.'" target="_blank" title="Click Here to Rate and Review this Plugin on WordPress.org">Rate This Plugin</a>';
		}
		
		return $links;
	}
	
	
	public function display_support_us(){
				
		$string = '<p><b>Thank You for using the '.$this->plugin_title.' Plugin for WordPress!</b></p>';
		$string .= "<p>Please take a moment to <b>Support the Developer</b> by doing some of the following items:</p>";
		
		$rate_url = 'http://wordpress.org/support/view/plugin-reviews/' . basename(dirname(__FILE__)) . '?rate=5#postform';
		$string .= "<li><a href='$rate_url' target='_blank' title='Click Here to Rate and Review this Plugin on WordPress.org'>Click Here</a> to Rate and Review this Plugin on WordPress.org!</li>";

		$string .= "<li><a href='http://www.youtube.com/subscription_center?add_user=MyWebsiteAdvisor' target='_blank' title='Click Here to Subscribe to our YouTube Channel'>Click Here</a> to Subscribe to our YouTube Channel!</li>";
		
		$string .= "<li><a href='http://facebook.com/MyWebsiteAdvisor' target='_blank' title='Click Here to Follow us on Facebook'>Click Here</a> to Follow MyWebsiteAdvisor on Facebook!</li>";
		$string .= "<li><a href='http://twitter.com/MWebsiteAdvisor' target='_blank' title='Click Here to Follow us on Twitter'>Click Here</a> to Follow MyWebsiteAdvisor on Twitter!</li>";
		$string .= "<li><a href='http://mywebsiteadvisor.com/tools/premium-wordpress-plugins/' target='_blank' title='Click Here to Purchase one of our Premium WordPress Plugins'>Click Here</a> to Purchase Premium WordPress Plugins!</li>";
	
		return $string;
	}  
  
  
  
  
  	public function display_social_media(){
	
		$social = '<style>
	
		.fb_edge_widget_with_comment {
			position: absolute;
			top: 0px;
			right: 200px;
		}
		
		</style>
		
		<div  style="height:20px; vertical-align:top; width:45%; float:right; text-align:right; margin-top:5px; padding-right:16px; position:relative;">
		
			<div id="fb-root"></div>
			<script>(function(d, s, id) {
			  var js, fjs = d.getElementsByTagName(s)[0];
			  if (d.getElementById(id)) return;
			  js = d.createElement(s); js.id = id;
			  js.src = "//connect.facebook.net/en_US/all.js#xfbml=1&appId=253053091425708";
			  fjs.parentNode.insertBefore(js, fjs);
			}(document, "script", "facebook-jssdk"));</script>
			
			<div class="fb-like" data-href="http://www.facebook.com/MyWebsiteAdvisor" data-send="true" data-layout="button_count" data-width="450" data-show-faces="false"></div>
			
			
			<a href="https://twitter.com/MWebsiteAdvisor" class="twitter-follow-button" data-show-count="false"  >Follow @MWebsiteAdvisor</a>
			<script>!function(d,s,id){var js,fjs=d.getElementsByTagName(s)[0];if(!d.getElementById(id)){js=d.createElement(s);js.id=id;js.src="//platform.twitter.com/widgets.js";fjs.parentNode.insertBefore(js,fjs);}}(document,"script","twitter-wjs");</script>
		
		
		</div>';
		
		return $social;

	}	








	//build optional tabs, using debug tools class worker methods as callbacks
	private function build_optional_tabs(){
		
		
		$watermark_preview = array(
			'id' => 'watermark_preview',
			'title' => __( 'Watermark Preview', $this->plugin_name ),
			'callback' => array(&$this, 'show_watermark_preview')
		);
		$this->settings_page->add_section( $watermark_preview );
		
			

		
		
		if(true === $this->debug){
			//general debug settings
			$plugin_debug = array(
				'id' => 'plugin_debug',
				'title' => __( 'Settings Debug', $this->plugin_name ),
				'callback' => array(&$this, 'show_plugin_settings')
			);
			
			$this->settings_page->add_section( $plugin_debug );
		}	
		
		
				
		$upgrade_plugin = array(
			'id' => 'upgrade_plugin',
			'title' => __( 'Upgrades', $this->plugin_name ),
			'callback' => array(&$this, 'show_plugin_upgrades')
		);
		$this->settings_page->add_section( $upgrade_plugin );
	
	}
	
	
	
	
	
	
	
	
	
		
	public function get_plugin_upgrades(){
		ob_start();
		$this->show_plugin_upgrades();
		return ob_get_clean();	
	}
	
	
	public function show_plugin_upgrades(){
		
		$html = "<style>
			ul.upgrade_features li { list-style-type: disc; }
			ul.upgrade_features  { margin-left:30px;}
		</style>";
		
		$html .= "<script>
		
			function  trans_watermark_upgrade(){
        		window.open('http://mywebsiteadvisor.com/tools/wordpress-plugins/simple-watermark/');
        		return false;
			}
			

			
	
			function  trans_watermark_learn_more(){
        		window.open('http://mywebsiteadvisor.com/tools/wordpress-plugin/simple-watermark/');
        		return false;
			}

			
		</script>";
		
		//simple watermark ultra
		$html .= "</form><h2>Upgrade to Simple Watermark Ultra Today!</h2>";
		
		$html .= "<p><b>Premium Features include:</b></p>";
		
		$html .= "<ul class='upgrade_features'>";
		$html .= "<li>Fully Adjustable Watermark Position</li>";
		$html .= "<li>Highest Quality Watermarks</li>";
		$html .= "<li>Lifetime Priority Support and Update License</li>";
		$html .= "</ul>";
		
		$html .=  '<div style="padding-left: 1.5em; margin-left:5px;">';
		$html .= "<p class='submit'>";
		$html .= "<input type='submit' class='button-primary' value='Upgrade to Simple Watermark Ultra &raquo;' onclick='return trans_watermark_upgrade()'> &nbsp;";
		$html .= "<input type='submit' class='button-secondary' value='Learn More &raquo;' onclick='return trans_watermark_learn_more()'>";
		$html .= "</p>";		
		$html .=  "</div>";
		
		echo $html;
	}

	

	

	public function show_watermark_preview(){
		$img_url = admin_url()."options-general.php?page=".$this->setting_name."&action=watermark_preview";
		echo "<img src=$img_url width='100%'>";
		echo "<p><strong>You can customize the preview image by replacing the image named ";
		echo " <a href='".$this->plugin_url."example.jpg' target='_blank'>'example.jpg'</a> in the plugin directory.</strong></p>";
	}
	
	
 

	// displays the plugin options array
	public function show_plugin_settings(){
				
		echo "<pre>";
			print_r($this->opt);
		echo "</pre>";
			
	}
	
	
	

	
		/**
	 * List all fonts from the fonts dir
	 *
	 * @return array
	 */
	private function get_font_list() {
		$plugin_dir = WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . str_replace(basename(__FILE__), null, plugin_basename(__FILE__));
		$fonts_dir =  $plugin_dir . DIRECTORY_SEPARATOR . "fonts";

		$fonts = array();
		try {
			$dir = new DirectoryIterator($fonts_dir);

			foreach($dir as $file) {
				if($file->isFile()) {
					$font = pathinfo($file->getFilename());

					if(strtolower($font['extension']) == 'ttf') {
						if(!$file->isReadable()) {
							$this->_messages['unreadable-font'] = sprintf('Some fonts are not readable, please try chmoding the contents of the folder <strong>%s</string> to writable and refresh this page.', $this->_plugin_dir . $this->_fonts_dir);
						}

						$fonts[$font['basename']] = str_replace('_', ' ', $font['filename']);
					}
				}
			}

			ksort($fonts);
		} catch(Exception $e) {}

		return $fonts;
	}

			
	
}
 
 
?>
