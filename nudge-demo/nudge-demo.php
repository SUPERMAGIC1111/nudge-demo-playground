<?php
/**
 * Plugin Name: NUDGE Demo
 * Description: Demo of NUDGE inside WooCommerce. Builds a marketing email in the browser. "Send test email" goes ONLY to your own admin address. No customers are contacted.
 * Version: 0.1.0
 * Author: AIgotya
 * License: GPL-2.0-or-later
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

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
		<p>Build an email below. <strong>Send test email</strong> sends it only to <code><?php echo esc_html( $me->user_email ); ?></code> (you). No customers are contacted. Customer data in the editor is made up.</p>
		<p>
			<button class="button button-primary" id="nd-send">Send test email to me</button>
			<span id="nd-msg" style="margin-left:12px"></span>
		</p>
		<iframe id="nd-frame" src="<?php echo $editor; ?>" style="width:100%;height:1500px;border:1px solid #dcdcde;border-radius:12px;background:#fff" title="NUDGE editor"></iframe>
	</div>
	<script>
	(function(){
		var f=document.getElementById('nd-frame'),btn=document.getElementById('nd-send'),msg=document.getElementById('nd-msg'),editorOrigin=<?php echo wp_json_encode( $editor_origin ); ?>;
		btn.addEventListener('click',function(){msg.textContent='Preparing…';f.contentWindow.postMessage({type:'nudge-export-request'},editorOrigin)});
		window.addEventListener('message',function(e){
			if(e.origin!==editorOrigin||e.source!==f.contentWindow||!e.data||e.data.type!=='nudge-export')return;
			var p=e.data.payload,fd=new FormData();
			fd.append('action','nudge_demo_send');fd.append('_wpnonce','<?php echo esc_js( $nonce ); ?>');fd.append('subject',p.subject);fd.append('html',p.html);
			fetch(ajaxurl,{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json()}).then(function(j){msg.textContent=j.success?('Sent to '+j.data.to+'. Check the mail log (Tools > WP Mail Logging).'):('Failed: '+(j.data&&j.data.message||'unknown'))}).catch(function(){msg.textContent='Failed.'});
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
