<?php
/*
Plugin Name: Eazyest Gallery IPTC Caption
Plugin URI: http://isabelcastillo.com/free-plugins/eazyest-gallery-iptc-caption
Description: Use the IPTC Title as your image captions for Eazyest Gallery images.
Version: 1.0
Author: Isabel Castillo
Author URI: http://isabelcastillo.com
License: GPL2
Text Domain: eazyest-gallery-iptc-caption
Domain Path: languages
*
* Copyright 2014 - 2015 Isabel Castillo

* Eazyest Gallery IPTC Caption is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 2 of the License, or
* any later version.
*
* Eazyest Gallery IPTC Caption is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with Eazyest Gallery IPTC Caption. If not, see <http://www.gnu.org/licenses/>.
*/

class Eazyest_Gallery_IPTC_Caption {

	private static $instance = null;

	public static function get_instance() {
		if ( null == self::$instance ) {
			self::$instance = new self;
		}
		return self::$instance;
	}

	private function __construct() {

		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		add_action( 'admin_menu', array($this, 'add_plugin_page' ) );
		add_action( 'admin_init', array($this, 'page_init' ) );
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );

	}
	
	function load_textdomain() {
		load_plugin_textdomain( 'eazyest-gallery-iptc-caption', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}

	/**
	* For each image in Eazyest Gallery, update caption with the IPTC/exif title if it exists, otherwise leave caption as is.
	* @since 0.1
	*/
    function update_captions(){
		
		set_time_limit(900);

		$post_type = 'galleryfolder';
		
		global $wpdb;
		$where = get_posts_by_author_sql( $post_type );
		$query = "SELECT * FROM $wpdb->posts p where p.post_type = 'attachment' AND (p.post_mime_type LIKE 'image/%')  AND (p.post_status = 'inherit') AND p.post_parent IN (SELECT $wpdb->posts.ID FROM $wpdb->posts  {$where} ) ORDER BY p.post_date DESC";
		$results =  $wpdb->get_results( $query );
		
		if( $results ) {

			foreach ( (array) $results as $image ) {

				$metadata = wp_get_attachment_metadata( $image->ID );

				if ( $metadata ) {
					$img_meta = isset($metadata['image_meta']) ? $metadata['image_meta'] : '';

					if ($img_meta) {
						$iptc_title = isset($img_meta['title']) ? $img_meta['title'] : '';

						// only update if iptc title exists, no sense in wasting resources

						if ($iptc_title) {
							$new_post_data = array(
								'ID'           => $image->ID,
								'post_excerpt' => $iptc_title
							);
							wp_update_post( $new_post_data );
						}

					}
				}

			}  // end foreach
		} // end if $results
	}

	/**
	* Run our script while sanitizing input field
	* @since 0.1
	*/
	function sanitize($input){

		// if they aggreed to disclaimer, then update captions
		if ( 'on' == $input ) { // @test that it does not run when not checked
			$this->update_captions();

			$type = 'updated';
			$message = __( 'Captions for your Eazyest Gallery Images have been updated.', 'eazyest-gallery-iptc-caption' );
		} else {
			$type = 'error';
			$message = __( 'Checkbox must be checked before Captions can be updated.', 'eazyest-gallery-iptc-caption' );

		}
		add_settings_error(
			'eg_iptc_update_caption_disclaimer',
			'',
			$message,
			$type
		);
		return $input;
    }

	/**
	* Add the plugin options page under the Eazyest Gallery menu
	* @since 0.1
	*/
	function add_plugin_page(){

		add_submenu_page( 'edit.php?post_type=galleryfolder', __('Eazyest Gallery IPTC Caption', 'eazyest-gallery-iptc-caption'), __('IPTC Caption', 'eazyest-gallery-iptc-caption'), 'manage_options', 'eg-iptc-caption', array($this, 'create_admin_page') );

    }

	/**
	* HTML for the options page
	* @since 0.1
	*/
	
	function create_admin_page(){ ?>
		<div class="wrap">
		<?php screen_icon(); ?>
		<h2><?php _e( 'Eazyest Gallery IPTC Title', 'eazyest-gallery-iptc-caption'); ?></h2>

		<p><?php _e('This will update the caption for all the image atttachments in your Eazyest Gallery. If an image has an IPTC title, then that will become the new caption. If an image does not have an IPTC title, then it will keep its existing caption.', 'eazyest-gallery-iptc-caption' ); ?></p>

		<p><?php _e('Please be patient after you click the button below. It could take a while if you have many images.', 'eazyest-gallery-iptc-caption' ); ?></p>

		<p><?php _e('When you are ready for the plugin to update the captions, click "Update Captions".', 'eazyest-gallery-iptc-caption' ); ?></p>

		<p><?php printf( __('%sNote:%s if you later add new images to Eazyest Gallery, they will not be affected by this update. If you want your new images to use the IPTC title as caption, then you will have to "Update Captions" again after you add the new pictures.', 'eazyest-gallery-iptc-caption' ), '<strong>', '</strong>' ); ?></p>

		<form method="post" action="options.php">
			<?php 
			settings_fields( 'eg-iptc-caption-settings-group' );
			do_settings_sections( 'eg-iptc-caption' );
			submit_button( __( 'Update Captions', 'eazyest-gallery-iptc-caption' ) ); ?>
		</form>
		</div>
		<?php
    }

	/**
	* Register the plugin settings
	* @since 0.1
	*/
	function page_init(){	
		register_setting('eg-iptc-caption-settings-group', 'eg_iptc_update_caption_disclaimer', array($this, 'sanitize'));
		add_settings_section(
			'eg_iptc_caption_main_settings',
			__( 'Update Captions', 'eazyest-gallery-iptc-caption' ),
			array( $this, 'main_setting_section_callback' ),
			'eg-iptc-caption'
		);

		add_settings_field(
			'eg_iptc_update_caption_disclaimer',
			__( 'Please Agree', 'eazyest-gallery-iptc-caption' ),
			array($this, 'eg_iptc_setting_callback'),
			'eg-iptc-caption',
			'eg_iptc_caption_main_settings'
		);
			
	} // end page_init

	/**
	* Main Settings section callback
	* @since 0.1
	*/
	function main_setting_section_callback() {
		return true;
	}

	/**
	* HTML for checkbox setting
	* @since 0.1
	*/

	function eg_iptc_setting_callback($args) {

	    $html = '<input type="checkbox" id="eg_iptc_update_caption_disclaimer" name="eg_iptc_update_caption_disclaimer"'; 

		if ( get_option( 'eg_iptc_update_caption_disclaimer' ) ) {
			$html .= ' checked="checked"';
		}

		$html .= ' /><label for="eg_iptc_update_caption_disclaimer">' . 
		
		__(' Check this to confirm that you understand that you are using this plugin at your own risk, and that Isabel Castillo will not be held liable under any circumstances for any adverse effects caused by this plugin. I have done my best to ensure that this works as described. You understand that this will update all captions so that you may lose dear captions that you have written in the backend.', 'eazyest-gallery-iptc-caption' );
		echo $html;
	}

	/**
	* Displays admin notices
	*/
	function admin_notices() {
		settings_errors( 'eg_iptc_update_caption_disclaimer' );
	}

}
$recent_posts_see_all = Eazyest_Gallery_IPTC_Caption::get_instance();
