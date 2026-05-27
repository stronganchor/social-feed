<?php
/*
Plugin Name: Social Feed
Description: Displays a grid of recent posts from an Instagram page using a shortcode.
Version: 1.0.2
Author: Strong Anchor Tech
Author URI: https://stronganchortech.com/
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function social_feed_get_instagram_access_token() {
	$token = '';

	if ( defined( 'SOCIAL_FEED_INSTAGRAM_ACCESS_TOKEN' ) && is_string( SOCIAL_FEED_INSTAGRAM_ACCESS_TOKEN ) ) {
		$token = SOCIAL_FEED_INSTAGRAM_ACCESS_TOKEN;
	} elseif ( is_string( getenv( 'SOCIAL_FEED_INSTAGRAM_ACCESS_TOKEN' ) ) ) {
		$token = getenv( 'SOCIAL_FEED_INSTAGRAM_ACCESS_TOKEN' );
	}

	return trim( (string) apply_filters( 'social_feed_instagram_access_token', $token ) );
}

function social_feed_sanitize_instagram_id( $value ) {
	$value = sanitize_text_field( wp_unslash( $value ) );
	$value = preg_replace( '/[^A-Za-z0-9_.-]/', '', $value );

	return substr( $value, 0, 128 );
}

function instagram_feed_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'handle' => '',
			'limit'  => 9,
		),
		$atts,
		'instagram_feed'
	);

	$handle       = social_feed_sanitize_instagram_id( $atts['handle'] );
	$limit        = max( 1, min( 25, absint( $atts['limit'] ) ) );
	$access_token = social_feed_get_instagram_access_token();

	if ( '' === $handle || '' === $access_token ) {
		return '<p>' . esc_html__( 'Instagram feed is not configured.', 'social-feed' ) . '</p>';
	}

	$cache_key = 'social_feed_instagram_' . md5( $handle . '|' . $limit . '|' . md5( $access_token ) );
	$cached    = get_transient( $cache_key );
	if ( is_string( $cached ) && '' !== $cached ) {
		return $cached;
	}

	$url = add_query_arg(
		array(
			'fields'       => 'caption,media_url,permalink',
			'limit'        => $limit,
			'access_token' => $access_token,
		),
		'https://graph.instagram.com/' . rawurlencode( $handle ) . '/media'
	);

	$response = wp_remote_get(
		$url,
		array(
			'timeout'     => 10,
			'redirection' => 0,
		)
	);

	if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
		$message = '<p>' . esc_html__( 'Unable to load Instagram feed.', 'social-feed' ) . '</p>';
		set_transient( $cache_key, $message, 2 * MINUTE_IN_SECONDS );
		return $message;
	}

	$data = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( empty( $data['data'] ) || ! is_array( $data['data'] ) ) {
		$message = '<p>' . esc_html__( 'No Instagram posts found.', 'social-feed' ) . '</p>';
		set_transient( $cache_key, $message, 2 * MINUTE_IN_SECONDS );
		return $message;
	}

	$output = '<div class="instagram-feed">';
	foreach ( $data['data'] as $post ) {
		$permalink = isset( $post['permalink'] ) ? esc_url( $post['permalink'] ) : '';
		$media_url = isset( $post['media_url'] ) ? esc_url( $post['media_url'] ) : '';
		$caption   = isset( $post['caption'] ) ? $post['caption'] : '';

		if ( '' === $permalink || '' === $media_url ) {
			continue;
		}

		$output .= '<div class="instagram-post">';
		$output .= '<a href="' . $permalink . '" target="_blank" rel="noopener noreferrer">';
		$output .= '<img src="' . $media_url . '" alt="' . esc_attr__( 'Instagram Post', 'social-feed' ) . '">';
		$output .= '</a>';
		if ( '' !== $caption ) {
			$output .= '<p>' . esc_html( $caption ) . '</p>';
		}
		$output .= '</div>';
	}
	$output .= '</div>';

	set_transient( $cache_key, $output, 10 * MINUTE_IN_SECONDS );

	return $output;
}
add_shortcode( 'instagram_feed', 'instagram_feed_shortcode' );
