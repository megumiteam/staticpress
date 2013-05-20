<?php
if ( !class_exists('InputValidator') )
	require_once(dirname(__FILE__).'/class-InputValidator.php');

class static_press_admin {
	const OPTION_STATIC_URL   = 'StaticPress::static url';
	const OPTION_STATIC_DIR   = 'StaticPress::static dir';
	const OPTION_STATIC_BASIC = 'StaticPress::basic auth';
	const OPTION_PAGE = 'static-press';
	const TEXT_DOMAIN = 'static-press';
	const DEBUG_MODE  = false;
	const ACCESS_LEVEL = 'manage_options';

	private $plugin_basename;
	private $static_url;
	private $static_dir;
	private $basic_auth;
	private $admin_action;

	function __construct($plugin_basename){
		$this->static_url = get_option(self::OPTION_STATIC_URL, $this->get_site_url().'static/');
		$this->static_dir = get_option(self::OPTION_STATIC_DIR, ABSPATH);
		$this->basic_auth = get_option(self::OPTION_STATIC_BASIC, false);
		$this->plugin_basename = $plugin_basename;
		$this->admin_action = admin_url('/admin.php') . '?page=' . self::OPTION_PAGE . '-options';

		add_action('admin_menu', array(&$this, 'admin_menu'));
		add_filter('plugin_action_links', array(&$this, 'plugin_setting_links'), 10, 2 );

		add_action('admin_head', array($this,'add_admin_head'), 99);
	}

	public function static_url(){
		return $this->static_url;
	}

	public function static_dir(){
		return $this->static_dir;
	}

	public function remote_get_option(){
		return 
			$this->basic_auth
			? array('headers' => array('Authorization' => 'Basic '.$this->basic_auth))
			: array();
	}

	private function get_site_url(){
		global $current_blog;
		return trailingslashit(
			isset($current_blog)
			? get_home_url($current_blog->blog_id)
			: get_home_url()
			);
	}

	//**************************************************************************************
	// Add Admin Menu
	//**************************************************************************************
	public function admin_menu() {
		$hook = add_menu_page(
			__('StaticPress', self::TEXT_DOMAIN) ,
			__('StaticPress', self::TEXT_DOMAIN) ,
			self::ACCESS_LEVEL,
			self::OPTION_PAGE ,
			array($this, 'static_static_page') ,
			plugins_url('images/staticpress.png')
			);
		add_action('admin_print_scripts-'.$hook, array($this, 'add_admin_scripts'));

		$hook = add_submenu_page(
			self::OPTION_PAGE ,
			__('StaticPress Options', self::TEXT_DOMAIN) ,
			__('StaticPress Options', self::TEXT_DOMAIN) ,
			self::ACCESS_LEVEL,
			self::OPTION_PAGE . '-options' ,
			array($this, 'options_page'),
			plugins_url('images/staticpress_options.png')
			);
	}

	public function add_admin_scripts(){
	}

