=== Changelog as a Service - Publish, Display, and Communicate Beautiful Changelogs ===
Contributors: changelogwp, wpsunshine, pinedigital
Tags: changelog, version history, updates, plugin changelog, release notes
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.3.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Beautiful changelogs for plugins, themes, and more. Color-coded badges, search, and filtering. Connects to ChangelogWP.com.

== Description ==

Are you tired of managing changelogs manually or using plain text that looks unprofessional? ChangelogWP transforms how you create and display changelogs for your WordPress products, making it easy to show users what's new, what's fixed, and what's improved—all with beautiful, color-coded badges that make your updates stand out.

**Why You Need a Professional Changelog Plugin**

Whether you're a plugin developer, theme creator, or agency managing multiple WordPress products, maintaining clear, visually appealing changelogs is essential for:
* Building user trust by transparently showing what's changed
* Reducing support requests with clear update documentation
* Increasing user engagement with searchable, filterable changelogs
* Providing REST API endpoints for automation and syndication

Our changelog plugin solves the problem of unprofessional changelog displays by giving you a dedicated post type, beautiful badge system, and flexible display options—all while using the familiar WordPress readme.txt format you already know.

**🌐 Optional: [ChangelogWP.com](https://changelogwp.com) - Centralized Changelog Service & Email Notifications**

This plugin works perfectly on its own, but you can also optionally use it with ChangelogWP.com—a centralized changelog service that enhances your changelog workflow:

**For Product Owners:** Submit your changelogs to ChangelogWP.com using this plugin. Your changelogs will be available in a centralized directory where users can discover and subscribe to your product updates. Add a "Get notified of updates" signup link to your embedded changelogs so visitors can easily subscribe.

**For Site Owners:** Subscribe to any product's changelog on ChangelogWP.com to receive email notifications whenever new versions are released. Stay informed about updates, new features, bug fixes, and security patches for all your favorite WordPress plugins and themes—all in one place.

Whether you use ChangelogWP.com or not, this plugin provides everything you need to create and display beautiful changelogs on your WordPress site.

= What Makes This Changelog Plugin Different =

Unlike basic changelog solutions or manual HTML, our changelog plugin provides:

**Professional Display with Color-Coded Badges**
Automatically detect and display badges for Added (green), Fixed (orange), Improved (blue), Security (red), and more. Each badge is color-coded and customizable, making your changelog visually appealing and easy to scan.

**Flexible Display Options**
Display your changelog anywhere using shortcodes or the Gutenberg block. Customize heading levels, order (newest/oldest first), enable search and filtering, and control which versions to show—all without touching code.

**Easy Content Creation**
Use the familiar WordPress readme.txt format you already know. No HTML required, just simple text with version headers and bullet points. The plugin automatically formats everything beautifully.

**Developer-Friendly**
Full REST API support for automation and syndication. Integrates with Easy Digital Downloads for seamless product changelog management. Extensive filter and action hooks for complete customization.

= Key Features =

* **Dedicated Changelog Post Type** - Manage all your changelogs in one place
* **Color-Coded Badge System** - Automatic badges for Added, Fixed, Improved, Security, Dev, Deprecated, Removed, and Tweak
* **Fully Customizable Badges** - Customize colors, labels, emojis, and create unlimited custom badge types
* **Shortcode & Gutenberg Block** - Display changelogs anywhere with `[changelog_service]` or use the modern block editor
* **WordPress.org Plugin Support** - Display changelogs from any WordPress.org plugin by specifying its slug, with automatic daily caching
* **Search & Filter Functionality** - Let users search changelog content and filter by badge type
* **REST API Endpoints** - JSON endpoints for changelog syndication and automation
* **EDD Integration** - Works seamlessly with Easy Digital Downloads products
* **Version Control Meta Fields** - Track version, tested up to, requires WordPress/PHP, and homepage
* **Copy Link Buttons** - One-click copy links to specific versions (always enabled on hover)
* **Signup Links** - Let users subscribe to changelog updates via ChangelogWP.com with one click
* **Responsive & Accessible** - Full keyboard navigation, ARIA labels, and screen reader support

= Who Is This Plugin For? =

**WordPress Plugin Developers**
Maintain professional changelogs for your free and premium plugins. Display updates on your website with beautiful badges that build user trust.

**WordPress Theme Developers**
Show your theme updates clearly with searchable, filterable changelogs. Let users see exactly what changed in each version.

**Product Managers**
Keep users informed about updates, new features, bug fixes, and security patches with transparent, professional changelog displays.

== Installation ==

1. Upload the `changelog-service` folder to your `/wp-content/plugins/` directory, or install directly through the WordPress plugin screen.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Go to **Changelogs > Add New** to create your first changelog using the familiar readme.txt format.
4. Display your changelog anywhere using the `[changelog_service]` shortcode or the Gutenberg block.
5. Optionally, customize badge colors, labels, and styles under **Changelogs > Settings > Badges**.

== Frequently Asked Questions ==

= How do I display my changelog on my website? =

You have three easy options:
1. **Shortcode**: Use `[changelog_service id="123"]` or `[changelog_service slug="my-plugin"]` anywhere on your site
2. **Gutenberg Block**: Search for "Changelog" in the block inserter and select your changelog
3. **Automatic Display**: If your changelog post type is public, it displays automatically on single changelog pages

= Can I customize the badge colors and styles? =

Absolutely! Go to **Changelogs > Settings > Badges** to customize colors, labels, and emojis for each badge type. Choose from three badge styles (rounded, square, or pill) and position them left or inline. You can also create unlimited custom badge types with your own prefixes.

= Can I use this for WordPress themes or other projects? =

Yes! While the terminology focuses on plugins, it works perfectly for themes, any WordPress product, or even non-WordPress project changelogs. Just create a changelog post and use the shortcode or block wherever you need it.

= How do I format my changelog entries to get badges? =

Use the format "Prefix - Description" or "Prefix: Description" or "Prefix : Description". For example:
* `Added - New feature` (shows green "New" badge)
* `Fixed: Bug resolved` (shows orange "Fixed" badge)
* `Improved - Performance boost` (shows blue "Improved" badge)

See the Badge Format section below for all supported prefixes.

= Does this work with page builders like Elementor, Beaver Builder, or Divi? =

Yes! Use the `[changelog_service]` shortcode in any page builder that supports shortcodes. Most popular page builders have shortcode widgets or modules you can use.

= Can I search and filter changelog entries? =

Yes! Enable search and filtering in your shortcode: `[changelog_service id="123" search="true" filter_badges="true"]`. Users can search changelog content or filter by badge type (Added, Fixed, Improved, etc.). Results show version headers with matching items.

= Is there a REST API I can use? =

Yes! This changelog plugin provides REST API endpoints for changelog data:
* `GET /wp-json/changelogwp/v1/changelog/{slug}` - Get single changelog
* `GET /wp-json/changelogwp/v1/changelogs` - Get all changelogs with pagination

Perfect for automation, syndication, or integrating with external services.

= Does this integrate with Easy Digital Downloads? =

Yes! If you have EDD Software Licensing active, you can display changelogs for your EDD products using the `edd_id` attribute: `[changelog_service edd_id="456"]`. This is perfect for premium plugin developers managing product changelogs.

= Is it accessible for screen readers? =

Yes! It includes full accessibility support:
* Keyboard navigation for all interactive elements
* ARIA labels and roles for screen readers
* Visible focus indicators
* Semantic HTML structure
* Screen reader text for icon-only buttons like copy links

= Can I create custom badge types? =

Yes! Go to **Changelogs > Settings > Badges** and click "Add Badge" to create custom badge types with your own prefixes (like "Enhancement", "Performance", "Breaking"), colors, and emojis. No coding required.

= How do I link to specific versions in my changelog? =

Copy link buttons are always enabled and appear on hover next to each version number. Users can click to copy the URL with the version anchor, making it easy to share specific updates.

= How do I add a signup link for update notifications? =

Use the `signup` shortcode parameter to add a "Get notified of updates" link that connects to ChangelogWP.com:

**For WordPress.org plugins** (auto-generates URL from plugin slug):
`[changelog_service plugin_slug="woocommerce" signup="true"]`

**For other sources** (provide a custom URL):
`[changelog_service id="123" signup="https://changelogwp.com/plugin/my-plugin/"]`

You can also enable this globally in **Changelogs > Settings > Display** by checking "Signup Link". When using the Gutenberg block, toggle "Show Signup Link" in the block settings—if using a WordPress.org plugin, the URL is auto-generated; otherwise, enter a custom URL.

== Screenshots ==

1. Changelog edit screen showing the plain text editor with readme.txt format - easy to use, no HTML required
2. Settings page displaying badge customization options - customize colors, labels, emojis, and add custom badge types
3. Frontend changelog display with color-coded badges - professional, easy-to-scan changelog with Added, Fixed, Improved, and Security badges
4. Gutenberg block interface in the editor - select changelog and configure display options with the modern block editor

== Changelog ==

= 1.3.3 - September 15, 2026 =
* Fix: Changelogs pulled from Easy Digital Downloads Software Licensing showed a backslash before quotes and apostrophes

= 1.3.2 - April 3, 2026 =
* Fix: Allow x.x.x.x version numbers for version number parsing
* Fix: Allow pipe "|" as separator between version number and date

= 1.3.1 - March 24, 2026 =
* Fix: Allow version line to include date without a separator

= 1.3.0 - March 23, 2026 =
* New: Timeline template - a vertical timeline layout with version badges on the line and changelog items to the right
* New: `template` shortcode attribute - choose between "default" and "timeline" layouts
* New: Template selector added to Gutenberg block editor
* New: Default template setting in plugin settings with customizable timeline colors (line, version badge background, version badge text)
* New: Release notes support - free-form text between a version header and list items is now displayed as release notes
* New: Inline markdown support - bold, italic, links, and code in release notes and changelog items

= 1.2.0 - February 26, 2026 =
* New: Signup link setting to show "Get notified of updates" link to ChangelogWP.com
* New: `signup` shortcode parameter - set to "true" to auto-generate URL from plugin_slug, or provide custom URL
* New: Signup link attribute added to Gutenberg block with editor control
* New: Filter 'caas_shortcode_output'
* Fix: Settings page now preserves active tab after saving
* Fix: "View advanced usage" link in changelog editor now points to correct URL
* Fix: Badge parsing now supports en-dash (–) and em-dash (—) in addition to regular hyphen (-) for formats like "Fix – Description"

= 1.1.0 - January 23, 2026 =
* New: WordPress.org plugin slug support - Display changelogs from any WordPress.org plugin by specifying its slug
* New: Filter for 'changelog-service' post type arguments
* Change: Post type rewrite slug changed to "changelog-details" to better avoid permalink collisions
* Fix: Processing EDD changelogs with HTML structure

= 1.0.0 - January 16, 2026 =
* New: Changelog custom post type with plain text editor
* New: Color-coded badge system (Added, Fixed, Improved, Security, Dev, Deprecated, Removed, Tweak)
* New: Customizable badge colors, labels, and emojis
* New: Custom badge types with user-defined prefixes
* New: Shortcode support with flexible attributes
* New: Gutenberg block integration
* New: REST API endpoints for changelog data
* New: Search and filter functionality
* New: Copy link buttons for version anchors
* New: EDD Software Licensing integration
* New: Full accessibility support

== Credits ==

Badge system inspired by [EDD Changelog Badges](https://wordpress.org/plugins/edd-changelog-badges/) by Very Good Plugins.

Emoji picker powered by [emoji-picker-element](https://github.com/nolanlawson/emoji-picker-element) by Nolan Lawson (Apache-2.0 license).

