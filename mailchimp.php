<?php

/*
	People Place Sync
	------------------------
	
	Plugin Name: People Place Sync
	Plugin URI: https://gumroad.com/l/people-place
	Description: Sync a MailChimp subscriber list with the People Place Plugin
	Author: Community Powered
	Version: 1.0
	Author URI: http://creativepeopleplace.info

 */

// useful constants 
define('PPS_JS_URL',plugins_url('/assets/js',__FILE__));
define('PPS_CSS_URL',plugins_url('/assets/css',__FILE__));
define('PPS_IMAGES_URL',plugins_url('/assets/images',__FILE__));
define('PPS_KML_URL',plugins_url('/assets/kml',__FILE__));
define('PPS_PATH', dirname(__FILE__));
define('PPS_BASE', plugin_basename(__FILE__));
define('PPS_FILE', __FILE__);

/***************************************************************
* Function pps_create_schedule
* Register our schedule with WordPress or manual cron
***************************************************************/
	
add_action('init', 'pps_create_schedule', 100); // used to be wp_loaded

function pps_create_schedule() {

	$options = get_option('pps_options');
	
	// manual cron
	if (isset($_GET['pps_key'])) {
		
		if ($_GET['pps_key'] == $options['cron_key']) {
			
			// unregister existing cron
			wp_clear_scheduled_hook('pps_mailchimp_sync');

			// clean up transient
			delete_transient('pp_points');
			
			// sync 
			do_action('pps_mailchimp_sync');
		}
	}
	
	// wordpress cron
	if ($options['cron_key'] == '') {

		$recurrence = isset( $options['recurrence'] ) ? $options['recurrence'] : 'daily';
		
		// clear the schedule if recurrence was changed
		$schedule = wp_get_schedule('pps_mailchimp_sync');
		if ($schedule != $recurrence)
			wp_clear_scheduled_hook('pps_mailchimp_sync');
		
		if (!wp_next_scheduled('pps_mailchimp_sync')) {
			wp_schedule_event(time(), $recurrence, 'pps_mailchimp_sync');
		}
		
		// use to debug
		//wp_clear_scheduled_hook('pps_mailchimp_sync');
		//do_action('pps_mailchimp_sync');*/
	}
}

/***************************************************************
* Function pps_mailchimp_api_call
* Fetch and parse the mailchimp api
***************************************************************/

add_action('pps_mailchimp_sync', 'pps_mailchimp_api_call' );

function pps_mailchimp_api_call() {

	global $wpdb;
	
	// this may take a while
	set_time_limit(0);
 
	$options = get_option('pps_options');
	$author_id = isset( $options['author'] ) ? $options['author'] : 0;
	$author = get_user_by( 'id', $author_id );
	
	// don't do anything if not configured
	if (empty($author))
		return;

	// load mail chimp api
	require_once PPS_PATH . '/assets/lib/MCAPI.class.php';
	
	// if api key and or list id is empty then bail
	if (!isset($options['mailchimp_api_key'])) return;
    if (!isset($options['mailchimp_list_id'])) return;
    if ($options['mailchimp_api_key'] == '') return;
    if ($options['mailchimp_list_id'] == '') return;
    
	$api = new MCAPI($options['mailchimp_api_key']);
	
	$retval = $api->listMembers($options['mailchimp_list_id'], 'subscribed', null, 0, 15000);
	
	if ($api->errorCode){
	
		_e("Unable to load listMembers()!", 'pps');
		echo "\n\tCode=".$api->errorCode;
		echo "\n\tMsg=".$api->errorMessage."\n";
		
	} else {
		
		foreach($retval['data'] as $member){
		    
		    // grab this members details 
		    $member_details = $api->listMemberInfo($options['mailchimp_list_id'], $member['email']);
		    if ($api->errorCode){
		    
				_e("Unable to load listMemberInfo()!\n", 'pps');
				echo "\tCode=".$api->errorCode."\n";
				echo "\tMsg=".$api->errorMessage."\n";
			
			} else {
								
				$pid = wp_strip_all_tags($member_details['data'][0]['id']);
				$post = array(
					'post_title' => $pid,
					'post_name' => $pid,
					'post_status' => 'publish',
					'post_type' => 'pp',
					//'post_date' => get_date_from_gmt(date('Y-m-d H:i:s', time()-(60*60*5))),
					//'post_date_gmt' => date('Y-m-d H:i:s', time()-(60*60*5)),
					'post_author' => $author->ID,
				);

				// if this post already exists then update it
				$existing = new WP_Query(array('post_type' => 'pp', 'name' => $pid, 'post_status' => 'any'));
				
				if ($existing->have_posts()) {
				
					$existing->the_post();
					$post['ID'] = get_the_ID();
					$post['post_status'] = get_post_status();
					$post_id = wp_update_post($post);

				// else insert it				
				} else {
				
					$post_id = wp_insert_post( $post );
				
				}
			
		    	if ($member_details['data'][0]['merges']['POSTCODE'] != '') {
			    	
			    	$postcode = $member_details['data'][0]['merges']['POSTCODE']; 
			    	
					// update the post meta always
					update_post_meta($post_id, '_pp_postcode', $postcode);
				
			    	// lookup the postcode
			    	$coordinates = pp_map_get_coordinates($postcode);
			    	
			    	// save the results
			    	if (!empty($coordinates)) {
			    		if (isset($coordinates['lat'])) { update_post_meta($post_id, '_pp_lat', $coordinates['lat']); }
			    		if (isset($coordinates['lng'])) { update_post_meta($post_id, '_pp_lng', $coordinates['lng']); }
			    	}
		    	}
				
				if ($member_details['data'][0]['merges']['ORGANISATI'] == '') {
					//update_post_meta($post_id, '_pp_type', 'individual');				
					wp_set_object_terms($post_id, array('individual'), 'pp_category', false);
				} else {
					//update_post_meta($post_id, '_pp_type', 'organisation');
					wp_set_object_terms($post_id, array('organisation'), 'pp_category', false);
				}
			}
			
		    //print_r($member);
		    //echo $member['email']." - ".$member['timestamp']."\n";
		}
	}
	
	if ($_GET['pps_key'] == $options['cron_key']) {
		_e('Sync Complete', 'pps');
		exit;
	}	
}

