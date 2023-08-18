<?php
/**
 * @brief		Category Node
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Gallery
 * @since		04 Mar 2014
 */

namespace IPS\gallery;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Category Node
 */
class _Category extends \IPS\Node\Model implements \IPS\Node\Permissions
{
	use \IPS\Content\ClubContainer, \IPS\Content\ViewUpdates;
	
	/**
	 * @brief	[ActiveRecord] Multiton Store
	 */
	protected static $multitons;
	
	/**
	 * @brief	[ActiveRecord] Database Table
	 */
	public static $databaseTable = 'gallery_categories';
	
	/**
	 * @brief	[ActiveRecord] Database Prefix
	 */
	public static $databasePrefix = 'category_';
		
	/**
	 * @brief	[Node] Order Database Column
	 */
	public static $databaseColumnOrder = 'position';
	
	/**
	 * @brief	[Node] Parent ID Database Column
	 */
	public static $databaseColumnParent = 'parent_id';
	
	/**
	 * @brief	[Node] Node Title
	 */
	public static $nodeTitle = 'categories';

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
		'app'		=> 'gallery',
		'module'	=> 'gallery',
		'prefix'	=> 'categories_'
	);
	
	/**
	 * @brief	[Node] App for permission index
	 */
	public static $permApp = 'gallery';
	
	/**
	 * @brief	[Node] Type for permission index
	 */
	public static $permType = 'category';
	
	/**
	 * @brief	The map of permission columns
	 */
	public static $permissionMap = array(
		'view' 				=> 'view',
		'read'				=> 2,
		'add'				=> 3,
		'reply'				=> 4,
		'rate'				=> 5,
		'review'			=> 6,
	);

	/**
	 * @brief	[Node] Title prefix.  If specified, will look for a language key with "{$key}_title" as the key
	 */
	public static $titleLangPrefix = 'gallery_category_';
	
	/**
	 * @brief	[Node] Description suffix.  If specified, will look for a language key with "{$titleLangPrefix}_{$id}_{$descriptionLangSuffix}" as the key
	 */
	public static $descriptionLangSuffix = '_desc';
	
	/**
	 * @brief	[Node] Moderator Permission
	 */
	public static $modPerm = 'gallery_categories';

	/**
	 * @brief	Content Item Class
	 */
	public static $contentItemClass = 'IPS\gallery\Image';
	
	/**
	 * Get SEO name
	 *
	 * @return	string
	 */
	public function get_name_seo()
	{
		if( !$this->_data['name_seo'] )
		{
			$this->name_seo	= \IPS\Http\Url\Friendly::seoTitle( \IPS\Lang::load( \IPS\Lang::defaultLanguage() )->get( 'gallery_category_' . $this->id ) );
			$this->save();
		}

		return $this->_data['name_seo'] ?: \IPS\Http\Url\Friendly::seoTitle( \IPS\Lang::load( \IPS\Lang::defaultLanguage() )->get( 'gallery_category_' . $this->id ) );
	}

	/**
	 * [Node] Get whether or not this node is enabled
	 *
	 * @note	Return value NULL indicates the node cannot be enabled/disabled
	 * @return	bool|null
	 */
	protected function get__enabled()
	{
		return NULL;
	}

	/**
	 * Get sort order
	 *
	 * @return	string
	 */
	public function get__sortBy()
	{
		return str_replace( 'album_', '', $this->sort_options );
	}

	/**
	 * [Node] Get number of content items
	 *
	 * @return	int
	 * @note	We return null if there are non-public albums so that we can count what you can see properly
	 */
	protected function get__items()
	{
		return $this->nonpublic_albums ? NULL : $this->count_imgs;
	}

	/**
	 * [Node] Get number of content comments
	 *
	 * @return	int
	 */
	protected function get__comments()
	{
		return (int) $this->count_comments;
	}

	/**
	 * [Node] Get number of content comments (including children)
	 *
	 * @return	int
	 */
	protected function get__commentsForDisplay()
	{
		$comments = $this->_comments;

		foreach( $this->children() as $child )
		{
			$comments += $child->_comments;
		}

		return $comments;
	}

	/**
	 * [Node] Get number of unapproved content items
	 *
	 * @return	int
	 */
	protected function get__unnapprovedItems()
	{
		return $this->count_imgs_hidden;
	}
	
	/**
	 * [Node] Get number of unapproved content comments
	 *
	 * @return	int
	 */
	protected function get__unapprovedComments()
	{
		return $this->count_comments_hidden;
	}

	/**
	 * Set number of items
	 *
	 * @param	int	$val	Items
	 * @return	void
	 */
	protected function set__items( $val )
	{
		$this->count_imgs = (int) $val;
	}

	/**
	 * Set number of items
	 *
	 * @param	int	$val	Comments
	 * @return	void
	 */
	protected function set__comments( $val )
	{
		$this->count_comments = (int) $val;
	}

	/**
	 * [Node] Get number of unapproved content items
	 *
	 * @param	int	$val	Unapproved Items
	 * @return	void
	 */
	protected function set__unapprovedItems( $val )
	{
		$this->count_imgs_hidden = $val;
	}
	
	/**
	 * [Node] Get number of unapproved content comments
	 *
	 * @param	int	$val	Unapproved Comments
	 * @return	void
	 */
	protected function set__unapprovedComments( $val )
	{
		$this->count_comments_hidden = $val;
	}

	/**
	 * [Node] Add/Edit Form
	 *
	 * @param	\IPS\Helpers\Form	$form	The form
	 * @return	void
	 */
	public function form( &$form )
	{
		$form->addTab( 'category_settings' );
		$form->addHeader( 'category_settings' );
		$form->add( new \IPS\Helpers\Form\Translatable( 'category_name', NULL, TRUE, array( 'app' => 'gallery', 'key' => ( $this->id ? "gallery_category_{$this->id}" : NULL ) ) ) );
		$form->add( new \IPS\Helpers\Form\Translatable( 'category_description', NULL, FALSE, array(
			'app'		=> 'gallery',
			'key'		=> ( $this->id ? "gallery_category_{$this->id}_desc" : NULL ),
			'editor'	=> array(
				'app'			=> 'gallery',
				'key'			=> 'Categories',
				'autoSaveKey'	=> ( $this->id ? "gallery-cat-{$this->id}" : "gallery-new-cat" ),
				'attachIds'		=> $this->id ? array( $this->id, NULL, 'description' ) : NULL, 
				'minimize'		=> 'cdesc_placeholder'
			)
		) ) );

		$class = \get_called_class();

		$form->add( new \IPS\Helpers\Form\Node( 'gcategory_parent_id', $this->id ? $this->parent_id : ( \IPS\Request::i()->parent ?: 0 ), FALSE, array(
			'class'		      => '\IPS\gallery\Category',
			'disabled'	      => false,
			'zeroVal'         => 'node_no_parentg',
			'permissionCheck' => function( $node ) use ( $class )
			{
				if( isset( $class::$subnodeClass ) AND $class::$subnodeClass AND $node instanceof $class::$subnodeClass )
				{
					return FALSE;
				}

				return !isset( \IPS\Request::i()->id ) or ( $node->id != \IPS\Request::i()->id and !$node->isChildOf( $node::load( \IPS\Request::i()->id ) ) );
			}
		) ) );

		$sortoptions = array( 
			'album_last_img_date'		=> 'sort_updated', 
			'album_last_comment'		=> 'sort_last_comment',
			'album_rating_aggregate'	=> 'sort_rating', 
			'album_comments'			=> 'sort_num_comments',
			'album_reviews'				=> 'sort_num_reviews',
			'album_name'				=> 'sort_album_name', 
			'album_count_comments'		=> 'sort_album_count_comments', 
			'album_count_imgs'			=> 'sort_album_count_imgs'
		);
		$form->add( new \IPS\Helpers\Form\Select( 'category_sort_options', $this->sort_options ?: 'updated', FALSE, array( 'options' => $sortoptions ), NULL, NULL, NULL, 'category_sort_options' ) );

		$form->add( new \IPS\Helpers\Form\Select( 'category_sort_options_img', $this->sort_options_img ?: 'updated', FALSE, array( 'options' => array( 'updated' => 'sort_updated', 'last_comment' => 'sort_last_comment', 'title' => 'album_sort_caption', 'rating' => 'sort_rating', 'date' => 'sort_date', 'num_comments' => 'sort_num_comments', 'num_reviews' => 'sort_num_reviews', 'views' => 'sort_views' ) ), NULL, NULL, NULL, 'category_sort_options_img' ) );

		$form->add( new \IPS\Helpers\Form\Radio( 'category_allow_albums', $this->id ? $this->allow_albums : 1, TRUE, array( 'options' => array( 0 => 'cat_no_allow_albums', 1 => 'cat_allow_albums', 2 => 'cat_require_albums' ) ) ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'category_approve_img', $this->approve_img, FALSE ) );

		if( \IPS\Settings::i()->gallery_watermark_path )
		{
			$form->add( new \IPS\Helpers\Form\YesNo( 'category_watermark', $this->id ? $this->watermark : TRUE, FALSE ) );
		}
		
		$form->add( new \IPS\Helpers\Form\YesNo( 'allow_anonymous_comments', $this->id ? $this->allow_anonymous : FALSE, FALSE, array() ) );

		$form->addHeader( 'category_comments_and_ratings' );
		$form->add( new \IPS\Helpers\Form\YesNo( 'category_allow_comments', $this->id ? $this->allow_comments : TRUE, FALSE, array( 'togglesOn' => array( 'category_approve_com' ) ), NULL, NULL, NULL, 'category_allow_comments' ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'category_approve_com', $this->approve_com, FALSE, array(), NULL, NULL, NULL, 'category_approve_com' ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'gcategory_allow_rating', $this->id ? $this->allow_rating : TRUE, FALSE ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'category_allow_reviews', $this->id ? $this->allow_reviews : FALSE, FALSE, array( 'togglesOn' => array( 'category_review_moderate' ) ) ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'category_review_moderate', $this->id ? $this->review_moderate : FALSE, FALSE, array(), NULL, NULL, NULL, 'category_review_moderate' ) );

		if ( \IPS\Settings::i()->tags_enabled )
		{
			$form->addHeader( 'category_tags' );
			$form->add( new \IPS\Helpers\Form\YesNo( 'category_can_tag', $this->id ? $this->can_tag : TRUE, FALSE, array( 'togglesOn' => array( 'category_tag_prefixes', 'category_preset_tags' ) ), NULL, NULL, NULL, 'category_can_tag' ) );
			$form->add( new \IPS\Helpers\Form\YesNo( 'category_tag_prefixes', $this->id ? $this->tag_prefixes : TRUE, FALSE, array(), NULL, NULL, NULL, 'category_tag_prefixes' ) );
			$form->add( new \IPS\Helpers\Form\Text( 'category_preset_tags', $this->preset_tags, FALSE, array( 'autocomplete' => array( 'unique' => 'true', 'alphabetical' => \IPS\Settings::i()->tags_alphabetical ), 'nullLang' => 'ctags_predefined_unlimited' ), NULL, NULL, NULL, 'category_preset_tags' ) );
		}

		$form->addTab( 'category_rules' );
		$form->add( new \IPS\Helpers\Form\Radio( 'category_show_rules', $this->id ? $this->show_rules : 0, FALSE, array(
			'options' => array(
				0	=> 'category_show_rules_none',
				1	=> 'category_show_rules_link',
				2	=> 'category_show_rules_full'
			),
			'toggles'	=> array(
				1	=> array(
					'category_rules_title',
					'category_rules_text'
				),
				2	=> array(
					'category_rules_title',
					'category_rules_text'
				),
			)
		) ) );
		$form->add( new \IPS\Helpers\Form\Translatable( 'category_rules_title', NULL, FALSE, array( 'app' => 'gallery', 'key' => ( $this->id ? "gallery_category_{$this->id}_rulestitle" : NULL ) ), NULL, NULL, NULL, 'category_rules_title' ) );
		$form->add( new \IPS\Helpers\Form\Translatable( 'category_rules_text', NULL, FALSE, array( 'app' => 'gallery', 'key' => ( $this->id ? "gallery_category_{$this->id}_rules" : NULL ), 'editor' => array( 'app' => 'gallery', 'key' => 'Categories', 'autoSaveKey' => ( $this->id ? "gallery-rules-{$this->id}" : "gallery-new-rules" ), 'attachIds' => $this->id ? array( $this->id, NULL, 'rules' ) : NULL ) ), NULL, NULL, NULL, 'category_rules_text' ) );
		//$form->add( new \IPS\Helpers\Form\Url( 'category_rules_link', $this->rules_link, FALSE, array(), NULL, NULL, NULL, 'category_rules_link' ) );
		
		$form->addTab( 'error_messages' );
		$form->add( new \IPS\Helpers\Form\Translatable( 'category_permission_custom_error', NULL, FALSE, array( 'app' => 'gallery', 'key' => ( $this->id ? "gallery_category_{$this->id}_permerror" : NULL ), 'editor' => array( 'app' => 'gallery', 'key' => 'Categories', 'autoSaveKey' => ( $this->id ? "gallery-permerror-{$this->id}" : "gallery-new-permerror" ), 'attachIds' => $this->id ? array( $this->id, NULL, 'permerror' ) : NULL, 'minimize' => 'gallery_permerror_placeholder' ) ), NULL, NULL, NULL, 'gallery_permission_custom_error' ) );
	}
	
	/**
	 * [Node] Format form values from add/edit form for save
	 *
	 * @param	array	$values	Values from the form
	 * @return	array
	 */
	public function formatFormValues( $values )
	{
		/* Fix field (lang conflict) */
		if( isset( $values['gcategory_allow_rating'] ) )
		{
			$values['category_allow_rating'] = $values['gcategory_allow_rating'];
			unset( $values['gcategory_allow_rating'] );
		}
		if( isset( $values['gcategory_parent_id'] ) )
		{
			$values['category_parent_id'] = $values['gcategory_parent_id'];
			unset( $values['gcategory_parent_id'] );
		}
		
		if( isset( $values['allow_anonymous_comments'] ) )
		{
			$values['allow_anonymous'] = ( $values['allow_anonymous_comments'] ? 2 : 0 );
			unset( $values['allow_anonymous_comments'] );
		}
		
		/* If watermarks are disabled, enable them at the category level so that if you later enable watermarks they work in your existing categories */
		if( !\IPS\Settings::i()->gallery_watermark_path )
		{
			$values['category_watermark']	= 1;
		}

		/* Claim attachments */
		if ( !$this->id )
		{
			$this->save();
			\IPS\File::claimAttachments( 'gallery-new-cat', $this->id, NULL, 'description', TRUE );
			\IPS\File::claimAttachments( 'gallery-new-rules', $this->id, NULL, 'rules', TRUE );
			\IPS\File::claimAttachments( 'gallery-new-permerror', $this->id, NULL, 'permerror', TRUE );
		}

		/* Custom language fields */
		if( isset( $values['category_name'] ) )
		{
			\IPS\Lang::saveCustom( 'gallery', "gallery_category_{$this->id}", $values['category_name'] );
			$values['name_seo']	= \IPS\Http\Url\Friendly::seoTitle( $values['category_name'][ \IPS\Lang::defaultLanguage() ] );
			unset( $values['category_name'] );
		}

		if( array_key_exists( 'category_description', $values ) )
		{
			\IPS\Lang::saveCustom( 'gallery', "gallery_category_{$this->id}_desc", $values['category_description'] );
			unset( $values['category_description'] );
		}

		if( array_key_exists( 'category_rules_title', $values ) )
		{
			\IPS\Lang::saveCustom( 'gallery', "gallery_category_{$this->id}_rulestitle", $values['category_rules_title'] );
			unset( $values['category_rules_title'] );
		}

		if( array_key_exists( 'category_rules_text', $values ) )
		{
			\IPS\Lang::saveCustom( 'gallery', "gallery_category_{$this->id}_rules", $values['category_rules_text'] );
			unset( $values['category_rules_text'] );
		}

		if( array_key_exists( 'category_permission_custom_error', $values ) )
		{
			\IPS\Lang::saveCustom( 'gallery', "gallery_category_{$this->id}_permerror", $values['category_permission_custom_error'] );
			unset( $values['category_permission_custom_error'] );
		}

		/* Parent ID */
		if ( isset( $values['category_parent_id'] ) )
		{
			$values['category_parent_id'] = $values['category_parent_id'] ? \intval( $values['category_parent_id']->id ) : 0;
		}

		/* Cannot be null */
		if( !isset( $values['category_approve_com'] ) )
		{
			$values['category_approve_com']	= 0;
		}

		/* Send to parent */
		return $values;
	}

	/**
	 * @brief	Cached URL
	 */
	protected $_url	= NULL;
	
	/**
	 * @brief	URL Base
	 */
	public static $urlBase = 'app=gallery&module=gallery&controller=browse&category=';
	
	/**
	 * @brief	URL Base
	 */
	public static $urlTemplate = 'gallery_category';
	
	/**
	 * @brief	SEO Title Column
	 */
	public static $seoTitleColumn = 'name_seo';

	/**
	 * Get "No Permission" error message
	 *
	 * @return	string
	 */
	public function errorMessage()
	{
		if ( \IPS\Member::loggedIn()->language()->checkKeyExists( "gallery_category_{$this->id}_permerror" ) )
		{
			try
			{
				$message = trim( \IPS\Member::loggedIn()->language()->get( "gallery_category_{$this->id}_permerror" ) );
				if ( !empty( $message ) AND ( $message != '<p></p>' ) )
				{
					return \IPS\Theme::i()->getTemplate('global', 'core', 'global')->richText( $message );
				}
			}
			catch ( \Exception $e ) {}
		}

		return 'node_error_no_perm';
	}

	/**
	 * Get latest image information
	 *
	 * @return	\IPS\gallery\Image|NULL
	 */
	public function lastImage()
	{
		$latestImageData	= $this->getLatestImageId();
		$latestImage		= NULL;
		
		if( $latestImageData !== NULL )
		{
			try
			{
				$latestImage	= \IPS\gallery\Image::load( $latestImageData['id'] );
			}
			catch( \OutOfRangeException $e ){}
		}

		return $latestImage;
	}

	/**
	 * Retrieve the latest image ID in categories and children categories
	 *
	 * @return	array|NULL
	 */
	protected function getLatestImageId()
	{
		$latestImage	= NULL;

		if ( $this->last_img_id )
		{
			$latestImage = array( 'id' => $this->last_img_id, 'date' => $this->last_img_date );
		}

		foreach( $this->children() as $child )
		{
			$childLatest = $child->getLatestImageId();

			if( $childLatest !== NULL AND ( $latestImage === NULL OR $childLatest['date'] > $latestImage['date'] ) )
			{
				$latestImage	= $childLatest;
			}
		}

		return $latestImage;
	}
	
	/**
	 * Delete Record
	 *
	 * @return	void
	 */
	public function delete()
	{
		\IPS\File::unclaimAttachments( 'gallery_Categories', $this->id );
		parent::delete();
		
		\IPS\Lang::deleteCustom( 'gallery', "gallery_category_{$this->id}_rulestitle" );
		\IPS\Lang::deleteCustom( 'gallery', "gallery_category_{$this->id}_rules" );
		\IPS\Lang::deleteCustom( 'gallery', "gallery_category_{$this->id}_permerror" );
	}
	
	/**
	 * Set last comment
	 *
	 * @param	\IPS\Content\Comment|NULL	$comment	The latest comment or NULL to work it out
	 * @return	int
	 * @note	We actually want to set the last image info, not the last comment, so we ignore $comment
	 */
	public function setLastComment( \IPS\Content\Comment $comment=NULL )
	{
		$this->setLastImage();
	}

	/**
	 * Set last image data
	 *
	 * @param	\IPS\gallery\Image|NULL	$image	The latest image or NULL to work it out
	 * @return	void
	 */
	public function setLastImage( \IPS\gallery\Image $image=NULL )
	{
		/* Reset counts */
		$this->resetCommentCounts();
		$this->commentCountsReset = TRUE;

		/* Reset last image */
		if( $image === NULL )
		{
			try
			{
				$result	= \IPS\Db::i()->select( '*', 'gallery_images', array( 'image_category_id=? AND image_approved=1 AND ( image_album_id = 0 OR album_type NOT IN ( 2, 3 ) )', $this->id ), 'image_date DESC', 1, NULL, NULL, \IPS\Db::SELECT_FROM_WRITE_SERVER )->join(
					'gallery_albums',
					"image_album_id=album_id"
				)->first();

				$image	= \IPS\gallery\Image::constructFromData( $result );
			}
			catch ( \UnderflowException $e )
			{
				$this->last_img_id		= 0;
				$this->last_img_date	= 0;
				return;
			}
		}
				
		$this->last_img_id			= $image->id;
		$this->last_img_date		= $image->date;

		if( $image->album_id )
		{
			$album = \IPS\gallery\Album::constructFromData( $result );
			$album->setLastImage();
			$album->save();
		}
	}

	/**
	 * @brief	Track if we've already reset comment counts so we don't do it more than once between saves
	 */
	protected $commentCountsReset = FALSE;

	/**
	 * Set the comment/approved/hidden counts
	 *
	 * @return void
	 */
	public function resetCommentCounts()
	{
		if( $this->commentCountsReset )
		{
			return;
		}

		parent::resetCommentCounts();

		/* If we allow albums, add album comment/review counts too */
		if( $this->allow_albums )
		{
			try
			{
				$consolidated = \IPS\Db::i()->select( 'SUM(album_comments) as comments, SUM(album_comments_unapproved) as unapproved_comments', 'gallery_albums', array( 'album_category_id=?', $this->_id ), NULL, NULL, NULL, NULL, \IPS\Db::SELECT_FROM_WRITE_SERVER )->first();

				$this->_comments			= $this->count_comments + $consolidated['comments'];
				$this->_unapprovedComments	= $this->count_comments_hidden + $consolidated['unapproved_comments'];
			}
			catch( \UnderflowException $e ){}
		}
	}

	/**
	 * [ActiveRecord] Save Changed Columns
	 *
	 * @return	void
	 */
	public function save()
	{
		parent::save();

		$this->commentCountsReset = FALSE;
	}

	/**
	 * Get last comment time
	 *
	 * @note	This should return the last comment time for this node only, not for children nodes
	 * @param   \IPS\Member|NULL    $member         MemberObject
	 * @return	\IPS\DateTime|NULL
	 */
	public function getLastCommentTime( \IPS\Member $member = NULL )
	{
		return $this->last_img_date ? \IPS\DateTime::ts( $this->last_img_date ) : NULL;
	}

	/**
	 * @brief	Cached cover photo
	 */
	protected $coverPhoto	= NULL;

	/**
	 * Retrieve a cover photo
	 *
	 * @param	string	$size	Masked or small
	 * @return	string|null
	 * @throws	\InvalidArgumentException
	 */
	public function coverPhoto( $size='small' )
	{
		if ( !$this->can( 'read' ) )
		{
			return NULL;
		}

		/* Make sure it's a valid size */
		if( !\in_array( $size, array( 'masked', 'small' ) ) )
		{
			throw new \InvalidArgumentException;
		}
		
		$property = $size . "_file_name";

		/* If we have an explicit cover photo set, make sure it's valid and load/cache it */
		if( $this->cover_img_id )
		{
			if( $this->coverPhoto === NULL )
			{
				$this->coverPhoto = $this->coverPhotoObject();
			}
		}

		if( $this->coverPhoto !== NULL )
		{
			return (string) \IPS\File::get( 'gallery_Images', $this->coverPhoto->$property )->url;
		}

		if( $lastImage = $this->lastImage() )
		{
			return (string) \IPS\File::get( 'gallery_Images', $lastImage->$property )->url;
		}

		return NULL;
	}

	/**
	 * Retrieve a cover photo object
	 *
	 * @return	\IPS\gallery\Image|null
	 */
	public function coverPhotoObject()
	{
		/* If we have an explicit cover photo set, make sure it's valid and load/cache it */
		if( $this->cover_img_id )
		{
			if( $this->coverPhoto === NULL )
			{
				try
				{
					$this->coverPhoto	= \IPS\gallery\Image::load( $this->cover_img_id );

					if( $this->coverPhoto !== NULL )
					{
						return $this->coverPhoto;
					}
				}
				catch( \OutOfRangeException $e )
				{
					/* Cover photo isn't valid, reset category automatically */
					$this->cover_img_id	= 0;
					$this->save();
				}
			}
		}

		if( $lastImage = $this->lastImage() )
		{
			return $lastImage;
		}

		return NULL;
	}

	/**
	 * Load record based on a URL
	 *
	 * @param	\IPS\Http\Url	$url	URL to load from
	 * @return	static
	 * @throws	\InvalidArgumentException
	 * @throws	\OutOfRangeException
	 */
	public static function loadFromUrl( \IPS\Http\Url $url )
	{
		$qs = array_merge( $url->queryString, $url->hiddenQueryString );
		
		if ( isset( $qs['category'] ) )
		{
			return static::load( $qs['category'] );
		}
		
		throw new \InvalidArgumentException;
	}

	/**
	 * Check if any albums are located in this category
	 *
	 * @return	bool
	 */
	public function hasAlbums()
	{
		return (bool) \IPS\Db::i()->select( 'COUNT(*) as total', 'gallery_albums', array( 'album_category_id=?', $this->id ) )->first();
	}

	/**
	 * Should we show the form to delete or move content?
	 *
	 * @return bool
	 */
	public function showDeleteOrMoveForm()
	{
		/* Do we have any albums? */
		if( $this->hasAlbums() )
		{
			return TRUE;
		}

		return parent::showDeleteOrMoveForm();
	}
	
	/**
	 * Form to delete or move content
	 *
	 * @param	bool	$showMoveToChildren	If TRUE, will show "move to children" even if there are no children
	 * @return	\IPS\Helpers\Form
	 */
	public function deleteOrMoveForm( $showMoveToChildren=FALSE )
	{
		if ( $this->hasChildren( NULL ) OR $this->hasAlbums() )
		{
			$showMoveToChildren = TRUE;
			if( $this->hasChildren( NULL ) AND $this->hasAlbums() )
			{
				\IPS\Member::loggedIn()->language()->words['node_move_children']	= \IPS\Member::loggedIn()->language()->addToStack( 'node_move_catsalbums', FALSE );
				\IPS\Member::loggedIn()->language()->words['node_delete_children']	= \IPS\Member::loggedIn()->language()->addToStack( 'node_delete_children_catsalbums', FALSE );
			}
			else if( $this->hasChildren( NULL ) )
			{
				\IPS\Member::loggedIn()->language()->words['node_move_children']	= \IPS\Member::loggedIn()->language()->addToStack( 'node_move_children', FALSE, array( 'sprintf' => array( \IPS\Member::loggedIn()->language()->addToStack( static::$nodeTitle ) ) ) );
				\IPS\Member::loggedIn()->language()->words['node_delete_children']	= \IPS\Member::loggedIn()->language()->addToStack( 'node_delete_children_cats', FALSE );
			}
			else
			{
				\IPS\Member::loggedIn()->language()->words['node_move_children']	= \IPS\Member::loggedIn()->language()->addToStack( 'node_move_subalbums', FALSE );
				\IPS\Member::loggedIn()->language()->words['node_delete_children']	= \IPS\Member::loggedIn()->language()->addToStack( 'node_delete_children_albums', FALSE );
			}
		}
		return parent::deleteOrMoveForm( $showMoveToChildren );
	}
	
	/**
	 * Handle submissions of form to delete or move content
	 *
	 * @param	array	$values			Values from form
	 * @return	void
	 */
	public function deleteOrMoveFormSubmit( $values )
	{
		if ( $this->hasAlbums() )
		{
			foreach ( new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'gallery_albums', array( 'album_category_id=?', $this->id ) ), 'IPS\gallery\Album' ) as $album )
			{
				if ( isset( $values['node_move_children'] ) AND $values['node_move_children'] )
				{
					$album->moveTo( \IPS\gallery\Category::load( ( isset( $values['node_destination'] ) ) ? $values['node_destination'] : \IPS\Request::i()->node_move_children ) );
				}
				else
				{
					\IPS\Task::queue( 'core', 'DeleteOrMoveContent', array( 'class' => 'IPS\gallery\Album', 'id' => $album->_id, 'deleteWhenDone' => TRUE ) );
				}
			}
		}
		
		return parent::deleteOrMoveFormSubmit( $values );
	}

	/**
	 * Set Default Values
	 *
	 * @return	void
	 */
	public function setDefaultValues()
	{
		$this->sort_options = 'album_last_img_date';
		$this->sort_options_img = 'updated';
	}

	/**
	 * Get template for node tables
	 *
	 * @return	callable
	 */
	public static function nodeTableTemplate()
	{
		return array( \IPS\Theme::i()->getTemplate( 'browse', 'gallery' ), 'categoryRow' );
	}

	/**
	 * Check permissions on any node
	 *
	 * For example - can be used to check if the user has
	 * permission to create content in any node to determine
	 * if there should be a "Submit" button
	 *
	 * @param	mixed								$permission						A key which has a value in static::$permissionMap['view'] matching a column ID in core_permission_index
	 * @param	\IPS\Member|\IPS\Member\Group|NULL	$member							The member or group to check (NULL for currently logged in member)
	 * @param	array								$where							Additional WHERE clause
	 * @param	bool								$considerPostBeforeRegistering	If TRUE, and $member is a guest, will return TRUE if "Post Before Registering" feature is enabled
	 * @return	bool
	 * @throws	\OutOfBoundsException	If $permission does not exist in static::$permissionMap
	 */
	public static function canOnAny( $permission, $member=NULL, $where = array(), $considerPostBeforeRegistering = TRUE )
	{
		/* Load member */
		$member = $member ?: \IPS\Member::loggedIn();

		if ( $member->members_bitoptions['remove_gallery_access'] )
		{
			return FALSE;
		}

		return parent::canOnAny( $permission, $member, $where, $considerPostBeforeRegistering );
	}

    /**
     * [ActiveRecord] Duplicate
     *
     * @return	void
     */
    public function __clone()
    {
        if ( $this->skipCloneDuplication === TRUE )
        {
            return;
        }

        $this->public_albums = 0;
        $this->nonpublic_albums = 0;
        $this->cover_img_id = 0;

        $oldId = $this->id;

        parent::__clone();

        foreach ( array( 'rules_title' => "gallery_category_{$this->id}_rulestitle", 'rules_text' => "gallery_category_{$this->id}_rules" ) as $fieldKey => $langKey )
        {
            $oldLangKey = str_replace( $this->id, $oldId, $langKey );
            \IPS\Lang::saveCustom( 'gallery', $langKey, iterator_to_array( \IPS\Db::i()->select( 'word_custom, lang_id', 'core_sys_lang_words', array( 'word_key=?', $oldLangKey ) )->setKeyField( 'lang_id' )->setValueField('word_custom') ) );
        }
    }
    	
	/**
	 * Get content for embed
	 *
	 * @param	array	$params	Additional parameters to add to URL
	 * @return	string
	 */
	public function embedContent( $params )
	{
		return 'x';
	}
	
	/**
	 * [Node] Get the fully qualified node type (mostly for Pages because Pages is Pages)
	 *
	 * @return	string
	 */
	public static function fullyQualifiedType()
	{
		return \IPS\Member::loggedIn()->language()->addToStack('__app_gallery') . ' ' . \IPS\Member::loggedIn()->language()->addToStack( static::$nodeTitle . '_sg');
	}
	
	/* !Clubs */
	
	/**
	 * Get acp language string
	 *
	 * @return	string
	 */
	public static function clubAcpTitle()
	{
		return 'editor__gallery_Categories';
	}
		
	/**
	 * Set form for creating a node of this type in a club
	 *
	 * @param	\IPS\Helpers\Form	$form	Form object
	 * @return	void
	 */
	public function clubForm( \IPS\Helpers\Form $form, \IPS\Member\Club $club )
	{
		$itemClass = static::$contentItemClass;
		$form->add( new \IPS\Helpers\Form\Text( 'club_node_name', $this->_id ? $this->_title : \IPS\Member::loggedIn()->language()->addToStack( $itemClass::$title . '_pl' ), TRUE, array( 'maxLength' => 255 ) ) );
		$form->add( new \IPS\Helpers\Form\Editor( 'club_node_description', $this->_id ? \IPS\Member::loggedIn()->language()->get( static::$titleLangPrefix . $this->_id . '_desc' ) : NULL, FALSE, array( 'app' => 'gallery', 'key' => 'Categories', 'autoSaveKey' => ( $this->id ? "gallery-cat-{$this->id}" : "gallery-new-cat" ), 'attachIds' => $this->id ? array( $this->id, NULL, 'description' ) : NULL, 'minimize' => 'cdesc_placeholder' ) ) );
		
		$form->add( new \IPS\Helpers\Form\YesNo( 'category_allow_albums_club', $this->id ? $this->allow_albums : TRUE ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'category_allow_comments', $this->id ? $this->allow_comments : TRUE ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'category_allow_reviews', $this->id ? $this->allow_reviews : FALSE ) );

		$sortoptions = array(
			'album_last_img_date'		=> 'sort_updated',
			'album_last_comment'		=> 'sort_last_comment',
			'album_rating_aggregate'	=> 'sort_rating',
			'album_comments'			=> 'sort_num_comments',
			'album_reviews'				=> 'sort_num_reviews',
			'album_name'				=> 'sort_album_name',
			'album_count_comments'		=> 'sort_album_count_comments',
			'album_count_imgs'			=> 'sort_album_count_imgs'
		);
		$form->add( new \IPS\Helpers\Form\Select( 'category_sort_options', $this->sort_options ?: 'updated', FALSE, array( 'options' => $sortoptions ), NULL, NULL, NULL, 'category_sort_options' ) );
		$form->add( new \IPS\Helpers\Form\Select( 'category_sort_options_img', $this->sort_options_img ?: 'updated', FALSE, array( 'options' => array( 'updated' => 'sort_updated', 'last_comment' => 'sort_last_comment', 'title' => 'album_sort_caption', 'rating' => 'sort_rating', 'date' => 'sort_date', 'num_comments' => 'sort_num_comments', 'num_reviews' => 'sort_num_reviews', 'views' => 'sort_views' ) ), NULL, NULL, NULL, 'category_sort_options_img' ) );
		
		if( $club->type == 'closed' )
		{
			$form->add( new \IPS\Helpers\Form\Radio( 'club_node_public', $this->id ? $this->isPublic() : 0, TRUE, array( 'options' => array( '0' => 'club_node_public_no', '1' => 'club_node_public_view', '2' => 'club_node_public_participate' ) ) ) );
		}
	}
	
	/**
	 * Class-specific routine when saving club form
	 *
	 * @param	\IPS\Member\Club	$club	The club
	 * @param	array				$values	Values
	 * @return	void
	 */
	public function _saveClubForm( \IPS\Member\Club $club, $values )
	{
		$this->allow_albums = $values['category_allow_albums_club'];
		$this->allow_comments = $values['category_allow_comments'];
		$this->allow_reviews = $values['category_allow_reviews'];
		$this->sort_options = $values['category_sort_options'];
		$this->sort_options_img = $values['category_sort_options_img'];
		$this->can_tag = TRUE;
		$this->tag_prefixes = TRUE;
		
		if ( $values['club_node_name'] )
		{
			$this->name_seo	= \IPS\Http\Url\Friendly::seoTitle( $values['club_node_name'] );
		}
		
		if ( !$this->_id )
		{
			$this->save();
			\IPS\File::claimAttachments( 'gallery-new-cat', $this->id, NULL, 'description' );
		}
	}

	/**
	 * @brief   The class of the ACP \IPS\Node\Controller that manages this node type
	 */
	protected static $acpController = "IPS\\gallery\\modules\\admin\\gallery\\categories";
	
	/**
	 * Content was held for approval by container
	 * Allow node classes that can determine if content should be held for approval in individual nodes
	 *
	 * @param	string				$content	The type of content we are checking (item, comment, review).
	 * @param	\IPS\Member|NULL	$member		Member to check or NULL for currently logged in member.
	 * @return	bool
	 */
	public function contentHeldForApprovalByNode( string $content, ?\IPS\Member $member = NULL ): bool
	{
		/* If members group bypasses, then no. */
		$member = $member ?: \IPS\Member::loggedIn();
		if ( $member->group['g_avoid_q'] )
		{
			return FALSE;
		}
		
		switch( $content )
		{
			case 'item':
				return (bool) $this->approve_img;
				break;
			
			case 'comment':
				return (bool) $this->approve_com;
				break;
			
			case 'review':
				return (bool) $this->review_moderate;
				break;
		}
	}

}