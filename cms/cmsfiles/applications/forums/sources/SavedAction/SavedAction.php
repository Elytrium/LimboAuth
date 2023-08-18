<?php
/**
 * @brief		Saved Action Node
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Forums
 * @since		31 Jan 2014
 */

namespace IPS\forums;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Saved Action Node
 */
class _SavedAction extends \IPS\Node\Model
{
	/**
	 * @brief	[ActiveRecord] Multiton Store
	 */
	protected static $multitons;
	
	/**
	 * @brief	[ActiveRecord] Database Table
	 */
	public static $databaseTable = 'forums_topic_mmod';
	
	/**
	 * @brief	[Node] Node Title
	 */
	public static $nodeTitle = 'saved_actions';
	
	/**
	 * @brief	[ActiveRecord] ID Database Column
	 */
	public static $databaseColumnId = 'mm_id';
	
	/**
	 * @brief	[Node] ACP Restrictions
	 * @code
	 	array(
	 		'app'		=> 'core',				// The application key which holds the restrictrions
	 		'module'	=> 'foo',				// The module key which holds the restrictions
	 		'map'		=> array(				// [Optional] The key for each restriction - can alternatively use "prefix"
	 			'add'			=> 'foo_add',
	 			'edit'			=> 'foo_edit',
	 			'permissions'	=> 'foo_perms',
	 			'delete'		=> 'foo_delete'
	 		),
	 		'all'		=> 'foo_manage',		// [Optional] The key to use for any restriction not provided in the map (only needed if not providing all 4)
	 		'prefix'	=> 'foo_',				// [Optional] Rather than specifying each  key in the map, you can specify a prefix, and it will automatically look for restrictions with the key "[prefix]_add/edit/permissions/delete"
	 * @endcode
	 */
	protected static $restrictions = array(
		'app'		=> 'forums',
		'module'	=> 'forums',
		'prefix'	=> 'savedActions_'
	);
	
	/**
	 * @brief	[Node] Title prefix.  If specified, will look for a language key with "{$key}_title" as the key
	 */
	public static $titleLangPrefix = 'forums_mmod_';
	
	/**
	 * Get available saved actions for a forum
	 *
	 * @param	\IPS\forums\Forum	$forum	The forum
	 * @param	\IPS\Member|NULL	$member	The member (NULL for currently logged in)
	 * @return	array
	 */
	public static function actions( \IPS\forums\Forum $forum, \IPS\Member $member = NULL )
	{
		$return = array();
		$member = $member ?: \IPS\Member::loggedIn();
		
		if ( $member->modPermission('can_use_saved_actions') and \IPS\forums\Topic::modPermission( 'use_saved_actions', $member, $forum ) )
		{
			foreach ( static::getStore() as $action )
			{
				if ( $action['mm_enabled'] and ( $action['mm_forums'] == '*' or \in_array( $forum->id, array_filter( explode( ',', $action['mm_forums'] ) ) ) ) )
				{
					$return[ $action['mm_id'] ] = static::constructFromData( $action );
				}
			}
		}
		
		return $return;
	}
	
		
	/**
	 * [Node] Get whether or not this node is enabled
	 *
	 * @note	Return value NULL indicates the node cannot be enabled/disabled
	 * @return	bool|null
	 */
	protected function get__enabled()
	{
		return $this->mm_enabled;
	}

	/**
	 * [Node] Set whether or not this node is enabled
	 *
	 * @param	bool|int	$enabled	Whether to set it enabled or disabled
	 * @return	void
	 */
	protected function set__enabled( $enabled )
	{
		$this->mm_enabled	= $enabled;
	}

