<?php
/**
 * Plugin Name: NUDGE Demo
 * Description: Demo of NUDGE inside WooCommerce. Builds a marketing email in the browser. "Send test email" goes ONLY to your own admin address. No customers are contacted.
 * Version: 0.1.0
 * Author: AIgotya
 * License: GPL-2.0-or-later
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

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
	$me     = wp_get_current_user();
	$nonce  = wp_create_nonce( 'nudge_demo_send' );
	?>
	<div class="wrap" style="max-width:1100px">
		<h1 style="display:flex;gap:12px;align-items:center">NUDGE <span style="font-size:12px;background:#f76e11;color:#fff;border-radius:999px;padding:3px 10px">demo</span></h1>
		<p>Build an email below. <strong>Send test email</strong> sends it only to <code><?php echo esc_html( $me->user_email ); ?></code> (the fake admin of this throwaway shop, not a real inbox). No real person is contacted. Customer data in the editor is made up. To see the result: click <strong>Open mail log</strong>, or <strong>Download email</strong> and open the file.</p>
		<p>
			<button class="button button-primary" id="nd-send">Send test email to me</button>
			<button class="button" id="nd-dl">Download email (.html)</button>
			<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=wpml_plugin_log' ) ); ?>">Open mail log</a>
			<span id="nd-msg" style="margin-left:12px"></span>
		</p>
		<iframe id="nd-frame" src="<?php echo $editor; ?>" style="width:100%;height:1500px;border:1px solid #dcdcde;border-radius:12px;background:#fff" title="NUDGE editor"></iframe>
	</div>
	<script>
	(function(){
		var f=document.getElementById('nd-frame'),btn=document.getElementById('nd-send'),msg=document.getElementById('nd-msg'),editorOrigin=<?php echo wp_json_encode( $editor_origin ); ?>;
		var mode='send',dl=document.getElementById('nd-dl');
		btn.addEventListener('click',function(){mode='send';msg.textContent='Preparing…';f.contentWindow.postMessage({type:'nudge-export-request'},editorOrigin)});
		dl.addEventListener('click',function(){mode='download';msg.textContent='Preparing…';f.contentWindow.postMessage({type:'nudge-export-request'},editorOrigin)});
		window.addEventListener('message',function(e){
			if(e.origin!==editorOrigin||e.source!==f.contentWindow||!e.data||e.data.type!=='nudge-export')return;
			if(mode==='download'){var q=e.data.payload,page='<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'+String(q.subject).replace(/[<>&]/g,'')+'</title></head><body style="margin:0">'+q.html+'</body></html>',a=document.createElement('a');a.href=URL.createObjectURL(new Blob([page],{type:'text/html'}));a.download='nudge-email.html';document.body.appendChild(a);a.click();a.remove();msg.textContent='Downloaded nudge-email.html. Open it in any browser.';return}
			var p=e.data.payload,fd=new FormData();
			fd.append('action','nudge_demo_send');fd.append('_wpnonce','<?php echo esc_js( $nonce ); ?>');fd.append('subject',p.subject);fd.append('html',p.html);
			fetch(ajaxurl,{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json()}).then(function(j){msg.textContent=j.success?('Sent to '+j.data.to+'. It is in the mail log now: click "Open mail log".'):('Failed: '+(j.data&&j.data.message||'unknown'))}).catch(function(){msg.textContent='Failed.'});
		});
	})();
	</script>
	<?php
}

add_action( 'wp_ajax_nudge_demo_send', function () {
	check_ajax_referer( 'nudge_demo_send' );
	if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( array( 'message' => 'Not allowed' ), 403 ); }
	$me      = wp_get_current_user();
	$to      = $me->user_email; // fixed: only ever the logged-in admin. Never a customer.
	$name    = $me->display_name ? $me->display_name : 'there';
	$subject = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : 'NUDGE test';
	$html    = isset( $_POST['html'] ) ? wp_kses_post( wp_unslash( $_POST['html'] ) ) : '';
	$subject = str_replace( '{{first_name}}', $name, '[NUDGE test] ' . $subject );
	$html    = str_replace( '{{first_name}}', esc_html( $name ), $html );
	$ok      = wp_mail( $to, $subject, $html, array( 'Content-Type: text/html; charset=UTF-8' ) );
	if ( $ok ) { wp_send_json_success( array( 'to' => $to ) ); }
	wp_send_json_error( array( 'message' => 'wp_mail returned false' ) );
} );
