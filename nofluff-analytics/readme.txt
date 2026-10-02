=== No Fluff Analytics ===
Contributors: nofluff
Tags: analytics, statistics, privacy, core web vitals, campaigns
Requires at least: 6.3
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.4.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Connects your site to the No Fluff dashboard: visitor statistics, campaigns, Core Web Vitals, goals and shop revenue, without code.

== Description ==

No Fluff Analytics adds the No Fluff tracking script to every page of your site. You need a No Fluff dashboard account; the site ID is on your site's page in the dashboard.

* By default the tracking script stores nothing in the visitor's browser and reads nothing stored there
* Pageviews, referrers and Core Web Vitals from real visitors
* Optional goals: successful form submissions (Contact Form 7, WPForms, Gravity Forms, Elementor, Bricks) and WooCommerce orders
* Shop orders and revenue in the dashboard, signed with a secret so nobody else can report sales for your site
* Optional, for visitors who consent: campaigns linked to enquiries and orders on later visits, even after a visit of a single page, through an identity snippet you place in your consent tool (the plugin never loads it itself)
* Your own logged-in editors are not counted
* Works with common caching and optimisation plugins (WP Rocket, Autoptimize, LiteSpeed Cache, SiteGround Optimizer, Cloudflare Rocket Loader)
* Adds a suggested section to your privacy policy guide, with or without the identity snippet

= External service =