	/**
	 * [Node] Add/Edit Form
	 *
	 * @param	\IPS\Helpers\Form	$form	The form
	 * @return	void
	 */
	public function form( &$form )
	{
		$form->addHeader( 'settings' );
		$form->add( new \IPS\Helpers\Form\Translatable( 'mm_title', NULL, TRUE, array( 'app' => 'forums', 'key' => ( $this->mm_id ? "forums_mmod_{$this->mm_id}" : NULL ) ) ) );		
		$form->add( new \IPS\Helpers\Form\Node( 'mm_forums', ( $this->mm_id and $this->mm_forums != '*' ) ? $this->mm_forums : 0, FALSE, array( 'class' => 'IPS\forums\Forum', 'multiple' => TRUE, 'zeroVal' => 'all' ) ) );
		
		$form->addHeader( 'topic_properties' );
		$form->add( new \IPS\Helpers\Form\Radio( 'topic_state', $this->mm_id ? $this->topic_state : 'leave', FALSE, array( 'options' => array( 'leave' => 'mm_leave', 'open' => 'unlock', 'close' => 'lock' ) ) ) );
		$form->add( new \IPS\Helpers\Form\Radio( 'topic_pin', $this->mm_id ? $this->topic_pin : 'leave', FALSE, array( 'options' => array( 'leave' => 'mm_leave', 'pin' => 'pin', 'unpin' => 'unpin' ) ) ) );
		$form->add( new \IPS\Helpers\Form\Radio( 'topic_approve', $this->mm_id ? $this->topic_approve : 0, FALSE, array( 'options' => array( 0 => 'mm_leave', 1 => 'unhide', 2 => 'hide' ) ) ) );
		$form->add( new \IPS\Helpers\Form\Node( 'topic_move', ( $this->mm_id and $this->topic_move != -1 ) ? $this->topic_move : 0, FALSE, array( 'class' => 'IPS\forums\Forum', 'zeroVal' => 'topic_move_none', 'zeroValTogglesOff' => array( 'topic_move_link' ), 'permissionCheck' => function ( $forum )
					{
						return $forum->sub_can_post and !$forum->redirect_url;
					} ) ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'topic_move_link', $this->mm_id ? $this->topic_move_link : TRUE, FALSE, array(), NULL, NULL, NULL, 'topic_move_link' ) );
		
		$form->addHeader( 'topic_title' );
		$form->add( new \IPS\Helpers\Form\Text( 'topic_title_st', $this->mm_id ? $this->topic_title_st : NULL, FALSE, array( 'trim' => FALSE ) ) );
		$form->add( new \IPS\Helpers\Form\Text( 'topic_title_end', $this->mm_id ? $this->topic_title_end : NULL, FALSE, array( 'trim' => FALSE ) ) );
		
		$form->addHeader( 'add_reply' );
		$form->add( new \IPS\Helpers\Form\YesNo( 'topic_reply', $this->mm_id ? $this->topic_reply : FALSE, FALSE, array( 'togglesOn' => array( 'topic_reply_content_editor', 'topic_reply_postcount' ) ) ) );
		$form->add( new \IPS\Helpers\Form\Editor( 'topic_reply_content', $this->mm_id ? $this->topic_reply_content : FALSE, FALSE, array( 'app' => 'core', 'key' => 'Admin', 'autoSaveKey' => ( $this->mm_id ? "forums-mmod-{$this->mm_id}" : "forums-new-mmod" ), 'attachIds' => $this->mm_id ? array( $this->mm_id, NULL, 'mmod' ) : NULL ), NULL, NULL, NULL, 'topic_reply_content_editor' ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'topic_reply_postcount', $this->mm_id ? $this->topic_reply_postcount : TRUE, FALSE, array(), NULL, NULL, NULL, 'topic_reply_postcount' ) );
	}
	
	/**
	 * [Node] Format form values from add/edit form for save
	 *
	 * @param	array	$values	Values from the form
	 * @return	array
	 */
	public function formatFormValues( $values )
	{
		if( isset( $values['mm_forums'] ) )
		{
			if ( $values['mm_forums'] == 0 )
			{
				$values['mm_forums'] = '*';
			}
			else 
			{
				$forums = array();
				foreach ( $values['mm_forums'] as $forum )
				{
					$forums[] = $forum->_id;
				}
				
				$values['mm_forums'] = ( implode( ',', $forums ) );
			}
		}

		if( isset( $values['topic_move'] ) )
		{
			if ( !\is_object( $values['topic_move'] ) and $values['topic_move'] == 0 )
			{
				$values['topic_move'] = -1;
			}
			else
			{
				$values['topic_move'] = $values['topic_move']->_id;
			}
		}
				
		if ( !$this->mm_id )
		{
			$this->mm_enabled = $values['mm_enabled'] = TRUE;
			$this->save();
			\IPS\File::claimAttachments( 'forums-new-mmod', $this->mm_id, NULL, 'forumsSavedAction' );
		}

		if ( isset( $values['mm_title'] ) )
		{
			\IPS\Lang::saveCustom( 'forums', "forums_mmod_{$this->mm_id}", $values['mm_title'] );
			unset( $values['mm_title'] );
		}

		return $values;
	}
	
