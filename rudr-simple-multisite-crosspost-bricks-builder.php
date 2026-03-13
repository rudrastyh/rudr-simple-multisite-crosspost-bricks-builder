<?php
/**
 * Plugin Name: Simple Multisite Crossposting – Bricks Builder
 * Plugin URI: https://rudrastyh.com/support/bricks-builder
 * Description: Adds better compatibility with Bricks Builder.
 * Network: true
 * Author: Misha Rudrastyh
 * Author URI: https://rudrastyh.com
 * Version: 1.4
 */

class Rudr_SMC_Bricks_Builder {

	function __construct() {

		add_filter( 'rudr_pre_crosspost_meta', array( $this, 'process' ), 25, 3 );

	}

	public function process( $meta_value, $meta_key, $object_id ) {

		if( ! in_array(
			$meta_key,
			array(
				'_bricks_page_header_2',
				'_bricks_page_content_2',
				'_bricks_page_footer_2',
			)
		) ) {
			return $meta_value;
		}

		// we are currently on a new blog by the way, let's remember it and switch back
		$new_blog_id = get_current_blog_id();
		// we will need global classes as well
		$new_blog_global_classes = get_option( 'bricks_global_classes', array() );
		$new_blog_global_classes_ids = wp_list_pluck( $new_blog_global_classes, 'id' ); 
		restore_current_blog();
		$global_classes = get_option( 'bricks_global_classes', array() );


		// now we convert the meta key json into an array of elements
		$bricks = wp_slash( maybe_unserialize( $meta_value ) );

		foreach( $bricks as &$brick ) {

			// we need to process global classes right away
			if( ! empty( $brick[ 'settings' ][ '_cssGlobalClasses' ] ) && is_array( $brick[ 'settings' ][ '_cssGlobalClasses' ] ) ) {
				foreach( $brick[ 'settings' ][ '_cssGlobalClasses' ] as $class_id ) {
					// we already have this global class, skip
					if( in_array( $class_id, $new_blog_global_classes_ids ) ) {
						continue;
					}
					// it is time to extract our global class
					$class = current( array_filter( $global_classes, function( $class ) use ( $class_id ) {
						return $class[ 'id' ] === $class_id;
					} ) );

					// let's add our new global class
					if( ! empty( $class ) ) {
						$new_blog_global_classes[] = $class;
					}

				}
			}

			switch( $brick[ 'name' ] ) {
				case 'image' : {
					if( ! empty( $brick[ 'settings' ][ 'image' ] ) ) {
						$brick[ 'settings' ][ 'image' ] = $this->process_image_in_brick( $brick[ 'settings' ][ 'image' ], $new_blog_id );
					}
					break;
				}
				case 'image-gallery':
				case 'carousel': {
					if( ! empty( $brick[ 'settings' ][ 'items' ][ 'images' ] ) ) {
						foreach( $brick[ 'settings' ][ 'items' ][ 'images' ] as &$image ) {
							$image = $this->process_image_in_brick( $image, $new_blog_id, $brick[ 'settings' ][ 'items' ][ 'size' ] );
						}
					}
					break;
				}
				case 'logo' : {
					if( ! empty( $brick[ 'settings' ][ 'logo' ] ) ) {
						$brick[ 'settings' ][ 'logo' ] = $this->process_image_in_brick( $brick[ 'settings' ][ 'logo' ], $new_blog_id );
					}
					break;
				}
				// handles only SVG icons (may need to add another one if someone is using images instead of svg)
				case 'icon' : {
					if( ! empty( $brick[ 'settings' ][ 'icon' ][ 'svg' ] ) ) {
					$brick[ 'settings' ][ 'icon' ][ 'svg' ] = $this->process_image_in_brick( $brick[ 'settings' ][ 'icon' ][ 'svg' ], $new_blog_id );
					}
					break;
				}
				// handles svg element, which is a 'file'
				case 'svg' : {
					if( ! empty( $brick[ 'settings' ][ 'file' ] ) ) {
					$brick[ 'settings' ][ 'file' ] = $this->process_image_in_brick( $brick[ 'settings' ][ 'file' ], $new_blog_id );
					}
					break;
				}
				default : {
					// processing background
					if( ! empty( $brick[ 'settings' ][ '_background' ][ 'image' ] ) ) {
						$brick[ 'settings' ][ '_background' ][ 'image' ] =  $this->process_image_in_brick( $brick[ 'settings' ][ '_background' ][ 'image' ], $new_blog_id );
					}
					break;
				}
			}

		}
		//file_put_contents( __DIR__ . '/log.txt', print_r( $bricks, true ) );
		// go back
		switch_to_blog( $new_blog_id );

		// update global classes on the target blog
		update_option( 'bricks_global_classes', $new_blog_global_classes );

		return maybe_serialize( $bricks );

	}

	private function process_image_in_brick( $image, $new_blog_id, $size = null ){
		if( empty( $image[ 'id' ] ) ) {
			return $image;
		}
		// our goal here is get an attachment_id
		$attachment_id = $image[ 'id' ];
		// we need some attachment data
		$attachment_data = Rudr_Simple_Multisite_Crosspost::prepare_attachment_data( $attachment_id );

		if( $attachment_data ) {
			switch_to_blog( $new_blog_id );
			$upload = Rudr_Simple_Multisite_Crosspost::maybe_copy_image( $attachment_data );
			if( $upload ) {
				$image[ 'id' ] = $upload[ 'id' ];
				$image[ 'full' ] = $upload[ 'url' ];
				$image[ 'url' ] = $upload[ 'url' ];
				// maybe we need to adjust size of an image
				$size = isset( $image[ 'size' ] ) ? $image[ 'size' ] : ( $size ? $size : null );
				if( $size && 'full' !== $size ) {
					$upload_sized = wp_get_attachment_image_src( $image[ 'id' ], $size );
					if( isset( $upload_sized[0] ) ) {
						$image[ 'url' ] = $upload_sized[0];
					}
				}
			}
			restore_current_blog();
		}

		return $image;

	}

}

new Rudr_SMC_Bricks_Builder();
