<?php
/**
 * Print SVG markup
 *
 * @category   Plugin
 * @package    Mo\Core
 * @author     Christoph Schüßler <schuessler@montagmorgens.com>
 * @license    https://www.gnu.org/licenses/gpl-2.0.txt GNU/GPLv2
 * @since      1.0.0
 */

namespace Mo\Core;

trait Svg {

	/**
	 * The path inside the theme directory at which the SVG files of the used icon sets are supposed to be found.
	 * 
	 * @var string Icon set path.
	 */
	private static $icon_set_path = '/assets/svg-sprite/';

	/**
	 * Handle icon sets which are loaded from a content URL that has deviates from the domain of the site URL.
	 * 
	 * External domains are not compatable with `<use href="..."/>`, so we need
	 * fetch the SVG data manually and insert it into the HTML.
	 */
	protected function fix_icon_sets_for_external_content_url() {
		if (parse_url(get_template_directory_uri(), PHP_URL_HOST) == parse_url(get_site_url(), PHP_URL_HOST))
			return;

		add_action('wp_footer', function() {
			?><script type="text/javascript">
				(async () => {
					const iconSetBaseUrl = "<?= get_template_directory_uri() . self::$icon_set_path ?>";
					const iconSetBaseUrlRegExp = iconSetBaseUrl.replace(/[.*+?^${}()|\/[\]\\]/g, '\\$&');

					const iconSetUses = Array.from(document.querySelectorAll("svg use"))
						.map((elem) => {
							const url = elem.getAttribute("xlink:href") || elem.getAttribute("href");
							return url && url.match(new RegExp(`^${iconSetBaseUrlRegExp}`))
								? { elem, url }
								: null;
						})
						.filter((result) => !!result)
						.map((data) => {
							const result = data.url.match(
								new RegExp(`^${iconSetBaseUrlRegExp}((?:(?!\.svg).)+)\.svg[^#]*#(.+)`)
							);
							return { setId: result[1], iconId: result[2], ...data };
						});

					const uniqueIconSetUrls = {};
					iconSetUses.forEach(({ url, setId }) => {
						if (!uniqueIconSetUrls[setId])
							uniqueIconSetUrls[setId] = url.split("#")[0];
					});

					const svgHtml = await Promise.all(
						Object.values(uniqueIconSetUrls).map((url) =>
							fetch(url).then((res) => res.text())
						)
					).then((parts) => parts.join(""));

					const wrapper = document.createElement("div");
					wrapper.style.display = "none";
					wrapper.innerHTML = svgHtml;
					document.body.insertBefore(wrapper, document.body.childNodes[0]);

					iconSetUses.forEach(({elem, iconId}) => {
						elem.removeAttribute("xlink:href");
						elem.removeAttribute("href");
						elem.setAttribute("href", `#${iconId}`);
					});
				})();
			</script><?php
		});
	}

