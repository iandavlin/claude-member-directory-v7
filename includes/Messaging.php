<?php
/**
 * BuddyBoss messaging integration.
 *
 * Provides a lightweight AJAX-backed compose form so visitors can message
 * a member-directory profile owner via the BuddyBoss messaging system.
 *
 * Static class: Messaging::init() wires the AJAX handler.
 * Messaging::is_available() checks whether BuddyBoss messaging is active.
 */

namespace MemberDirectory;

defined( 'ABSPATH' ) || exit;

class Messaging {

	/**
	 * Wire AJAX handler (logged-in users only — no nopriv variant).
	 */
	public static function init(): void {
		add_action( 'wp_ajax_memdir_ajax_send_message', [ self::class, 'handle_send_message' ] );
	}

	/**
	 * Whether BuddyBoss messaging is available on this site.
	 *
	 * Returns true only when the messages_new_message() function exists
	 * (BuddyBoss/BuddyPress loaded) AND the messages component is active.
	 */
	public static function is_available(): bool {
		return function_exists( 'messages_new_message' )
			&& function_exists( 'bp_is_active' )
			&& bp_is_active( 'messages' );
	}

	/**
	 * AJAX handler: send a BuddyBoss message on behalf of the logged-in user.
	 *
	 * Expected POST params:
	 *   nonce        — md_save_nonce
	 *   recipient_id — user ID of the profile author
	 *   subject      — message subject
	 *   content      — message body
	 */
	public static function handle_send_message(): void {
		// Verify nonce (reuses the plugin's existing nonce).
		if ( ! check_ajax_referer( 'md_save_nonce', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => 'Security check failed.' ], 403 );
		}

		// Must be logged in.
		$sender_id = get_current_user_id();
		if ( ! $sender_id ) {
			wp_send_json_error( [ 'message' => 'You must be logged in to send a message.' ], 401 );
		}

		// BuddyBoss messaging must be active.
		if ( ! self::is_available() ) {
			wp_send_json_error( [ 'message' => 'Messaging is not available.' ], 400 );
		}

		// Parse and sanitize inputs.
		$recipient_id = absint( $_POST['recipient_id'] ?? 0 );
		$subject      = sanitize_text_field( wp_unslash( $_POST['subject'] ?? '' ) );
		$content      = sanitize_textarea_field( wp_unslash( $_POST['content'] ?? '' ) );

		// Validate.
		if ( ! $recipient_id || ! get_userdata( $recipient_id ) ) {
			wp_send_json_error( [ 'message' => 'Invalid recipient.' ], 400 );
		}
		if ( $recipient_id === $sender_id ) {
			wp_send_json_error( [ 'message' => 'You cannot message yourself.' ], 400 );
		}
		if ( empty( $subject ) ) {
			wp_send_json_error( [ 'message' => 'Subject is required.' ], 400 );
		}
		if ( empty( $content ) ) {
			wp_send_json_error( [ 'message' => 'Message content is required.' ], 400 );
		}

		// Send via BuddyBoss.
		$result = messages_new_message( [
			'sender_id'  => $sender_id,
			'recipients' => [ $recipient_id ],
			'subject'    => $subject,
			'content'    => $content,
			'error_type' => 'wp_error',
		] );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ], 500 );
		}

		wp_send_json_success( [ 'message' => 'Message sent successfully.' ] );
	}
}
