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

		// -- Fetch value once for all types ------------------------------------
		// PERF: A single get_field() call serves every type below, including
		// true_false and wysiwyg which previously read it again inside their
		// own renderers. The pre-fetched $value is passed through so no
		// type-specific renderer needs its own DB round-trip.
		//
		// Revert: remove $value here; restore get_field() calls inside
		// render_true_false() and render_wysiwyg().
		// ---------------------------------------------------------------------
		$value = get_field( $key, $post_id );

		// -- true_false -------------------------------------------------------
		// false (0) is a meaningful value ("No") and must always render.
		// Bypasses the standard empty-value guard used by all other types.
		if ( $type === 'true_false' ) {
			self::render_true_false( $value, $label );
			return;
		}

		// -- wysiwyg ----------------------------------------------------------
		// ACF's the_field() must be used for output -- it applies wpautop
		// and shortcode expansion that get_field() would bypass.
		// The $value fetched above is only used for the emptiness check;
		// the_field() still handles final output.
		if ( $type === 'wysiwyg' ) {
			self::render_wysiwyg( $value, $key, $label, $post_id );
			return;
		}

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
				self::render_map( $label, $value, $field, $post_id );
				break;

			case 'select':
				// get_field() with return_format:'value' returns the stored key (e.g. 'student').
				// Look up the human-readable label from the ACF field object's choices array;
				// fall back to a capitalised version of the raw key if lookup fails.
				$field_obj   = get_field_object( $key, $post_id );
				$choices     = ( is_array( $field_obj ) && isset( $field_obj['choices'] ) ) ? $field_obj['choices'] : [];
				$display_val = $choices[ (string) $value ] ?? ucfirst( str_replace( '_', ' ', (string) $value ) );
				self::render_text( $label, 'select', $display_val );
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
	// PERF: $value is passed in from render() -- no second get_field() call.
	// the_field() is still used for final output (applies wpautop + shortcodes).
	// Revert: change signature back to (string $key, string $label, int $post_id)
	//         and replace is_empty($value) with is_empty(get_field($key,$post_id)).
	private static function render_wysiwyg( mixed $value, string $key, string $label, int $post_id ): void {
		if ( self::is_empty( $value ) ) {
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
	 * Keys used: url (required), alt, width, height, caption.
	 *
	 * Wraps the image in a GLightbox-compatible link so clicking opens the
	 * full-size image in a lightbox. The WP attachment caption is output as
	 * both a <figcaption> and as data-description for the lightbox overlay.
	 */
	private static function render_image( string $label, mixed $value ): void {
		if ( ! is_array( $value ) || empty( $value['url'] ) ) {
			return;
		}

		$caption = $value['caption'] ?? '';
		$alt     = $value['alt']     ?? '';
		$sizes   = $value['sizes']   ?? [];

		// Pick the best available intermediate size and its real dimensions.
		// ACF stores per-size dimensions as {size}-width and {size}-height.
		$src    = $value['url'];
		$width  = $value['width']  ?? '';
		$height = $value['height'] ?? '';

		foreach ( [ 'medium_large', 'medium' ] as $preferred ) {
			if ( ! empty( $sizes[ $preferred ] ) ) {
				$src    = $sizes[ $preferred ];
				$width  = $sizes[ "{$preferred}-width" ]  ?? $width;
				$height = $sizes[ "{$preferred}-height" ] ?? $height;
				break;
			}
		}

		self::open_wrapper( 'image', $label );
		echo '<figure class="memdir-figure">';
		printf(
			'<a href="%s" class="glightbox" data-description="%s"><img class="memdir-field-value" src="%s" alt="%s" width="%s" height="%s" loading="lazy"></a>',
			esc_url( $value['url'] ),
			esc_attr( $caption ),
			esc_url( $src ),
			esc_attr( $alt ),
			esc_attr( (string) $width ),
			esc_attr( (string) $height )
		);
		if ( $caption !== '' ) {
			printf( '<figcaption class="memdir-figure__caption">%s</figcaption>', esc_html( $caption ) );
		}
		echo '</figure>';
		self::close_wrapper();
	}

	/**
	 * gallery
	 * ACF returns an array of image arrays. Each item has the same shape as
	 * a single image field (url, alt, width, height, caption). Images without
	 * a url are silently skipped rather than producing a broken <img>.
	 *
	 * All images in a gallery share a data-gallery attribute so GLightbox
	 * groups them for prev/next navigation. Captions display in the lightbox
	 * overlay and optionally as figcaptions below each thumbnail.
	 */
	private static function render_gallery( string $label, mixed $value ): void {
		if ( ! is_array( $value ) || empty( $value ) ) {
			return;
		}

		// Unique gallery ID so GLightbox groups only this field's images.
		$gallery_id = 'gallery-' . wp_unique_id();

		self::open_wrapper( 'gallery', $label );
		echo '<div class="memdir-gallery">';

		foreach ( $value as $image ) {
			if ( ! is_array( $image ) || empty( $image['url'] ) ) {
				continue;
			}

			$caption = $image['caption'] ?? '';

			echo '<figure class="memdir-gallery__figure">';
			printf(
				'<a href="%s" class="glightbox" data-gallery="%s" data-description="%s"><img src="%s" alt="%s" loading="lazy"></a>',
				esc_url( $image['url'] ),
				esc_attr( $gallery_id ),
				esc_attr( $caption ),
				esc_url( $image['sizes']['thumbnail'] ?? $image['url'] ),
				esc_attr( $image['alt'] ?? '' )
			);
			if ( $caption !== '' ) {
				printf( '<figcaption class="memdir-gallery__caption">%s</figcaption>', esc_html( $caption ) );
			}
			echo '</figure>';
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
	 * ACF returns an array with address, lat, lng, city, state, state_short,
	 * country, country_short, street_number, street_name, post_code, etc.
	 *
	 * Display precision is controlled by a companion `_display_precision`
	 * select field on the same post. Values: address (full), city, state,
	 * country. Falls back to "address" (show everything) when absent.
	 *
	 * The full geocoded data (lat/lng) is always stored regardless of
	 * display precision — useful for proximity search in DirectoryQuery.
	 */
	private static function render_map( string $label, mixed $value, array $field = [], int $post_id = 0 ): void {
		if ( ! is_array( $value ) || empty( $value['address'] ) ) {
			return;
		}

		// Look up display precision companion field.
		$precision = 'address';
		if ( $post_id && ! empty( $field['name'] ) ) {
			// Derive companion field name: replace the trailing suffix with _display_precision.
			// e.g. member_directory_location_location → member_directory_location_display_precision
			$section_prefix = preg_replace( '/[^_]+$/', '', $field['name'] ); // "member_directory_location_"
			$precision_val  = get_field( $section_prefix . 'display_precision', $post_id );
			if ( $precision_val && in_array( $precision_val, [ 'address', 'city', 'state', 'country' ], true ) ) {
				$precision = $precision_val;
			}
		}

		$display = self::format_location( $value, $precision );
		if ( empty( $display ) ) {
			return;
		}

		self::open_wrapper( 'google_map', $label );
		echo '<p class="memdir-field-value">' . esc_html( $display ) . '</p>';
		self::close_wrapper();
	}

	/**
	 * Build a display string from a Google Maps value array at the given
	 * precision level. Falls back through progressively coarser levels
	 * when finer components are missing.
	 *
	 * @param array  $value     ACF google_map value (address, city, state, country, etc.).
	 * @param string $precision One of: address, city, state, country.
	 * @return string
	 */
	public static function format_location( array $value, string $precision ): string {
		$city          = $value['city']          ?? '';
		$state         = $value['state_short']   ?? ( $value['state'] ?? '' );
		$country       = $value['country']       ?? '';

		switch ( $precision ) {
			case 'country':
				return $country;

			case 'state':
				$parts = array_filter( [ $state, $country ] );
				return implode( ', ', $parts );

			case 'city':
				$parts = array_filter( [ $city, $state ] );
				return implode( ', ', $parts );

			case 'address':
			default:
				return $value['address'] ?? '';
		}
	}

	/**
	 * true_false
	 * A boolean toggle. false (0) means "No" — it is a real value, not an
	 * absent one — so this renderer always outputs something and does not
	 * apply the standard is_empty() guard. The wrapper always renders.
	 */
	// PERF: $value is passed in from render() -- no second get_field() call.
	// Revert: change signature back to (string $key, string $label, int $post_id)
	//         and add  $value = get_field( $key, $post_id );  as the first line.
	private static function render_true_false( mixed $value, string $label ): void {
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
			$url = Directory::get_term_filter_url( $taxonomy, $term->slug );
			if ( ! empty( $url ) ) {
				echo '<li><a href="' . esc_url( $url ) . '" class="memdir-field-link">' . esc_html( $term->name ) . '</a></li>';
			} else {
				echo '<li>' . esc_html( $term->name ) . '</li>';
			}
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
