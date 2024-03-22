<?php
/**
 * Functions to handle article search related apis, saved searches for article.
 *
 *
 * @package Houzi Mobile Api
 * @since Houzi 1.3.9
 * @author Adil Soomro
 */


//-----------------------------Lightspeed exclude URLs-------------------------------------
// By default all POST URLs aren't cached
add_action( 'litespeed_init', function () {
    
    //these URLs need to be excluded from lightspeed caches
    $exclude_url_list = array(
        "saved-searches"
    );
    foreach ($exclude_url_list as $exclude_url) {
        if (strpos($_SERVER['REQUEST_URI'], $exclude_url) !== FALSE) {
            do_action( 'litespeed_control_set_nocache', 'no-cache for rest api' );
        }
    }
    //add these URLs to cache if required (even POSTs)
    $include_url_list = array(
        "sample-url",
        "search-test"
    );
    foreach ($include_url_list as $include_url) {
        if (strpos($_SERVER['REQUEST_URI'], $include_url) !== FALSE) {
            do_action( 'litespeed_control_set_cacheable', 'cache for rest api' );
        }
    }
});


// houzez-mobile-api/v1/search-article
add_action( 'rest_api_init', function () {
  register_rest_route( 'houzez-mobile-api/v1', '/search-articles', array(
    'methods' => 'POST',
    'callback' => 'search_articles',
    'permission_callback' => '__return_true'
  ));
  register_rest_route( 'houzez-mobile-api/v1', '/article-comments', array(
    'methods' => 'POST',
    'callback' => 'list_article_comments',
    'permission_callback' => '__return_true'
  ));
  register_rest_route( 'houzez-mobile-api/v1', '/add-comment', array(
    'methods' => 'POST',
    'callback' => 'add_comment',
    'permission_callback' => '__return_true'
  ));

});

function search_articles() {
    
    $query_args = setup_article_search_query();
    query_article_and_send_json($query_args);
}

function query_article_and_send_json($query_actual) {

    $query_args = new WP_Query( $query_actual );
    
    $articles = array();
    $found_posts = $query_args->found_posts;
    while( $query_args->have_posts() ):
        $query_args->the_post();
        $article = $query_args->post;
        array_push($articles, article_node($article) );
        
    endwhile;

    wp_reset_postdata();
    wp_send_json( array( 'success' => true ,'count' => $found_posts , 'result' => $articles), 200);
    //wp_send_json( array( 'success' => true, 'query' => $query_actual), 200);
}



