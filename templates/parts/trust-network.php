<?php
/**
 * Partial: Trust Network Section.
 *
 * First non-ACF, code-driven section. Renders trust relationship data
 * from the custom DB table instead of ACF field groups. Appears as a
 * peer to other sections with its own pill and right-panel toggle.
 *
 * View mode:
 *   - "Trusted by N builders" — avatar+name cards linking to builder profiles
 *   - Request button for logged-in non-author viewers (state-dependent)
 *   - "My Trusted Repair Network" — only visible to the profile author
 *
 * Edit mode:
 *   - Pending requests with Accept/Decline buttons
 *   - Accepted relationships with Remove buttons
 *   - "My Trusted Repair Network" outbound list with Remove buttons
 *
 * Ghost logic: returns early (no output) if section disabled AND no
 * visible data AND viewer can't act.
 *
 * Expected variables (set by the caller before include):
 *
 *   @var int    $post_id  The member-directory post ID.
 *   @var bool   $is_edit  Whether the viewer is in edit mode.
 *   @var array  $viewer   Viewer context from PmpResolver.
 *   @var int    $section_color  Section color index for data-color attr.
 */

use MemberDirectory\TrustNetwork;

defined( 'ABSPATH' ) || exit;

// ---------------------------------------------------------------------------
// Section enabled check.
// ---------------------------------------------------------------------------
$trust_enabled = TrustNetwork::is_trust_enabled( $post_id );

// ---------------------------------------------------------------------------
// Fetch data.
// ---------------------------------------------------------------------------
$accepted_builders = TrustNetwork::get_trusting_builders( $post_id );
$pending_requests  = $is_edit ? TrustNetwork::get_pending_requests( $post_id ) : [];

// Current viewer's relationship with this profile (for request button).
$current_user_id = get_current_user_id();
$relationship    = $current_user_id ? TrustNetwork::get_relationship( $current_user_id, $post_id ) : null;

// Outbound network: profiles this profile's author trusts.
$target_author_id   = (int) get_post_field( 'post_author', $post_id );
$outbound_trusted   = TrustNetwork::get_trusted_by_user( $target_author_id );

// ---------------------------------------------------------------------------
// Ghost logic.
//
// In view mode: if the section is disabled, output nothing.
// In edit mode: always render (so author can see pending requests and toggle).
// ---------------------------------------------------------------------------
$has_data = ( count( $accepted_builders ) > 0 || count( $outbound_trusted ) > 0 );

if ( ! $is_edit ) {
	// View mode ghost: section disabled → hide.
	if ( ! $trust_enabled ) {
		return;
	}

	// If no data AND viewer has no pending/active relationship AND is not
	// logged in → nothing to show.
	if ( ! $has_data && ! $relationship && ! $current_user_id ) {
		return;
	}
}

// ---------------------------------------------------------------------------
// Resolve profiles (batch).
// ---------------------------------------------------------------------------

// Builder profiles (accepted trust relationships).
$builder_user_ids = array_column( $accepted_builders, 'requester_id' );

// Pending requester profiles (edit mode only).
$pending_user_ids = array_column( $pending_requests, 'requester_id' );

// Combine for a single batch resolution.
$all_user_ids = array_unique( array_merge( $builder_user_ids, $pending_user_ids ) );
$user_profiles = TrustNetwork::resolve_profiles( array_map( 'intval', $all_user_ids ) );

// Outbound trusted post profiles.
$outbound_post_ids = array_column( $outbound_trusted, 'target_post' );
$post_profiles     = TrustNetwork::resolve_post_profiles( array_map( 'intval', $outbound_post_ids ) );

// Is the current viewer the profile author?
$is_own_profile = ( $current_user_id === $target_author_id );

// Is the viewer the author or admin (for showing "self-trust" outbound)?
$can_see_outbound = $is_own_profile || ( $viewer['is_admin'] ?? false );

?>
<div class="memdir-section<?php echo $is_edit ? ' memdir-section--edit' : ''; ?>"
     data-section="trust"
     data-color="<?php echo esc_attr( (string) ( $section_color ?? 0 ) ); ?>"
     data-post-id="<?php echo esc_attr( (string) $post_id ); ?>">

