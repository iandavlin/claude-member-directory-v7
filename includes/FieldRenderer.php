<?php
/**
 * Field Renderer — View-Mode HTML Output.
 *
 * Converts a single section field into its view-mode HTML representation.
 * This class knows nothing about PMP or edit mode — it is called only after
 * PmpResolver has already determined that the field should be visible.
 *
 * Every field type supported by the section config schema has a dedicated
 * private renderer. The single public entry point render() dispatches to the
 * correct renderer based on $field['type'].
 *
 * ────────────────────────────────────────────────────────────────────
 *  WRAPPER STRUCTURE
 * ────────────────────────────────────────────────────────────────────
 *
 * Every rendered field is wrapped in:
 *
 *   <div class="memdir-field memdir-field--{type}">
 *     <span class="memdir-field-label">{label}</span>
 *     {type-specific value HTML}
 *   </div>
 *
 * The wrapper is skipped entirely when the field value is empty —
 * except for true_false, where false (0) is a meaningful value ("No").
 *
 * ────────────────────────────────────────────────────────────────────
 *  USAGE
 * ────────────────────────────────────────────────────────────────────
 *
 *   // In a template partial, after PmpResolver has approved visibility:
 *   foreach ( $section['fields'] as $field ) {
 *       if ( PmpResolver::can_view( $pmp_args, $viewer ) ) {
 *           FieldRenderer::render( $field, get_the_ID() );
 *       }
 *   }
 */

namespace MemberDirectory;

defined( 'ABSPATH' ) || exit;

class FieldRenderer {

	// -----------------------------------------------------------------------
	// Public entry point
	// -----------------------------------------------------------------------

	/**
	 * Render a single field in view mode.
	 *
	 * Dispatches to the correct private renderer based on $field['type'].
	 * Outputs HTML directly — returns void.
	 *
	 * @param array{
	 *     key:         string,
	 *     label:       string,
	 *     type:        string,
	 *     pmp_default: string,
	 *     filterable:  bool,
	 *     taxonomy:    string|null,
	 *     required:    bool,
	 * } $field    The field definition from the section config.
	 *
	 * @param int $post_id  The member-directory post to read field data from.
	 */
	public static function render( array $field, int $post_id ): void {
		$type  = $field['type']  ?? '';
		$key   = $field['key']   ?? '';
		$label = $field['label'] ?? '';

		// ── Taxonomy ─────────────────────────────────────────────────────
		// Uses get_the_terms() instead of get_field() — handled in full
		// before the main dispatch so we never call get_field() on it.
		if ( $type === 'taxonomy' ) {
			self::render_taxonomy( $field, $post_id );
			return;
		}

		// ── true_false ────────────────────────────────────────────────────
		// false (0) is a meaningful value ("No") and must always render.
		// Bypasses the standard empty-value guard used by all other types.
		if ( $type === 'true_false' ) {
			self::render_true_false( $key, $label, $post_id );
			return;
		}

		// ── wysiwyg ───────────────────────────────────────────────────────
		// ACF's the_field() must be used for output — it applies wpautop
		// and shortcode expansion that get_field() would bypass.
		// get_field() is still called first to check for emptiness.
		if ( $type === 'wysiwyg' ) {
			self::render_wysiwyg( $key, $label, $post_id );
			return;
		}

		// ── All other types ───────────────────────────────────────────────
		// Fetch via get_field() and bail early if nothing is saved.
		// This prevents empty wrappers from cluttering the DOM.
		$value = get_field( $key, $post_id );

		if ( self::is_empty( $value ) ) {
			return;
		}

		switch ( $type ) {
			case 'text':
			case 'email':
			case 'number':
				self::render_text( $label, $type, (string) $value );
				break;

			case 'textarea':
				self::render_textarea( $label, (string) $value );
				break;

			case 'url':
				self::render_url( $label, (string) $value );
				break;

			case 'image':
				self::render_image( $label, $value );
				break;

			case 'gallery':
				self::render_gallery( $label, $value );
				break;

			case 'file':
				self::render_file( $label, $value );
				break;

			case 'google_map':
				self::render_map( $label, $value );
				break;

			case 'checkbox':
			case 'radio':
				// radio returns a single string from ACF; normalise to array
				// so render_list() iterates uniformly over both field types.
				$items = is_array( $value ) ? $value : [ $value ];
				self::render_list( $label, $type, $items );
				break;

			// Unknown type: silently skip. New field types added to the
			// section config schema without a matching case produce no output.
		}
	}