function setup_article_search_query() {
    $meta_query = array();
    $tax_query = array();
    $date_query = array();
    $allowed_html = array();
    $keyword_array =  '';

    

    $article_id = isset($_POST['article_id']) ? ($_POST['article_id']) : '';

    $tag_id = isset($_POST['tag_id']) ? ($_POST['tag_id']) : '';
    $tag_slug = isset($_POST['tag_slug']) ? ($_POST['tag_slug']) : '';
    $category_id = isset($_POST['category_id']) ? ($_POST['category_id']) : '';
    $category_slug = isset($_POST['category_slug']) ? ($_POST['category_slug']) : '';

    $keyword = isset($_POST['keyword']) ? $_POST['keyword'] : '';
    $featured = isset($_POST['featured']) ? ($_POST['featured']) : '';
    
    
    $publish_date = isset($_POST['publish_date']) ? ($_POST['publish_date']) : '';

    
    $author_id = isset($_POST['author_id']) ? ($_POST['author_id']) : '';

    
    $page = isset($_POST['page']) ? (int) $_POST['page'] : 1;
    $per_page = isset($_POST['per_page']) ? (int) $_POST['per_page'] : 20;

    $query_args = array(
        'post_type'      => 'post',
        'posts_per_page' => -1,
        'post_status' => 'publish'
    );

    // Article ID
    if( !empty( $article_id )  ) {
        $query_args['ID'] = $article_id;
    }

    $keyword = stripcslashes($keyword);

    if ( !empty( $keyword )) {
        $keyword = trim( $keyword );
        if ( ! empty( $keyword ) ) {
            $query_args['s'] = $keyword;
        }    
    }

    //Date Query
    if( !empty($publish_date) ) {
        $publish_date = explode('/', $publish_date);
        $query_args['date_query'] = array(
            array(
                'year' => $publish_date[2],
                'compare'   => '>=',
            ),
            array(
                'month' => $publish_date[1],
                'compare'   => '>=',
            ),
            array(
                'day' => $publish_date[0],
                'compare'   => '>=',
            )
        );
    }

    // tag id
    if( !empty( $tag_id )  ) {
        $query_args['tag_id'] = $tag_id;
    }
    // tag slug
    if( !empty( $tag_slug )  ) {
        $query_args['tag'] = $tag_slug;
    }

    // category id
    if( !empty( $category_id )  ) {
        $query_args['cat'] = $category_id;
    }
    // category slug
    if( !empty( $category_slug )  ) {
        $query_args['category_name'] = $category_slug;
    }

    
    if(!empty($featured)) {
        $meta_query[] = array(
            'key' => 'featured',
            'value' => $featured,
            'type' => 'CHAR',
            'compare' => '=',
        );
    }

     // author id logic
     if( !empty( $author_id )) {    
        $query_args['author'] = $author_id;
    }

    $meta_count = count($meta_query);

    if( $meta_count > 0 || !empty($keyword_array)) {
        $query_args['meta_query'] = array(
            'relation' => 'AND',
            $keyword_array,
            array(
                'relation' => 'AND',
                $meta_query
            ),
        );
    }

    
    $tax_count = count($tax_query);
    $tax_query['relation'] = 'AND';
    if( $tax_count > 0 ) {
        $query_args['tax_query']  = $tax_query;
    }
    
    $query_args['paged'] = $page;

    if( $per_page > 0 ) {
        $query_args['posts_per_page']  = $per_page;
    }
    return $query_args;
}
function article_node($article){
    
    $post_id = $article->ID;
    
    $article->thumbnail    = get_the_post_thumbnail_url( $post_id, "thumbnail" );
    $article->photo    = get_the_post_thumbnail_url( $post_id, "full" );
    $article->meta    = get_post_meta($post_id);
    $author_info = array();
    $author_info["id"]   = $article->post_author;
    $author_info["name"]   = get_author_name($article->post_author);
	$author_info["avatar"] = get_avatar_url($article->post_author); 
    $article->author = $author_info;
    $article->categories = get_current_language_categories($post_id);
    $article->tags = get_current_language_tags($post_id);
    $article->comment_count = get_comment_count($post_id);
    
    return $article;
}
function get_current_language_categories($post_id) {
    $current_lang = apply_filters( 'wpml_current_language', "en" );

    $post_categories = wp_get_post_categories($post_id);
    $localized_categories = array();
    if (! empty($post_categories)){
        foreach ($post_categories as $category) :
        $localized_term_id = apply_filters( 'wpml_object_id', $category, "category", true, $current_lang );
        $term = get_term( $localized_term_id );
        
        $localized_categories[] = $term;
        
        endforeach;
    }
    return $localized_categories;
}
function get_current_language_tags($post_id) {
    $current_lang = apply_filters( 'wpml_current_language', "en" );

    $post_tags = wp_get_post_tags($post_id);
    $localized_tags = array();
    if (! empty($post_tags)){
        foreach ($post_tags as $tag) :
        $localized_term_id = apply_filters( 'wpml_object_id', $tag, "post_tag", true, $current_lang );
        $term = get_term( $localized_term_id );
        
        $localized_tags[] = $term;
        
        endforeach;
    }
    return $localized_tags;
}


