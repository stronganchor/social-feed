<?php
/*
Plugin Name: Social Feed
Description: Displays a grid of recent posts from an Instagram page using a shortcode.
Version: 1.0.0
Author: Strong Anchor Tech
Author URI: https://stronganchortech.com/
*/

function instagram_feed_shortcode($atts) {
    $handle = $atts['handle'];
    $access_token = 'YOUR_ACCESS_TOKEN';
    $url = 'https://graph.instagram.com/' . $handle . '/media?fields=caption,media_url,permalink&access_token=' . $access_token;
    
    $response = wp_remote_get($url);
    $data = json_decode(wp_remote_retrieve_body($response));
    
    $output = '<div class="instagram-feed">';
    foreach ($data->data as $post) {
        $output .= '<div class="instagram-post">';
        $output .= '<a href="' . $post->permalink . '" target="_blank">';
        $output .= '<img src="' . $post->media_url . '" alt="Instagram Post">';
        $output .= '</a>';
        $output .= '<p>' . $post->caption . '</p>';
        $output .= '</div>';
    }
    $output .= '</div>';
    
    return $output;
}
add_shortcode('instagram_feed', 'instagram_feed_shortcode');
