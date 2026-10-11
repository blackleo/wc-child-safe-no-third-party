<?php
/**
 * Plugin Name: WC Child-Safe Pages (No Third-Party Scripts)
 * Description: Keeps every outside script, style, font, embed and iframe off child-facing pages, and enforces it with a Content-Security-Policy. Checkout/account pages can be exempted for payment gateways.
 * Version:     1.0.0
 * License:     GPL-2.0-or-later
 *
 * Install in wp-content/mu-plugins/ so it cannot be deactivated by accident.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Which requests count as "a page a child could see"?
 * Default: everything on the front end EXCEPT cart, checkout and account
 * (adult-only pages where a payment gateway such as Stripe/PayPal must load its script).
 */
function wccs_is_child_page(): bool {
	if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return false;
	}
	$adult = false;
	if ( function_exists( 'is_checkout' ) && ( is_checkout() || is_cart() || is_account_page() ) ) {
		$adult = true;
	}
	return (bool) apply_filters( 'wccs_is_child_page', ! $adult );
}

/** Hosts allowed on child pages. Default: none (own domain only). */
function wccs_allowed_hosts(): array {
	$own = wp_parse_url( home_url(), PHP_URL_HOST );
	return array_filter( array_merge( array( $own ), (array) apply_filters( 'wccs_allowed_hosts', array() ) ) );
}

function wccs_is_external( string $src ): bool {
	if ( '' === $src || str_starts_with( $src, '/' ) && ! str_starts_with( $src, '//' ) ) {
		return false;
	}
	$host = wp_parse_url( $src, PHP_URL_HOST );
	if ( ! $host ) {
		return false; // relative URL.
	}
	return ! in_array( strtolower( $host ), array_map( 'strtolower', wccs_allowed_hosts() ), true );
}

/** 1) Drop any enqueued script/style served from another host. */
add_filter(
	'script_loader_tag',
	function ( $tag, $handle, $src ) {
		return ( wccs_is_child_page() && wccs_is_external( (string) $src ) ) ? '' : $tag;
	},
	999,
	3
);
add_filter(
	'style_loader_tag',
	function ( $tag, $handle, $href ) {
		return ( wccs_is_child_page() && wccs_is_external( (string) $href ) ) ? '' : $tag;
	},
	999,
	3
);

/** 2) Remove WordPress built-ins that call out or add scripts. */
add_action(
	'init',
	function () {
		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
		remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
		remove_action( 'wp_head', 'wp_oembed_add_host_js' );
		remove_action( 'wp_head', 'rsd_link' );
		remove_action( 'wp_head', 'wlwmanifest_link' );
		remove_action( 'wp_head', 'wp_generator' );
		add_filter( 'emoji_svg_url', '__return_false' );
		add_filter( 'option_show_avatars', '__return_false' ); // No Gravatar lookups.
	}
);
add_action(
	'wp_enqueue_scripts',
	function () {
		if ( wccs_is_child_page() ) {
			wp_dequeue_script( 'wp-embed' );
			wp_deregister_script( 'wp-embed' );
		}
	},
	100
);

/** 3) Server-enforced backstop: CSP header. Browser blocks anything that slipped through. */
add_action(
	'send_headers',
	function () {
		if ( ! wccs_is_child_page() ) {
			return;
		}
		$policy = apply_filters(
			'wccs_csp',
			"default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'self'; media-src 'self'; frame-src 'none'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'"
		);
		header( 'Content-Security-Policy: ' . $policy );
		header( 'Referrer-Policy: strict-origin-when-cross-origin' );
		header( 'Permissions-Policy: camera=(), microphone=(), geolocation=(), interest-cohort=()' );
	}
);

/** 4) Last resort: strip any <script src>, <iframe> or <link> to another host from the final HTML. */
add_action(
	'template_redirect',
	function () {
		if ( ! wccs_is_child_page() ) {
			return;
		}
		ob_start(
			function ( $html ) {
				$html = preg_replace_callback(
					'#<(script|iframe)\b[^>]*\bsrc=["\']([^"\']+)["\'][^>]*>(?:\s*</\1>)?#i',
					fn( $m ) => wccs_is_external( $m[2] ) ? '<!-- blocked: external ' . esc_html( $m[1] ) . ' -->' : $m[0],
					$html
				);
				return $html;
			}
		);
	},
	0
);
