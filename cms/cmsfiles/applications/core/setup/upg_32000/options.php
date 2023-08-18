<?php
/**
 * @brief		Upgrader: Custom Upgrade Options
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		5 Jun 2014
 */

$options	= array(
	new \IPS\Helpers\Form\Radio( '32000_avatar_or_photo', NULL, TRUE, array( 'options' => array( 'avatars' => 'avph_avatar', 'photos' => 'avph_photo' ) ) )
);