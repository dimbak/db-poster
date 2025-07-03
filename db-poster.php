<?php
/**
 * Plugin Name:       DB-poster
 * Plugin URI:        https:///
 * Plugin URI:        https://myexample.com
 * Description:       Submit posts with unique title from the front end using the WordPress REST API
 * Version:           1.0
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            DB
 * Author URI:        https://author.example.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Update URI:        https://example.com/my-plugin/
 * Text Domain:       db-poster
 * Domain Path:       /languages
 *
 * This program is free software; you can redistribute it and/or modify it under the terms of the GNU
 * General License version 2, as published by the Free Software Foundation. You may NOT assume
 * that you can use any other version of the GPL.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without
 * even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


add_action( 'init', '1dbposter_load_textdomain' );

function dbposter_load_textdomain() {
	load_plugin_textdomain( 'db-poster', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' ); 
}

add_action( 'wp_enqueue_scripts', 'enqueued_scripts' );

function enqueued_scripts () {
	wp_register_script( 'poster', plugin_dir_url( __FILE__ ) . '/js/poster.js', array( 'jquery', 'wp-api-request' ), null, true );
	wp_localize_script( 'poster', 'wma', array(
    	'checking'   =>  __( "'Ελεγχος", 'db-poster' ),
		'new_title'  =>  __( 'Νέος τίτλος', 'db-poster' ),
		'not_found'  => __( 'Δεν βρέθηκε', 'db-poster' ),
		'title_exists' => __( 'Ο τιτλος υπάρχει', 'db-poster'),
		'not_allowed' => __( 'Δεν έχετε τα απαραίτητα δικαιώματα', 'db-poster'),
		'added_post' => __( 'Προστέθεικε', 'db-poster'),
    ));
	wp_enqueue_script( 'poster' );
	
}

add_shortcode( 'rest_post_shortcode', 'rest_new_post_form' );

function rest_new_post_form() {

	$current_user = $GLOBALS['current_user'];



	if ( is_user_logged_in() && current_user_can( 'edit_others_posts' ) ) {
	
	
		if ( isset( $_GET['filo-message'] ) ) {
			$form_status = sanitize_text_field( $_GET['filo-message'] );
			
			switch ( $form_status ) {
				case 'emptyfields':
					$message = __( 'Please fill the fields.', 'db-poster' );
					printf( '<div>' . $message . '</div>' );
					break;

				case 'titleexists':
					$message = __( 'This title exists', 'db-poster' );
					printf( '<div>' . $message . '</div>' );
					break;
				case 1:
					$message      = __( 'Success!', 'db-poster');
					if ( isset( $_GET['filo-newpost-id'] ) ) {
						$preview_link = sanitize_url( get_preview_post_link( $_GET['filo-newpost-id'] ) );
						$post_array   = sanitize_html( get_post( $_GET['filo-newpost-id'], ARRAY_A ) );
						printf( '<div class=""><p>' .esc_html( $message ). '</p> <a href=' .esc_url( $preview_link ). '>' . esc_html( $post_array['post_title'] ). '</a></div>' );
						break;
					}

				default:
					$message = __( 'Something went wrong.', 'db-poster' );
					break;
			}
		}
	?>
		<form action="#" method="POST" class="comment-form" id="rest-form-new-post">
			<?php wp_nonce_field( 'filo-nonce-action', 'filo-nonce-name' ); ?>
			<div class="form-group">
				<label for="post_title" class="post-title-label"><?php esc_html_e( 'Please enter the post title', 'db-poster' ); ?></label>
				<input type="text" class="form-title-input" name="post_title" />
				<label for="post_content"><?php esc_html_e( 'Please enter the post content', 'db-poster' ); ?></label>
				<textarea class="form-content-textarea" name="post_content" rows="5" cols="3" ></textarea>
			</div>
			<input type="hidden" name="post_author" value="<?php echo esc_html( $current_user->ID ); ?>"/>
			<input id="submit" type="submit" name="filo-submit-button-name" id="submit" class="submit" value="<?php esc_attr_e( 'Submit', 'db-poster' ); ?>" />
			<div><a href="<?php echo esc_url( wp_logout_url( get_permalink() ) ); ?>"> <?php esc_html_e( 'Logout', 'db-poster' ); ?></a></div> 
			
		</form>
<?php 
	} else if ( ! current_user_can( 'edit_other_posts' ) ) { ?>
	 <a href='<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>' > <?php  esc_html_e( 'Please login', 'db-poster' ) ?>  </a>
	<?php }
}


function rest_handle_form() {
	
	if ( ! isset( $_POST['filo-submit-button-name'] ) || ! isset( $_POST['filo-nonce-name'] ) )  {
		return;
	}

	if ( ! wp_verify_nonce( $_POST['filo-nonce-name'], 'filo-nonce-action' ) ) {
		error_log( 'nonce not verified' );
		return;
	}
	if ( current_user_can( 'edit_others_posts' ) ) {
	
		$clear_url = wp_parse_url( wp_get_referer(), PHP_URL_PATH );

		if ( ! empty( $_POST['post_title'] ) || ! empty ( $_POST['post_author'] ) || ! empty ( $_POST['post_content'] ) ) {
			$filo_author  = sanitize_text_field( $_POST['post_author'] );
			$filo_title   = sanitize_text_field( $_POST['post_title'] );
			$filo_content = sanitize_textarea_field( $_POST['post_content'] );

			$postarr = array(
				'post_author'  =>  $filo_author, 
				'post_title'   => $filo_title, 
				'post_content' => $filo_content, 
				'post_status'  => 'publish',
			);
	
			$args = array(
				'title' => $_POST['post_title'],
				'post_status' => 'publish',
			);
		
			// check if title exists
			$posts = get_posts( $args );

			// if $posts count = 0 then title does not exist

			if ( count( $posts ) == 0 ) {
				$new_post = wp_insert_post( $postarr, false);
				$url      = esc_url( add_query_arg(
								array(
									'filo-message' => 1,
									'filo-newpost-id' => $new_post,
									)
								) );

				
			} else {
				$url = esc_url( add_query_arg( 'filo-message', 'titleexists', $clear_url) );
				
			}
		}  else  {
			$url = esc_url( add_query_arg( 'filo-message', 'emptyfields', $clear_url) );
			
		} 
		
		wp_safe_redirect( $url );
		exit();
	} else {
		prints( 'wrong user role' );
	}
}

add_action( 'template_redirect', 'rest_handle_form' );

// register GET and POST routes. GET for checking if the title exists and POST to create the post.

add_action( 'rest_api_init', function () {
	register_rest_route( 'myplugin/v1', '/form-submissions/(?P<postTitle>[a-zA-Z0-9-]+)', array(
	  'methods' => 'GET',
	  'callback' => 'rest_get_callback',
	  'permission_callback' => '__return_true',
	  'args' => array(
		'postTitle' => array(
		  'validate_callback' => function( $param, $request, $key ) {
			return is_string( $param );
		  }
		),
	  ),
	) );

	register_rest_route( 'myplugin/v1', '/form-submissions/(?P<postTitle>[a-zA-Z0-9-]+)', array(
		'methods' => 'POST',
		'callback' => 'rest_post_callback',
		'permission_callback' => function () {
		  return current_user_can( 'edit_others_posts' );
		},
		'args' => array(
			'postTitle' => array(
			  'validate_callback' => function($param, $request, $key) {
				return is_string( $param );
			  }
			),
		  ),
	) );

});

function rest_post_callback($request) {

	$post_title = $request['postTitle'];
	// sent from poster.js it is the value of the textrea
	if ( isset( $_POST['postContent'] ) ) {
		$post_content = sanitize_textarea_field( $_POST['postContent'] );
	}
	
	$args = array(
		'ID'		   => '',
		'post_title'   => $post_title,
		'post_type'    => 'post',
		'post_content' => $post_content,
		'post_status'  => 'publish',
		'numberposts'  => 1,
		'post_author'  => 1,
	);

	$new_post = wp_insert_post( $args, false);

	if ( $new_post == 0 ) {
		return new WP_REST_RESPONSE( 'NOT ADDED', 400 );
	}

	return new WP_REST_RESPONSE( 'ADDED', 200 );
}


function rest_get_callback( $request ) {

	$post_title = $request->get_param( 'postTitle' );
	
	$args = array(
		'title'       => $post_title,
		'post_type'   => 'post',
		'post_status' => 'publish',
		'numberposts' => 1,
	);

	$my_posts = get_posts( $args );
	if ( count( $my_posts ) > 0 ) {
		return new WP_REST_Response( 'Title exists', 400 );
	}
	
	return new WP_REST_Response(  'New title' , 200 ); 
}
