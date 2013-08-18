## People-Place-Sync

* Plugin Name: People Place Sync
* Plugin URI: [github.com/CreativePeoplePlace/People-Place-Sync](https://github.com/CreativePeoplePlace/People-Place-Sync)
* Description: Sync a MailChimp subscriber list with the People Place Plugin
* Author: Community Powered
* Version: 1.1
* Author URI: [creativepeoplepace.info](http://creativepeopleplace.info)
* License: GNU General Public License v2.0
* License URI: [http://www.gnu.org/licenses/gpl-2.0.html](http://www.gnu.org/licenses/gpl-2.0.html)
* Text Domain: pps

## About

People Place Sync is an open source WordPress plugin that syncs a MailChimp subscriber list with the [People Place Plugin](https://github.com/CreativePeoplePlace/People-Place). 

We hope the plugin is flexible enough to accommodate your own MailChimp lists but some technical knowledge may be required to get it setup and working. At the very least you will require/need:

* Your MailChimp API key
* Your MailChimp List ID
* A custom Postcode field with a field ID of `POSTCODE` in your MailChimp subscriber list
* A custom Organisation field with a field ID of `ORGANISATI` in your MailChimp subscriber list
* A custom URL field with a field ID of `URL` in your MailChimp subscriber list

There is a 150000 subscriber list limit.

Help support development by [purchasing this plugin](https://gumroad.com/l/people-place).

## Attention!

This is the first version of the plugin and it may have a few minor bugs. Please let us know if you experience problems.

Please submit all bugs, questions and suggestions to the [GitHub Issues](https://github.com/CreativePeoplePlace/People-Place-Sync/issues) queue.

## Installation

To install this plugin:

1. Login to your wp-admin area and visit "Plugins -> Add New". Select the Upload link at the top of the page. Browse to the .zip file you have downloaded from GitHub and hit the "Install Now" button.
1. Alternatively you can unzip the plugin folder (.zip). Then, via FTP, upload the "People-Place-Sync" folder to your server and place it in the /wp-content/plugins/ directory.
1. Login to your wp-admin and visit "Plugins" once more. Scroll down until you find this plugin in the list and activate it.

## Usage

Head to "Settings -> MailChimp Sync" in your WordPress dashboard and enter your API details. You can also setup a manual cron job (if WordPress cron fails to work) by entering a secret key for manual cron. This will disable WordPress cron. The URL to ping is:

http://yoursite.com/?pps_key=secretkey

## Changelog

#### 1.1
* Add support for syncing voluntary activity

#### 1.0
* Uploaded to Github - this should be considered an alpha release