if( !function_exists('add_comment') ) {
function add_comment() {
    if (! is_user_logged_in() ) {
        $ajax_response = array( 'success' => false, 'reason' => 'Please provide user auth.' );
        wp_send_json($ajax_response, 403);
        return; 
    }

    if (!create_nonce_or_throw_error('comment-security', 'comment-nonce')) {
        return;
    }
    
    $comment_content = wp_kses_post( $_POST['comment_content'] );

    if (empty($comment_content)) {
        $ajax_response = array( 'success' => false, 'reason' => 'Comment cannot be empty' );
        wp_send_json($ajax_response, 403);
        return; 
    }
    $comment_author_email = wp_kses_post( $_POST['comment_author_email'] );
    if (empty($comment_author_email)) {
        $ajax_response = array( 'success' => false, 'reason' => 'Comment author email required.' );
        wp_send_json($ajax_response, 403);
        return; 
    }
    $comment_author_name = wp_kses_post( $_POST['comment_author'] );
    if (empty($comment_author_name)) {
        $ajax_response = array( 'success' => false, 'reason' => 'Comment author name required.' );
        wp_send_json($ajax_response, 403);
        return; 
    }

    $user_id = get_current_user_id();

    $comment_post_ID = wp_kses_post( $_POST['comment_post_ID'] );
    
    $submission_action = intval($_POST['is_update']);
    $comment_ID = wp_kses_post( $_POST['comment_ID'] );
    $user_name = wp_get_current_user();
    
    $comment_parent = wp_kses_post( $_POST['comment_parent'] );    
    
    $data = array(
        'user_id' => $user_id,
        'comment_post_ID' => $comment_post_ID,
        'comment_content' => $comment_content,
    );
    
    if (!empty($comment_parent)) {
        $data['comment_parent'] = $comment_parent;
    }
    if (!empty($comment_author_email)) {
        $data['comment_author_email'] = $comment_author_email;
    }
    if (!empty($comment_author_name)) {
        $data['comment_author'] = $comment_author_name;
    }
    $candidate_comment_id = '';
    $comment_message = 'Comment added';
    if( $submission_action == 1 ) {

        $data['comment_ID'] = intval( $comment_ID );
        $comment = wp_update_comment( $data );
        $candidate_comment_id = $comment_ID;
        $comment_message = 'Comment updated';
    } else {
        
        $candidate_comment_id = wp_insert_comment($data);;
    }

    $ajax_response = array(
        'success' => true,
        'message' => $comment_message
    );
    array_push($ajax_response, $candidate_comment_id );
    wp_send_json($ajax_response, 200);
    
}}

function list_article_comments() {
    $query_args = setup_comment_search_query();
    query_comment_and_send_json($query_args);
}
function setup_comment_search_query() {
    
    $comment_id = isset($_POST['comment_id']) ? ($_POST['comment_id']) : '';

    
    $keyword = isset($_POST['keyword']) ? $_POST['keyword'] : '';
    
    $author_id = isset($_POST['author_id']) ? ($_POST['author_id']) : '';
    $post_id = isset($_POST['post_id']) ? ($_POST['post_id']) : '';
    $parent_comment_id = isset($_POST['parent_comment_id']) ? ($_POST['parent_comment_id']) : '';
    
    $page = isset($_POST['page']) ? (int) $_POST['page'] : 1;
    $per_page = isset($_POST['per_page']) ? (int) $_POST['per_page'] : 20;

    $query_args = array(
        'status' => 'approve',
        'no_found_rows' => false,
    );

    // comment ID
    if( !empty( $comment_id )  ) {
        $query_args['comment__in'] = array( $comment_id );
    }

    // parent comment ID
    if( !empty( $parent_comment_id )  ) {
        $query_args['parent'] = $parent_comment_id;
    }

    // post ID
    if( !empty( $post_id )  ) {
        $query_args['post_id'] = $post_id;
    }


    $keyword = stripcslashes($keyword);

    if ( !empty( $keyword )) {
        $keyword = trim( $keyword );
        if ( ! empty( $keyword ) ) {
            $query_args['search'] = $keyword;
        }    
    }

     // author id logic
     if( !empty( $author_id )) {    
        $query_args['user_id'] = $author_id;
    }

    
    
    $query_args['paged'] = $page;

    if( $per_page > 0 ) {
        $query_args['number']  = $per_page;
    }
    return $query_args;
}
function query_comment_and_send_json($query_actual) {

    $comments_query = new WP_Comment_Query( $query_actual );
    
    $comments = array();
    $comment_count = $comments_query->found_comments;
    $found_comments = $comments_query->comments;

    // Comment Loop
    
    if ( $found_comments ) {
        foreach ( $found_comments as $comment ) {
            $comment->comment_author_avatar = get_avatar_url($comment->user_id); 
            array_push($comments, $comment );
        }
    }

    wp_send_json( array( 'success' => true ,'count' => $comment_count , 'result' => $comments), 200);
}
