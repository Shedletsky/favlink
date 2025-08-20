<?php
/**
 * Plugin Name: Favlink
 * Plugin URI:  https://example.com/favlink
 * Description: Shortcode [favlink url="" text="" size=""]. Renders a site favicon + link text.
 *              • `size` omitted → icon auto-scales to the surrounding font-size (incl. Gutenberg presets).
 *              • Handles theme quirks that shove first-child links left, guarantees icon obeys font sizing.
 * Version:     1.4.1
 * Author:      John Shedletsky
 * License:     GPL-2.0+
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Prevent direct access.
}

if ( ! class_exists( 'Favlink' ) ) :

final class Favlink {

	/**
	 * Whether the shortcode was rendered on this request.
	 *
	 * @var bool
	 */
	private static $needs_css = false;

	/**
	 * Entry point.
	 */
	public static function init() : void {
		add_shortcode( 'favlink', [ __CLASS__, 'render_shortcode' ] );

		// Front-end styles.
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_styles_if_needed' ], 20 );
		// Block-editor styles so icons look correct while editing.
		add_action( 'enqueue_block_editor_assets', [ __CLASS__, 'enqueue_editor_styles_if_needed' ], 20 );
	}

	/**
	 * Shortcode callback.
	 *
	 * @param array $atts url, text, size (optional px).
	 * @return string Link HTML.
	 */
	public static function render_shortcode( array $atts ) : string {
		$atts = shortcode_atts(
			[
				'url'  => '',
				'text' => '',
				'size' => '', // empty → autosize.
			],
			$atts,
			'favlink'
		);

		// Bail if no valid URL.
		$domain = parse_url( $atts['url'], PHP_URL_HOST );
		if ( ! $domain ) {
			return '';
		}

		$icon_src  = esc_url( "https://icons.duckduckgo.com/ip3/{$domain}.ico" );
		$link_text = $atts['text'] ?: $domain;
		$size_num  = (int) $atts['size'];

		// Build optional explicit dimensions.
		$dim_attr = '';
		$style    = ' style="height:1em!important;width:auto!important"';
		if ( $size_num > 0 ) {
			$dim_attr = sprintf( ' width="%1$d" height="%1$d"', $size_num );
			// Add inline style to overrule any !important rules from themes/minifiers.
			$style    = sprintf( ' style="height:%1$dpx;width:%1$dpx"', $size_num );
		}

		self::$needs_css = true;

		// Added decoding="async" and fetchpriority="low" for non-blocking load.
		return sprintf(
			'<a href="%1$s" class="favlink" rel="noopener noreferrer" target="_blank">' .
				'<img loading="lazy" decoding="async" fetchpriority="low" src="%2$s" alt=""%3$s%4$s /> %5$s' .
			'</a>',
			esc_url( $atts['url'] ),
			$icon_src,
			$dim_attr,
			$style,
			esc_html( $link_text )
		);
	}

	/**
	 * Front-end: enqueue inline CSS once.
	 */
	public static function enqueue_styles_if_needed() : void {
		if ( ! self::$needs_css ) {
			return;
		}
		wp_enqueue_style( 'favlink-style', false, [], '1.4.1' );
		wp_add_inline_style( 'favlink-style', self::build_css() );
	}

	/**
	 * Block editor: enqueue identical CSS so icons scale in the editor canvas.
	 */
	public static function enqueue_editor_styles_if_needed() : void {
		if ( ! self::$needs_css ) {
			return;
		}
		wp_enqueue_style( 'favlink-editor-style', false, [], '1.4.1' );
		wp_add_inline_style( 'favlink-editor-style', self::build_css( true ) );
	}

	/**
	 * Construct resilient CSS rules. `$in_editor = true` adds the editor wrapper selectors.
	 */
	private static function build_css( bool $in_editor = false ) : string {
		$base_selector = $in_editor ? '.editor-styles-wrapper ' : '';

		$rules = [
			// Anchor layout: inline-flex ensures icon + text stay together; zero out indent hacks.
			"{$base_selector}.favlink{display:inline-flex;align-items:baseline;gap:.35em;vertical-align:baseline;max-width:100%;text-indent:0!important;margin:0!important;padding:0!important;line-height:inherit}",
			// Default icon size = 1em of current font-size.
			"{$base_selector}.favlink img{flex:0 0 auto;height:1em!important;width:auto!important;vertical-align:baseline}",
			// Prevent first-child negative indents used by some themes.
			"{$base_selector}p > .favlink:first-child{margin-left:0!important;text-indent:0!important}",
		];

		// Gutenberg typography presets. Map both front and (optionally) editor.
		$presets = [
			'small'   => 'small',
			'medium'  => 'medium',
			'large'   => 'large',
			'x-large' => 'x-large',
			'xx-large'=> 'xx-large',
		];
		foreach ( $presets as $slug => $var ) {
			$rules[] = "{$base_selector}.has-{$slug}-font-size .favlink img{height:var(--wp--preset--font-size--{$var})!important}";
		}

		return implode( '', $rules );
	}
}

Favlink::init();

endif;