	// -----------------------------------------------------------------------
	// Private type renderers
	// -----------------------------------------------------------------------

	/**
	 * text / email / number
	 * Plain scalar value — escaped and wrapped in a paragraph.
	 */
	private static function render_text( string $label, string $type, string $value ): void {
		self::open_wrapper( $type, $label );
		echo '<p class="memdir-field-value">' . esc_html( $value ) . '</p>';
		self::close_wrapper();
	}

	/**
	 * textarea
	 * Multi-line text. Newlines become <br> tags. nl2br() wraps esc_html()
	 * so the <br> elements it inserts are not themselves escaped away.
	 */
	private static function render_textarea( string $label, string $value ): void {
		self::open_wrapper( 'textarea', $label );
		echo '<p class="memdir-field-value">' . nl2br( esc_html( $value ) ) . '</p>';
		self::close_wrapper();
	}

	/**
	 * url
	 * An anchor that opens in a new tab. rel="noopener" prevents the linked
	 * page from accessing window.opener (a cross-origin security concern).
	 * The URL is used as both the href and the visible link text.
	 */
	private static function render_url( string $label, string $value ): void {
		self::open_wrapper( 'url', $label );
		printf(
			'<a class="memdir-field-value" href="%s" target="_blank" rel="noopener">%s</a>',
			esc_url( $value ),
			esc_html( $value )
		);
		self::close_wrapper();
	}

	/**
	 * wysiwyg
	 * the_field() is used for output — never get_field() — because ACF's
	 * the_field() runs the value through wpautop, do_shortcode, and other
	 * content filters that the raw stored value does not have applied.
	 * The output is trusted CMS content and must not be escaped.
	 *
	 * get_field() is called first solely to check whether anything is saved.
	 */
	private static function render_wysiwyg( string $key, string $label, int $post_id ): void {
		if ( self::is_empty( get_field( $key, $post_id ) ) ) {
			return;
		}

		self::open_wrapper( 'wysiwyg', $label );
		echo '<div class="memdir-field-value memdir-wysiwyg">';
		the_field( $key, $post_id ); // Intentionally unescaped — trusted ACF content.
		echo '</div>';
		self::close_wrapper();
	}

	/**
	 * image
	 * ACF returns an array when the field's return_format is 'array'
	 * (as set in the acf_group definition inside each section JSON file).
	 * Keys used: url (required), alt, width, height.
	 */
	private static function render_image( string $label, mixed $value ): void {
		if ( ! is_array( $value ) || empty( $value['url'] ) ) {
			return;
		}

		self::open_wrapper( 'image', $label );
		printf(
			'<img class="memdir-field-value" src="%s" alt="%s" width="%s" height="%s" loading="lazy">',
			esc_url( $value['url'] ),
			esc_attr( $value['alt']    ?? '' ),
			esc_attr( (string) ( $value['width']  ?? '' ) ),
			esc_attr( (string) ( $value['height'] ?? '' ) )
		);
		self::close_wrapper();
	}

	/**
	 * gallery
	 * ACF returns an array of image arrays. Each item has the same shape as
	 * a single image field (url, alt, width, height). Images without a url
	 * are silently skipped rather than producing a broken <img>.
	 */
	private static function render_gallery( string $label, mixed $value ): void {
		if ( ! is_array( $value ) || empty( $value ) ) {
			return;
		}

		self::open_wrapper( 'gallery', $label );
		echo '<div class="memdir-gallery">';

		foreach ( $value as $image ) {
			if ( ! is_array( $image ) || empty( $image['url'] ) ) {
				continue;
			}
			printf(
				'<img src="%s" alt="%s" width="%s" height="%s" loading="lazy">',
				esc_url( $image['url'] ),
				esc_attr( $image['alt']    ?? '' ),
				esc_attr( (string) ( $image['width']  ?? '' ) ),
				esc_attr( (string) ( $image['height'] ?? '' ) )
			);
		}

		echo '</div>';
		self::close_wrapper();
	}

