=== WP-Pingdom ===

Contributors: ecb29
Tags: ping, pingdom, monitoring, widget, stats, statistics, uptime
Tested up to: 2.3
Stable tag: trunk

Adds a sidebar widget to display Website uptime using Pingdom, with which you must have an account and api key.

== Description ==

= What is WP Pingdom? =

WP Pingdom brings the power of Pingdom's automatic site monitoring to your Wordpress blog, allowing you to include your uptime statistics on your blog in textual or graphical form. The remote calls to Pingdom and the graph image are cached, so the Widget should not affect your site's performance.

You can read more at the [WP Pingdom plugin](http://wordpress-plugins.feifei.us/pingdom/) page.  WP Pingdom is 100% GPL compatible.

= Requirements =

   1. Your Pingdom username, password, and api-key
   2. Wordpress 2.1+
   3. PHP-soap extension
   4. PHP 5.0+

== Installation ==

= Installation Instructions =

1. Download the plugin and unzip it.
2. Copy the pingdom folder to wp-content/plugins
3. Make sure wp-content/cache is writable (777)
4. Add `define('ENABLE_CACHE', true);` to your wp-config.php file
5. Activate the plugin and drag into your Widgetized sidebar
6. Fill in your personalized information and hit save.

= Notes =

Should you encounter any issues using this widget, please leave a comment. Likewise for improvements, outcry, and other commentary you might have.

== Screenshots ==

1. Wordpress Pingdom Widget In Action