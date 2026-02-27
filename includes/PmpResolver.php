<?php
/**
 * PMP Resolver — Privacy / Visibility Engine.
 *
 * PMP stands for Public / Member / Private. Every field on every member
 * profile has a PMP value that determines who can see it. This class
 * contains the single authoritative implementation of PMP resolution.
 *
 * ────────────────────────────────────────────────────────────────────
 *  THE WATERFALL
 * ────────────────────────────────────────────────────────────────────
 *
 * PMP values exist at three levels, stacked bottom-to-top:
 *
 *     FIELD     (lowest)   — can be: public, member, private, or inherit
 *     SECTION   (middle)   — can be: public, member, private, or inherit
 *     GLOBAL    (top)      — can be: public, member, private  (NEVER inherit)
 *
 * Resolution starts at the bottom (field) and walks upward:
 *
 *     1. If the FIELD has an explicit value → that value wins. Stop.
 *     2. If the FIELD is "inherit" → look at the SECTION.
 *     3. If the SECTION has an explicit value → that value wins. Stop.
 *     4. If the SECTION is also "inherit" → look at GLOBAL.
 *     5. GLOBAL always has an explicit value → that value wins. Stop.
 *
 * The waterfall always terminates because Global can never be "inherit".
 *
 * "Lowest explicit override wins" means a field set to Private stays
 * private even if the section or global is Public. This is the opposite
 * of a "most permissive" rule.
 *
 * ────────────────────────────────────────────────────────────────────
 *  GHOST BEHAVIOR
 * ────────────────────────────────────────────────────────────────────
 *
 * When can_view() returns false the field must not render at all.
 * No placeholders, no "This field is private" labels, no empty
 * containers. The content does not exist for that viewer. Templates
 * must check can_view() before emitting any HTML for a field.
 *
 * ────────────────────────────────────────────────────────────────────
 *  VIEW AS SIMULATION
 * ────────────────────────────────────────────────────────────────────
 *
 * The spoof_viewer() method creates a fake $viewer array so the post
 * author can preview their profile as a Member or as the Public.
 * The spoofed viewer is passed into can_view() in place of the real
 * viewer. No saved data changes — it is purely a display simulation.
 *
 * ────────────────────────────────────────────────────────────────────
 *  USAGE
 * ────────────────────────────────────────────────────────────────────
 *
 *   // Build the viewer context for the current user:
 *   $viewer = PmpResolver::resolve_viewer( $post_id );
 *
 *   // Or spoof a viewer for View As:
 *   $viewer = PmpResolver::spoof_viewer( 'public' );
 *
 *   // Check visibility for a specific field:
 *   $visible = PmpResolver::can_view( [
 *       'field_pmp'   => 'inherit',   // from the post meta for this field
 *       'section_pmp' => 'member',    // from the post meta for this section
 *       'global_pmp'  => 'public',    // from the post meta for the profile
 *   ], $viewer );
 *
 *   if ( $visible ) {
 *       // Render the field.
 *   }
 *   // else: ghost — emit nothing.
 */

namespace MemberDirectory;

defined( 'ABSPATH' ) || exit;

class PmpResolver {

	/**
	 * The three explicit PMP values. Anything else is treated as "inherit".
	 * Order does not matter here — this is a membership test, not a ranking.
	 */
	const EXPLICIT_VALUES = [ 'public', 'member', 'private' ];

	// -----------------------------------------------------------------------
	// can_view — the core visibility check
	// -----------------------------------------------------------------------

