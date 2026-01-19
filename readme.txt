=== FRS Lead Pages ===
Contributors: 21stcenturylending
Tags: lead generation, landing pages, real estate, mortgage, open house
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.1
Stable tag: 1.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Lead generation landing page builder with multi-step wizard for mortgage and real estate professionals.

== Description ==

FRS Lead Pages is a powerful lead generation landing page builder designed for mortgage loan officers and real estate agents. Create beautiful, high-converting landing pages with an intuitive multi-step wizard.

= Features =

* **Open House Pages** - Create stunning property showcase pages with lead capture
* **Customer Spotlight Pages** - Highlight success stories and testimonials
* **Special Event Pages** - Promote seminars, webinars, and community events
* **Mortgage Calculator Pages** - Interactive calculator with lead capture
* **LO/Realtor Co-Branding** - Seamless partner collaboration
* **QR Code Generation** - Automatic QR codes for print materials
* **FluentForms Integration** - Powerful form builder integration
* **Analytics Dashboard** - Track views and conversions

= Requirements =

* WordPress 6.0 or higher
* PHP 8.1 or higher
* Fluent Forms plugin (required)
* FRS WP Users plugin (recommended for enhanced profiles)

== Installation ==

1. Upload the `frs-lead-pages` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Ensure Fluent Forms is installed and activated
4. Navigate to Lead Pages in the admin menu to get started

== Changelog ==

= 1.3.0 =
* Fixed: NMLS now pulls from FRS Profiles table and person post meta instead of placeholder user meta values
* Added: frs_get_user_nmls() helper function for accurate NMLS lookup across all data sources
* Updated: All landing page types (Open House, Customer Spotlight, Special Event, Mortgage Calculator) now display real NMLS numbers

= 1.2.0 =
* Added: NMLS lookup from FRS Profiles table for accurate data
* Added: Support for linked person post meta as NMLS source
* Fixed: NMLS placeholder values replaced with real data
* Improved: Role-based permissions for page creation
* Improved: Lead viewing permissions (LO sees assigned, Realtor sees own)

= 1.1.1 =
* Fixed: Permission checks for page creation
* Improved: REST API endpoint security

= 1.1.0 =
* Added: Mortgage Calculator page type
* Added: Custom capabilities for lead pages
* Improved: FluentForms integration

= 1.0.0 =
* Initial release
