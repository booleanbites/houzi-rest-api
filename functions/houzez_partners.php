<?php
 /**
 * Exposing Houzez Partners.
 *
 *
 * @package Houzi Mobile Api
 * @since Houzi 1.0
 * @author Hassan Ali @ BooleanBites Ltd.
 */
class HouziPartners {

	public function __construct() {
        add_action( 'registered_post_type', [$this, 'wpse_65075_modify_houzez_partner_rest_base'], 10, 2 );
        add_filter( 'rest_prepare_houzez_partner', [ $this, 'add_featured_image_url'], 10, 3 );
	}

    public function wpse_65075_modify_houzez_partner_rest_base( $post_type, $args ) {
		if ( 'houzez_partner' != $post_type )
			return;

		$args->show_in_rest = true;

		global $wp_post_types;
		$wp_post_types[$post_type] = $args;
    }

    public function add_featured_image_url( $data, $post, $request ) {
        // Retrieve the featured image URL and add it to the response
        $featured_image_id = $data->data['featured_media'];
        if ( $featured_image_id ) {
            $featured_image_url = wp_get_attachment_image_url( $featured_image_id, 'full' );
            if ( $featured_image_url ) {
                $data->data['featured_image_url'] = $featured_image_url;
            }
        }
        $meta = get_post_meta($post->ID);
        $data->meta = $meta;
        return $data;
    }

}
new HouziPartners;