	/**
	 * Check Permissions and run the saved action
	 *
	 * @param	\IPS\forums\Topic	$topic	The topic to run on
	 * @param	\IPS\Member|NULL	$member	Member running (NULL for currently logged in member)
	 * @return	void
	 */
	public function runOn( \IPS\forums\Topic $topic, \IPS\Member $member = NULL )
	{
		/* Permission Checks */
		$member = $member ?: \IPS\Member::loggedIn();

		/* Check the member can use saved actions, and they can moderate in this forum */
		if ( !$member->modPermission('can_use_saved_actions') )
		{
			throw new \DomainException('NO_PERMISSION');
		}

		if ( $member->modPermission( 'forums' ) !== -1 AND $member->modPermission( 'forums' ) !== TRUE )
		{
			if ( !\is_array( $member->modPermission( 'forums' ) ) or !\in_array( $topic->container()->_id, $member->modPermission( 'forums' ) ) )
			{
				throw new \DomainException('NO_PERMISSION');
			}
		}
		/* Check the action is enabled and allowed for the content item */
		if ( !$this->mm_enabled )
		{
			throw new \DomainException('DISABLED');
		}
		if ( $this->mm_forums !== '*' and !\in_array( $topic->container()->_id, explode( ',', $this->mm_forums ) ) )
		{
			throw new \DomainException('BAD_FORUM');
		}

		$this->_runOn( $topic, $member);
	}

	/**
	 * Run saved action
	 *
	 * @param	\IPS\forums\Topic	$topic	The topic to run on
	 * @param	\IPS\Member|NULL	$member	Member running (NULL for currently logged in member)
	 * @return	void
	 */
	protected function _runOn( \IPS\forums\Topic $topic, \IPS\Member $member = NULL )
	{
		/* Archived Topics can't used saved actions */
		if ( $topic->isArchived() )
		{
			throw new \DomainException('TOPIC_ARCHIVED');
		}
		/* Open/Close */
		if ( $this->topic_state == 'open' )
		{
			$topic->state = 'open';
		}
		elseif ( $this->topic_state == 'close' )
		{
			$topic->state = 'closed';
		}

		/* Pin/Unpin */
		if ( $this->topic_pin == 'pin' )
		{
			$topic->pinned = TRUE;
		}
		elseif ( $this->topic_pin == 'unpin' )
		{
			$topic->pinned = FALSE;
		}

		/* Title */
		if ( $this->topic_title_st )
		{
			$topic->title = $this->topic_title_st . $topic->title;
		}
		if ( $this->topic_title_end )
		{
			$topic->title .= $this->topic_title_end;
		}

		/* Save */
		$topic->save();

		/* Hide/Unhide */
		if ( $this->topic_approve == 1 )
		{
			$topic->unhide( $member );
		}
		elseif ( $this->topic_approve == 2 )
		{
			$topic->hide( $member );
		}

		/* Reply */
		if ( $this->topic_reply )
		{
			$reply = \IPS\forums\Topic\Post::create( $topic, $this->topic_reply_content, FALSE, NULL, isset( $this->topic_reply_postcount ) AND  $this->topic_reply_postcount ? TRUE : FALSE );
		}

		/* Move */
		if ( $this->topic_move != -1 )
		{
			try
			{
				$topic->move( \IPS\forums\Forum::load( $this->topic_move ), $this->topic_move_link );
			}
			catch ( \Exception $e ) { }
		}
	}

	/**
	 * Delete Record
	 *
	 * @return	void
	 */
	public function delete()
	{
		\IPS\File::unclaimAttachments( 'core_Admin', $this->mm_id, NULL, 'forumsSavedAction' );
		parent::delete();
	}

	/**
	 * @brief	[ActiveRecord] Caches
	 * @note	Defined cache keys will be cleared automatically as needed
	 */
	protected $caches = array( 'forumsSavedActions' );

	/**
	 * Get data store
	 *
	 * @return	array
	 */
	public static function getStore()
	{
		if ( !isset( \IPS\Data\Store::i()->forumsSavedActions ) )
		{
			\IPS\Data\Store::i()->forumsSavedActions = iterator_to_array( \IPS\Db::i()->select( '*', 'forums_topic_mmod' ) );
		}
		
		return \IPS\Data\Store::i()->forumsSavedActions;
	}
}