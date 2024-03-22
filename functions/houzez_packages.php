<?php
 /**
 * Exposing HouzezPackages.
 *
 *
 * @package Houzi Mobile Api
 * @since Houzi 1.0
 * @author Hassan Ali @ BooleanBites Ltd.
 */
class HouzezPackages {

    public function __construct() {
        add_filter( 'houzez_packages_meta', array( $this, 'houzez_packages_metaboxes' ) );
        add_filter( 'rest_prepare_houzez_packages', [ $this, 'add_additional_package_details'], 10, 3 );
    }

    public function add_additional_package_details( $data, $post, $request ) {
        $meta_data = get_post_meta( $post->ID );

        $additional_details = [];

        foreach ( $meta_data as $key => $value ) {
            $additional_details[ $key ] = $value[0];
        }

        $data->data['additional_details'] = $additional_details;

        return $data;
    }


    public function houzez_packages_metaboxes( $meta_boxes ) {
        foreach ( $meta_boxes as &$meta_box ) {
            if ( isset( $meta_box['post_types'] ) && in_array( 'houzez_packages', $meta_box['post_types'] ) ) {
                
                $meta_box['fields'][] = array(
                    'id' => "android_iap_product_id",
                    'name' => esc_html__( 'In App Purchase - Android Product ID', 'houzez' ),
                    'placeholder' => esc_html__( 'Enter the Android Product ID', 'houzez' ),
                    'type' => 'text',
                    'std' => "",
                    'columns' => 6,
                );
    
                $meta_box['fields'][] = array(
                    'id' => "ios_iap_product_id",
                    'name' => esc_html__( 'In App Purchase - iOS Product ID ', 'houzez' ),
                    'placeholder' => esc_html__( 'Enter the iOS Product ID', 'houzez' ),
                    'type' => 'text',
                    'std' => "",
                    'columns' => 6,
                );
    
            }
        }
    
        return $meta_boxes;
    }
}

new HouzezPackages;

