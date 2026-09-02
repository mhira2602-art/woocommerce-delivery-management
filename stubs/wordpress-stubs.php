<?php
/**
 * Minimal WordPress function stubs for static analysis in a non-WordPress runtime.
 */

declare( strict_types=1 );

namespace {
	function __($text, $domain = null) { return $text; }
	function _e($text, $domain = null): void { }
	function esc_html($text) { return $text; }
	function esc_html__($text, $domain = null) { return $text; }
	function esc_attr($text) { return $text; }
	function esc_attr__($text, $domain = null) { return $text; }
	function esc_url($url) { return $url; }
	function esc_sql($sql) { return $sql; }
	function wp_unslash($value) { return $value; }
	function sanitize_text_field($value) { return $value; }
	function sanitize_email($value) { return $value; }
	function sanitize_key($value) { return $value; }
	function wp_nonce_field($action, $name = '_wpnonce', $referer = true, $echo = true): void { }
	function wp_verify_nonce($nonce, $action = -1) { return true; }
	function admin_url($path = '') { return $path; }
	function wp_safe_redirect($location, $status = 302) { return true; }
	function wp_die($message = '', $title = '', $args = array()) { return null; }
	function selected($selected, $current, $echo = true) { return $selected === $current ? ' selected="selected"' : ''; }
	function checked($checked, $current, $echo = true) { return $checked === $current ? ' checked="checked"' : ''; }
	function wp_kses_post($value) { return $value; }
	function add_action($hook, $function_to_add, $priority = 10, $accepted_args = 1): void { }
	function add_filter($hook, $function_to_add, $priority = 10, $accepted_args = 1): void { }
	function current_user_can($capability) { return true; }
	function user_can($user, $capability) { return true; }
	function get_userdata($user_id) { return null; }
	function wp_get_current_user() { return new \WP_User(); }
	function add_menu_page($page_title, $menu_title, $capability, $menu_slug, $function = '', $icon_url = '', $position = null) { return null; }
	function add_submenu_page($parent_slug, $page_title, $menu_title, $capability, $menu_slug, $function = '', $position = null) { return null; }
	function get_option($option, $default = false) { return $default; }
	function update_option($option, $value, $autoload = null) { return true; }
	function is_admin() { return true; }
	function plugin_dir_path($file) { return dirname($file) . '/'; }
	function plugin_dir_url($file) { return 'http://example.com/'; }
	function plugin_basename($file) { return basename($file); }
	function get_charset_collate() { return ''; }
	function is_readable($path) { return false; }
	function is_object($value) { return is_object($value); }
	function __return_true() { return true; }
	function __return_false() { return false; }
	function get_permalink($post) { return ''; }
	function home_url($path = '') { return $path; }
	function wp_nonce_ays($action) { return ''; }
	function is_user_logged_in() { return true; }
}

namespace {
	class WP_User {
		public array $roles = array( 'administrator' );
		public function has_cap($capability) { return true; }
	}
}
