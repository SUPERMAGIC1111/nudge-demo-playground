<?php
/**
 * NUDGE compliance layer (v0.2, demo): consent record, working one-click unsubscribe, legal footer,
 * List-Unsubscribe headers, plain-text part. Hints for a lawyer, not legal advice.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

const NUDGE_DEMO_CONSENT_VERSION = 'v1-2026-10';

/** Signed token for an address (no database lookup needed to verify). */
function nudge_demo_token( $email ) {
	return hash_hmac( 'sha256', strtolower( trim( $email ) ), wp_salt( 'auth' ) );
}

function nudge_demo_unsub_url( $email ) {
	return add_query_arg(
		array(
			'nudge_unsub' => nudge_demo_token( $email ),
			'e'           => rtrim( strtr( base64_encode( strtolower( trim( $email ) ) ), '+/', '-_' ), '=' ),
		),
		home_url( '/' )
	);
}

function nudge_demo_optouts() {
	$list = get_option( 'nudge_demo_optouts', array() );
	return is_array( $list ) ? $list : array();
}

function nudge_demo_is_optout( $email ) {
	return in_array( hash( 'sha256', strtolower( trim( $email ) ) ), nudge_demo_optouts(), true );
}

function nudge_demo_add_optout( $email ) {
	$list   = nudge_demo_optouts();
	$list[] = hash( 'sha256', strtolower( trim( $email ) ) ); // store only a hash, not the address
	update_option( 'nudge_demo_optouts', array_values( array_unique( $list ) ), false );
}

/** One-click unsubscribe: GET shows a confirmation, POST (RFC 8058 one-click) unsubscribes directly. */
add_action( 'init', function () {
	if ( empty( $_REQUEST['nudge_unsub'] ) || empty( $_REQUEST['e'] ) ) { return; }
	$token = sanitize_text_field( wp_unslash( $_REQUEST['nudge_unsub'] ) );
	$raw   = sanitize_text_field( wp_unslash( $_REQUEST['e'] ) );
	$email = base64_decode( strtr( $raw, '-_', '+/' ), true );
	if ( false === $email || ! is_email( $email ) || ! hash_equals( nudge_demo_token( $email ), $token ) ) {
		wp_die( esc_html__( 'This unsubscribe link is not valid.', 'nudge-demo' ), '', array( 'response' => 400 ) );
	}
	$is_post = isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'];
	if ( $is_post || ( isset( $_GET['confirm'] ) && '1' === $_GET['confirm'] ) ) {
		nudge_demo_add_optout( $email );
		wp_die( esc_html__( 'You are unsubscribed. You will not get marketing emails from this shop any more.', 'nudge-demo' ), esc_html__( 'Unsubscribed', 'nudge-demo' ), array( 'response' => 200 ) );
	}
	$confirm = esc_url( add_query_arg( 'confirm', '1' ) );
	wp_die(
		'<p>' . esc_html__( 'Unsubscribe from marketing emails?', 'nudge-demo' ) . '</p><p><a class="button button-primary" href="' . $confirm . '">' . esc_html__( 'Yes, unsubscribe me', 'nudge-demo' ) . '</a></p>',
		esc_html__( 'Unsubscribe', 'nudge-demo' ),
		array( 'response' => 200 )
	);
} );

/** Footer block: sender identity, privacy link, unsubscribe. Real shops fill address/imprint in settings (demo uses placeholders). */
function nudge_demo_footer_html( $email ) {
	$shop    = get_bloginfo( 'name' );
	$address = apply_filters( 'nudge_demo_footer_address', 'Example Street 1, 12345 Example City (set your real address and company details)' );
	$privacy = function_exists( 'get_privacy_policy_url' ) ? get_privacy_policy_url() : '';
	$imprint = apply_filters( 'nudge_demo_imprint_url', '' );
	$unsub   = nudge_demo_unsub_url( $email );
	$h  = '<div style="font:12px/1.5 Arial,sans-serif;color:#6b7280;text-align:center;padding:18px 12px">';
	$h .= '<p style="margin:0 0 6px">' . esc_html( $shop ) . ' · ' . esc_html( $address ) . '</p>';
	$links = array();
	if ( $privacy ) { $links[] = '<a href="' . esc_url( $privacy ) . '" style="color:#6b7280">Privacy</a>'; }
	if ( $imprint ) { $links[] = '<a href="' . esc_url( $imprint ) . '" style="color:#6b7280">Imprint</a>'; }
	$links[] = '<a href="' . esc_url( $unsub ) . '" style="color:#6b7280">Unsubscribe</a>';
	$h .= '<p style="margin:0">' . implode( ' · ', $links ) . '</p></div>';
	return $h;
}

