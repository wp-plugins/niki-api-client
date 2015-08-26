=== Niki API Client ===
Contributors: hzegwaard, jurgen_fundament
Donate link: http://fundament.nl/
Tags: niki rest api, api, client
Requires at least: 3.0.1
Tested up to: 4.1.1
Stable tag: 0.2.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html


This Niki API Client is an interface to the Niki house-database API, for easy usage in Wordpress sites. For example using a template.

== Description ==

The Niki API Client is a Wordpress implementation of the Niki.nl REST API. It yields the
following functionality:

* authorisation: using username and password, acquire an oauth (version 1.0) token to access the Niki API
* project selection: in a list of all projects in your account, select the relevant project(s) to use in the website
* resource implementations: Implementation of all resources of the [Niki REST API](https://api.niki.nl/apidocs) except for the 'search' resources.

= Example implementation =  
 In the plugin directory, a folder named 'examples' is included. This folder contains 3 items:
 # /classes: example view classes for housetypes, housetype-listings, and interest forms
 # /theme: contains an example wordpress-theme, this theme can be installed (copied) in your theme folder
 # niki-functions.php: file providing methods for displaying various Niki data
 
 Of course, these are example implementations, not ment for direct production use. They will point you in the right direction while
 implementing your own Niki-data filled website.

== Installation ==

= From your WordPress dashboard =
1. Visit 'Plugins > Add New'
2. Search for 'Niki API client'
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Under `Niki server`, enter `Niki OAuth URL` : `https://auth.niki.nl`
5. Under `Niki server`, enter `Niki API URL` : `https://api.niki.nl`
6. Press `Wijzigingen opslaan`
7. Click `Vraag Niki API Token aan` and follow instructions.
8. When the token is retrieved, under `Niki projecten` check the relevant projects and click `Wijzigingen opslaan`
9. You are now ready to use the Niki data in your Wordpress site.

= From WordPress.org =
1. Download Niki API client
2. Upload the plugin folder to the `/wp-content/plugins/` directory using your favorite method (ftp, sftp, scp, etc...)
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Under `Niki server`, enter `Niki OAuth URL` : `https://auth.niki.nl`
5. Under `Niki server`, enter `Niki API URL` : `https://api.niki.nl`
6. Press `Wijzigingen opslaan`
7. Click `Vraag Niki API Token aan` and follow instructions.
8. When the token is retrieved, under `Niki projecten` check the relevant projects and click `Wijzigingen opslaan`
9. You are now ready to use the Niki data in your Wordpress site.	 

= Installing the example theme =
1. Using FTP, copy the folder /examples/theme/niki-template to your themes folder
2. Go to your admin dashboard http://www.yoursite.nl/wp-admin
3. Under display->theme's, click 'activate' on item 'Niki Template'
4. Now go visit your site http://www.yoursite.nl/niki/aanbod and http://www.yoursite.nl/niki/interesse 



== Frequently Asked Questions ==

= How do I show information of a specific housetype in my site? =

Include the `niki-functions.php` in your page template and call the specific housetype information
by specifying project id and housetype id like so:

`
<?
require_once $niki->get_plugin_path() . "examples/niki-functions.php";
niki_show_woningtype('TBIGEN_79DBBC06-518B-4591-882E-EE63359CCBA8','TBIGEN_4D14999B-87DB-4874-ACB9-B3CBD87B2936');
?>
`
In this example, the first parameter is the Niki project id, and the second parameter is the Niki housetype id.

= How do I list the housetypes in my selected projects? =

Include the `niki-functions.php` in your page template and call the listing example function like so:
`
<? 
require_once $niki->get_plugin_path() . "examples/niki-functions.php";
niki_show_aanbod();	
?>
`

This will generate a listing of all housetypes in the projects selected in the admin configuration.


= How do i render a default interest form? =

Include the `niki-functions.php` in your page template (if you dit not do already so), and show the form:
`
<? 
require_once $niki->get_plugin_path() . "examples/niki-functions.php";
niki_show_interesse();	
?>
`

= How do I make a generic Niki API request =

Given any Niki API resource, you can access the Niki API by the following code:
`
<?
// example project resource
$resource = '/projects/34/TBIGEN_79DBBC06-518B-4591-882E-EE63359CCBA8' ;
$myProject = $niki->get_niki_resource($resource, array()); // empty parameter array, not needed here
// display the contents of $myProject
var_dump($myProject);
?>
`

== Changelog ==
= 0.2.3 =
Added Jurgen to contributors

= 0.2.2 = 
Added extra div to detailpage for additional styling options.

= 0.2.1 = 
Always initialize niki object in contructor for global accessing of plugin.

= 0.1 =
Initial Release