	/**
	 * Print SVG icon markup from image sprite.
	 *
	 * @param string       $icon The icon name in the SVG sprite map.
	 * @param string/array $classes CSS class names (optional).
	 * @param string       $title The title (optional).
	 * @param string       $desc The description (optional).
	 * @param string       $role The aria-role (defaults to 'presentation').
	 * @param string       $set The icon set, i.e. the filename of the SVG sprite map (without extension).
	 * @param bool         $basetheme Set to false if the sprite should load from a child theme.
	 */
	public function the_svg_icon( $icon, $classes = null, $title = null, $desc = null, $role = 'presentation', $set = 'ui', $basetheme = true ) {

		if ( ! is_string( $icon ) || empty( $icon ) || ! is_string( $set ) ) {
			return false;
		}

		$path  = $basetheme ? get_template_directory_uri() : get_stylesheet_directory_uri();
		$theme = $basetheme ? wp_get_theme( get_template() ) : wp_get_theme();

		$data['icon'] = $path . filter_var( '/assets/svg-sprite/' . $set . '.svg?v=' . rawurlencode( $theme['Version'] ) . '#' . $icon, FILTER_SANITIZE_URL );

		// The CSS classes.
		$classes = is_array( $classes ) ? join( ' ', $classes ) : $classes;
		if ( is_string( $classes ) && ! empty( $classes ) ) {
			$data['classes'] = trim( filter_var( $classes, FILTER_SANITIZE_STRING ) );
		}

		// The title.
		if ( is_string( $title ) && ! empty( $title ) ) {
			$data['title'] = trim( filter_var( $title, FILTER_SANITIZE_STRING ) );
		}

		// The description.
		if ( is_string( $desc ) && ! empty( $desc ) ) {
			$data['desc'] = trim( filter_var( $desc, FILTER_SANITIZE_STRING ) );
		}

		// The aria-role.
		if ( 'image' === $role ) {
			$data['role'] = 'image';
		} else {
			$data['role'] = 'presentation';
		}

		return \Timber::compile_string(
			'
			{% apply spaceless %}
			<svg role="{{ role }}"{% if classes %} class="{{ classes }}"{% endif %}>

			{% if title %}
			<title>{{ title }}</title>
			{% endif %}

			{% if desc %}
			<desc>{{ desc }}</desc>
			{% endif %}

			<use xlink:href="{{ icon }}"></use>
			</svg>
			{% endapply %}
			',
			$data
		);
	}

	/**
	 * Print SVG image markup from image sprite.
	 *
	 * @param string       $icon The icon name in the SVG sprite map.
	 * @param string/array $classes CSS class names (optional).
	 * @param string       $alt The alt text (optional).
	 * @param string       $role The aria-role (defaults to 'presentation').
	 * @param bool         $basetheme Set to false if the image should load from a child theme.
	 */
	public function the_svg_img( $icon, $classes = null, $alt = null, $role = 'presentation', $basetheme = true ) {

		if ( ! is_string( $icon ) || empty( $icon ) ) {
			return false;
		}

		$path = $basetheme ? get_template_directory_uri() : get_stylesheet_directory_uri();

		$data['src'] = filter_var( $path . '/assets/images/' . $icon . '.svg', FILTER_SANITIZE_URL );

		// The CSS classes.
		$classes = is_array( $classes ) ? join( ' ', $classes ) : $classes;
		if ( is_string( $classes ) && ! empty( $classes ) ) {
			$data['classes'] = trim( filter_var( $classes, FILTER_SANITIZE_STRING ) );
		}

		// The alt text.
		if ( is_string( $alt ) && ! empty( $alt ) ) {
			$data['alt'] = trim( filter_var( $alt, FILTER_SANITIZE_STRING ) );
		}

		// The aria-role.
		if ( 'image' === $role ) {
			$data['role'] = 'image';
		} else {
			$data['role'] = 'presentation';
		}

		return \Timber::compile_string(
			'<img src="{{ src }}" class="{{ classes ? classes ~ " js-inline-svg" : "js-inline-svg" }}"{% if alt %} alt="{{ alt }}"{% endif %} role="{{ role }}">',
			$data
		);
	}

	/**
	 * Get SVG file and return SVG markup.
	 *
	 * @param Timber\Image $image The image object.
	 */
	public function get_svg_content( $image ) {
		if ( empty( $image ) || gettype( $image ) !== 'object' || get_class( $image ) !== 'Timber\Image' || 'image/svg+xml' !== $image->post_mime_type ) {
			return false;
		}

		// Parse SVG ans strip namespace declaration.
		// phpcs:ignore WordPress.WP.AlternativeFunctions
		$svg = file_get_contents( $image->file_loc );
		if ( $svg ) {
			$svg = new \SimpleXMLElement( $svg );
			$dom = dom_import_simplexml( $svg );
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			return $dom->ownerDocument->saveXML( $dom->ownerDocument->documentElement );
		}
		return false;
	}
}
