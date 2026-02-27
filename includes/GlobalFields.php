<?php
/**
 * Global Fields — Hardcoded ACF Field Group.
 *
 * Registers the two post-level control fields that apply to the entire
 * member profile, not to any individual section:
 *
 *   1. Profile Visibility (global_pmp) — the top of the PMP waterfall.
 *      Applies to the whole profile when section and field levels are
 *      both set to 'inherit'.
 *
 *   2. Primary Section — which section feeds the sticky header
 *      (name, tagline, location). Only sections with can_be_primary === true
 *      in their JSON config appear as choices.
 *
 * This group is registered via acf_add_local_field_group() on the acf/init
 * hook. It does not come from a JSON file and does not require a sync.
 * The Primary Section choices are built dynamically from SectionRegistry
 * so the select options stay in sync as sections are added or removed.
 */

namespace MemberDirectory;

defined( 'ABSPATH' ) || exit;

class GlobalFields {

	/**
	 * Register the acf/init hook.
	 * Called once from Plugin::init() during plugins_loaded.
	 */
	public static function init(): void {
		add_action( 'acf/init', [ self::class, 'register' ] );
	}

	/**
	 * Build and register the global controls field group with ACF.
	 * Fires on the acf/init action, after ACF is fully loaded.
	 */
	public static function register(): void {
		acf_add_local_field_group( [
			'key'      => 'group_md_global_controls',
			'title'    => 'Member Directory — Global Controls',
			'fields'   => [
				self::field_global_pmp(),
				self::field_primary_section(),
			],
			'location' => [
				[
					[
						'param'    => 'post_type',
						'operator' => '==',
						'value'    => 'member-directory',
					],
				],
			],
			'position'   => 'side',
			'menu_order' => 1,
		] );
	}

	// -----------------------------------------------------------------------
	// Field definitions
	// -----------------------------------------------------------------------

	/**
	 * Field 1: Profile Visibility (global PMP).
	 *
	 * A button_group with three options — Public, Members Only, Private.
	 * This is the top of the PMP waterfall: when both section and field PMP
	 * are set to 'inherit', this value determines visibility.
	 *
	 * @return array  ACF field definition array.
	 */
	private static function field_global_pmp(): array {
		return [
			'key'           => 'field_md_global_pmp',
			'name'          => 'member_directory_global_pmp',
			'label'         => 'Profile Visibility',
			'type'          => 'button_group',
			'choices'       => [
				'public'  => 'Public',
				'member'  => 'Members Only',
				'private' => 'Private',
			],
			'default_value' => 'member',
			'layout'        => 'horizontal',
		];
	}

	/**
	 * Field 2: Primary Section.
	 *
	 * A select whose choices are built dynamically from SectionRegistry,
	 * filtered to sections that have can_be_primary === true. This ensures
	 * only valid sections (profile, business) appear as options, and new
	 * primary-capable sections added via JSON are picked up automatically
	 * after the next admin sync.
	 *
	 * SectionRegistry::get_sections() is called at acf/init time — after
	 * SectionRegistry::load_from_db() has already run on the init hook
	 * (priority 20) — so the in-memory section cache is populated.
	 *
	 * @return array  ACF field definition array.
	 */
	private static function field_primary_section(): array {
		$choices = [];

		foreach ( SectionRegistry::get_sections() as $section ) {
			if ( ! empty( $section['can_be_primary'] ) ) {
				$choices[ $section['key'] ] = $section['label'];
			}
		}

		return [
			'key'           => 'field_md_primary_section',
			'name'          => 'member_directory_primary_section',
			'label'         => 'Primary Section',
			'type'          => 'select',
			'choices'       => $choices,
			'default_value' => 'profile',
			'allow_null'    => 0,
			'ui'            => 0,
		];
	}
}