/***************************************************************
* Function pps_register_settings
* Register settings with WordPress
***************************************************************/

add_action( 'admin_init', 'pps_register_settings' );

function pps_register_settings() {
	
	register_setting( 'pps_options', 'pps_options' );
	
	add_settings_section( 'pps_cron', __('Cron Settings', 'pps'), 'pps_section_cron', 'pps_options' );
	add_settings_field( 'recurrence', __('WordPress Cron Recurrence', 'pps'), 'pps_field_recurrence', 'pps_options', 'pps_cron' );
	add_settings_field( 'cron_key', __('Secret Key for Manual Cron','pps'), 'pps_field_cron_key', 'pps_options', 'pps_cron');

	add_settings_section( 'pps_general', __('Sync Settings', 'pps'), 'pps_section_general', 'pps_options' );
	add_settings_field( 'author', __('Author','pps'), 'pps_field_author', 'pps_options', 'pps_general' );
	add_settings_field( 'mailchimp_api_key', __('MailChimp API Key','pps'), 'pps_field_api_key', 'pps_options', 'pps_general');
	add_settings_field( 'mailchimp_list_id', __('MailChimp List ID','pps'), 'pps_field_list_id', 'pps_options', 'pps_general');

}

// cron section callback
function pps_section_cron() {
	_e('Entering a Secret Key will disable WordPress Cron.', 'pps');
}

// general section call back
function pps_section_general() {
	//_e('General Settings.', 'pps');
}

// recurrence
function pps_field_recurrence() {
	$schedules = wp_get_schedules();
	$options = get_option( 'pps_options' );
	$recurrence = isset( $options['recurrence'] ) ? $options['recurrence'] : 'daily';
?>
	<select name="pps_options[recurrence]">
	<?php foreach ( $schedules as $key => $schedule ): ?>
		<option value="<?php echo $key; ?>" <?php selected( $key == $recurrence ); ?>><?php echo $schedule['display']; ?></option>
	<?php endforeach; ?>
	</select>
<?php
}

// cron key
function pps_field_cron_key() {
	$options = get_option( 'pps_options' );
	$key = isset( $options['cron_key'] ) ? $options['cron_key'] : '';
?>
	<input type="text" name="pps_options[cron_key]" value="<?php echo esc_attr( $key ); ?>" size="50" />
<?php
}

// author
function pps_field_author() {
	$options = get_option( 'pps_options' );
	$author = isset( $options['author'] ) ? $options['author'] : '';
	wp_dropdown_users( array(
		'name' => 'pps_options[author]',
		'selected' => $author
	) );
}

// api key
function pps_field_api_key() {
	$options = get_option( 'pps_options' );
	$key = isset( $options['mailchimp_api_key'] ) ? $options['mailchimp_api_key'] : '';
?>
	<input type="text" name="pps_options[mailchimp_api_key]" value="<?php echo esc_attr( $key ); ?>" size="50" />
<?php
}

// list id
function pps_field_list_id() {
	$options = get_option( 'pps_options' );
	$id = isset( $options['mailchimp_list_id'] ) ? $options['mailchimp_list_id'] : '';
?>
	<input type="text" name="pps_options[mailchimp_list_id]" value="<?php echo esc_attr( $id ); ?>" size="50" />
<?php
}

/***************************************************************
* Function pps_options_page
* Add plugin options page under settings
***************************************************************/

add_action( 'admin_menu', 'pps_options_page' );

function pps_options_page() {
	add_options_page( __('MailChimp Options', 'pps'), __('MailChimp Sync','pps'), 'manage_options', 'pps_options', 'pps_options_page_contents' );
}

// Plugin Options page contents
function pps_options_page_contents() {
?>
<div class="wrap">
	<?php screen_icon(); ?>
	<h2><?php _e('People Place &amp; MailChimp Sync Options', 'pps'); ?></h2>
	
	<form method="post" action="options.php">
		<?php //wp_nonce_field( 'update-options' ); ?>
		<?php settings_fields( 'pps_options' ); ?>
		<?php do_settings_sections( 'pps_options' ); ?>
		<?php submit_button(); ?>
	</form>
</div>
<?php
}

/***************************************************************
* Function pps_admin_styles_script
* Load custom admin CSS
***************************************************************/

add_action( 'admin_enqueue_scripts', 'pps_admin_styles_scripts' );

function pps_admin_styles_scripts($hook) {

	global $pagenow, $post_type;

	// custom admin css
	if ($pagenow == 'options-general.php' && $_GET['page'] == 'pps_options') {
		wp_register_style('pps-admin', PPS_CSS_URL . '/admin.css', filemtime(PPS_PATH . '/assets/css/admin.css'));
		wp_enqueue_style('pps-admin');
	}

}