<?php
/**
 * @brief		RSS Import extension: RssImport
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Forums
 * @since		09 Oct 2019
 */

namespace IPS\forums\extensions\core\RssImport;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	RSS Import extension: RssImport
 */
class _RssImport
{
	/**
	 * @brief	RSSImport Class
	 */
	public $classes = array();

	public $fileStorage = 'forums_Forums';

	/**
	 * Constructor
	 *
	 * @return	void
	 */
	public function __construct()
	{
		$this->classes = array( 'IPS\\forums\\Topic' );
	}

	/**
	 * Return available options for a Form\Select
	 *
	 * @return array
	 */
	public function availableOptions()
	{
		return array( 'IPS\\forums\\Topic' => \IPS\Member::loggedIn()->language()->addToStack('__app_forums') );
	}

	/**
	 * Show in the Admin CP?
	 *
	 * @param	Object 	$class	The class to check
	 * @return boolean
	 */
	public function showInAdminCp( $class ): bool
	{
		return true;
	}

	/**
	 * Node selector options
	 *
	 * @param 	\IPS\core\Rss\Import|null	$rss	Existing RSS object if editing|NULL if not
	 * @return array
	 */
	public function nodeSelectorOptions( $rss )
	{
		return array( 'class' => 'IPS\forums\Forum', 'permissionCheck' => function ( $forum )
		{
			return $forum->sub_can_post and !$forum->redirect_url;
		} );
	}

	/**
	 * @param \IPS\core\Rss\Import 	$rss 		RSS object
	 * @param array 				$article 	RSS feed article importing
	 * @param \IPS\Node\Model 		$container  Container object
	 * @param	string				$content	Post content with read more link if set
	 * @return \IPS\Content
	 */
	public function create( \IPS\core\Rss\Import $rss, $article, \IPS\Node\Model $container, $content )
	{
		$settings = $rss->settings;
		$class = $rss->_class;
		$member = \IPS\Member::load( $rss->member );
		$topic = $class::createItem( $member, NULL, $article['date'], $container, $settings['topic_hide'] );
		$topic->title = $rss->topic_pre . $article['title'];

		if ( ! $settings['topic_open'] )
		{
			$topic->state = 'closed';
		}

		$topic->save();

		/* Send notifications */
		if ( !$settings['topic_hide'] )
		{
			$topic->sendNotifications();
		}

		/* Add to search index */
		\IPS\Content\Search\Index::i()->index( $topic );

		$post = \IPS\forums\Topic\Post::create( $topic, $content, TRUE, NULL, \IPS\forums\Topic\Post::incrementPostCount( $container ), $member, $article['date'], ( array_key_exists( 'SERVER_ADDR', $_SERVER ) ) ? $_SERVER['SERVER_ADDR'] : NULL );
		$topic->topic_firstpost = $post->pid;

		$topic->save();

		/* Claim any attachments */
		if ( isset( $article['attachment'] ) )
		{
			\IPS\Db::i()->insert( 'core_attachments_map', array(
				'attachment_id' => $article['attachment']['attach_id'],
				'location_key' => 'forums_Forums',
				'id1' => $topic->tid,
				'id2' => $post->pid,
			) );
		}
		return $topic;
	}

	/**
	 * Addition Form elements
	 *
	 * @param	\IPS\Helpers\Form			$form	The form
	 * @param	\IPS\core\Rss\Import|null		$rss	Existing RSS object if editing|NULL if not
	 * @return	void
	 */
	public function form( &$form, $rss=NULL )
	{
		$settings = NULL;

		if ( $rss )
		{
			$settings = $rss->settings;
		}

		/* Make rss_import_node_id make sense for forums */
		\IPS\Member::loggedIn()->language()->words['rss_import_node_id'] = \IPS\Member::loggedIn()->language()->addToStack('topic_container');

		$form->add( new \IPS\Helpers\Form\Radio( 'rss_import_topic_open', ( $settings ? $settings['topic_open'] : 1 ), FALSE, array( 'options' => array( 1 => 'unlocked', 0 => 'locked' ) ) ) );
		$form->add( new \IPS\Helpers\Form\Radio( 'rss_import_topic_hide', ( $settings ? $settings['topic_hide'] : 0 ), FALSE, array( 'options' => array( 0 => 'unhidden', 1 => 'hidden' ) ) ) );
	}

	/**
	 * Process additional fields unique to this extension
	 *
	 * @param	array				$values	Values from form
	 * @param	\IPS\core\Rss\Import		$rss	Existing RSS object
	 * @return	array
	 */
	public function saveForm( &$values, $rss )
	{
		$return = array(
			'topic_open' => $values['rss_import_topic_open'],
			'topic_hide' => $values['rss_import_topic_hide']
		);

		unset( $values['rss_import_topic_open'], $values['rss_import_topic_hide'] );

		return $return;
	}
}