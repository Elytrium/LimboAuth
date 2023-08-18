//<?php

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	exit;
}

class cms_hook_topics extends _HOOK_CLASS_
{

/* !Hook Data - DO NOT REMOVE */
public static function hookData() {
 return array_merge_recursive( array (
  'topic' => 
  array (
    0 => 
    array (
      'selector' => '#elTopicActions_menu',
      'type' => 'add_inside_start',
      'content' => '{{if $topic->hidden() != -2 and \IPS\Member::loggedIn()->modPermission( \'can_copy_topic_database\' )}}
	<li class=\'ipsMenu_item\'>
      	<a href=\'{url="app=cms&module=database&controller=topic&id=$topic->tid&_new=1" base="front" seoTemplate="topic_copy" seoTitle="$topic->title_seo"}\' data-ipsDialog data-ipsDialog-size=\'narrow\' data-ipsDialog-remoteVerify=\'false\' data-ipsDialog-title=\'{lang="copy_select_database"}\'>{lang="copy_topic_to_database"}</a>
	</li>
{{endif}}',
    ),
    1 => 
    array (
      'selector' => '#elTopicActionsBottom_menu',
      'type' => 'add_inside_start',
      'content' => '{{if $topic->hidden() != -2 and \IPS\Member::loggedIn()->modPermission( \'can_copy_topic_database\' )}}
	<li class=\'ipsMenu_item\'>
      	<a href=\'{url="app=cms&module=database&controller=topic&id=$topic->tid&_new=1" base="front" seoTemplate="topic_copy" seoTitle="$topic->title_seo"}\' data-ipsDialog data-ipsDialog-size=\'narrow\' data-ipsDialog-remoteVerify=\'false\' data-ipsDialog-title=\'{lang="copy_select_database"}\'>{lang="copy_topic_to_database"}</a>
	</li>
{{endif}}',
    ),
  ),
), parent::hookData() );
}
/* End Hook Data */


}