This plugin loads a script from, and sends usage data to, the No Fluff dashboard (by default https://app.nofluff.agency). Sent per pageview: page address, referring page, browser language, screen width, and loading-time measurements; for goals, the event name, the form plugin and form ID, or the order number, order total and currency, plus, if an order secret is set, a signature of those three values computed on your server (the secret itself is never sent). The tracking script stores nothing in the browser and reads nothing stored there. The IP address and browser identifier are used to form a pseudonymous value that changes daily; only the device type, the browser and operating system family and the country are stored, not the IP address. Requests to the dashboard pass through Cloudflare (Cloudflare, Inc., USA), which No Fluff uses as a proxy in front of it: https://www.cloudflare.com/privacypolicy/

The plugin does not load the optional identity snippet (nf-id.js, from the same address). If you place it in your consent tool, it stores one random identifier in the visitor's browser (local storage key nf_id, first party, no expiry; the value is renewed after 400 days) once the visitor agrees, hands it to the tracking script on the same page, and sends nothing itself. The tracking script adds the identifier to the page's pageview, if that has not been sent yet, and to later goal events on that page; if the pageview was already sent, it sends the identifier once for that page, with the page address, so the current visit is linked from the moment of consent. From the next page after a withdrawal, the identifier is no longer read or sent; the visit in which it happens stays linked until it ends (after 30 minutes without activity). Data about individual visits is deleted 14 months after the end of the month in which it was collected.

Service: https://nofluff.agency

== Installation ==

1. Upload the plugin through Plugins → Add New → Upload Plugin and activate it.
2. Open Settings → No Fluff.
3. Paste your site ID (or the whole snippet) from the No Fluff dashboard and save.

For the US region, define `NOFLUFF_ANALYTICS_HOST` in wp-config.php with the dashboard address you were given.

== Frequently Asked Questions ==

= I do not see any visits =

Visits of logged-in administrators, editors and authors are not counted by default. Check in a private browser window. If the site sends a Content-Security-Policy header, it must allow the dashboard address in script-src and connect-src.

= How do I get campaign and revenue data? =

Campaigns need nothing extra within one visit: add utm_source, utm_medium and utm_campaign to the links you share; Google and Microsoft ad clicks are recognised automatically. Revenue needs the order secret (see below). To link a campaign to an enquiry or order on a later visit, the visitor has to agree to statistics in your consent tool and the identity snippet from Settings → No Fluff has to run there. No Fluff can place it for you. If you do it yourself, put it in the statistics category of your consent tool, then tick "I placed the identity snippet in my consent tool" so the suggested privacy policy text covers it.

= Does the plugin handle consent? =

No. It never loads the identity snippet itself, because it cannot know whether a visitor agreed; that is your consent tool's job. The tracking script the plugin adds must stay outside the consent tool: if the consent tool blocks it, visits before consent are not counted and your numbers drop.

= How do form submissions become goals? =

In the dashboard, add a goal of type "Custom event" named `form_submit` (or `purchase` for WooCommerce orders).

= Where does the order secret come from? =

From your site's page in the dashboard, next to the site ID. It is needed if you want orders and their totals in the dashboard's Orders and Revenue figures: the plugin signs each order with it on your server, so only your shop can report orders for your site. It does not check that an order was paid (see below). Without it, orders only count as goal conversions. If a new secret is created for your site in the dashboard (No Fluff does this on request), paste it here as well. Orders placed in between are not counted as orders or revenue.

= An order is missing from the revenue =

First check that it was sent at all. Orders placed while logged in as an administrator, shop manager, editor or author are not sent while "Do not count logged-in users who can edit content" is on, so test with a customer account or in a private window. Nothing is sent from a copy of the site whose WordPress environment type is not "production" (a staging or development copy), or for an order whose payment failed or was cancelled; a successful retry of the same order is sent. Orders with a total of 0, and orders whose buyer never reaches the order confirmation page, are not counted either.

An order only counts as an order and as revenue if it was signed: check that the order secret here matches the one in the dashboard. Orders in a currency other than the site's own are counted as orders but not added to the revenue, and single orders above 10,000 are not counted. The same order number counts once, so use one site ID per shop, and keep in mind that changing the order numbering later can reuse numbers that were already counted. Revenue is the order total at checkout, including orders still awaiting payment such as bank transfers; refunds and later cancellations are not subtracted.

== Changelog ==

= 1.4.1 =
* Bricks Builder forms count as sent, with the form element's id, like the other form plugins.
* The suggested privacy policy text for the identity snippet says which pages the identifier is linked to: the page on which the visitor agreed, including the campaign link that brought them there, and the pages after it. Pages opened earlier in that visit are not linked, and nothing stays linked after a withdrawal. The No Fluff dashboard released with it no longer links pages through the IP address and browser identifier.

= 1.3.0 =
* Pages that do not exist (404) are reported as the event "nf_404", so the dashboard can show which addresses Google still sends visitors to.
* Elementor Pro forms report the form's widget id instead of its name.
* The suggested privacy policy text names what the tracking script now measures on a page: active time, scroll depth, the kind of contact link tapped (not its target) and form starts and sends (not what is entered).
* The suggested privacy policy text gives the legal basis for allowing Cloudflare its own use of the traffic data: Art. 6(1)(f) GDPR, the security of No Fluff's service and of Cloudflare's network.

= 1.2.1 =
* The suggested privacy policy text names Cloudflare, through whose network requests reach No Fluff's servers in the EU: what it processes, that this can happen outside the EU, and the basis for transfers to the USA and other countries.

= 1.2.0 =
* Settings → No Fluff shows the optional identity snippet to place in the statistics category of your consent tool, for campaigns across visits. The plugin never loads it itself.
* The identity snippet hands its identifier to the tracking script on the same page, so a visit of a single page is linked to its campaign as soon as the visitor agrees. The tracking script itself still stores nothing in the browser and reads nothing stored there.
* New setting "I placed the identity snippet in my consent tool": it only switches the suggested privacy policy text to the version that covers the identifier, the link to the current visit and its earlier pages, and that a withdrawal takes effect from the next page while the visit in which it happens stays linked until it ends.
* The suggested privacy policy text now says how long visit data is kept and no longer calls the service cookieless; nor do the plugin's descriptions.
* The suggested privacy policy text names the stored key, says it stays until the visitor deletes the site's data, and lists the device type, browser, operating system and country stored with each page.

= 1.1.0 =
* WooCommerce orders now also send the order number and, if the order secret is set, a signature, so the dashboard can show orders and revenue.
* New setting: order secret.
* Orders whose payment failed or was cancelled are no longer reported; a successful retry of the same order is.
* Orders are no longer reported from staging or development copies (WordPress environment type other than production).

= 1.0.0 =
* First release.

== Upgrade Notice ==

= 1.4.1 =
Bricks Builder forms now count as sent. Only with the identity snippet: the suggested privacy policy text changed (which pages the identifier is linked to; nothing stays linked after a withdrawal). Check Settings → Privacy and update your privacy policy.

= 1.3.0 =
Reports 404 pages and the Elementor Pro form id. The suggested privacy policy text changed (what is measured on a page, the legal basis for Cloudflare's own use): check Settings → Privacy and update your privacy policy.

= 1.2.1 =
The suggested privacy policy text now names Cloudflare: check Settings → Privacy and update your privacy policy.

= 1.2.0 =
Shows the optional identity snippet for your consent tool. Nothing changes on your site until you place it. The suggested privacy policy text changed: check Settings → Privacy.
