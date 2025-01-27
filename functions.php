<?php

use likes\PostLike;

/**
 * Get post likes html output
 *
 * @return string likes
 */
function get_post_likes_html( int|\WP_Post $post = null ): string {
	return ( new PostLike() )->get_post_likes( $post );
}

/**
 * Get post likes
 */
function get_post_likes( int $post_id ) {
	return get_post_meta( $post_id, '_post_like_count', true );
}

function delete_post_likes( int $post_id ) {
	return delete_post_meta( $post_id, '_post_like_count', true );
}

function update_post_likes( int $post_id, int $val ) {
	return update_post_meta( $post_id, '_post_like_count', $val);
}