<?php if ( $is_edit ) : ?>
	<?php // ── Edit Mode ─────────────────────────────────────────────── ?>

	<div class="memdir-section-controls">
		<p class="memdir-section-controls__title">Trust</p>
	</div>

	<div class="memdir-field-content">
		<h2 class="memdir-section-title">Trust Network</h2>
		<p class="memdir-section-subtitle">Manage trusted repair partner relationships.</p>

		<?php // ── Pending Requests ───────────────────────────────── ?>
		<?php if ( ! empty( $pending_requests ) ) : ?>
		<div class="memdir-trust-block">
			<h3 class="memdir-trust-block__heading">Pending Requests</h3>
			<div class="memdir-trust-list">
				<?php foreach ( $pending_requests as $req ) :
					$req_uid  = (int) $req['requester_id'];
					$profile  = $user_profiles[ $req_uid ] ?? null;
					if ( ! $profile ) { continue; }
				?>
				<div class="memdir-trust-card memdir-trust-card--pending">
					<img class="memdir-trust-card__avatar"
					     src="<?php echo esc_url( $profile['avatar'] ); ?>"
					     alt="<?php echo esc_attr( $profile['name'] ); ?>"
					     width="40" height="40">
					<a class="memdir-trust-card__name" href="<?php echo esc_url( $profile['url'] ); ?>">
						<?php echo esc_html( $profile['name'] ); ?>
					</a>
					<div class="memdir-trust-card__actions">
						<button type="button" class="memdir-trust-btn memdir-trust-btn--accept"
						        data-trust-action="respond"
						        data-trust-id="<?php echo esc_attr( (string) $req['id'] ); ?>"
						        data-trust-response="accepted">Accept</button>
						<button type="button" class="memdir-trust-btn memdir-trust-btn--decline"
						        data-trust-action="respond"
						        data-trust-id="<?php echo esc_attr( (string) $req['id'] ); ?>"
						        data-trust-response="declined">Decline</button>
					</div>
				</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php endif; ?>

		<?php // ── Accepted (Inbound) ───────────────────────────── ?>
		<?php if ( ! empty( $accepted_builders ) ) : ?>
		<div class="memdir-trust-block">
			<h3 class="memdir-trust-block__heading">
				Trusted by <?php echo count( $accepted_builders ); ?>
				builder<?php echo count( $accepted_builders ) !== 1 ? 's' : ''; ?>
			</h3>
			<div class="memdir-trust-list">
				<?php foreach ( $accepted_builders as $rel ) :
					$b_uid   = (int) $rel['requester_id'];
					$profile = $user_profiles[ $b_uid ] ?? null;
					if ( ! $profile ) { continue; }
				?>
				<div class="memdir-trust-card">
					<img class="memdir-trust-card__avatar"
					     src="<?php echo esc_url( $profile['avatar'] ); ?>"
					     alt="<?php echo esc_attr( $profile['name'] ); ?>"
					     width="40" height="40">
					<a class="memdir-trust-card__name" href="<?php echo esc_url( $profile['url'] ); ?>">
						<?php echo esc_html( $profile['name'] ); ?>
					</a>
					<button type="button" class="memdir-trust-btn memdir-trust-btn--remove"
					        data-trust-action="remove"
					        data-trust-id="<?php echo esc_attr( (string) $rel['id'] ); ?>">Remove</button>
				</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php endif; ?>

		<?php // ── Outbound (My Trusted Repair Network) ─────────── ?>
		<?php if ( ! empty( $outbound_trusted ) ) : ?>
		<div class="memdir-trust-block">
			<h3 class="memdir-trust-block__heading">My Trusted Repair Network</h3>
			<div class="memdir-trust-list">
				<?php foreach ( $outbound_trusted as $rel ) :
					$t_pid   = (int) $rel['target_post'];
					$profile = $post_profiles[ $t_pid ] ?? null;
					if ( ! $profile ) { continue; }
				?>
				<div class="memdir-trust-card">
					<img class="memdir-trust-card__avatar"
					     src="<?php echo esc_url( $profile['avatar'] ); ?>"
					     alt="<?php echo esc_attr( $profile['name'] ); ?>"
					     width="40" height="40">
					<a class="memdir-trust-card__name" href="<?php echo esc_url( $profile['url'] ); ?>">
						<?php echo esc_html( $profile['name'] ); ?>
					</a>
					<button type="button" class="memdir-trust-btn memdir-trust-btn--remove"
					        data-trust-action="remove"
					        data-trust-id="<?php echo esc_attr( (string) $rel['id'] ); ?>">Remove</button>
				</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php endif; ?>

		<?php if ( empty( $pending_requests ) && empty( $accepted_builders ) && empty( $outbound_trusted ) ) : ?>
		<p class="memdir-trust-empty">No trust relationships yet. When builders request you as a trusted repair partner, their requests will appear here.</p>
		<?php endif; ?>
	</div>

