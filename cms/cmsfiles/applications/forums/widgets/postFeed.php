<?php
/**
 * @brief		Topic Feed Widget
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Forums
 * @since		16 Oct 2014
 */

namespace IPS\forums\widgets;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * postFeed Widget
 */
class _postFeed extends \IPS\Content\WidgetComment
{
	/**
	 * @brief	Widget Key
	 */
	public $key = 'postFeed';
	
	/**
	 * @brief	App
	 */
	public $app = 'forums';
		
	/**
	 * @brief	Plugin
	 */
	public $plugin = '';

	/**
	 * Class
	 */
	protected static $class = 'IPS\forums\Topic\Post';

	/**
	 * @brief	Moderator permission to generate caches on [optional]
	 */
	protected $moderatorPermissions	= array( 'can_view_hidden_content', 'can_view_hidden_post' );

 	/**
 	 * Return the form elements to use
 	 *
 	 * @return array
 	 */
 	protected function formElements()
 	{
 		$elements = parent::formElements();

		$class		= static::$class;
		$itemClass	= $class::$itemClass;

 		$elements['container'] = new \IPS\Helpers\Form\Node( 'widget_feed_container_' . $itemClass::$title, isset( $this->configuration['widget_feed_container'] ) ? $this->configuration['widget_feed_container'] : 0, FALSE, array(
				'class'           => $itemClass::$containerNodeClass,
				'zeroVal'         => 'all_public',
				'permissionCheck' => function ( $forum )
				{
					return $forum->sub_can_post and !$forum->redirect_url and $forum->can_view_others;
				},
				'multiple'        => true,
				'forceOwner'	  => false,
				'clubs'			  => TRUE
			) );

 		return $elements;
 	}

	/**
	 * Get where clause
	 *
	 * @return	array
	 */
	protected function buildWhere()
	{
		$class		= static::$class;
		$itemClass	= $class::$itemClass;
		$containerClass = $itemClass::$containerNodeClass;
		$where = parent::buildWhere();
		if ( !isset( $this->configuration['widget_feed_use_perms'] ) or $this->configuration['widget_feed_use_perms'] )
		{
			if ( $customNodes = $containerClass::customPermissionNodes() )
			{
				if ( \count( $customNodes['password'] ) )
				{
					$where['container'][] = array('forums_forums.password IS NULL AND forums_forums.can_view_others=1');
				}
				else
				{
					$where['container'][] = array('forums_forums.can_view_others=1');
				}
			}
		}
		return $where;
	}
}