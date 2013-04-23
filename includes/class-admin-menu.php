<?php
if ( !class_exists('InputValidator') )
	require_once(dirname(__FILE__).'/class-InputValidator.php');

class static_static_admin {
	const OPTION_STATIC_URL  = 'static static::static url';
	const OPTION_STATIC_DIR  = 'static static::static dir';
	const OPTION_PAGE = 'static-static';
	const TEXT_DOMAIN = 'static-static';
	const DEBUG_MODE  = TRUE;

	private $plugin_basename;
	private $static_url;
	private $static_dir;

	function __construct($plugin_basename){
		$this->static_url = get_option(static_static_admin::OPTION_STATIC_URL, '/');
		$this->static_dir = get_option(static_static_admin::OPTION_STATIC_DIR, ABSPATH);
		$this->plugin_basename = $plugin_basename;

		add_action('admin_menu', array(&$this, 'admin_menu'));
		add_filter('plugin_action_links', array(&$this, 'plugin_setting_links'), 10, 2 );
	}

	public function static_url(){
		return $this->static_url;
	}

	public function static_dir(){
		return $this->static_dir;
	}

	//**************************************************************************************
	// Add Admin Menu
	//**************************************************************************************
	public function admin_menu() {
		$title = __('Static Static', self::TEXT_DOMAIN);
		$this->admin_hook = add_options_page($title, $title, 'manage_options', self::OPTION_PAGE, array(&$this, 'options_page'));
		$this->admin_action = admin_url('/options-general.php') . '?page=' . self::OPTION_PAGE;
	}

	public function options_page(){
		$nonce_action  = 'update_options';
		$nonce_name    = '_wpnonce_update_options';

		$title = __('Static Static Options', self::TEXT_DOMAIN);

		$iv = new InputValidator('POST');
		$iv->set_rules($nonce_name, 'required');
		$iv->set_rules('static_url', array('trim','esc_html'));
		$iv->set_rules('static_dir', array('trim','esc_html'));

		// Update options
		if (!is_wp_error($iv->input($nonce_name)) && check_admin_referer($nonce_action, $nonce_name)) {
			// Get posted options
			$static_url = $iv->input('static_url');
			$static_dir = $iv->input('static_dir');

			// Update options
			update_option(self::OPTION_STATIC_URL, $static_url);
			update_option(self::OPTION_STATIC_DIR, $static_dir);
			printf(
				'<div id="message" class="updated fade"><p><strong>%s</strong></p></div>'."\n",
				empty($err_message) ? __('Done!', self::TEXT_DOMAIN) : $err_message
				);

			$this->static_url = $static_url;
			$this->static_dir = $static_dir;
		}

?>
		<div class="wrap">
		<?php screen_icon(); ?>
		<h2><?php echo esc_html( $title ); ?></h2>
		<form method="post" action="<?php echo $this->admin_action;?>">
		<?php echo wp_nonce_field($nonce_action, $nonce_name, true, false) . "\n"; ?>
		<table class="wp-list-table fixed"><tbody>
		<?php $this->input_field('static_url', __('Static URL', self::TEXT_DOMAIN), $this->static_url); ?>
		<?php $this->input_field('static_dir', __('Save DIR',   self::TEXT_DOMAIN), $this->static_dir); ?>
		</tbody></table>
		<?php submit_button(); ?>
		</form>
		</div>
<?php

		$this->static_static_page();
	}

	private function input_field($field, $label, $val){
		$label = sprintf('<th><label for="%1$s">%2$s</label></th>'."\n", $field, $label);
		$input_field = sprintf('<td><input type="text" name="%1$s" value="%2$s" id="%1$s" size=100 /></td>'."\n", $field, esc_attr($val));
		echo "<tr>\n{$label}{$input_field}</tr>\n";
	}

	private function static_static_page(){
		$title = __('Static Static', self::TEXT_DOMAIN);
?>
		<div class="wrap" style="margin=top:2em;">
		<?php screen_icon(); ?>
		<h2><?php echo esc_html( $title ); ?></h2>
		<?php submit_button(__('Rebuild',   self::TEXT_DOMAIN), 'primary', 'rebuild'); ?>
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
				$('#loader').remove();
				$('#rebuild-result').append('<p><strong><?php echo __('Error!',   self::TEXT_DOMAIN);?></strong></p>');				
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
				$('#loader').remove();
				$('#rebuild-result').append('<p><strong><?php echo __('Error!',   self::TEXT_DOMAIN);?></strong></p>');				
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
				$('#loader').remove();
				$('#rebuild-result').append('<p><strong><?php echo __('End',   self::TEXT_DOMAIN);?></strong></p>');
				file_count = 0;
			},
			error: function(){
				$('#loader').remove();
				$('#rebuild-result').append('<p><strong><?php echo __('Error!',   self::TEXT_DOMAIN);?></strong></p>');				
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