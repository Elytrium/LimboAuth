<?php
/**
 * @brief		Upgrader: Custom Upgrade Options
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Forums
 * @since		5 Jun 2014
 */

$options	= array(
	new \IPS\Helpers\Form\Radio( '40000_qa_forum', 0, TRUE, array( 'options' => array( 0 => '40000_qa_forum_0', 1 => '40000_qa_forum_1' ) ) )
);