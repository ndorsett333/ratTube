=== RatTube ===
Contributors: rattube
Tags: media, workflow, custom-post-type
Requires at least: 6.5
Tested up to: 6.7
Requires PHP: 8.1
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

RatTube provides a foundation for collecting media conversion requests through a frontend form and storing them as Rat Media posts.

== Description ==
This initial release includes:
- Rat Media custom post type and meta schema.
- Frontend converter page auto-created at /rat-media-convert.
- Shortcode-based form with nonce verification and safe validation.
- Settings scaffold at Settings > RatTube.
- Submission log area for validation and persistence issues.

No conversion/downloading logic is included yet.

== Installation ==
1. Upload the plugin folder to /wp-content/plugins/.
2. Activate RatTube from Plugins.
3. Visit the frontend page at /rat-media-convert.
4. Submit a URL and output format to create a Rat Media draft entry.

== Future Extension Hooks ==
- Action: rattube_submission_created($post_id, $payload)
- Action: rattube_after_submission_prepared($post_id, $payload)
- Filter: rattube_allowed_output_formats($formats)
- Filter: rattube_converter_slug($slug)

== Changelog ==
= 0.1.0 =
* Initial foundation scaffold.