	/**
	 * file
	 * ACF returns an array when return_format is 'array'. Keys used: url
	 * (required for the href) and filename (used as the link text).
	 * The `download` attribute tells the browser to download the file
	 * rather than navigate to it.
	 */
	private static function render_file( string $label, mixed $value ): void {
		if ( ! is_array( $value ) || empty( $value['url'] ) ) {
			return;
		}

		self::open_wrapper( 'file', $label );
		printf(
			'<a class="memdir-field-value" href="%s" download>%s</a>',
			esc_url( $value['url'] ),
			esc_html( $value['filename'] ?? $value['url'] )
		);
		self::close_wrapper();
	}

	/**
	 * google_map
	 * ACF returns an array with address, lat, and lng. Only the human-readable
	 * address string is displayed — no map embed. lat/lng are available for a
	 * future enhancement (e.g. a JS-powered interactive map).
	 */
	private static function render_map( string $label, mixed $value ): void {
		if ( ! is_array( $value ) || empty( $value['address'] ) ) {
			return;
		}

		self::open_wrapper( 'google_map', $label );
		echo '<p class="memdir-field-value">' . esc_html( $value['address'] ) . '</p>';
		self::close_wrapper();
	}

	/**
	 * true_false
	 * A boolean toggle. false (0) means "No" — it is a real value, not an
	 * absent one — so this renderer always outputs something and does not
	 * apply the standard is_empty() guard. The wrapper always renders.
	 */
	private static function render_true_false( string $key, string $label, int $post_id ): void {
		$value = get_field( $key, $post_id );

		self::open_wrapper( 'true_false', $label );
		echo '<p class="memdir-field-value">' . ( $value ? 'Yes' : 'No' ) . '</p>';
		self::close_wrapper();
	}

	/**
	 * checkbox / radio
	 * checkbox: ACF returns an array of the selected choice keys/labels.
	 * radio:    ACF returns a single string; render() normalises it to an
	 *           array before calling here, so this method always sees a list.
	 */
	private static function render_list( string $label, string $type, array $items ): void {
		if ( empty( $items ) ) {
			return;
		}

		self::open_wrapper( $type, $label );
		echo '<ul class="memdir-field-list">';

		foreach ( $items as $item ) {
			echo '<li>' . esc_html( (string) $item ) . '</li>';
		}

		echo '</ul>';
		self::close_wrapper();
	}

	/**
	 * taxonomy
	 * Uses get_the_terms() — the canonical WordPress function for fetching
	 * terms attached to a post — rather than ACF's get_field(). This is more
	 * reliable and decoupled from ACF's internal storage format.
	 *
	 * get_the_terms() returns false when no terms exist and WP_Error on
	 * failure; both cases are treated as empty and produce no output.
	 */
	private static function render_taxonomy( array $field, int $post_id ): void {
		$taxonomy = $field['taxonomy'] ?? '';
		$label    = $field['label']    ?? '';

		if ( empty( $taxonomy ) ) {
			return;
		}

		$terms = get_the_terms( $post_id, $taxonomy );

		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return;
		}

		self::open_wrapper( 'taxonomy', $label );
		echo '<ul class="memdir-field-list">';

		foreach ( $terms as $term ) {
			echo '<li>' . esc_html( $term->name ) . '</li>';
		}

		echo '</ul>';
		self::close_wrapper();
	}

	// -----------------------------------------------------------------------
	// Wrapper helpers
	// -----------------------------------------------------------------------

	/**
	 * Print the opening div and label span for a field.
	 *
	 * @param string $type   ACF field type — used as a BEM modifier class.
	 * @param string $label  The human-readable field label.
	 */
	private static function open_wrapper( string $type, string $label ): void {
		printf(
			'<div class="memdir-field memdir-field--%s"><span class="memdir-field-label">%s</span>',
			esc_attr( $type ),
			esc_html( $label )
		);
	}

	/** Print the closing div for a field wrapper. */
	private static function close_wrapper(): void {
		echo '</div>';
	}

	// -----------------------------------------------------------------------
	// Utility
	// -----------------------------------------------------------------------

	/**
	 * Determine whether a value returned by get_field() should be treated
	 * as absent (nothing to render).
	 *
	 * ACF returns null, false, empty string, or empty array for fields with
	 * no saved content. PHP's strict comparisons catch all four cases.
	 * Note: integer 0 is NOT empty here — it is a valid number field value.
	 *
	 * @param  mixed $value  Value returned by get_field().
	 * @return bool          True if the field has no renderable content.
	 */
	private static function is_empty( mixed $value ): bool {
		return $value === null || $value === false || $value === '' || $value === [];
	}
}
