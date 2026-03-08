<?php
/**
 * Trust Network — Trusted Repair Partner relationships.
 *
 * First non-ACF, code-driven section. Uses a custom DB table instead of
 * ACF field groups. Builders request trust relationships with luthiers;
 * luthiers accept or decline. Accepted relationships are publicly visible.
 *
 * @since 0.2.0
 */

namespace MemberDirectory;

defined( 'ABSPATH' ) || exit;

class TrustNetwork {

	/* ── Constants ───────────────────────────────────────── */

	const TABLE_SUFFIX   = 'memdir_trust_network';
	const META_ENABLED   = '_memdir_trust_enabled';
	const STATUS_PENDING  = 'pending';
	const STATUS_ACCEPTED = 'accepted';
	const STATUS_DECLINED = 'declined';

	/* ── Bootstrap ───────────────────────────────────────── */

	/**
	 * Register AJAX hooks. Called from Plugin::init().
	 * No ACF dependency — no timing guard needed.
	 */
	public static function init(): void {
		add_action( 'wp_ajax_memdir_ajax_trust_request', [ self::class, 'handle_request' ] );
		add_action( 'wp_ajax_memdir_ajax_trust_respond', [ self::class, 'handle_respond' ] );
		add_action( 'wp_ajax_memdir_ajax_trust_cancel',  [ self::class, 'handle_cancel' ] );
		add_action( 'wp_ajax_memdir_ajax_trust_remove',  [ self::class, 'handle_remove' ] );
		add_action( 'wp_ajax_memdir_ajax_trust_toggle',  [ self::class, 'handle_toggle' ] );

		// Auto-create table if missing (handles already-active plugins
		// that skip the activation hook).
		if ( is_admin() ) {
			self::maybe_install_table();
		}
	}

	/* ── Table Management ────────────────────────────────── */

	/**
	 * Get the full table name with prefix.
	 */
	private static function table(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_SUFFIX;
	}

	/**
	 * Create the trust network table via dbDelta.
	 * Called on plugin activation and as a fallback from init().
	 */
	public static function install_table(): void {
		global $wpdb;
		$table   = self::table();
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			requester_id BIGINT(20) UNSIGNED NOT NULL,
			target_post BIGINT(20) UNSIGNED NOT NULL,
			status VARCHAR(10) NOT NULL DEFAULT 'pending',
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			responded_at DATETIME DEFAULT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY unique_trust (requester_id, target_post),
			KEY idx_target (target_post, status),
			KEY idx_requester (requester_id, status)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Install table only if it does not exist yet.
	 */
	private static function maybe_install_table(): void {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			self::install_table();
		}
	}

	/* ── Section Enabled State ───────────────────────────── */

	/**
	 * Check if the Trust section is enabled for a post.
	 * Default: disabled (empty meta = never set = off).
	 */
	public static function is_trust_enabled( int $post_id ): bool {
		return get_post_meta( $post_id, self::META_ENABLED, true ) === '1';
	}

	/* ── Query Methods ───────────────────────────────────── */