	/**
	 * Determine whether a field should be visible to a given viewer.
	 *
	 * This is the single point of truth for all PMP visibility decisions
	 * in the entire plugin. Every template, every card, every sidebar
	 * entry calls this method before rendering.
	 *
	 * @param array{
	 *     field_pmp:   string,
	 *     section_pmp: string,
	 *     global_pmp:  string,
	 * } $args  The three PMP values for this field in this context.
	 *
	 * @param array{
	 *     is_author:    bool,
	 *     is_admin:     bool,
	 *     is_logged_in: bool,
	 * } $viewer  The viewer context (real or spoofed).
	 *
	 * @return bool  True = show the field. False = ghost (emit nothing).
	 */
	public static function can_view( array $args, array $viewer ): bool {

		// -----------------------------------------------------------------
		// Step 1: Author and admin always see everything.
		//
		// This check comes first so that the author can always see their
		// own content regardless of PMP settings. Admins get the same
		// privilege so they can help troubleshoot profiles.
		// -----------------------------------------------------------------

		if ( ! empty( $viewer['is_author'] ) || ! empty( $viewer['is_admin'] ) ) {
			return true;
		}

		// -----------------------------------------------------------------
		// Step 2: Resolve the effective PMP value via the waterfall.
		//
		// Start at the field level. If the field has an explicit value
		// (public, member, or private), that value wins — stop. If the
		// field is set to "inherit", move up to the section. Same rule.
		// If the section is also "inherit", fall through to global.
		// Global is always explicit, so the waterfall always terminates.
		// -----------------------------------------------------------------

		$field_pmp   = $args['field_pmp']   ?? 'inherit';
		$section_pmp = $args['section_pmp'] ?? 'inherit';
		$global_pmp  = $args['global_pmp']  ?? 'public';

		if ( self::is_explicit( $field_pmp ) ) {
			// Waterfall stops at field level.
			$effective = $field_pmp;
		} elseif ( self::is_explicit( $section_pmp ) ) {
			// Field was "inherit" — waterfall stops at section level.
			$effective = $section_pmp;
		} else {
			// Both field and section were "inherit" — global wins.
			// Global should always be explicit, but default to 'public'
			// as a safety net if something is misconfigured.
			$effective = $global_pmp;
		}

		// -----------------------------------------------------------------
		// Step 3: Apply the resolved effective value against the viewer.
		//
		//   public  → visible to everyone (logged in or logged out)
		//   member  → visible only to logged-in users
		//   private → visible only to author and admin (already caught
		//             in step 1, so reaching here means: hide)
		// -----------------------------------------------------------------

		return match ( $effective ) {
			'public'  => true,
			'member'  => ! empty( $viewer['is_logged_in'] ),
			'private' => false,
			default   => false, // Unknown value — fail closed (hide).
		};
	}

	// -----------------------------------------------------------------------
	// resolve_viewer — build the real viewer context
	// -----------------------------------------------------------------------

	/**
	 * Build a $viewer array for the current WordPress user relative to a
	 * specific member-directory post.
	 *
	 * This is the "real" viewer — not spoofed. It checks:
	 *   - Whether the current user is logged in at all.
	 *   - Whether the current user is the author of the given post.
	 *   - Whether the current user has the manage_options capability
	 *     (i.e. is a WordPress administrator).
	 *
	 * @param  int $post_id  The member-directory post being viewed.
	 *
	 * @return array{
	 *     is_author:    bool,
	 *     is_admin:     bool,
	 *     is_logged_in: bool,
	 * }
	 */
	public static function resolve_viewer( int $post_id ): array {
		$is_logged_in = is_user_logged_in();
		$current_user = $is_logged_in ? get_current_user_id() : 0;
		$post_author  = (int) get_post_field( 'post_author', $post_id );

		return [
			'is_author'    => $is_logged_in && $current_user === $post_author,
			'is_admin'     => $is_logged_in && current_user_can( 'manage_options' ),
			'is_logged_in' => $is_logged_in,
		];
	}

	// -----------------------------------------------------------------------
	// spoof_viewer — fake viewer for View As simulation
	// -----------------------------------------------------------------------

	/**
	 * Build a spoofed $viewer array for the View As feature.
	 *
	 * The post author uses this to preview their profile as different
	 * viewer types without changing any saved data. The spoofed array
	 * is passed directly into can_view() in place of the real viewer.
	 *
	 * Only two spoof levels exist:
	 *   - "member" — simulates a logged-in non-author user
	 *   - "public" — simulates a logged-out visitor
	 *
	 * There is no "author" or "admin" spoof because those viewers
	 * always see everything — there is nothing to preview.
	 *
	 * @param  string $level  Either "member" or "public".
	 *
	 * @return array{
	 *     is_author:    bool,
	 *     is_admin:     bool,
	 *     is_logged_in: bool,
	 * }
	 */
	public static function spoof_viewer( string $level ): array {
		return match ( $level ) {
			'member' => [
				'is_author'    => false,
				'is_admin'     => false,
				'is_logged_in' => true,
			],
			// "public" and any unrecognized level both resolve to the most
			// restrictive viewer: logged out, not author, not admin.
			default => [
				'is_author'    => false,
				'is_admin'     => false,
				'is_logged_in' => false,
			],
		};
	}

	// -----------------------------------------------------------------------
	// Private helpers
	// -----------------------------------------------------------------------

	/**
	 * Check whether a PMP value is explicit (not "inherit").
	 *
	 * Explicit values are: public, member, private.
	 * Everything else (including "inherit", empty strings, nulls,
	 * and typos) is treated as non-explicit, which means the
	 * waterfall continues upward.
	 *
	 * @param  string $value  The PMP value to check.
	 * @return bool           True if the value is explicit.
	 */
	private static function is_explicit( string $value ): bool {
		return in_array( $value, self::EXPLICIT_VALUES, true );
	}
}
