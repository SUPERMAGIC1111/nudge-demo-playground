<?php
/**
 * Plugin Name: NUDGE Demo
 * Description: Synthetic NUDGE preview inside WooCommerce. Builds an editable example email in the browser; delivery is disabled.
 * Version: 0.2.1 (safe public preview)
 * Author: AIgotya
 * License: GPL-2.0-or-later
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

require_once __DIR__ . '/includes/compliance.php';

// First visit only: WooCommerce sends new admins to its own setup Home. Send them to the NUDGE demo instead (once).
add_action( 'admin_init', function () {
	if ( get_option( 'nudge_demo_landed' ) || wp_doing_ajax() || ! current_user_can( 'manage_options' ) ) { return; }
	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
	if ( 'wc-admin' === $page ) {
		update_option( 'nudge_demo_landed', 1 );
		wp_safe_redirect( admin_url( 'admin.php?page=nudge-demo' ) );
		exit;
	}
	if ( 'nudge-demo' === $page ) { update_option( 'nudge_demo_landed', 1 ); }
}, 99 );

add_action( 'admin_menu', function () {
	add_menu_page( 'NUDGE (demo)', 'NUDGE (demo)', 'manage_options', 'nudge-demo', 'nudge_demo_page', 'dashicons-email-alt', 56 );
} );

function nudge_demo_page() {
	$editor = esc_url( plugins_url( 'editor.html', __FILE__ ) );
	$editor_origin = wp_parse_url( $editor, PHP_URL_SCHEME ) . '://' . wp_parse_url( $editor, PHP_URL_HOST );
	$editor_port = wp_parse_url( $editor, PHP_URL_PORT );
	if ( $editor_port ) { $editor_origin .= ':' . $editor_port; }
	?>
	<div class="wrap" style="max-width:1100px">
		<h1 style="display:flex;gap:12px;align-items:center">NUDGE <span style="font-size:12px;background:#f76e11;color:#fff;border-radius:999px;padding:3px 10px">demo</span></h1>
		<p>This is a synthetic visual preview. It builds an editable example email, but does not query WooCommerce customers, send email, or connect to an AI service. Download the preview to inspect it outside WordPress.</p>
		<p>
			<button class="button" disabled title="This public preview never sends email">Email sending disabled</button>
			<button class="button" id="nd-dl" disabled>Download email (.html)</button>
			<span id="nd-msg" style="margin-left:12px"></span>
			<br><small>Made-up example counts only. Consent, audience selection and delivery need the full NUDGE product and merchant-approved rules.</small>
		</p>
		<iframe id="nd-frame" src="<?php echo $editor; ?>" style="width:100%;height:1500px;border:1px solid #dcdcde;border-radius:12px;background:#fff" title="NUDGE editor"></iframe>
	</div>
	<script>
	(function(){
		var f=document.getElementById('nd-frame'),msg=document.getElementById('nd-msg'),editorOrigin=<?php echo wp_json_encode( $editor_origin ); ?>;
		var dl=document.getElementById('nd-dl'),ready=false,waiting=null;
		function clearWaiting(){if(waiting){window.clearTimeout(waiting);waiting=null}}
		function requestExport(){if(!ready){msg.textContent='Editor is still loading. Please wait…';return}dl.disabled=true;msg.textContent='Preparing download…';f.contentWindow.postMessage({type:'nudge-export-request'},editorOrigin);clearWaiting();waiting=window.setTimeout(function(){waiting=null;dl.disabled=false;msg.textContent='The editor did not respond. Reload this Playground and try again.'},10000)}
		dl.addEventListener('click',requestExport);
		f.addEventListener('load',function(){msg.textContent='Loading editor…';f.contentWindow.postMessage({type:'nudge-export-ready-request'},editorOrigin)});
		window.addEventListener('message',function(e){
			if(e.origin!==editorOrigin||e.source!==f.contentWindow||!e.data)return;
			if(e.data.type==='nudge-export-ready'){ready=true;clearWaiting();dl.disabled=false;msg.textContent='Editor ready. Email sending is disabled.';return}
			if(e.data.type!=='nudge-export')return;
			clearWaiting();dl.disabled=false;var q=e.data.payload,page='<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'+String(q.subject).replace(/[<>&]/g,'')+'</title></head><body style="margin:0">'+q.html+'</body></html>',a=document.createElement('a');a.href=URL.createObjectURL(new Blob([page],{type:'text/html'}));a.download='nudge-email.html';document.body.appendChild(a);a.click();a.remove();msg.textContent='Downloaded nudge-email.html. Open it in any browser.';
		});
	})();
	</script>
	<?php
}

// Demo only: a FIXED list of made-up customers on the reserved .test domain (cannot receive mail, never real people).
add_action( 'wp_ajax_nudge_demo_send_all', function () {
	check_ajax_referer( 'nudge_demo_send' );
	if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( array( 'message' => 'Not allowed' ), 403 ); }
	wp_send_json_error( array( 'message' => 'Email delivery is disabled in this public preview.' ), 403 );
} );

add_action( 'wp_ajax_nudge_demo_send', function () {
	check_ajax_referer( 'nudge_demo_send' );
	if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( array( 'message' => 'Not allowed' ), 403 ); }
	wp_send_json_error( array( 'message' => 'Email delivery is disabled in this public preview.' ), 403 );
} );
