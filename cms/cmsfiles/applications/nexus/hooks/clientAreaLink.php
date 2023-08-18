//<?php

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
    exit;
}

class nexus_hook_clientAreaLink extends _HOOK_CLASS_
{

/* !Hook Data - DO NOT REMOVE */
public static function hookData() {
 return array_merge_recursive( array (
  'userBar' => 
  array (
    1 => 
    array (
      'selector' => '#cUserLink',
      'type' => 'add_before',
      'content' => '{{if \IPS\Member::loggedIn()->canAccessModule( \IPS\Application\Module::get( \'nexus\', \'store\' ) )}}
	{template="cartHeader" app="nexus" group="store" params=""}
{{endif}}',
    ),
    2 => 
    array (
      'selector' => '#elSignInLink',
      'type' => 'add_before',
      'content' => '{{if \IPS\Member::loggedIn()->canAccessModule( \IPS\Application\Module::get( \'nexus\', \'store\' ) )}}
	{template="cartHeader" app="nexus" group="store" params=""}
{{endif}}',
    ),
  ),
  'mobileNavigation' => 
  array (
    0 => 
    array (
      'selector' => '#elUserNav_mobile',
      'type' => 'add_inside_end',
      'content' => '{{if \IPS\Member::loggedIn()->canAccessModule( \IPS\Application\Module::get( \'nexus\', \'store\' ) )}}
	{template="cartHeaderMobile" app="nexus" group="store" params=""}
{{endif}}',
    ),
    1 => 
    array (
      'selector' => '#elMobileDrawer > div.ipsDrawer_menu > div.ipsDrawer_content',
      'type' => 'add_inside_start',
      'content' => '{{if !\IPS\Member::loggedIn()->member_id AND \IPS\Member::loggedIn()->canAccessModule( \IPS\Application\Module::get( \'nexus\', \'store\' ) )}}
<ul id=\'elUserNav_mobile\' class=\'ipsList_inline signed_in ipsClearfix\'>
	{template="cartHeaderMobile" app="nexus" group="store" params=""}
</ul>
{{endif}}',
    ),
  ),
  'globalTemplate' => 
  array (
    0 => 
    array (
      'selector' => 'html > head',
      'type' => 'add_inside_end',
      'content' => '{{if \IPS\Settings::i()->maxmind_key and \IPS\Settings::i()->maxmind_id and \IPS\Settings::i()->maxmind_tracking_code}}
<script>
  (function() {
    var mmapiws = window.__mmapiws = window.__mmapiws || {};
    mmapiws.accountId = "{expression=\'\IPS\Settings::i()->maxmind_id\'}";
    var loadDeviceJs = function() {
      var element = document.createElement(\'script\');
      element.async = true;
      element.src = \'https://device.maxmind.com/js/device.js\';
      document.body.appendChild(element);
    };
    if (window.addEventListener) {
      window.addEventListener(\'load\', loadDeviceJs, false);
    } else if (window.attachEvent) {
      window.attachEvent(\'onload\', loadDeviceJs);
    }
  })();
</script>
{{endif}}',
    ),
  ),
), parent::hookData() );
}
/* End Hook Data */












































}