	public function add_admin_head(){

?>

<style type="text/css" id="<?php echo self::OPTION_PAGE;?>-menu-css">
#toplevel_page_<?php echo self::OPTION_PAGE;?> .wp-menu-image {
	background: url( <?php echo plugins_url('images/menuicon-splite.png', dirname(__FILE__)); ?> ) 0 90% no-repeat !important;
}
#toplevel_page_<?php echo self::OPTION_PAGE;?>.current .wp-menu-image,
#toplevel_page_<?php echo self::OPTION_PAGE;?>.wp-has-current-submenu .wp-menu-image,
#toplevel_page_<?php echo self::OPTION_PAGE;?>:hover .wp-menu-image {
	background-position: top left !important;
}
#icon-static-press {background-image: url(<?php echo plugins_url('images/rebuild32.png', dirname(__FILE__)); ?>);}
#icon-static-press-options {background-image: url(<?php echo plugins_url('images/options32.png', dirname(__FILE__)); ?>);}
</style>
<?php
	}

	public function options_page(){
		$nonce_action  = 'update_options';
		$nonce_name    = '_wpnonce_update_options';

		$title = __('StaticPress Options', self::TEXT_DOMAIN);

		$iv = new InputValidator('POST');
		$iv->set_rules($nonce_name, 'required');
		$iv->set_rules('static_url', array('trim','esc_html'));
		$iv->set_rules('static_dir', array('trim','esc_html'));
		$iv->set_rules('basic_usr',  array('trim','esc_html'));
		$iv->set_rules('basic_pwd',  array('trim','esc_html'));

		// Update options
		if (!is_wp_error($iv->input($nonce_name)) && check_admin_referer($nonce_action, $nonce_name)) {
			// Get posted options
			$static_url = $iv->input('static_url');
			$static_dir = $iv->input('static_dir');
			$basic_usr  = $iv->input('basic_usr');
			$basic_pwd  = $iv->input('basic_pwd');
			$basic_auth = 
				($basic_usr && $basic_pwd)
				? base64_encode("{$basic_usr}:{$basic_pwd}")
				: false;

			// Update options
			update_option(self::OPTION_STATIC_URL, $static_url);
			update_option(self::OPTION_STATIC_DIR, $static_dir);
			update_option(self::OPTION_STATIC_BASIC, $basic_auth);
			printf(
				'<div id="message" class="updated fade"><p><strong>%s</strong></p></div>'."\n",
				empty($err_message) ? __('Done!', self::TEXT_DOMAIN) : $err_message
				);

			$this->static_url = $static_url;
			$this->static_dir = $static_dir;
			$this->basic_auth = $basic_auth;
		}

		$basic_usr = $basic_pwd = '';
		if ( $this->basic_auth )
			list($basic_usr,$basic_pwd) = explode(':', base64_decode($this->basic_auth));
?>
		<div class="wrap" id="<?php echo self::OPTION_PAGE; ?>-options">
		<?php screen_icon(self::OPTION_PAGE.'-options'); ?>
		<h2><?php echo esc_html( $title ); ?></h2>
		<form method="post" action="<?php echo $this->admin_action;?>">
		<?php echo wp_nonce_field($nonce_action, $nonce_name, true, false) . "\n"; ?>
		<table class="wp-list-table fixed"><tbody>
		<?php $this->input_field('static_url', __('Static URL', self::TEXT_DOMAIN), $this->static_url); ?>
		<?php $this->input_field('static_dir', __('Save DIR (Document root)', self::TEXT_DOMAIN), $this->static_dir); ?>
		<?php $this->input_field('basic_usr', __('(OPTION) BASIC Auth User', self::TEXT_DOMAIN), $basic_usr); ?>
		<?php $this->input_field('basic_pwd', __('(OPTION) BASIC Auth Password', self::TEXT_DOMAIN), $basic_pwd, 'password'); ?>
		</tbody></table>
		<?php submit_button(); ?>
		</form>
		</div>
<?php
	}

	private function input_field($field, $label, $val, $type = 'text'){
		$label = sprintf('<th><label for="%1$s">%2$s</label></th>'."\n", $field, $label);
		$input_field = sprintf('<td><input type="%3$s" name="%1$s" value="%2$s" id="%1$s" size=100 /></td>'."\n", $field, esc_attr($val), $type);
		echo "<tr>\n{$label}{$input_field}</tr>\n";
	}

	public function static_static_page(){
		$title = __('Rebuild', self::TEXT_DOMAIN);
?>
		<div class="wrap" style="margin=top:2em;" id="<?php echo self::OPTION_PAGE; ?>">
		<?php screen_icon(); ?>
		<h2><?php echo esc_html( $title ); ?></h2>
		<?php submit_button(__('Rebuild', self::TEXT_DOMAIN), 'primary', 'rebuild'); ?>
		<div id="rebuild-result"></div>
		</div>
<?php

		wp_enqueue_script('jQuery', false, array(), false, true);
		add_action('admin_footer', array(&$this, 'admin_footer'));
	}

	public function admin_footer(){
		$admin_ajax = admin_url('admin-ajax.php');
?>
<script type="text/javascript">
jQuery(function($){
	var file_count = 0;
	var loader = $('<div id="loader" style="line-height: 115px; text-align: center;"><img alt="activity indicator" src="<?php echo plugins_url( 'images/ajax-loader.gif' , dirname(__FILE__) ); ?>"></div>');

	function static_static_init(){
		file_count = 0;
		$('#rebuild').hide();
		$('#rebuild-result')
			.html('<p><strong><?php echo __('Initialyze...',   self::TEXT_DOMAIN);?></strong></p>')
			.after(loader);
		$.ajax('<?php echo $admin_ajax; ?>',{
			data: {action: 'static_static_init'},
			cache: false,
			dataType: 'json',
			type: 'POST',
			success: function(response){
				<?php if (self::DEBUG_MODE) echo "console.log(response);\n" ?>
				if (response.result) {
					$('#rebuild-result').append('<p><strong><?php echo __('URLS',   self::TEXT_DOMAIN);?></strong></p>')
					var ul = $('<ul></ul>');
					$.each(response.urls_count, function(){
						ul.append('<li>' + this.type + ' (' + this.count + ')</li>');
					});
					$('#rebuild-result').append('<p></p>').append(ul);
				}
				$('#rebuild-result').append('<p><strong><?php echo __('Fetch Start...',   self::TEXT_DOMAIN);?></strong></p>');				
				static_static_fetch();
			},
			error: function(){
				$('#rebuild').show();
				$('#loader').remove();
				$('#rebuild-result').append('<p id="message"><strong><?php echo __('Error!',   self::TEXT_DOMAIN);?></strong></p>');
				$('html,body').animate({scrollTop: $('#message').offset().top},'slow');
				file_count = 0;
			}
		});
	}

	function static_static_fetch(){
		$.ajax('<?php echo $admin_ajax; ?>',{
			data: {action: 'static_static_fetch'},
			cache: false,
			dataType: 'json',
			type: 'POST',
			success: function(response){
				if ($('#rebuild-result ul.result-list').size() == 0)
					$('#rebuild-result').append('<p class="result-list-wrap"><ul class="result-list"></ul></p>');				
				if (response.result) {
					<?php if (self::DEBUG_MODE) echo "console.log(response);\n" ?>
					var ul = $('#rebuild-result ul.result-list');
					$.each(response.files, function(){
						if (this.static) {
							file_count++;
							ul.append('<li>' + file_count + ' : ' + this.static + '</li>');
						}
					});
					$('html,body').animate({scrollTop: $('li:last-child', ul).offset().top},'slow');
					if (response.final)
						static_static_finalyze();
					else
						static_static_fetch();
				} else {
					static_static_finalyze();
				}
			},
			error: function(){
				$('#rebuild').show();
				$('#loader').remove();
				$('#rebuild-result').append('<p id="message"><strong><?php echo __('Error!',   self::TEXT_DOMAIN);?></strong></p>');
				$('html,body').animate({scrollTop: $('#message').offset().top},'slow');
				file_count = 0;
			}
		});
	}

	function static_static_finalyze(){
		$.ajax('<?php echo $admin_ajax; ?>',{
			data: {action: 'static_static_finalyze'},
			cache: false,
			dataType: 'json',
			type: 'POST',
			success: function(response){
				<?php if (self::DEBUG_MODE) echo "console.log(response);\n" ?>
				$('#rebuild').show();
				$('#loader').remove();
				$('#rebuild-result').append('<p id="message"><strong><?php echo __('End',   self::TEXT_DOMAIN);?></strong></p>');
				$('html,body').animate({scrollTop: $('#message').offset().top},'slow');
				file_count = 0;
			},
			error: function(){
				$('#rebuild').show();
				$('#loader').remove();
				$('#rebuild-result').append('<p id="message"><strong><?php echo __('Error!',   self::TEXT_DOMAIN);?></strong></p>');
				$('html,body').animate({scrollTop: $('#message').offset().top},'slow');
				file_count = 0;
			}
		});
	}

	$('#rebuild').click(static_static_init);
});
</script>
<?php
	}

	//**************************************************************************************
	// Add setting link
	//**************************************************************************************
	public function plugin_setting_links($links, $file) {
		if ($file === $this->plugin_basename) {
			$settings_link = '<a href="' . $this->admin_action . '">' . __('Settings') . '</a>';
			array_unshift($links, $settings_link); // before other links
		}

		return $links;
	}
}
