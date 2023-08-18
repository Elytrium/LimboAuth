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
 * latestTopics Widget
 */
class _topicFeed extends \IPS\Content\Widget
{
	/**
	 * @brief	Widget Key
	 */
	public $key = 'topicFeed';
	
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
	protected static $class = 'IPS\forums\Topic';

 	/**
 	 * Return the form elements to use
 	 *
 	 * @return array
 	 */
 	protected function formElements()
 	{
 		$elements = parent::formElements();

 		$class	= static::$class;

 		$elements['container'] = new \IPS\Helpers\Form\Node( 'widget_feed_container_' . $class::$title, isset( $this->configuration['widget_feed_container'] ) ? $this->configuration['widget_feed_container'] : 0, FALSE, array(
				'class'           => $class::$containerNodeClass,
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
		$class	= static::$class;
		$containerClass = $class::$containerNodeClass;
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

		if ( isset( $this->configuration['widget_feed_status_solved'] ) and $this->configuration['widget_feed_status_solved'] == 'unsolved' and empty( $this->configuration['widget_feed_container'] ) )
		{
			$where['container'][] = array( '( (' . \IPS\Db::i()->bitwiseWhere( \IPS\forums\Forum::$bitOptions['forums_bitoptions'], 'bw_enable_answers' ) . ') OR ( ' . \IPS\Db::i()->bitwiseWhere( \IPS\forums\Forum::$bitOptions['forums_bitoptions'], 'bw_enable_answers_moderator' ) . ' ) )' );
		}

		return $where;
	}
}