<?php else : ?>
	<?php // ── View Mode ─────────────────────────────────────────────── ?>

	<div class="memdir-field-content">
		<h2 class="memdir-section-title">Trust Network</h2>

		<?php // ── Accepted (Inbound) — visible to everyone ─────── ?>
		<?php if ( ! empty( $accepted_builders ) ) : ?>
		<div class="memdir-trust-block">
			<h3 class="memdir-trust-block__heading">
				Trusted by <?php echo count( $accepted_builders ); ?>
				builder<?php echo count( $accepted_builders ) !== 1 ? 's' : ''; ?>
			</h3>
			<div class="memdir-trust-list">
				<?php foreach ( $accepted_builders as $rel ) :
					$b_uid   = (int) $rel['requester_id'];
					$profile = $user_profiles[ $b_uid ] ?? null;
					if ( ! $profile ) { continue; }
				?>
				<div class="memdir-trust-card">
					<img class="memdir-trust-card__avatar"
					     src="<?php echo esc_url( $profile['avatar'] ); ?>"
					     alt="<?php echo esc_attr( $profile['name'] ); ?>"
					     width="40" height="40">
					<a class="memdir-trust-card__name" href="<?php echo esc_url( $profile['url'] ); ?>">
						<?php echo esc_html( $profile['name'] ); ?>
					</a>
				</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php endif; ?>

		<?php // ── Request Button (logged-in non-author only) ──── ?>
		<?php if ( $current_user_id && ! $is_own_profile ) : ?>
		<div class="memdir-trust-action">
			<?php if ( ! $relationship ) : ?>
				<button type="button"
				        class="memdir-trust-btn memdir-trust-btn--request"
				        data-trust-action="request"
				        data-target-post="<?php echo esc_attr( (string) $post_id ); ?>">
					Request as Trusted Repair Partner
				</button>

			<?php elseif ( $relationship['status'] === 'pending' ) : ?>
				<span class="memdir-trust-badge memdir-trust-badge--pending">Request Pending</span>
				<button type="button"
				        class="memdir-trust-btn memdir-trust-btn--cancel"
				        data-trust-action="cancel"
				        data-trust-id="<?php echo esc_attr( (string) $relationship['id'] ); ?>">
					Cancel Request
				</button>

			<?php elseif ( $relationship['status'] === 'accepted' ) : ?>
				<span class="memdir-trust-badge memdir-trust-badge--accepted">Trusted Partner &#10003;</span>
				<button type="button"
				        class="memdir-trust-btn memdir-trust-btn--remove"
				        data-trust-action="remove"
				        data-trust-id="<?php echo esc_attr( (string) $relationship['id'] ); ?>">
					Remove
				</button>

			<?php endif; // Declined: ghost — show nothing. ?>
		</div>
		<?php endif; ?>

		<?php // ── Outbound (Author's Own Trusted Network) ────── ?>
		<?php if ( $can_see_outbound && ! empty( $outbound_trusted ) ) : ?>
		<div class="memdir-trust-block">
			<h3 class="memdir-trust-block__heading">My Trusted Repair Network</h3>
			<div class="memdir-trust-list">
				<?php foreach ( $outbound_trusted as $rel ) :
					$t_pid   = (int) $rel['target_post'];
					$profile = $post_profiles[ $t_pid ] ?? null;
					if ( ! $profile ) { continue; }
				?>
				<div class="memdir-trust-card">
					<img class="memdir-trust-card__avatar"
					     src="<?php echo esc_url( $profile['avatar'] ); ?>"
					     alt="<?php echo esc_attr( $profile['name'] ); ?>"
					     width="40" height="40">
					<a class="memdir-trust-card__name" href="<?php echo esc_url( $profile['url'] ); ?>">
						<?php echo esc_html( $profile['name'] ); ?>
					</a>
				</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php endif; ?>

	</div>

<?php endif; ?>

</div><?php
// No closing PHP tag — intentional. Prevents accidental whitespace output.
