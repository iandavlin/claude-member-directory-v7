<?php
/**
 * BuddyBoss messaging integration.
 *
 * Provides a lightweight AJAX-backed compose form so visitors can message
 * a member-directory profile owner via the BuddyBoss messaging system.
 *
 * Per-profile messaging access control:
 *   off        — DMs disabled (default)
 *   connection — only BuddyBoss connections can message
 *   all        — any logged-in user can message
 *
 * Static class: Messaging::init() wires the AJAX handlers.
 * Messaging::is_available() checks whether BuddyBoss messaging is active.
 */

namespace MemberDirectory;

defined( 'ABSPATH' ) || exit;

class Messaging {

	/** Post meta key for per-profile messaging access level. */
	const ACCESS_META = '_memdir_messaging_access';

	/** Valid access levels. */
	const ACCESS_LEVELS = [ 'off', 'connection', 'all' ];

	/**
	 * Wire AJAX handlers (logged-in users only — no nopriv variants).
	 */
	public static function init(): void {
		add_action( 'wp_ajax_memdir_ajax_send_message', [ self::class, 'handle_send_message' ] );
		add_action( 'wp_ajax_memdir_ajax_save_messaging_access', [ self::class, 'handle_save_access' ] );
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
	 * Get the messaging access level for a member-directory post.
	 *
	 * @param int $post_id The member-directory post ID.
	 * @return string One of: off, connection, all.
	 */
	public static function get_access( int $post_id ): string {
		$value = get_post_meta( $post_id, self::ACCESS_META, true );
		return in_array( $value, self::ACCESS_LEVELS, true ) ? $value : 'off';
	}

	/**
	 * Human-readable label for a messaging access level.
	 *
	 * @param string $level One of: off, connection, all.
	 * @return string Display label.
	 */
	public static function get_access_label( string $level ): string {
		switch ( $level ) {
			case 'connection': return 'Connections Only';
			case 'all':        return 'All Members';
			default:           return 'Off';
		}
	}

	/**
	 * Check whether a viewer can message a profile owner based on the
	 * profile's messaging access setting.
	 *
	 * @param int $post_id   The member-directory post ID.
	 * @param int $viewer_id The viewer's user ID (0 = logged out).
	 * @return bool True if the viewer is allowed to message.
	 */
	public static function can_message( int $post_id, int $viewer_id ): bool {
		if ( ! self::is_available() || ! $viewer_id ) {
			return false;
		}

		$author_id = (int) get_post_field( 'post_author', $post_id );
		if ( $viewer_id === $author_id ) {
			return false; // Can't message yourself.
		}

		$access = self::get_access( $post_id );

		switch ( $access ) {
			case 'all':
				return true;

			case 'connection':
				// Check BuddyBoss friendship/connection status.
				if ( function_exists( 'friends_check_friendship' ) ) {
					return friends_check_friendship( $viewer_id, $author_id );
				}
				return false;

			default: // 'off'
				return false;
		}
	}

	/**
	 * AJAX handler: save messaging access level.
	 *
	 * Expected POST params:
	 *   nonce   — md_save_nonce
	 *   post_id — member-directory post ID
	 *   access  — off | connection | all
	 */
	public static function handle_save_access(): void {
		if ( ! check_ajax_referer( 'md_save_nonce', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => 'Security check failed.' ], 403 );
		}

		$post_id = absint( $_POST['post_id'] ?? 0 );
		$access  = sanitize_text_field( wp_unslash( $_POST['access'] ?? '' ) );

		if ( ! $post_id || get_post_type( $post_id ) !== 'member-directory' ) {
			wp_send_json_error( [ 'message' => 'Invalid post.' ], 400 );
		}

		// Only the post author (or admin) may change this setting.
		$current_user = get_current_user_id();
		$post_author  = (int) get_post_field( 'post_author', $post_id );
		if ( $current_user !== $post_author && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Permission denied.' ], 403 );
		}

		if ( ! in_array( $access, self::ACCESS_LEVELS, true ) ) {
			wp_send_json_error( [ 'message' => 'Invalid access level.' ], 400 );
		}

		update_post_meta( $post_id, self::ACCESS_META, $access );

		wp_send_json_success( [
			'message' => 'Messaging access updated.',
			'access'  => $access,
			'label'   => self::get_access_label( $access ),
		] );
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

		// Find the recipient's member-directory post to check access.
		$recipient_posts = get_posts( [
			'post_type'      => 'member-directory',
			'author'         => $recipient_id,
			'posts_per_page' => 1,
			'fields'         => 'ids',
		] );

		if ( empty( $recipient_posts ) ) {
			wp_send_json_error( [ 'message' => 'Recipient has no profile.' ], 400 );
		}

		$recipient_post_id = (int) $recipient_posts[0];

		// Enforce per-profile messaging access.
		if ( ! self::can_message( $recipient_post_id, $sender_id ) ) {
			wp_send_json_error( [ 'message' => 'This member is not accepting messages.' ], 403 );
		}

		// Temporarily bypass BuddyBoss connection requirement — our plugin
		// handles its own access control via the per-profile setting above.
		$bypass = false;
		if ( function_exists( 'bp_force_friendship_to_message' ) && bp_force_friendship_to_message() ) {
			add_filter( 'bp_force_friendship_to_message', '__return_false' );
			$bypass = true;
		}

		// Send via BuddyBoss.
		$result = messages_new_message( [
			'sender_id'  => $sender_id,
			'recipients' => [ $recipient_id ],
			'subject'    => $subject,
			'content'    => $content,
			'error_type' => 'wp_error',
		] );

		// Restore the friendship filter.
		if ( $bypass ) {
			remove_filter( 'bp_force_friendship_to_message', '__return_false' );
		}

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ], 500 );
		}

		wp_send_json_success( [ 'message' => 'Message sent successfully.' ] );
	}
}