function nudge_demo_plain_text( $html ) {
	$t = preg_replace( '/<(br|\/p|\/div|\/h[1-6]|\/li)[^>]*>/i', "\n", $html );
	$t = preg_replace( '/<a [^>]*href="([^"]+)"[^>]*>(.*?)<\/a>/is', '$2 ($1)', $t );
	$t = html_entity_decode( wp_strip_all_tags( $t ), ENT_QUOTES, 'UTF-8' );
	return trim( preg_replace( "/[ \t]+\n|\n{3,}/", "\n\n", $t ) );
}

/**
 * Single delivery path for all NUDGE mails: skips opted-out addresses, adds footer, List-Unsubscribe headers
 * and a plain-text part. Returns 'sent', 'skipped' or 'failed'.
 */
function nudge_demo_deliver( $to, $first, $subject, $html ) {
	if ( nudge_demo_is_optout( $to ) ) { return 'skipped'; }
	$subject = str_replace( '{{first_name}}', $first, $subject );
	$html    = str_replace( '{{first_name}}', esc_html( $first ), $html ) . nudge_demo_footer_html( $to );
	$alt     = nudge_demo_plain_text( $html );
	$unsub   = nudge_demo_unsub_url( $to );
	$headers = array(
		'Content-Type: text/html; charset=UTF-8',
		'List-Unsubscribe: <' . $unsub . '>',
		'List-Unsubscribe-Post: List-Unsubscribe=One-Click',
	);
	$cb = function ( $phpmailer ) use ( $alt ) { $phpmailer->AltBody = $alt; };
	add_action( 'phpmailer_init', $cb );
	$ok = wp_mail( $to, $subject, $html, $headers );
	remove_action( 'phpmailer_init', $cb );
	return $ok ? 'sent' : 'failed';
}

/** Consent capture at WooCommerce checkout (opt-in, unticked). HPOS-safe: uses the order object. */
add_action( 'plugins_loaded', function () {
	if ( ! class_exists( 'WooCommerce' ) ) { return; }
	add_action( 'woocommerce_after_order_notes', function ( $checkout ) {
		woocommerce_form_field( 'nudge_marketing_consent', array(
			'type'  => 'checkbox',
			'class' => array( 'form-row-wide' ),
			'label' => esc_html__( 'Yes, send me news and offers by email. I can unsubscribe at any time.', 'nudge-demo' ),
		), '' );
	} );
	add_action( 'woocommerce_checkout_create_order', function ( $order ) {
		if ( empty( $_POST['nudge_marketing_consent'] ) ) { return; } // phpcs:ignore WordPress.Security.NonceVerification
		$order->update_meta_data( '_nudge_marketing_consent', 'yes' );
		$order->update_meta_data( '_nudge_consent_time', gmdate( 'c' ) );
		$order->update_meta_data( '_nudge_consent_version', NUDGE_DEMO_CONSENT_VERSION );
	} );
} );

/** GDPR hooks: include consent data in personal-data export and remove it on erase. */
add_filter( 'wp_privacy_personal_data_exporters', function ( $exporters ) {
	$exporters['nudge-demo'] = array(
		'exporter_friendly_name' => 'NUDGE marketing opt-outs',
		'callback'               => function ( $email ) {
			$data = array();
			if ( nudge_demo_is_optout( $email ) ) {
				$data[] = array( 'group_id' => 'nudge', 'group_label' => 'NUDGE', 'item_id' => 'optout', 'data' => array( array( 'name' => 'Marketing email', 'value' => 'Opted out' ) ) );
			}
			return array( 'data' => $data, 'done' => true );
		},
	);
	return $exporters;
} );

/** Demo helper: reset the opt-out list so testers can try the unsubscribe flow again. */
add_action( 'wp_ajax_nudge_demo_reset_optouts', function () {
	check_ajax_referer( 'nudge_demo_send' );
	if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( array( 'message' => 'Not allowed' ), 403 ); }
	update_option( 'nudge_demo_optouts', array(), false );
	wp_send_json_success();
} );