	/**
	 * Get accepted trust relationships where this post is the target.
	 * Returns who trusts this luthier.
	 *
	 * @return array[] Rows with id, requester_id, created_at.
	 */
	public static function get_trusting_builders( int $target_post_id ): array {
		global $wpdb;
		$table = self::table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT id, requester_id, created_at FROM {$table}
			 WHERE target_post = %d AND status = %s
			 ORDER BY created_at ASC",
			$target_post_id,
			self::STATUS_ACCEPTED
		), ARRAY_A ) ?: [];
	}

	/**
	 * Get accepted trust relationships where this user is the requester.
	 * Returns who this builder trusts.
	 *
	 * @return array[] Rows with id, target_post, created_at.
	 */
	public static function get_trusted_by_user( int $requester_id ): array {
		global $wpdb;
		$table = self::table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT id, target_post, created_at FROM {$table}
			 WHERE requester_id = %d AND status = %s
			 ORDER BY created_at ASC",
			$requester_id,
			self::STATUS_ACCEPTED
		), ARRAY_A ) ?: [];
	}

	/**
	 * Get pending trust requests targeting this post.
	 *
	 * @return array[] Rows with id, requester_id, created_at.
	 */
	public static function get_pending_requests( int $target_post_id ): array {
		global $wpdb;
		$table = self::table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT id, requester_id, created_at FROM {$table}
			 WHERE target_post = %d AND status = %s
			 ORDER BY created_at ASC",
			$target_post_id,
			self::STATUS_PENDING
		), ARRAY_A ) ?: [];
	}

	/**
	 * Get the relationship between a requester and a target post.
	 *
	 * @return array|null Row with id, requester_id, target_post, status, created_at, responded_at.
	 */
	public static function get_relationship( int $requester_id, int $target_post_id ): ?array {
		global $wpdb;
		$table = self::table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table}
			 WHERE requester_id = %d AND target_post = %d",
			$requester_id,
			$target_post_id
		), ARRAY_A );

		return $row ?: null;
	}

	/**
	 * Get a single trust row by ID.
	 */
	private static function get_row( int $id ): ?array {
		global $wpdb;
		$table = self::table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE id = %d",
			$id
		), ARRAY_A );

		return $row ?: null;
	}

	/* ── Profile Resolution ──────────────────────────────── */

	/**
	 * Batch-resolve user IDs to member profile display data.
	 *
	 * @param  int[] $user_ids
	 * @return array Keyed by user_id: [ 'user_id', 'name', 'url', 'avatar' ]
	 */
	public static function resolve_profiles( array $user_ids ): array {
		if ( empty( $user_ids ) ) {
			return [];
		}

		$posts = get_posts( [
			'post_type'      => 'member-directory',
			'author__in'     => array_map( 'intval', $user_ids ),
			'posts_per_page' => count( $user_ids ),
			'post_status'    => 'publish',
			'no_found_rows'  => true,
		] );

		if ( empty( $posts ) ) {
			return [];
		}

		// Batch prefetch all meta.
		$post_ids = wp_list_pluck( $posts, 'ID' );
		update_postmeta_cache( $post_ids );

		// Build author → post map.
		$by_author = [];
		foreach ( $posts as $p ) {
			$by_author[ (int) $p->post_author ] = $p;
		}

		$results = [];
		foreach ( $user_ids as $uid ) {
			$uid = (int) $uid;
			if ( ! isset( $by_author[ $uid ] ) ) {
				continue;
			}

			$post = $by_author[ $uid ];
			$mid  = $post->ID;

			// Try primary section name field, fall back to post title.
			$primary = get_post_meta( $mid, 'member_directory_primary_section', true ) ?: 'profile';
			$name    = get_post_meta( $mid, 'member_directory_' . $primary . '_name', true );
			if ( empty( $name ) ) {
				$name = $post->post_title;
			}

			// Avatar from primary section header.
			$avatar_id  = get_post_meta( $mid, 'member_directory_' . $primary . '_avatar', true );
			$avatar_url = $avatar_id
				? wp_get_attachment_image_url( (int) $avatar_id, 'thumbnail' )
				: get_avatar_url( $uid, [ 'size' => 48 ] );

			$results[ $uid ] = [
				'user_id' => $uid,
				'post_id' => $mid,
				'name'    => $name,
				'url'     => get_permalink( $mid ),
				'avatar'  => $avatar_url ?: '',
			];
		}

		return $results;
	}

	/**
	 * Batch-resolve post IDs to member profile display data.
	 *
	 * @param  int[] $post_ids
	 * @return array Keyed by post_id: [ 'post_id', 'name', 'url', 'avatar', 'author_id' ]
	 */
	public static function resolve_post_profiles( array $post_ids ): array {
		if ( empty( $post_ids ) ) {
			return [];
		}

		$post_ids = array_map( 'intval', $post_ids );

		// Batch prime: post objects + all their meta.
		_prime_post_caches( $post_ids, true, false );
		update_postmeta_cache( $post_ids );

		$results = [];
		foreach ( $post_ids as $pid ) {
			if ( get_post_status( $pid ) !== 'publish' ) {
				continue;
			}

			$author_id = (int) get_post_field( 'post_author', $pid );
			$primary   = get_post_meta( $pid, 'member_directory_primary_section', true ) ?: 'profile';
			$name      = get_post_meta( $pid, 'member_directory_' . $primary . '_name', true );
			if ( empty( $name ) ) {
				$name = get_the_title( $pid );
			}

			$avatar_id  = get_post_meta( $pid, 'member_directory_' . $primary . '_avatar', true );
			$avatar_url = $avatar_id
				? wp_get_attachment_image_url( (int) $avatar_id, 'thumbnail' )
				: get_avatar_url( $author_id, [ 'size' => 48 ] );

			$results[ $pid ] = [
				'post_id'   => $pid,
				'author_id' => $author_id,
				'name'      => $name,
				'url'       => get_permalink( $pid ),
				'avatar'    => $avatar_url ?: '',
			];
		}

		return $results;
	}

	/* ── Validation Helpers ──────────────────────────────── */

	/**
	 * Get the current user's published member-directory post ID (or 0).
	 */
	private static function get_user_profile_post( int $user_id ): int {
		$posts = get_posts( [
			'post_type'      => 'member-directory',
			'author'         => $user_id,
			'posts_per_page' => 1,
			'post_status'    => 'publish',
			'fields'         => 'ids',
			'no_found_rows'  => true,
		] );
		return ! empty( $posts ) ? (int) $posts[0] : 0;
	}

	/* ── AJAX Handlers ───────────────────────────────────── */

	/**
	 * Builder sends a trust request to a luthier.
	 *
	 * POST: nonce, target_post_id
	 */
	public static function handle_request(): void {
		if ( ! check_ajax_referer( 'md_save_nonce', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => 'Security check failed.' ], 403 );
		}

		$target_post_id = isset( $_POST['target_post_id'] ) ? absint( $_POST['target_post_id'] ) : 0;
		$current_user   = get_current_user_id();

		if ( ! $current_user ) {
			wp_send_json_error( [ 'message' => 'You must be logged in.' ], 403 );
		}

		// Target must be a published member-directory post.
		if ( ! $target_post_id || get_post_type( $target_post_id ) !== 'member-directory'
			|| get_post_status( $target_post_id ) !== 'publish' ) {
			wp_send_json_error( [ 'message' => 'Invalid member profile.' ], 400 );
		}

		// Can't self-trust.
		$target_author = (int) get_post_field( 'post_author', $target_post_id );
		if ( $target_author === $current_user ) {
			wp_send_json_error( [ 'message' => 'You cannot trust yourself.' ], 400 );
		}

		// Requester must have their own published profile.
		if ( ! self::get_user_profile_post( $current_user ) ) {
			wp_send_json_error( [ 'message' => 'You need a published profile to send trust requests.' ], 400 );
		}

		// No existing relationship.
		$existing = self::get_relationship( $current_user, $target_post_id );
		if ( $existing ) {
			wp_send_json_error( [ 'message' => 'A trust relationship already exists.' ], 400 );
		}

		// Insert pending row.
		global $wpdb;
		$inserted = $wpdb->insert(
			self::table(),
			[
				'requester_id' => $current_user,
				'target_post'  => $target_post_id,
				'status'       => self::STATUS_PENDING,
			],
			[ '%d', '%d', '%s' ]
		);

		if ( ! $inserted ) {
			wp_send_json_error( [ 'message' => 'Could not send request.' ], 500 );
		}

		wp_send_json_success( [
			'status'  => self::STATUS_PENDING,
			'message' => 'Trust request sent.',
		] );
	}

	/**
	 * Luthier accepts or declines a pending trust request.
	 *
	 * POST: nonce, trust_id, response (accepted|declined)
	 */
	public static function handle_respond(): void {
		if ( ! check_ajax_referer( 'md_save_nonce', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => 'Security check failed.' ], 403 );
		}

		$trust_id = isset( $_POST['trust_id'] ) ? absint( $_POST['trust_id'] ) : 0;
		$response = isset( $_POST['response'] )
			? sanitize_text_field( wp_unslash( $_POST['response'] ) )
			: '';

		if ( ! in_array( $response, [ self::STATUS_ACCEPTED, self::STATUS_DECLINED ], true ) ) {
			wp_send_json_error( [ 'message' => 'Invalid response.' ], 400 );
		}

		$row = self::get_row( $trust_id );
		if ( ! $row || $row['status'] !== self::STATUS_PENDING ) {
			wp_send_json_error( [ 'message' => 'Request not found or already responded.' ], 400 );
		}

		// Only the target post author can respond.
		$target_author = (int) get_post_field( 'post_author', (int) $row['target_post'] );
		if ( $target_author !== get_current_user_id() && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Permission denied.' ], 403 );
		}

		global $wpdb;
		$updated = $wpdb->update(
			self::table(),
			[
				'status'       => $response,
				'responded_at' => current_time( 'mysql' ),
			],
			[ 'id' => $trust_id ],
			[ '%s', '%s' ],
			[ '%d' ]
		);

		if ( $updated === false ) {
			wp_send_json_error( [ 'message' => 'Could not update request.' ], 500 );
		}

		$label = $response === self::STATUS_ACCEPTED ? 'accepted' : 'declined';
		wp_send_json_success( [
			'status'  => $response,
			'message' => "Request {$label}.",
		] );
	}

	/**
	 * Builder cancels their own pending trust request.
	 *
	 * POST: nonce, trust_id
	 */
	public static function handle_cancel(): void {
		if ( ! check_ajax_referer( 'md_save_nonce', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => 'Security check failed.' ], 403 );
		}

		$trust_id = isset( $_POST['trust_id'] ) ? absint( $_POST['trust_id'] ) : 0;

		$row = self::get_row( $trust_id );
		if ( ! $row || $row['status'] !== self::STATUS_PENDING ) {
			wp_send_json_error( [ 'message' => 'Request not found or not pending.' ], 400 );
		}

		// Only the requester can cancel.
		if ( (int) $row['requester_id'] !== get_current_user_id() ) {
			wp_send_json_error( [ 'message' => 'Permission denied.' ], 403 );
		}

		global $wpdb;
		$wpdb->delete( self::table(), [ 'id' => $trust_id ], [ '%d' ] );

		wp_send_json_success( [ 'message' => 'Request cancelled.' ] );
	}

	/**
	 * Either party removes an accepted trust relationship.
	 *
	 * POST: nonce, trust_id
	 */
	public static function handle_remove(): void {
		if ( ! check_ajax_referer( 'md_save_nonce', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => 'Security check failed.' ], 403 );
		}

		$trust_id = isset( $_POST['trust_id'] ) ? absint( $_POST['trust_id'] ) : 0;

		$row = self::get_row( $trust_id );
		if ( ! $row || $row['status'] !== self::STATUS_ACCEPTED ) {
			wp_send_json_error( [ 'message' => 'Relationship not found.' ], 400 );
		}

		// Either the requester or the target author can remove.
		$current_user  = get_current_user_id();
		$target_author = (int) get_post_field( 'post_author', (int) $row['target_post'] );
		if ( (int) $row['requester_id'] !== $current_user
			&& $target_author !== $current_user
			&& ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Permission denied.' ], 403 );
		}

		global $wpdb;
		$wpdb->delete( self::table(), [ 'id' => $trust_id ], [ '%d' ] );

		wp_send_json_success( [ 'message' => 'Relationship removed.' ] );
	}

	/**
	 * Toggle the Trust section enabled/disabled for a post.
	 *
	 * POST: nonce, post_id, enabled (1|0)
	 */
	public static function handle_toggle(): void {
		if ( ! check_ajax_referer( 'md_save_nonce', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => 'Security check failed.' ], 403 );
		}

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$enabled = isset( $_POST['enabled'] ) ? (bool) absint( $_POST['enabled'] ) : true;

		if ( ! $post_id || get_post_type( $post_id ) !== 'member-directory' ) {
			wp_send_json_error( [ 'message' => 'Invalid post.' ], 400 );
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( [ 'message' => 'Permission denied.' ], 403 );
		}

		update_post_meta( $post_id, self::META_ENABLED, $enabled ? '1' : '0' );

		wp_send_json_success( [
			'enabled' => $enabled,
		] );
	}
}
