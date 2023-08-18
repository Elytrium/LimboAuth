<?php
/**
 * @brief		Records Model
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Content
 * @since		8 April 2014
 */

namespace IPS\cms;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief Records Model
 */
class _Records extends \IPS\Content\Item implements
	\IPS\Content\Permissions,
	\IPS\Content\Pinnable, \IPS\Content\Lockable, \IPS\Content\Hideable, \IPS\Content\Featurable,
	\IPS\Content\Tags,
	\IPS\Content\Followable,
	\IPS\Content\Shareable,
	\IPS\Content\ReadMarkers,
	\IPS\Content\Ratings,
	\IPS\Content\Searchable,
	\IPS\Content\FuturePublishing,
	\IPS\Content\Embeddable,
	\IPS\Content\MetaData,
	\IPS\Content\Anonymous
{
	use \IPS\Content\Reactable, \IPS\Content\Reportable, \IPS\Content\Statistics, \IPS\Content\ViewUpdates;
	
	/**
	 * @brief	Multiton Store
	 */
	protected static $multitons = array();
	
	/**
	 * @brief	[ActiveRecord] Database Table
	 */
	public static $databaseTable = NULL;
	
	/**
	 * @brief	[ActiveRecord] ID Database Column
	 */
	public static $databaseColumnId = 'primary_id_field';

    /**
     * @brief	[ActiveRecord] Database ID Fields
     */
    protected static $databaseIdFields = array('record_static_furl', 'record_topicid');
    
    /**
	 * @brief	[ActiveRecord] Multiton Map
	 */
	protected static $multitonMap	= array();

	/**
	 * @brief	Database Prefix
	 */
	public static $databasePrefix = '';

	/**
	 * @brief	Application
	 */
	public static $application = 'cms';
	
	/**
	 * @brief	Module
	 */
	public static $module = 'records';
	
	/**
	 * @brief	Node Class
	 */
	public static $containerNodeClass = NULL;
	
	/**
	 * @brief	[Content\Item]	Comment Class
	 */
	public static $commentClass = NULL;
	
	/**
	 * @brief	[Content\Item]	First "comment" is part of the item?
	 */
	public static $firstCommentRequired = FALSE;
	
	/**
	 * @brief	[Content\Item]	Form field label prefix
	 */
	public static $formLangPrefix = 'content_record_form_';
	
	/**
	 * @brief	[Records] Custom Database Id
	 */
	public static $customDatabaseId = NULL;
	
	/**
	 * @brief 	[Records] Database object
	 */
	protected static $database = array();
	
	/**
	 * @brief 	[Records] Database object
	 */
	public static $title = 'content_record_title';
		
	/**
	 * @brief	[Records] Standard fields
	 */
	protected static $standardFields = array( 'record_publish_date', 'record_expiry_date', 'record_allow_comments', 'record_comment_cutoff' );

	/**
	 * The real definition of this happens in the magic autoloader inside the cms\Application class, but we need this one which contains all the "none database id related" fields for the Content Widget
	 *
	 * @var string[]
	 */
	public static $databaseColumnMap = array(
		'author'				=> 'member_id',
		'container'				=> 'category_id',
		'date'					=> 'record_saved',
		'is_future_entry'       => 'record_future_date',
		'future_date'           => 'record_publish_date',
		'num_comments'			=> 'record_comments',
		'unapproved_comments'	=> 'record_comments_queued',
		'hidden_comments'		=> 'record_comments_hidden',
		'last_comment'			=> 'record_last_comment',
		'last_comment_by'		=> 'record_last_comment_by',
		'last_comment_name'		=> 'record_last_comment_name',
		'views'					=> 'record_views',
		'approved'				=> 'record_approved',
		'pinned'				=> 'record_pinned',
		'locked'				=> 'record_locked',
		'featured'				=> 'record_featured',
		'rating'				=> 'record_rating',
		'rating_hits'			=> 'rating_hits',
		'rating_average'	    => 'record_rating',
		'rating_total'			=> 'rating_value',
		'num_reviews'	        => 'record_reviews',
		'last_review'	        => 'record_last_review',
		'last_review_by'        => 'record_last_review_by',
		'last_review_name'      => 'record_last_review_name',
		'updated'				=> 'record_last_comment',
		'meta_data'				=> 'record_meta_data',
		'author_name'			=> 'record_author_name',
		'is_anon'				=> 'record_is_anon',
		'last_comment_anon'		=> 'record_last_comment_anon'
	);


	/**
	 * @brief	Icon
	 */
	public static $icon = 'file-text';
	
	/**
	 * @brief	Include In Sitemap (We do not want to include in Content sitemap, as we have a custom extension
	 */
	public static $includeInSitemap = FALSE;
	
	/**
	 * @brief	Prevent custom fields being fetched twice when loading/saving a form
	 */
	public static $customFields = NULL;

	/**
	 * Whether or not to include in site search
	 *
	 * @return	bool
	 */
	public static function includeInSiteSearch()
	{
		return (bool) \IPS\cms\Databases::load( static::$customDatabaseId )->search;
	}

	/**
	 * Most, if not all of these are the same for different events, so we can just have one method
	 *
	 * @param   IPS\Content\Comment $comment    A comment item, leave null for these keys to be omitted
	 *
	 * @return  array
	 */
	public function getDataLayerProperties( ?\IPS\Content\Comment $comment = null )
	{
		$commentIdColumn = $comment ? $comment::$databaseColumnId : null;
		$index = $commentIdColumn ? ( $comment->$commentIdColumn ?: 0 ) : 0;
		if ( isset( $this->_dataLayerProperties[$index] ) )
		{
			return $this->_dataLayerProperties[$index];
		}

		/* Set the content_type and comment_type to lower case for consistency */
		$properties = parent::getDataLayerProperties( $comment );
		if ( isset( $properties['content_type'] ) )
		{
			$properties['content_type'] = \strtolower( \IPS\Lang::load( \IPS\Lang::defaultLanguage() )->addToStack( static::$title ) );
		}

		$this->_dataLayerProperties[$index] = $properties;
		return $properties;
	}

	/**
	 * Construct ActiveRecord from database row
	 *
	 * @param	array	$data							Row from database table
	 * @param	bool	$updateMultitonStoreIfExists	Replace current object in multiton store if it already exists there?
	 * @return	static
	 */
	public static function constructFromData( $data, $updateMultitonStoreIfExists = TRUE )
	{
		$obj = parent::constructFromData( $data, $updateMultitonStoreIfExists );

		/* Prevent infinite redirects */
		if ( ! $obj->record_dynamic_furl and ! $obj->record_static_furl )
		{
			if ( $obj->_title )
			{
				$obj->record_dynamic_furl = \IPS\Http\Url\Friendly::seoTitle( mb_substr( $obj->_title, 0, 255 ) );
				$obj->save();
			}
		}

		if ( $obj->useForumComments() )
		{
			$obj::$commentClass = 'IPS\cms\Records\CommentTopicSync' . static::$customDatabaseId;
		}

		return $obj;
	}

	/**
	 * Set custom posts per page setting
	 *
	 * @return int
	 */
	public static function getCommentsPerPage()
	{
		if ( ! empty( \IPS\cms\Databases\Dispatcher::i()->recordId ) )
		{
			$class = 'IPS\cms\Records' . static::$customDatabaseId;
			try
			{
				$record = $class::load( \IPS\cms\Databases\Dispatcher::i()->recordId );
				
				if ( $record->_forum_record and $record->_forum_comments and \IPS\Application::appIsEnabled('forums') )
				{
					return \IPS\forums\Topic::getCommentsPerPage();
				}
			}
			catch( \OutOfRangeException $e )
			{
				/* recordId is usually the record we're viewing, but this method is called on recordFeed widgets in horizontal mode which means recordId may not be __this__ record, so fail gracefully */
				return static::database()->field_perpage;
			}
		}
		else if( static::database()->forum_record and static::database()->forum_comments and \IPS\Application::appIsEnabled('forums') )
		{
			return \IPS\forums\Topic::getCommentsPerPage();
		}

		return static::database()->field_perpage;
	}

	/**
	 * Returns the database parent
	 * 
	 * @return \IPS\cms\Databases
	 */
	public static function database()
	{
		if ( ! isset( static::$database[ static::$customDatabaseId ] ) )
		{
			static::$database[ static::$customDatabaseId ] = \IPS\cms\Databases::load( static::$customDatabaseId );
		}
		
		return static::$database[ static::$customDatabaseId ];
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
		/* First, make sure the PAGE matches */
		$page = \IPS\cms\Pages\Page::loadFromUrl( $url );

		if( $page->_id != static::database()->page_id )
		{
			throw new \OutOfRangeException;
		}

		$qs = array_merge( $url->queryString, $url->hiddenQueryString );
		
		if ( isset( $qs['path'] ) )
		{
			$bits = explode( '/', trim( $qs['path'], '/' ) );
			$path = array_pop( $bits );
			
			try
			{
				return static::loadFromSlug( $path, FALSE );
			}
			catch ( \Exception $e ) { }
		}

		return parent::loadFromUrl( $url );
	}

	/**
	 * Load from slug
	 * 
	 * @param	string		$slug							Thing that lives in the garden and eats your plants
	 * @param	bool		$redirectIfSeoTitleIsIncorrect	If the SEO title is incorrect, this method may redirect... this stops that
	 * @param	integer		$categoryId						Optional category ID to restrict the look up in.
	 * @return	\IPS\cms\Record
	 */
	public static function loadFromSlug( $slug, $redirectIfSeoTitleIsIncorrect=TRUE, $categoryId=NULL )
	{
		$slug = trim( $slug, '/' );
		
		/* If the slug is an empty string, then there is nothing to try and load. */
		if ( empty( $slug ) )
		{
			throw new \OutOfRangeException;
		}

		/* Try the easiest option */
		preg_match( '#-r(\d+?)$#', $slug, $matches );

		if ( isset( $matches[1] ) AND \is_numeric( $matches[1] ) )
		{
			try
			{
				$record = static::load( $matches[1] );

				/* Check to make sure the SEO title is correct */
				if ( $redirectIfSeoTitleIsIncorrect and urldecode( str_replace( $matches[0], '', $slug ) ) !== $record->record_dynamic_furl and !\IPS\Request::i()->isAjax() and mb_strtolower( $_SERVER['REQUEST_METHOD'] ) == 'get' and !\IPS\ENFORCE_ACCESS )
				{
					$url = $record->url();

					/* Don't correct the URL if the visitor cannot see the record */
					if( !$record->canView() )
					{
						throw new \OutOfRangeException;
					}

					/* Redirect to the embed form if necessary */
					if( isset( \IPS\Request::i()->do ) and \IPS\Request::i()->do == 'embed' )
					{
						$url = $url->setQueryString( array( 'do' => "embed" ) );
					}

					\IPS\Output::i()->redirect( $url );
				}

				static::$multitons[ $record->primary_id_field ] = $record;

				return static::$multitons[ $record->primary_id_field ];
			}
			catch( \OutOfRangeException $ex ) { }
		}

		$where = array( array( '? LIKE CONCAT( record_dynamic_furl, \'%\') OR LOWER(record_static_furl)=?', $slug, mb_strtolower( $slug ) ) );
		if ( $categoryId )
		{
			$where[] = array( 'category_id=?', $categoryId );
		}
		
		foreach( \IPS\Db::i()->select( '*', static::$databaseTable, $where ) as $record )
		{
			$pass = FALSE;
			
			if ( mb_strtolower( $slug ) === mb_strtolower( $record['record_static_furl'] ) )
			{
				$pass = TRUE;
			}
			else
			{
				if ( isset( $matches[1] ) AND \is_numeric( $matches[1] ) AND $matches[1] == $record['primary_id_field'] )
				{
					$pass = TRUE;
				}
			}
				
			if ( $pass === TRUE )
			{
				static::$multitons[ $record['primary_id_field'] ] = static::constructFromData( $record );
				
				if ( $redirectIfSeoTitleIsIncorrect AND $slug !== $record['record_static_furl'] )
				{
					\IPS\Output::i()->redirect( static::$multitons[ $record['primary_id_field'] ]->url() );
				}
			
				return static::$multitons[ $record['primary_id_field'] ];
			}	
		}
		
		/* Still here? Consistent with AR pattern */
		throw new \OutOfRangeException();	
	}

	/**
	 * Load from slug history so we can 301 to the correct record.
	 *
	 * @param	string		$slug	Thing that lives in the garden and eats your plants
	 * @return	\IPS\cms\Record
	 */
	public static function loadFromSlugHistory( $slug )
	{
		$slug = trim( $slug, '/' );

		try
		{
			$row = \IPS\Db::i()->select( '*', 'cms_url_store', array( 'store_type=? and store_path=?', 'record', $slug ) )->first();

			return static::load( $row['store_current_id'] );
		}
		catch( \UnderflowException $ex ) { }

		/* Still here? Consistent with AR pattern */
		throw new \OutOfRangeException();
	}

	/**
	 * Indefinite Article
	 *
	 * @param	\IPS\Lang|NULL	$lang	The language to use, or NULL for the language of the currently logged in member
	 * @return	string
	 */
	public function indefiniteArticle( \IPS\Lang $lang = NULL )
	{
		$lang = $lang ?: \IPS\Member::loggedIn()->language();
		return $lang->addToStack( 'content_db_lang_ia_' . static::$customDatabaseId, FALSE );
	}
	
	/**
	 * Indefinite Article
	 *
	 * @param	array|null	$containerData	Container data
	 * @param	\IPS\Lang|null	$lang		Language object
	 * @return	string
	 */
	public static function _indefiniteArticle( array $containerData = NULL, \IPS\Lang $lang = NULL )
	{
		$lang = $lang ?: \IPS\Member::loggedIn()->language();
		return $lang->addToStack( 'content_db_lang_ia_' . static::$customDatabaseId, FALSE );
	}
	
	/**
	 * Definite Article
	 *
	 * @param	\IPS\Lang|NULL	$lang	The language to use, or NULL for the language of the currently logged in member
	 * @param	integer|boolean	$count	Number of items. If not false, pluralized version of phrase will be used.
	 * @return	string
	 */
	public function definiteArticle( \IPS\Lang $lang = NULL, $count = FALSE )
	{
		$lang = $lang ?: \IPS\Member::loggedIn()->language();
		if( $count === TRUE || ( \is_int( $count ) && $count > 1 ) )
		{
			return $lang->addToStack( 'content_db_lang_pl_' . static::$customDatabaseId, FALSE );
		}
		else
		{
			return $lang->addToStack( 'content_db_lang_sl_' . static::$customDatabaseId, FALSE );
		}
	}
	
	/**
	 * Definite Article
	 *
	 * @param	array			$containerData	Basic data about the container. Only includes columns returned by container::basicDataColumns()
	 * @param	\IPS\Lang|NULL	$lang			The language to use, or NULL for the language of the currently logged in member
	 * @param	array			$options		Options to pass to \IPS\Lang::addToStack
	 * @param	integer|boolean	$count			Number of items. If not false, pluralized version of phrase will be used.
	 * @return	string
	 */
	public static function _definiteArticle( array $containerData = NULL, \IPS\Lang $lang = NULL, $options = array(), $count = FALSE )
	{
		$lang = $lang ?: \IPS\Member::loggedIn()->language();
		if( $count === TRUE || ( \is_int( $count ) && $count > 1 ) )
		{
			return $lang->addToStack( 'content_db_lang_pl_' . static::$customDatabaseId, FALSE, $options );
		}
		else
		{
			return $lang->addToStack( 'content_db_lang_sl_' . static::$customDatabaseId, FALSE, $options );
		}
	}
	
	/**
	 * Get elements for add/edit form
	 *
	 * @param	\IPS\Content\Item|NULL	$item		The current item if editing or NULL if creating
	 * @param	\IPS\Node\Model|NULL	$container	Container (e.g. forum), if appropriate
	 * @return	array
	 */
	public static function formElements( $item=NULL, \IPS\Node\Model $container=NULL )
	{
		$customValues = ( $item ) ? $item->fieldValues() : array();
		$database     = \IPS\cms\Databases::load( static::$customDatabaseId );
		$fieldsClass  = 'IPS\cms\Fields' .  static::$customDatabaseId;
		$formElements = array();
		$elements     = parent::formElements( $item, $container );
		static::$customFields = $fieldsClass::fields( $customValues, ( $item ? 'edit' : 'add' ), $container, 0, ( ! $item ? NULL : $item ) );

		/* Build the topic state toggles */
		$options = array();
		$toggles = array();
		$values  = array();
		
		/* Title */
		if ( isset( static::$customFields[ $database->field_title ] ) )
		{
			$formElements['title'] = static::$customFields[ $database->field_title ];
			$formElements['title']->rowClasses[] = 'ipsFieldRow_primary';
			$formElements['title']->rowClasses[] = 'ipsFieldRow_fullWidth';
		}

		if ( isset( $elements['guest_name'] ) )
		{
			$formElements['guest_name'] = $elements['guest_name'];
		}
		
		if ( isset( $elements['guest_email'] ) )
		{
			$formElements['guest_email'] = $elements['guest_email'];
		}

		if ( isset( $elements['captcha'] ) )
		{
			$formElements['captcha'] = $elements['captcha'];
		}

		if ( \IPS\Member::loggedIn()->modPermission('can_content_edit_record_slugs') )
		{
			$formElements['record_static_furl_set'] = new \IPS\Helpers\Form\YesNo( 'record_static_furl_set', ( ( $item AND $item->record_static_furl ) ? TRUE : FALSE ), FALSE, array(
					'togglesOn' => array( 'record_static_furl' )
			)  );
			$formElements['record_static_furl'] = new \IPS\Helpers\Form\Text( 'record_static_furl', ( ( $item AND $item->record_static_furl ) ? $item->record_static_furl : NULL ), FALSE, array(), function( $val ) use ( $database )
            {
                /* Make sure key is unique */
                if ( empty( $val ) )
                {
                    return true;
                }
                
                /* Make sure it does not match the dynamic URL format */
                if ( preg_match( '#-r(\d+?)$#', $val ) )
                {
	                throw new \InvalidArgumentException('content_record_slug_not_unique');
                }

                try
                {
                    $cat = \intval( ( isset( \IPS\Request::i()->content_record_form_container ) ) ? \IPS\Request::i()->content_record_form_container : 0 );
                    $recordsClass = '\IPS\cms\Records' . $database->id;
					
					if ( $recordsClass::isFurlCollision( $val ) )
					{
						 throw new \InvalidArgumentException('content_record_slug_not_unique');
					}
					
                    /* Fetch record by static slug */
                    $record = $recordsClass::load( $val, 'record_static_furl' );

                    /* In the same category though? */
                    if ( isset( \IPS\Request::i()->id ) and $record->_id == \IPS\Request::i()->id )
                    {
                        /* It's ok, it's us! */
                        return true;
                    }

                    if ( $cat === $record->category_id )
                    {
                        throw new \InvalidArgumentException('content_record_slug_not_unique');
                    }
                }
                catch ( \OutOfRangeException $e )
                {
                    /* Slug is OK as load failed */
                    return true;
                }

                return true;
            }, \IPS\Member::loggedIn()->language()->addToStack('record_static_url_prefix', FALSE, array( 'sprintf' => array( \IPS\Settings::i()->base_url ) ) ), NULL, 'record_static_furl' );
		}
		
		if ( isset( $elements['tags'] ) )
		{ 
			$formElements['tags'] = $elements['tags'];
		}

		/* Now custom fields */
		foreach( static::$customFields as $id => $obj )
		{
			if ( $database->field_title === $id )
			{
				continue;
			}

			$formElements['field_' . $id ] = $obj;

			if ( $database->field_content == $id )
			{
				if ( isset( $elements['auto_follow'] ) )
				{
					$formElements['auto_follow'] = $elements['auto_follow'];
				}

				if ( \IPS\Settings::i()->edit_log and $item )
				{
					if ( \IPS\Settings::i()->edit_log == 2 )
					{
						$formElements['record_edit_reason'] = new \IPS\Helpers\Form\Text( 'record_edit_reason', ( $item ) ? $item->record_edit_reason : NULL, FALSE, array( 'maxLength' => 255 ) );
					}
					if ( \IPS\Member::loggedIn()->group['g_append_edit'] )
					{
						$formElements['record_edit_show'] = new \IPS\Helpers\Form\Checkbox( 'record_edit_show', FALSE );
					}
				}
			}
		}
		
		if ( isset( $elements['date'] ) AND $fieldsClass::fixedFieldFormShow( 'record_publish_date' ) AND ( \IPS\Member::loggedIn()->modPermission( "can_future_publish_content" ) or \IPS\Member::loggedIn()->modPermission( "can_future_publish_" . static::$title ) ) )
		{
			$formElements['record_publish_date'] = $elements['date'];
		}

		if ( $fieldsClass::fixedFieldFormShow( 'record_image' ) )
		{
			$fixedFieldSettings = static::database()->fixed_field_settings;
			$dims = TRUE;

			if ( isset( $fixedFieldSettings['record_image']['image_dims'] ) AND $fixedFieldSettings['record_image']['image_dims'][0] AND $fixedFieldSettings['record_image']['image_dims'][1] )
			{
				$dims = array( 'maxWidth' => $fixedFieldSettings['record_image']['image_dims'][0], 'maxHeight' => $fixedFieldSettings['record_image']['image_dims'][1] );
			}

			$formElements['record_image'] = new \IPS\Helpers\Form\Upload( 'record_image', ( ( $item and $item->record_image ) ? \IPS\File::get( 'cms_Records', $item->record_image ) : NULL ), FALSE, array( 'image' => $dims, 'storageExtension' => 'cms_Records', 'multiple' => false, 'allowStockPhotos' => true, 'canBeModerated' => TRUE ), NULL, NULL, NULL, 'record_image' );
		}

		if ( $fieldsClass::fixedFieldFormShow( 'record_expiry_date' ) )
		{
			$formElements['record_expiry_date'] = new \IPS\Helpers\Form\Date( 'record_expiry_date', ( ( $item AND $item->record_expiry_date ) ? \IPS\DateTime::ts( $item->record_expiry_date ) : NULL ), FALSE, array(
					'time'          => true,
					'unlimited'     => -1,
					'unlimitedLang' => 'record_datetime_noval'
			) );
		}

		if ( $fieldsClass::fixedFieldFormShow( 'record_allow_comments' ) )
		{
			$formElements['record_allow_comments'] = new \IPS\Helpers\Form\YesNo( 'record_allow_comments', ( ( $item ) ? $item->record_allow_comments : TRUE ), FALSE, array(
					'togglesOn' => array( 'record_comment_cutoff' )
			)  );
		}
		
		if ( $fieldsClass::fixedFieldFormShow( 'record_comment_cutoff' ) )
		{
			$formElements['record_comment_cutoff'] = new \IPS\Helpers\Form\Date( 'record_comment_cutoff', ( ( $item AND $item->record_comment_cutoff ) ? \IPS\DateTime::ts( $item->record_comment_cutoff ) : NULL ), FALSE, array(
					'time'          => true,
					'unlimited'     => -1,
					'unlimitedLang' => 'record_datetime_noval'
			), NULL, NULL, NULL, 'record_comment_cutoff' );
		}
		
		/* Post Anonymously */
		if ( $container and $container->canPostAnonymously( $container::ANON_ITEMS ) )
		{
			$formElements['post_anonymously'] = new \IPS\Helpers\Form\YesNo( 'post_anonymously', ( $item ) ? $item->isAnonymous() : FALSE , FALSE, array( 'label' => \IPS\Member::loggedIn()->language()->addToStack( 'post_anonymously_suffix' ) ), NULL, NULL, NULL, 'post_anonymously' );
		}

		if ( static::modPermission( 'lock', NULL, $container ) )
		{
			$options['lock'] = 'create_record_locked';
			$toggles['lock'] = array( 'create_record_locked' );
			
			if ( $item AND $item->record_locked )
			{
				$values[] = 'lock';
			}
		}
			
		if ( static::modPermission( 'pin', NULL, $container ) )
		{
			$options['pin'] = 'create_record_pinned';
			$toggles['pin'] = array( 'create_record_pinned' );
			
			if ( $item AND $item->record_pinned )
			{
				$values[] = 'pin';
			}
		}
		
		$canHide = ( $item ) ? $item->canHide() : ( \IPS\Member::loggedIn()->group['g_hide_own_posts'] == '1' or \in_array( 'IPS\cms\Records' . $database->id, explode( ',', \IPS\Member::loggedIn()->group['g_hide_own_posts'] ) ) );
		if ( static::modPermission( 'hide', NULL, $container ) or $canHide )
		{
			$options['hide'] = 'create_record_hidden';
			$toggles['hide'] = array( 'create_record_hidden' );
			
			if ( $item AND $item->record_approved === -1 )
			{
				$values[] = 'hide';
			}
		}
			
		if ( static::modPermission( 'feature', NULL, $container ) )
		{
			$options['feature'] = 'create_record_featured';
			$toggles['feature'] = array( 'create_record_featured' );

			if ( $item AND $item->record_featured === 1 )
			{
				$values[] = 'feature';
			}
		}
		
		if ( \IPS\Member::loggedIn()->modPermission('can_content_edit_meta_tags') )
		{
			$formElements['record_meta_keywords'] = new \IPS\Helpers\Form\TextArea( 'record_meta_keywords', $item ? $item->record_meta_keywords : '', FALSE );
			$formElements['record_meta_description'] = new \IPS\Helpers\Form\TextArea( 'record_meta_description', $item ? $item->record_meta_description : '', FALSE );
		}
		
		if ( \count( $options ) or \count( $toggles ) )
		{
			$formElements['create_record_state'] = new \IPS\Helpers\Form\CheckboxSet( 'create_record_state', $values, FALSE, array(
					'options' 	=> $options,
					'toggles'	=> $toggles,
					'multiple'	=> TRUE
			) );
		}

		return $formElements;
	}

	/**
	 * Total item \count(including children)
	 *
	 * @param	\IPS\Node\Model	$container			The container
	 * @param	bool			$includeItems		If TRUE, items will be included (this should usually be true)
	 * @param	bool			$includeComments	If TRUE, comments will be included
	 * @param	bool			$includeReviews		If TRUE, reviews will be included
	 * @param	int				$depth				Used to keep track of current depth to avoid going too deep
	 * @return	int|NULL|string	When depth exceeds 10, will return "NULL" and initial call will return something like "100+"
	 * @note	This method may return something like "100+" if it has lots of children to avoid exahusting memory. It is intended only for display use
	 * @note	This method includes counts of hidden and unapproved content items as well
	 */
	public static function contentCount( \IPS\Node\Model $container, $includeItems=TRUE, $includeComments=FALSE, $includeReviews=FALSE, $depth=0 )
	{
		/* Are we in too deep? */
		if ( $depth > 10 )
		{
			return '+';
		}

		$count = $container->_items;

		if ( static::canViewHiddenItems( NULL, $container ) )
		{
			$count += $container->_unapprovedItems;
		}

		if ( static::canViewFutureItems( NULL, $container ) )
		{
			$count += $container->_futureItems;
		}

		if ( $includeComments )
		{
			$count += $container->record_comments;
		}

		/* Add Children */
		$childDepth	= $depth++;
		foreach ( $container->children() as $child )
		{
			$toAdd = static::contentCount( $child, $includeItems, $includeComments, $includeReviews, $childDepth );
			if ( \is_string( $toAdd ) )
			{
				return $count . '+';
			}
			else
			{
				$count += $toAdd;
			}

		}
		return $count;
	}

	/**
	 * [brief] Display title
	 */
	protected $displayTitle = NULL;

	/**
	 * [brief] Display content
	 */
	protected $displayContent = NULL;

	/**
	 * [brief] Record page
	 */
	protected $recordPage = NULL;

	/**
	 * [brief] Custom Display Fields
	 */
	protected $customDisplayFields = array();
	
	/**
	 * [brief] Custom Fields Database Values
	 */
	protected $customValueFields = NULL;
	
	/**
	 * Process create/edit form
	 *
	 * @param	array				$values	Values from form
	 * @return	void
	 */
	public function processForm( $values )
	{
		$isNew = $this->_new;
		$fieldsClass  = 'IPS\cms\Fields' . static::$customDatabaseId;
		$database     = \IPS\cms\Databases::load( static::$customDatabaseId );
		$categoryClass = 'IPS\cms\Categories' . static::$customDatabaseId;
		$container    = ( ! isset( $values['content_record_form_container'] ) ? $categoryClass::load( $this->category_id ) : $values['content_record_form_container'] );
		$autoSaveKeys = [];
		$imageUploads = [];

		/* Store a revision */
		if ( $database->revisions AND !$isNew )
		{
			$revision = new \IPS\cms\Records\Revisions;
			$revision->database_id = static::$customDatabaseId;
			$revision->record_id   = $this->_id;
			$revision->data        = $this->fieldValues( TRUE );

			$revision->save();
		}

		if ( isset( \IPS\Request::i()->postKey ) )
		{
			$this->post_key = \IPS\Request::i()->postKey;
		}

		if ( $isNew )
		{
			/* Peanut Butter Registering */
			if ( !\IPS\Member::loggedIn()->member_id and $container and !$container->can( 'add', \IPS\Member::loggedIn(), FALSE ) )
			{
				$this->record_approved = -3;
			}
			else
			{
				$this->record_approved = static::moderateNewItems( \IPS\Member::loggedIn(), $container ) ? 0 : 1;
			}
		}

		/* Moderator actions */
		if ( isset( $values['create_record_state'] ) )
		{
			if ( \in_array( 'lock', $values['create_record_state'] ) )
			{
				$this->record_locked = 1;
			}
			else
			{
				$this->record_locked = 0;
			}
	
			if ( \in_array( 'hide', $values['create_record_state'] ) )
			{
				$this->record_approved = -1;
			}
			else if  ( $this->record_approved !== 0 )
			{
				$this->record_approved = 1;
			}
	
			if ( \in_array( 'pin', $values['create_record_state'] ) )
			{
				$this->record_pinned = 1;
			}
			else
			{
				$this->record_pinned = 0;
			}
	
			if ( \in_array( 'feature', $values['create_record_state'] ) )
			{
				$this->record_featured = 1;
			}
			else
			{
				$this->record_featured = 0;
			}
		}
	
		/* Dates */
		if ( isset( $values['record_expiry_date'] ) and $values['record_expiry_date'] )
		{
			if ( $values['record_expiry_date'] === -1 )
			{
				$this->record_expiry_date = 0;
			}
			else
			{
				$this->record_expiry_date = $values['record_expiry_date']->getTimestamp();
			}
		}
		if ( isset( $values['record_comment_cutoff'] ) and $values['record_comment_cutoff'] )
		{
			if ( $values['record_comment_cutoff'] === -1 )
			{
				$this->record_comment_cutoff = 0;
			}
			else
			{
				$this->record_comment_cutoff = $values['record_comment_cutoff']->getTimestamp();
			}
		}

		/* Edit stuff */
		if ( !$isNew )
		{
			if ( isset( $values['record_edit_reason'] ) )
			{
				$this->record_edit_reason = $values['record_edit_reason'];
			}

			$this->record_edit_time        = time();
			$this->record_edit_member_id   = \IPS\Member::loggedIn()->member_id;
			$this->record_edit_member_name = \IPS\Member::loggedIn()->name;

			if ( isset( $values['record_edit_show'] ) )
			{
				$this->record_edit_show = \IPS\Member::loggedIn()->group['g_append_edit'] ? $values['record_edit_show'] : TRUE;
			}
		}

		/* Record image */
		if ( array_key_exists( 'record_image', $values ) )
		{			
			if ( $values['record_image'] === NULL )
			{			
				if ( $this->record_image )
				{
					try
					{
						\IPS\File::get( 'cms_Records', $this->record_image )->delete();
					}
					catch ( \Exception $e ) { }
				}
				if ( $this->record_image_thumb )
				{
					try
					{
						\IPS\File::get( 'cms_Records', $this->record_image_thumb )->delete();
					}
					catch ( \Exception $e ) { }
				}
					
				$this->record_image = NULL;
				$this->record_image_thumb = NULL;
			}
			else
			{
				$imageUploads[] = $values['record_image'];
				$fixedFieldSettings = static::database()->fixed_field_settings;

				if ( isset( $fixedFieldSettings['record_image']['thumb_dims'] ) )
				{
					if ( $this->record_image_thumb )
					{
						try
						{
							\IPS\File::get( 'cms_Records', $this->record_image_thumb )->delete();
						}
						catch ( \Exception $e ) { }
					}
					
					$thumb = $values['record_image']->thumbnail( 'cms_Records', $fixedFieldSettings['record_image']['thumb_dims'][0], $fixedFieldSettings['record_image']['thumb_dims'][1] );
				}
				else
				{
					$thumb = $values['record_image'];
				}

				$this->record_image       = (string)$values['record_image'];
				$this->record_image_thumb = (string)$thumb;
			}
		}
		
		/* Should we just lock this? */
		if ( ( isset( $values['record_allow_comments'] ) AND ! $values['record_allow_comments'] ) OR ( $this->record_comment_cutoff > $this->record_publish_date ) )
		{
			$this->record_locked = 1;
		}
		
		if ( \IPS\Member::loggedIn()->modPermission('can_content_edit_meta_tags') )
		{
			foreach( array( 'record_meta_keywords', 'record_meta_description' ) as $k )
			{
				if ( isset( $values[ $k ] ) )
				{
					$this->$k = $values[ $k ];
				}
			}
		}

		/* Custom fields */
		$customValues = array();
		$afterEditNotificationsExclude = [ 'quotes' => [], 'mentions' => [] ];
	
		foreach( $values as $k => $v )
		{
			if ( mb_substr( $k, 0, 14 ) === 'content_field_' )
			{
				$customValues[ $k ] = $v;
			}
		}

		$fieldObjects = $fieldsClass::data( NULL, $container );
		
		if ( static::$customFields === NULL )
		{
			static::$customFields = $fieldsClass::fields( $customValues, ( $isNew ? 'add' : 'edit' ), $container, 0, ( $isNew ? NULL : $this ) );
		}
		
		$seen = [];
		
		foreach( static::$customFields as $key => $field )
		{
			$fieldId = $key;
			$seen[] = $fieldId;
			$key = 'field_' . $fieldId;
			
			if ( !$isNew )
			{
				$afterEditNotificationsExclude = array_merge_recursive( static::_getQuoteAndMentionIdsFromContent( $this->$key ) );
			}
			
			if ( isset( $customValues[ $field->name ] ) and \get_class( $field ) == 'IPS\Helpers\Form\Upload' )
			{
				if ( \is_array( $customValues[ $field->name ] ) )
				{
					$items = array();
					foreach( $customValues[ $field->name ] as $obj )
					{
						$imageUploads[] = $obj;
						$items[] = (string) $obj;
					}
					$this->$key = implode( ',', $items );
				}
				else
				{
					$imageUploads[] = $customValues[ $field->name ];
					$this->$key = (string) $customValues[ $field->name ];
				}
			}
			/* If we're using decimals, then the database field is set to DECIMALS, so we cannot using stringValue() */
			else if ( isset( $customValues[ $field->name ] ) and \get_class( $field ) == 'IPS\Helpers\Form\Number' and ( isset( $field->options['decimals'] ) and $field->options['decimals'] > 0 ) )
			{
				$this->$key = ( $field->value === '' ) ? NULL : $field->value;
			}
			else
			{
				if ( \get_class( $field ) == 'IPS\Helpers\Form\Editor' )
				{
					$autoSaveKeys[] = $isNew ? "RecordField_new_{$fieldId}" : [ $this->_id, $fieldId, static::$customDatabaseId ];
				}
				
				$this->$key = $field::stringValue( isset( $customValues[ $field->name ] ) ? $customValues[ $field->name ] : NULL );
			}
		}

		/* Now set up defaults */
		if ( $isNew )
		{
			foreach ( $fieldObjects as $obj )
			{
				if ( !\in_array( $obj->id, $seen ) )
				{
					/* We've not got a value for this as the field is hidden from us, so let us add the default value here */
					$key        = 'field_' . $obj->id;
					$this->$key = $obj->default_value;
				}
			}
		}

		/* Other data */
		if ( $isNew OR $database->_comment_bump & \IPS\cms\Databases::BUMP_ON_EDIT )
		{
			$this->record_updated = time();
		}

		$this->record_allow_comments   = isset( $values['record_allow_comments'] ) ? $values['record_allow_comments'] : ( ! $this->record_locked );
		
		if ( isset( $values[ 'content_field_' . $database->field_title ] ) )
		{
			$this->record_dynamic_furl     = \IPS\Http\Url\Friendly::seoTitle( $values[ 'content_field_' . $database->field_title ] );
		}

		if ( isset( $values['record_static_furl_set'] ) and $values['record_static_furl_set'] and isset( $values['record_static_furl'] ) and $values['record_static_furl'] )
		{
			$newFurl = \IPS\Http\Url\Friendly::seoTitle( $values['record_static_furl'] );

			if ( $newFurl != $this->record_static_furl )
			{
				$this->storeUrl();
			}
			
			$this->record_static_furl = $newFurl;
		}
		else
		{
			if( $isNew )
			{
				$this->record_static_furl = NULL;
			}
			/* Only remove the custom set furl if we are editing, we have the fields set, and they are empty. Otherwise an admin may have set the furl and then changed the author
				to a user who does not have permission to set the furl in which case we don't want it being reset */
			elseif ( isset( $values['record_static_furl_set'] ) and ( !$values['record_static_furl_set'] OR !isset( $values['record_static_furl'] ) OR !$values['record_static_furl'] ) )
			{
				$this->record_static_furl = NULL;
			}
		}
		
		$sendFilterNotifications = $this->checkProfanityFilters( FALSE, !$isNew, NULL, NULL, 'cms_Records' . static::$customDatabaseId, $autoSaveKeys, $imageUploads );
		
		if ( $isNew )
		{
			/* Set the author ID on 'new' only */
			$this->member_id = (int) ( static::$createWithMember ? static::$createWithMember->member_id : \IPS\Member::loggedIn()->member_id );
		}
		elseif ( !$sendFilterNotifications )
		{
			$this->sendQuoteAndMentionNotifications( array_unique( array_merge( $afterEditNotificationsExclude['quotes'], $afterEditNotificationsExclude['mentions'] ) ) );
		}
		
		if ( isset( $values['content_record_form_container'] ) )
		{
			$this->category_id = ( $values['content_record_form_container'] === 0 ) ? 0 : $values['content_record_form_container']->id;
		}

		$idColumn = static::$databaseColumnId;
		if ( ! $this->$idColumn )
		{
			$this->save();
		}

		/* Check for relational fields and claim attachments once we have an ID */
		foreach( $fieldObjects as $id => $row )
		{
			if ( $row->can( ( $isNew ? 'add' : 'edit' ) ) and $row->type == 'Editor' )
			{
				\IPS\File::claimAttachments( 'RecordField_' . ( $isNew ? 'new' : $this->_id ) . '_' . $row->id, $this->primary_id_field, $id, static::$customDatabaseId );
			}
			
			if ( $row->can( ( $isNew ? 'add' : 'edit' ) ) and $row->type == 'Upload' )
			{
				
				if ( $row->extra['type'] == 'image' and isset( $row->extra['thumbsize'] ) )
				{
					$dims = $row->extra['thumbsize'];
					$field = 'field_' . $row->id;
					$extra = $row->extra;
					$thumbs = iterator_to_array( \IPS\Db::i()->select( '*', 'cms_database_fields_thumbnails', array( array( 'thumb_field_id=?', $row->id ) ) )->setKeyField('thumb_original_location')->setValueField('thumb_location') );
					
					if ( $this->$field  )
					{
						foreach( explode( ',', $this->$field ) as $img )
						{
							try
							{
								$original = \IPS\File::get( 'cms_Records', $img );
								
								try
								{								
									$thumb = $original->thumbnail( 'cms_Records', $dims[0], $dims[1] );
									
									if ( isset( $thumbs[ (string) $original ] ) )
									{
										\IPS\Db::i()->delete( 'cms_database_fields_thumbnails', array( array( 'thumb_original_location=? and thumb_field_id=? and thumb_record_id=?', (string) $original, $row->id, $this->primary_id_field ) ) );
										
										try
										{
											\IPS\File::get( 'cms_Records', $thumbs[ (string) $original ] )->delete();
										}
										catch ( \Exception $e ) { }
									}
									
									\IPS\Db::i()->insert( 'cms_database_fields_thumbnails', array(
										'thumb_original_location' => (string) $original,
										'thumb_location'		  => (string) $thumb,
										'thumb_field_id'		  => $row->id,
										'thumb_database_id'		  => static::$customDatabaseId,
										'thumb_record_id'		  => $this->primary_id_field
									) );
								}
								catch ( \Exception $e ) { }
							}
							catch ( \Exception $e ) { }
						}
				
						/* Remove any thumbnails if the original has been removed */
						$orphans = iterator_to_array( \IPS\Db::i()->select( '*', 'cms_database_fields_thumbnails', array( array( 'thumb_record_id=?', $this->primary_id_field ), array( 'thumb_field_id=?', $row->id ), array( \IPS\Db::i()->in( 'thumb_original_location', explode( ',', $this->$field ), TRUE ) ) ) ) );
						
						if ( \count( $orphans ) )
						{
							foreach( $orphans as $thumb )
							{
								try
								{
									\IPS\File::get( 'cms_Records', $thumb['thumb_location'] )->delete();
								}
								catch ( \Exception $e ) { }
							}
							
							\IPS\Db::i()->delete( 'cms_database_fields_thumbnails', array( array( 'thumb_record_id=?', $this->primary_id_field ), array( 'thumb_field_id=?', $row->id ), array( \IPS\Db::i()->in( 'thumb_original_location', explode( ',', $this->$field ), TRUE ) ) ) );
						}
					}
				}
			}

			if ( $row->can( ( $isNew ? 'add' : 'edit' ) ) and $row->type == 'Item' )
			{
				$field = $this->processItemFieldData( $row );
			}
		}

		parent::processForm( $values );
	}

	/**
	 * Stores the URL so when its changed, the old can 301 to the new location
	 *
	 * @return void
	 */
	public function storeUrl()
	{
		if ( $this->record_static_furl )
		{
			\IPS\Db::i()->insert( 'cms_url_store', array(
				'store_path'       => $this->record_static_furl,
			    'store_current_id' => $this->_id,
			    'store_type'       => 'record'
			) );
		}
	}

	/**
	 * Stats for table view
	 *
	 * @param	bool	$includeFirstCommentInCommentCount	Determines whether the first comment should be inlcluded in the comment \count(e.g. For "posts", use TRUE. For "replies", use FALSE)
	 * @return	array
	 */
	public function stats( $includeFirstCommentInCommentCount=TRUE )
	{
		$return = array();

		if ( static::$commentClass and static::database()->options['comments'] )
		{
			$return['comments'] = (int) $this->mapped('num_comments');
		}

		if ( \IPS\IPS::classUsesTrait( $this, 'IPS\Content\ViewUpdates' ) )
		{
			$return['num_views'] = (int) $this->mapped('views');
		}

		return $return;
	}

	/**
	 * Get URL
	 *
	 * @param	string|NULL		$action		Action
	 * @return	\IPS\Http\Url
	 * @throws 	\LogicException
	 */
	public function url( $action=NULL )
	{
		if( $action == 'getPrefComment' )
		{
			$pref = \IPS\Member::loggedIn()->linkPref() ?: \IPS\Settings::i()->link_default;

			switch( $pref )
			{
				case 'unread':
					$action = \IPS\Member::loggedIn()->member_id ? 'getNewComment' : NULL;
					break;

				case 'last':
					$action = 'getLastComment';
					break;

				default:
					$action = NULL;
					break;
			}
		}
		elseif( !\IPS\Member::loggedIn()->member_id AND $action == 'getNewComment' )
		{
			$action = NULL;
		}

		if ( ! $this->recordPage )
		{
			/* If we're coming through the database controller embedded in a page, $currentPage will be set. If we're coming in via elsewhere, we need to fetch the page */
			try
			{
				$this->recordPage = \IPS\cms\Pages\Page::loadByDatabaseId( static::$customDatabaseId );
			}
			catch( \OutOfRangeException $ex )
			{
				if ( \IPS\cms\Pages\Page::$currentPage )
				{
					$this->recordPage = \IPS\cms\Pages\Page::$currentPage;
				}
				else
				{
					throw new \LogicException;
				}
			}
		}

		if ( $this->recordPage )
		{
			$pagePath   = $this->recordPage->full_path;
			$class		= '\IPS\cms\Categories' . static::$customDatabaseId;
			$catPath    = $class::load( $this->category_id )->full_path;
			$recordSlug = ! $this->record_static_furl ? $this->record_dynamic_furl . '-r' . $this->primary_id_field : $this->record_static_furl;

			if ( static::database()->use_categories )
			{
				$url = \IPS\Http\Url::internal( "app=cms&module=pages&controller=page&path=" . $pagePath . '/' . $catPath . '/' . $recordSlug, 'front', 'content_page_path', $recordSlug );
			}
			else
			{
				$url = \IPS\Http\Url::internal( "app=cms&module=pages&controller=page&path=" . $pagePath . '/' . $recordSlug, 'front', 'content_page_path', $recordSlug );
			}
		}

		if ( $action )
		{
			$url = $url->setQueryString( 'do', $action );
			$url = $url->setQueryString( 'd' , static::database()->id );
			$url = $url->setQueryString( 'id', $this->primary_id_field );
		}

		return $url;
	}
	
	/**
	 * Columns needed to query for search result / stream view
	 *
	 * @return	array
	 */
	public static function basicDataColumns()
	{
		$return = parent::basicDataColumns();
		$return[] = 'category_id';
		$return[] = 'record_static_furl';
		$return[] = 'record_dynamic_furl';
		$return[] = 'record_image';
		return $return;
	}
	
	/**
	 * Query to get additional data for search result / stream view
	 *
	 * @param	array	$items	Item data (will be an array containing values from basicDataColumns())
	 * @return	array
	 */
	public static function searchResultExtraData( $items )
	{
		$categoryIds = array();
		
		foreach ( $items as $item )
		{
			if ( $item['category_id'] )
			{
				$categoryIds[ $item['category_id'] ] = $item['category_id'];
			}
		}
		
		if ( \count( $categoryIds ) )
		{
			$categoryPaths = iterator_to_array( \IPS\Db::i()->select( array( 'category_id', 'category_full_path' ), 'cms_database_categories', \IPS\Db::i()->in( 'category_id', $categoryIds ) )->setKeyField('category_id')->setValueField('category_full_path') );
			
			$return = array();
			foreach ( $items as $item )
			{
				if ( $item['category_id'] )
				{
					$return[ $item['primary_id_field'] ] = $categoryPaths[ $item['category_id'] ];
				}
			}
			return $return;
		}
		
		return array();
	}

	/**
	 * Get snippet HTML for search result display
	 *
	 * @param	array		$indexData		Data from the search index
	 * @param	array		$authorData		Basic data about the author. Only includes columns returned by \IPS\Member::columnsForPhoto()
	 * @param	array		$itemData		Basic data about the item. Only includes columns returned by item::basicDataColumns()
	 * @param	array|NULL	$containerData	Basic data about the container. Only includes columns returned by container::basicDataColumns()
	 * @param	array		$reputationData	Array of people who have given reputation and the reputation they gave
	 * @param	int|NULL	$reviewRating	If this is a review, the rating
	 * @param	string		$view			'expanded' or 'condensed'
	 * @return	callable
	 */
	public static function searchResultSnippet( array $indexData, array $authorData, array $itemData, ?array $containerData, array $reputationData, $reviewRating, $view )
	{
		/* We want the custom template only for expanded view or if we have a record image */

		if ( $view == 'expanded' OR ( $view == 'condensed' AND isset( $itemData['record_image'] ) ) )
		{
			$url = static::urlFromIndexData( $indexData, $itemData );
			return \IPS\Theme::i()->getTemplate( 'global', 'cms', 'front' )->recordResultSnippet( $indexData, $itemData, $url, $view == 'condensed' );
		}
		else
		{
			return NULL;
		}
	}
	
	/**
	 * Get URL from index data
	 *
	 * @param	array		$indexData		Data from the search index
	 * @param	array		$itemData		Basic data about the item. Only includes columns returned by item::basicDataColumns()
	 * @param	string|NULL	$action			Action
	 * @return	\IPS\Http\Url
	 */
	public static function urlFromIndexData( $indexData, $itemData, $action = NULL )
	{
		if( $action == 'getPrefComment' )
		{
			$pref = \IPS\Member::loggedIn()->linkPref() ?: \IPS\Settings::i()->link_default;

			switch( $pref )
			{
				case 'unread':
					$action = \IPS\Member::loggedIn()->member_id ? 'getNewComment' : NULL;
					break;

				case 'last':
					$action = 'getLastComment';
					break;

				default:
					$action = NULL;
					break;
			}
		}
		elseif( !\IPS\Member::loggedIn()->member_id AND $action == 'getNewComment' )
		{
			$action = NULL;
		}

		if ( static::$pagePath === NULL )
		{
			static::$pagePath = \IPS\Db::i()->select( array( 'page_full_path' ), 'cms_pages', array( 'page_id=?', static::database()->page_id ) )->first();
		}
		
		$recordSlug = !$itemData['record_static_furl'] ? $itemData['record_dynamic_furl']  . '-r' . $itemData['primary_id_field'] : $itemData['record_static_furl'];
		
		if ( static::database()->use_categories )
		{
			$url = \IPS\Http\Url::internal( "app=cms&module=pages&controller=page&path=" . static::$pagePath . '/' . $itemData['extra'] . '/' . $recordSlug, 'front', 'content_page_path', $recordSlug );
		}
		else
		{
			$url = \IPS\Http\Url::internal( "app=cms&module=pages&controller=page&path=" . static::$pagePath . '/' . $recordSlug, 'front', 'content_page_path', $recordSlug );
		}

		if( $action )
		{
			$url = $url->setQueryString( 'do', $action );
		}

		return $url;
	}

	/**
	 * Template helper method to fetch custom fields to display
	 *
	 * @param   string  $type       Type of display
	 * @return  array
	 */
	public function customFieldsForDisplay( $type='display' )
	{
		if ( ! isset( $this->customDisplayFields['all'][ $type ] ) )
		{
			$fieldsClass = '\IPS\cms\Fields' . static::$customDatabaseId;
			$this->customDisplayFields['all'][ $type ] = $fieldsClass::display( $this->fieldValues(), $type, $this->container(), 'key', $this );
		}

		return $this->customDisplayFields['all'][ $type ];
	}

	/**
	 * Display a custom field by its key
	 *
	 * @param mixed      $key       Key to fetch
	 * @param string     $type      Type of display to fetch
	 * @return mixed
	 */
	public function customFieldDisplayByKey( $key, $type='display' )
	{
		$fieldsClass = '\IPS\cms\Fields' . static::$customDatabaseId;

		if ( ! isset( $this->customDisplayFields[ $key ][ $type ] ) )
		{
			foreach ( $fieldsClass::roots( 'view' ) as $row )
			{
				if ( $row->key === $key )
				{
					$field = 'field_' . $row->id;
					$value = ( $this->$field !== '' AND $this->$field !== NULL ) ? $this->$field : $row->default_value;
					$this->customDisplayFields[ $key ][ $type ] = $row->formatForDisplay( $row->displayValue( $value ), $value, $type, $this );
				}
			}
		}

		/* Still nothing? */
		if ( ! isset( $this->customDisplayFields[ $key ][ $type ] ) )
		{
			$this->customDisplayFields[ $key ][ $type ] = NULL;
		}

		return $this->customDisplayFields[ $key ][ $type ];
	}

	/**
	 * Get custom field_x keys and values
	 *
	 * @param	boolean	$allData	All data (true) or just custom field data (false)
	 * @return	array
	 */
	public function fieldValues( $allData=FALSE )
	{
		$fields = array();
		
		foreach( $this->_data as $k => $v )
		{
			if ( $allData === TRUE OR mb_substr( $k, 0, 6 ) === 'field_')
			{
				$fields[ $k ] = $v;
			}
		}

		return $fields;
	}
	
	/**
	 * Returns the content images
	 *
	 * @param	int|null	$limit				Number of attachments to fetch, or NULL for all
	 * @param	bool		$ignorePermissions	If set to TRUE, permission to view the images will not be checked
	 * @return	array|NULL
	 * @throws	\BadMethodCallException
	 */
	public function contentImages( $limit = NULL, $ignorePermissions = FALSE )
	{
		$idColumn = static::$databaseColumnId;
		$attachments = array();
		
		/* Record image */
		if ( $this->record_image )
		{
			$attachments[] = array( 'cms_Records' => $this->record_image );
		}

		$internal = \IPS\Db::i()->select( 'attachment_id', 'core_attachments_map', array( '(location_key=? OR location_key=?) and id1=? and id3=?', 'cms_Records', 'cms_Records' . static::$customDatabaseId, $this->$idColumn, static::$customDatabaseId ) );
		
		/* Attachments */
		foreach( \IPS\Db::i()->select( '*', 'core_attachments', array( array( 'attach_id IN(?)', $internal ), array( 'attach_is_image=1' ) ), 'attach_id ASC', $limit ) as $row )
		{
			$attachments[] = array( 'core_Attachment' => $row['attach_location'] );
		}
			
		/* Any upload fields */
		$categoryClass = 'IPS\cms\Categories' . static::$customDatabaseId;
		$container = $categoryClass::load( $this->category_id );
		$fieldsClass  = 'IPS\cms\Fields' . static::$customDatabaseId;
		$fieldValues = $this->fieldValues();
		$customFields = $fieldsClass::fields( $fieldValues, $ignorePermissions ? NULL : 'edit', $container, 0, $this );

		foreach( $customFields as $key => $field )
		{
			$fieldName = mb_substr( $field->name, 8 );
			if ( \get_class( $field ) == 'IPS\Helpers\Form\Upload' )
			{
				if ( \is_array( $fieldValues[ $fieldName ] ) )
				{
					foreach( $fieldValues[ $fieldName ] as $fileName )
					{
						$obj = \IPS\File::get( 'cms_Records', $fileName );
						if ( $obj->isImage() )
						{
							$attachments[] = array( 'cms_Records' => $fileName );
						}
					}
				}
				else
				{
					$obj = \IPS\File::get( 'cms_Records', $fieldValues[ $fieldName ] );
					if ( $obj->isImage() )
					{
						$attachments[] = array( 'cms_Records' => $fieldValues[ $fieldName ] );
					}
				}
			}
		}
		
		return \count( $attachments ) ? \array_slice( $attachments, 0, $limit ) : NULL;
	}

	/**
	 * Get the post key or create one if one doesn't exist
	 *
	 * @return  string
	 */
	public function get__post_key()
	{
		return ! empty( $this->post_key ) ? $this->post_key : md5( mt_rand() );
	}

	/**
	 * Get the publish date
	 *
	 * @return	string
	 */
	public function get__publishDate()
	{
        return $this->record_publish_date ? $this->record_publish_date : $this->record_saved;
	}

	/**
	 * Get the record id
	 *
	 * @return	int
	 */
	public function get__id()
	{
		return $this->primary_id_field;
	}
	
	/**
	 * Get value from data store
	 *
	 * @param	mixed	$key	Key
	 * @return	mixed	Value from the datastore
	 */
	public function __get( $key )
	{
		$val = parent::__get( $key );
		
		if ( $val === NULL )
		{
			if ( mb_substr( $key, 0, 6 ) === 'field_' and ! preg_match( '/^[0-9]+?/', mb_substr( $key, 6 ) ) )
			{
				$realKey = mb_substr( $key, 6 );
				if ( $this->customValueFields === NULL )
				{
					$fieldsClass = '\IPS\cms\Fields' . static::$customDatabaseId;
					
					foreach ( $fieldsClass::roots( 'view' ) as $row )
					{
						$field = 'field_' . $row->id; 
						$this->customValueFields[ $row->key ] = array( 'id' => $row->id, 'content' => $this->$field );
					}
				}
				
				if ( isset( $this->customValueFields[ $realKey ] ) )
				{
					$val = $this->customValueFields[ $realKey ]['content'];
				} 
			}
		}
		
		return $val;
	}
	
	/**
	 * Set value in data store
	 *
	 * @see		\IPS\Patterns\ActiveRecord::save
	 * @param	mixed	$key	Key
	 * @param	mixed	$value	Value
	 * @return	void
	 */
	public function __set( $key, $value )
	{
		if ( $key == 'field_' . static::database()->field_title )
		{
			$this->displayTitle = NULL;
		}
		if ( $key == 'field_' . static::database()->field_content )
		{
			$this->displayContent = NULL;
		}
		
		if ( mb_substr( $key, 0, 6 ) === 'field_' )
		{
			$realKey = mb_substr( $key, 6 );
			
			if ( preg_match( '/^[0-9]+?/', $realKey ) )
			{
				/* Wipe any stored values */
				$this->customValueFields = NULL;
			}
			else
			{
				/* This is setting by key */
				if ( $this->customValueFields === NULL )
				{
					$fieldsClass = '\IPS\cms\Fields' . static::$customDatabaseId;
					
					foreach ( $fieldsClass::roots( 'view' ) as $row )
					{
						$field = 'field_' . $row->id; 
						$this->customValueFields[ $row->key ] = array( 'id' => $row->id, 'content' => $this->$field );
					}
				}
			
				$field = 'field_' . $this->customValueFields[ $realKey ]['id'];
				$this->$field = $value;
				
				$this->customValueFields[ $realKey ]['content'] = $value;
				
				/* Rest key for the parent::__set() */
				$key = $field;
			}
		}
		
		parent::__set( $key, $value );
	}

	/**
	 * Get the record title for display
	 *
	 * @return	string
	 */
	public function get__title()
	{
		$field = 'field_' . static::database()->field_title;

		try
		{
			if ( ! $this->displayTitle )
			{
				$class = '\IPS\cms\Fields' .  static::database()->id;
				$this->displayTitle = $class::load( static::database()->field_title )->displayValue( $this->$field );
			}

			return $this->displayTitle;
		}
		catch( \Exception $e )
		{
			return $this->$field;
		}
	}
	
	/**
	 * Get the record content for display
	 *
	 * @return	string
	 */
	public function get__content()
	{
		$field = 'field_' . static::database()->field_content;

		try
		{
			if ( ! $this->displayContent )
			{
				$class = '\IPS\cms\Fields' .  static::database()->id;

				$this->displayContent = $class::load( static::database()->field_content )->displayValue( $this->$field );
			}

			return $this->displayContent;
		}
		catch( \Exception $e )
		{
			return $this->$field;
		}
	}
	
	/**
	 * Return forum sync on or off
	 *
	 * @return	int
	 */
	public function get__forum_record()
	{
		if ( $this->container()->forum_override and static::database()->use_categories )
		{
			return $this->container()->forum_record;
		}
		
		return static::database()->forum_record;
	}
	
	/**
	 * Return forum post on or off
	 *
	 * @return	int
	 */
	public function get__forum_comments()
	{
		if ( $this->container()->forum_override and static::database()->use_categories )
		{
			return $this->container()->forum_comments;
		}
		
		return static::database()->forum_comments;
	}
	
	/**
	 * Return forum sync delete
	 *
	 * @return	int
	 */
	public function get__forum_delete()
	{
		if ( $this->container()->forum_override and static::database()->use_categories )
		{
			return $this->container()->forum_delete;
		}
		
		return static::database()->forum_delete;
	}
	
	/**
	 * Return forum sync forum
	 *
	 * @return	int
	 * @throws  \UnderflowException
	 */
	public function get__forum_forum(): int
	{
		if ( $this->container()->forum_override and static::database()->use_categories )
		{
			if( !$this->container()->forum_forum )
			{
				throw new \UnderflowException('forum_sync_disabled');
			}

			return $this->container()->forum_forum;
		}
		
		return static::database()->forum_forum;
	}
	
	/**
	 * Return forum sync prefix
	 *
	 * @return	int
	 */
	public function get__forum_prefix()
	{
		if ( $this->container()->forum_override and static::database()->use_categories )
		{
			return $this->container()->forum_prefix;
		}
	
		return static::database()->forum_prefix;
	}
	
	/**
	 * Return forum sync suffix
	 *
	 * @return	int
	 */
	public function get__forum_suffix()
	{
		if ( $this->container()->forum_override and static::database()->use_categories )
		{
			return $this->container()->forum_suffix;
		}
	
		return static::database()->forum_suffix;
	}

	/**
	 * Return record image thumb
	 *
	 * @return	int
	 */
	public function get__record_image_thumb()
	{
		return $this->record_image_thumb ?: $this->record_image;
	}

	/**
	 * Get edit line
	 *
	 * @return	string|NULL
	 */
	public function editLine()
	{
		if ( $this->record_edit_time and ( $this->record_edit_show or \IPS\Member::loggedIn()->modPermission('can_view_editlog') ) and \IPS\Settings::i()->edit_log )
		{
			return \IPS\cms\Theme::i()->getTemplate( static::database()->template_display, 'cms', 'database' )->recordEditLine( $this );
		}
		return NULL;
	}

	/**
	 * Get mapped value
	 *
	 * @param	string	$key	date,content,ip_address,first
	 * @return	mixed
	 */
	public function mapped( $key )
	{
		if ( $key === 'title' )
		{
			return $this->_title;
		}
		else if ( $key === 'content' )
		{
			return $this->_content;
		}
		else if( $key === 'date')
		{
			return $this->_publishDate;
		}
		
		if ( isset( static::$databaseColumnMap[ $key ] ) )
		{
			$field = static::$databaseColumnMap[ $key ];
				
			if ( \is_array( $field ) )
			{
				$field = array_pop( $field );
			}
				
			return $this->$field;
		}
		return NULL;
	}
	
	/**
	 * Save
	 *
	 * @return void
	 */
	public function save()
	{
		$new = $this->_new;
			
		if ( $new OR static::database()->_comment_bump & \IPS\cms\Databases::BUMP_ON_EDIT )
		{
			$member = \IPS\Member::load( $this->member_id );
	
			/* Set last comment as record so that category listing is correct */
			if ( $this->record_saved > $this->record_last_comment )
			{
				$this->record_last_comment = $this->record_saved;
			}

			if ( $new )
			{
				$this->record_last_comment_by   = $this->member_id;
				$this->record_last_comment_name = $member->name;
			}
		}
	
		parent::save();

		if ( $this->category_id )
		{
			unset( static::$multitons[ $this->primary_id_field ] );
			
			foreach( static::$multitonMap as $fieldKey => $data )
			{
				foreach( $data as $fieldValue => $primaryId )
				{
					if( $primaryId == $this->primary_id_field )
					{
						unset( static::$multitonMap[ $fieldKey ][ $fieldValue ] );
					}
				}
			}
			
            $class = '\IPS\cms\Categories' . static::$customDatabaseId;
            $category = $class::load( $this->category_id );
            $category->setLastComment();
			$category->setLastReview();
            $category->save();
        }
	}
	
	/**
	 * Resync last comment
	 *
	 * @return	void
	 */
	public function resyncLastComment()
	{
		if ( $this->useForumComments() )
		{
			if ( $topic = $this->topic( FALSE ) )
			{
				$topic->resyncLastComment();
			}
		}
		
		parent::resyncLastComment();
	}
	
	/**
	 * Utility method to reset the last commenter of a record
	 *
	 * @param   boolean     $setCategory    Check and set the last commenter for a category
	 * @return void
	 */
	public function resetLastComment( $setCategory=false )
	{
		$comment = $this->comments( 1, 0, 'date', 'desc', NULL, FALSE );

		if ( $comment )
		{
			$this->record_last_comment      = $comment->mapped('date');
			$this->record_last_comment_by   = $comment->author()->member_id;
			$this->record_last_comment_name = $comment->author()->name;
			$this->record_last_comment_anon	= $comment->isAnonymous();
			$this->save();

			if ( $setCategory and $this->category_id )
			{
				$class = '\IPS\cms\Categories' . static::$customDatabaseId;
				$class::load( $this->category_id )->setLastComment( NULL );
				$class::load( $this->category_id )->save();
			}
		}
	}

	/**
	 * Resync the comments/unapproved comment counts
	 *
	 * @param	string	$commentClass	Override comment class to use
	 * @return void
	 */
	public function resyncCommentCounts( $commentClass=NULL )
	{
		if ( $this->useForumComments() )
		{
			$topic = $this->topic( FALSE );

			if ( $topic )
			{
				$this->record_comments = $topic->posts - 1;
				$this->record_comments_queued = $topic->topic_queuedposts;
				$this->record_comments_hidden = $topic->topic_hiddenposts;
				$this->save();
			}
		}
		else
		{
			parent::resyncCommentCounts( $commentClass );
		}
	}
	
	/**
	 * Are comments supported by this class?
	 *
	 * @param	\IPS\Member|NULL		$member		The member to check for or NULL to not check permission
	 * @param	\IPS\Node\Model|NULL	$container	The container to check in, or NULL for any container
	 * @return	bool
	 */
	public static function supportsComments( \IPS\Member $member = NULL, \IPS\Node\Model $container = NULL )
	{
		return parent::supportsComments() and static::database()->options['comments'];
	}
	
	/**
	 * Are reviews supported by this class?
	 *
	 * @param	\IPS\Member|NULL		$member		The member to check for or NULL to not check permission
	 * @param	\IPS\Node\Model|NULL	$container	The container to check in, or NULL for any container
	 * @return	bool
	 */
	public static function supportsReviews( \IPS\Member $member = NULL, \IPS\Node\Model $container = NULL )
	{
		return parent::supportsReviews() and static::database()->options['reviews'];
	}
	
	/**
	 * Ensure there aren't any collisions with page slugs
	 *
	 * @param   string  $path   Path to check
	 * @return  boolean
	 */
	static public function isFurlCollision( $slug )
	{
		try
		{
			\IPS\Db::i()->select( 'page_id', 'cms_pages', array( 'page_seo_name=?', \IPS\Http\Url\Friendly::seoTitle( $slug ) ) )->first();
			
			return TRUE;
		}
		catch( \UnderflowException $e )
		{
			return FALSE;
		}
	}
	
	/* !Relational Fields */
	/**
	 * Returns an array of Content items that have been linked to from another database.
	 * I think at least. The concept makes perfect sense until I think about it too hard.
	 *
	 * @note The returned array is in the format of {field_id} => array( object, object... )
	 *
	 * @return FALSE|array
	 */
	public function getReciprocalItems()
	{
		/* Check to see if any fields are linking to this database in this easy to use method wot I writted myself */
		if ( \IPS\cms\Databases::hasReciprocalLinking( static::database()->_id ) )
		{
			$return = array();
			/* Oh that's just lovely then. Lets be a good fellow and fetch the items then! */
			foreach( \IPS\Db::i()->select( '*', 'cms_database_fields_reciprocal_map', array( 'map_foreign_database_id=? and map_foreign_item_id=?', static::database()->_id, $this->primary_id_field ) ) as $record )
			{
				try
				{
					$recordClass = 'IPS\cms\Records' . $record['map_origin_database_id'];
                    $linkedRecord = $recordClass::load( $record['map_origin_item_id'] );
                    if( $linkedRecord->canView() )
                    {
                        $return[ $record['map_field_id'] ][] = $linkedRecord;
                    }
				}
				catch ( \Exception $ex ) { }
			}
			
			/* Has something gone all kinds of wonky? */
			if ( ! \count( $return ) )
			{
				return FALSE;
			}

			return $return;
		}

		return FALSE;
	}
	
	/* !IP.Board Integration */
	
	/**
	 * Use forum for comments
	 *
	 * @return boolean
	 */
	public function useForumComments()
	{
		try
		{
			return $this->_forum_record and $this->_forum_comments and $this->record_topicid and \IPS\Application::appIsEnabled('forums');
		}
		catch( \Exception $e)
		{
			return FALSE;
		}

	}
	
	/**
	 * Do Moderator Action
	 *
	 * @param	string				$action	The action
	 * @param	\IPS\Member|NULL	$member	The member doing the action (NULL for currently logged in member)
	 * @param	string|NULL			$reason	Reason (for hides)
	 * @param	bool				$immediately	Delete Immediately
	 * @return	void
	 * @throws	\OutOfRangeException|\InvalidArgumentException|\RuntimeException
	 */
	public function modAction( $action, \IPS\Member $member = NULL, $reason = NULL, $immediately = FALSE )
	{
		parent::modAction( $action, $member, $reason, $immediately );
		
		if ( $this->useForumComments() and ( $action === 'lock' or $action === 'unlock' ) )
		{
			if ( $topic = $this->topic() )
			{
				$topic->state = ( $action === 'lock' ? 'closed' : 'open' );
				$topic->save();	
			}
		}
	}

	/**
	 * Move
	 *
	 * @param	\IPS\Node\Model	$container	Container to move to
	 * @param	bool			$keepLink	If TRUE, will keep a link in the source
	 * @return	void
	 */
	public function move( \IPS\Node\Model $container, $keepLink=FALSE )
	{
		parent::move( $container, $keepLink );

		if( $this->record_static_furl )
		{
			$this->storeUrl();
		}
	}

	/**
	 * Get comments
	 *
	 * @param	int|NULL			$limit					The number to get (NULL to use static::getCommentsPerPage())
	 * @param	int|NULL			$offset					The number to start at (NULL to examine \IPS\Request::i()->page)
	 * @param	string				$order					The column to order by
	 * @param	string				$orderDirection			"asc" or "desc"
	 * @param	\IPS\Member|NULL	$member					If specified, will only get comments by that member
	 * @param	bool|NULL			$includeHiddenComments	Include hidden comments or not? NULL to base of currently logged in member's permissions
	 * @param	\IPS\DateTime|NULL	$cutoff					If an \IPS\DateTime object is provided, only comments posted AFTER that date will be included
	 * @param	mixed				$extraWhereClause	Additional where clause(s) (see \IPS\Db::build for details)
	 * @param	bool|NULL			$bypassCache			Used in cases where comments may have already been loaded i.e. splitting comments on an item.
	 * @param	bool				$includeDeleted			Include deleted content.
	 * @param	bool|NULL			$canViewWarn			TRUE to include Warning information, NULL to determine automatically based on moderator permissions.
	 * @return	array|NULL|\IPS\Content\Comment	If $limit is 1, will return \IPS\Content\Comment or NULL for no results. For any other number, will return an array.
	 */
	public function comments( $limit=NULL, $offset=NULL, $order='date', $orderDirection='asc', $member=NULL, $includeHiddenComments=NULL, $cutoff=NULL, $extraWhereClause=NULL, $bypassCache=FALSE, $includeDeleted=FALSE, $canViewWarn=NULL )
	{
		if ( $this->useForumComments() )
		{
			$recordClass = 'IPS\cms\Records\RecordsTopicSync' . static::$customDatabaseId;

			/* If we are pulling in ASC order we want to jump up by 1 to account for the first post, which is not a comment */
			if( mb_strtolower( $orderDirection ) == 'asc' )
			{
				$_pageValue = ( \IPS\Request::i()->page ? \intval( \IPS\Request::i()->page ) : 1 );

				if( $_pageValue < 1 )
				{
					$_pageValue = 1;
				}
				
				$offset = ( ( $_pageValue - 1 ) * static::getCommentsPerPage() ) + 1;
			}
			
			return $recordClass::load( $this->record_topicid )->comments( $limit, $offset, $order, $orderDirection, $member, $includeHiddenComments, $cutoff, $extraWhereClause, $bypassCache, $includeDeleted, $canViewWarn );
		}
		else
		{
			/* Because this is a static property, it may have been overridden by a block on the same page. */
			if ( \get_called_class() != 'IPS\cms\Records\RecordsTopicSync' . static::$customDatabaseId )
			{
				static::$commentClass = 'IPS\cms\Records\Comment' . static::$customDatabaseId;
			}
		}

		$where = NULL;
		if( static::$commentClass != 'IPS\cms\Records\CommentTopicSync' . static::$customDatabaseId )
		{
			$where = array( array( 'comment_database_id=?', static::$customDatabaseId ) );
			
			if( $extraWhereClause !== NULL )
			{
				if ( !\is_array( $extraWhereClause ) or !\is_array( $extraWhereClause[0] ) )
				{
					$extraWhereClause = array( $extraWhereClause );
				}
				
				$where = array_merge( $where, $extraWhereClause );
			}
		}
		
		return parent::comments( $limit, $offset, $order, $orderDirection, $member, $includeHiddenComments, $cutoff, $where, $bypassCache, $includeDeleted, $canViewWarn );
	}

	/**
	 * Get review page count
	 *
	 * @return	int
	 */
	public function reviewPageCount()
	{
		if ( $this->reviewPageCount === NULL )
		{
			$reviewClass = static::$reviewClass;
			$idColumn = static::$databaseColumnId;
			$where = array( array( $reviewClass::$databasePrefix . $reviewClass::$databaseColumnMap['item'] . '=?', $this->$idColumn ) );
			$where[] = array( 'review_database_id=?', static::$customDatabaseId );
			$count = $reviewClass::getItemsWithPermission( $where, NULL, NULL, 'read', \IPS\Content\Hideable::FILTER_AUTOMATIC, 0, NULL, FALSE, FALSE, FALSE, TRUE );
			$this->reviewPageCount = ceil( $count / static::$reviewsPerPage );

			if( $this->reviewPageCount < 1 )
			{
				$this->reviewPageCount	= 1;
			}
		}
		return $this->reviewPageCount;
	}

	/**
	 * Get reviews
	 *
	 * @param	int|NULL			$limit					The number to get (NULL to use static::getCommentsPerPage())
	 * @param	int|NULL			$offset					The number to start at (NULL to examine \IPS\Request::i()->page)
	 * @param	string				$order					The column to order by (NULL to examine \IPS\Request::i()->sort)
	 * @param	string				$orderDirection			"asc" or "desc" (NULL to examine \IPS\Request::i()->sort)
	 * @param	\IPS\Member|NULL	$member					If specified, will only get comments by that member
	 * @param	bool|NULL			$includeHiddenReviews	Include hidden comments or not? NULL to base of currently logged in member's permissions
	 * @param	\IPS\DateTime|NULL	$cutoff					If an \IPS\DateTime object is provided, only comments posted AFTER that date will be included
	 * @param	mixed				$extraWhereClause		Additional where clause(s) (see \IPS\Db::build for details)
	 * @param	bool				$includeDeleted			Include deleted content
	 * @param	bool|NULL			$canViewWarn			TRUE to include Warning information, NULL to determine automatically based on moderator permissions.
	 * @return	array|NULL|\IPS\Content\Comment	If $limit is 1, will return \IPS\Content\Comment or NULL for no results. For any other number, will return an array.
	 */
	public function reviews( $limit=NULL, $offset=NULL, $order=NULL, $orderDirection='desc', $member=NULL, $includeHiddenReviews=NULL, $cutoff=NULL, $extraWhereClause=NULL, $includeDeleted=FALSE, $canViewWarn=NULL )
	{
		$where = array( array( 'review_database_id=?', static::$customDatabaseId ) );

		return parent::reviews( $limit, $offset, $order, $orderDirection, $member, $includeHiddenReviews, $cutoff, $where, $includeDeleted );
	}

	/**
	 * Get available comment/review tabs
	 *
	 * @return	array
	 */
	public function commentReviewTabs()
	{
		$tabs = array();
		if ( static::database()->options['reviews'] )
		{
			$tabs['reviews'] = \IPS\Member::loggedIn()->language()->addToStack( 'cms_review_count', TRUE, array( 'pluralize' => array( $this->mapped('num_reviews') ) ) );
		}
		if ( static::database()->options['comments'] )
		{
			$count = $this->mapped('num_comments');
			if ( \IPS\Application::appIsEnabled('forums') and $this->_forum_comments and $topic = $this->topic() )
			{
				if ( $count != ( $topic->posts - 1 ) )
				{
					$this->record_comments = $topic->posts - 1;
					$this->save();
				}
				
				$count = ( $topic->posts - 1 ) > 0 ? $topic->posts - 1 : 0;
			}
			
			$tabs['comments'] = \IPS\Member::loggedIn()->language()->addToStack( 'cms_comment_count', TRUE, array( 'pluralize' => array( $count ) ) );
		}

		return $tabs;
	}

	/**
	 * Get comment/review output
	 *
	 * @param	string	$tab	Active tab
	 * @return	string
	 */
	public function commentReviews( $tab )
	{
		if ( $tab === 'reviews' )
		{
			return \IPS\cms\Theme::i()->getTemplate( static::database()->template_display, 'cms', 'database' )->reviews( $this );
		}
		elseif( $tab === 'comments' )
		{
			return \IPS\cms\Theme::i()->getTemplate( static::database()->template_display, 'cms', 'database' )->comments( $this );
		}

		return '';
	}

	/**
	 * Should new items be moderated?
	 *
	 * @param	\IPS\Member		$member							The member posting
	 * @param	\IPS\Node\Model	$container						The container
	 * @param	bool			$considerPostBeforeRegistering	If TRUE, and $member is a guest, will check if a newly registered member would be moderated
	 * @return	bool
	 */
	public static function moderateNewItems( \IPS\Member $member, \IPS\Node\Model $container = NULL, $considerPostBeforeRegistering = FALSE )
	{
		if ( static::database()->record_approve and !$member->group['g_avoid_q'] )
		{
			return !static::modPermission( 'approve', $member, $container );
		}

		return parent::moderateNewItems( $member, $container, $considerPostBeforeRegistering );
	}

	/**
	 * Should new comments be moderated?
	 *
	 * @param	\IPS\Member	$member							The member posting
	 * @param	bool		$considerPostBeforeRegistering	If TRUE, and $member is a guest, will check if a newly registered member would be moderated
	 * @return	bool
	 */
	public function moderateNewComments( \IPS\Member $member, $considerPostBeforeRegistering = FALSE )
	{
		return ( static::database()->options['comments_mod'] and !$member->group['g_avoid_q'] ) or parent::moderateNewComments( $member, $considerPostBeforeRegistering );
	}

	/**
	 * Should new reviews be moderated?
	 *
	 * @param	\IPS\Member	$member							The member posting
	 * @param	bool		$considerPostBeforeRegistering	If TRUE, and $member is a guest, will check if a newly registered member would be moderated
	 * @return	bool
	 */
	public function moderateNewReviews( \IPS\Member $member, $considerPostBeforeRegistering = FALSE )
	{
		return ( static::database()->options['reviews_mod'] and !$member->group['g_avoid_q'] ) or parent::moderateNewReviews( $member, $considerPostBeforeRegistering );
	}

	/**
	 * @brief Skip topic creation, useful if the topic may already exist
	 */
	public static $skipTopicCreation = FALSE;

	/**
	 * @brief Are we creating a record? Ignore topic syncs until we are done if so.
	 */
	protected static $creatingRecord = FALSE;

	/**
	 * @brief Store the member we are creating with if not the logged in member
	 */
	protected static $createWithMember = NULL;

	/**
	 * Create from form
	 *
	 * @param	array					$values				Values from form
	 * @param	\IPS\Node\Model|NULL	$container			Container (e.g. forum), if appropriate
	 * @param	bool					$sendNotification	Send Notification
	 * @return	\IPS\cms\Records
	 */
	public static function createFromForm( $values, \IPS\Node\Model $container = NULL, $sendNotification = TRUE )
	{
		if ( isset( $values['record_author_choice'] ) and $values['record_author_choice'] == 'notme' )
		{
			static::$createWithMember = $values['record_member_id'];
		}

		static::$creatingRecord = TRUE;
		$record = parent::createFromForm( $values, $container, $sendNotification );
		static::$creatingRecord = FALSE;

		if ( !static::$skipTopicCreation and \IPS\Application::appIsEnabled('forums') and $record->_forum_record and $record->_forum_forum and ! $record->hidden() and ! $record->record_future_date )
		{
			try
			{
				$record->syncTopic();
			}
			catch( \Exception $ex ) { }
		}

		return $record;
	}

	/**
	 * Create generic object
	 *
	 * @param	\IPS\Member				$author		The author
	 * @param	string|NULL				$ipAddress	The IP address
	 * @param	\IPS\DateTime			$time		The time
	 * @param	\IPS\Node\Model|NULL	$container	Container (e.g. forum), if appropriate
	 * @param	bool|NULL				$hidden		Hidden? (NULL to work our automatically)
	 * @return	static
	 */
	public static function createItem( \IPS\Member $author, $ipAddress, \IPS\DateTime $time, \IPS\Node\Model $container = NULL, $hidden=NULL )
	{
		/* This is fired inside createFromForm, and we need to switch the author? */
		return parent::createItem( static::$createWithMember !== NULL ? static::$createWithMember : $author, $ipAddress, $time, $container, $hidden );
	}

	/**
	 * Process after the object has been edited on the front-end
	 *
	 * @param	array	$values		Values from form
	 * @return	void
	 */
	public function processAfterEdit( $values )
	{
		if ( \IPS\Application::appIsEnabled('forums') and $this->_forum_record and $this->_forum_forum and ! $this->hidden() and ! $this->record_future_date )
		{
			try
			{
				$this->syncTopic();
			}
			catch( \Exception $ex ) { }
		}

		parent::processAfterEdit( $values );
	}

	/**
	 * Set the reciprocal field data
	 *
	 * @param mixed $row
	 * @return string
	 */
	public function processItemFieldData( \IPS\cms\Fields $row ): string
	{
		$idColumn = static::$databaseColumnId;
		\IPS\Db::i()->delete( 'cms_database_fields_reciprocal_map', array('map_origin_database_id=? and map_field_id=? and map_origin_item_id=?', static::$customDatabaseId, $row->id, $this->_id) );

		$field = 'field_' . $row->id;
		$extra = $row->extra;
		if ( $this->$field and !empty( $extra['database'] ) )
		{
			foreach ( explode( ',', $this->$field ) as $foreignId )
			{
				if ( $foreignId )
				{
					\IPS\Db::i()->insert( 'cms_database_fields_reciprocal_map', array(
						'map_origin_database_id' => static::$customDatabaseId,
						'map_foreign_database_id' => $extra['database'],
						'map_origin_item_id' => $this->$idColumn,
						'map_foreign_item_id' => $foreignId,
						'map_field_id' => $row->id
					) );
				}
			}
		}
		return $field;
	}

	/**
	 * Callback to execute when tags are edited
	 *
	 * @return	void
	 */
	protected function processAfterTagUpdate()
	{
		parent::processAfterTagUpdate();

		if ( \IPS\Application::appIsEnabled('forums') and $this->_forum_record and $this->_forum_forum and ! $this->hidden() and ! $this->record_future_date )
		{
			try
			{
				$this->syncTopic();
			}
			catch( \Exception $ex ) { }
		}
	}
	
	/**
	 * Process the comment form
	 *
	 * @param	array	$values		Array of `$form` values
	 * @return  \IPS\Content\Comment
	 */
	public function processCommentForm( $values )
	{
		if ( $this->useForumComments() )
		{
			$topic = $this->topic( FALSE );
		
			if ( $topic === NULL )
			{
				try
				{
					$this->syncTopic();
				}
				catch( \Exception $ex ) { }
				
				/* Try again */
				$topic = $this->topic( FALSE );
				if ( ! $topic )
				{
					return parent::processCommentForm( $values );
				}
			}
			
			$comment = $values[ static::$formLangPrefix . 'comment' . '_' . $this->_id ];
			$post    = \IPS\forums\Topic\Post::create( $topic, $comment, FALSE, ( isset( $values['guest_name'] ) ? $values['guest_name'] : NULL ) );
			
			$commentClass = 'IPS\cms\Records\CommentTopicSync' . static::$customDatabaseId;

			$idColumn = static::$databaseColumnId;
			$autoSaveKey = 'reply-' . static::$application . '/' . static::$module  . '-' . $this->$idColumn;

			/* First we have to update the attachment location key */
			\IPS\Db::i()->update( 'core_attachments_map', array( 'location_key' => 'forums_Forums' ), array( 'temp=?', md5( $autoSaveKey ) ) );

			/* Then "claim" the attachments */
			$parameters = array_merge( array( $autoSaveKey ), $post->attachmentIds() );
			\IPS\File::claimAttachments( ...$parameters );
			
			$topic->markRead();
			
			/* Post anonymously */
			if( isset( $values[ 'post_anonymously' ] ) )
			{
				$post->setAnonymous( $values[ 'post_anonymously' ] );
				$this->syncRecordFromTopic( $topic );
			}
			
			return $commentClass::load( $post->pid );
			
		}
		else
		{
			return parent::processCommentForm( $values );
		}
	}
	
	/**
	 * Syncing to run when hiding
	 *
	 * @param	\IPS\Member|NULL|FALSE	$member	The member doing the action (NULL for currently logged in member, FALSE for no member)
	 * @return	void
	 */
	public function onHide( $member )
	{
		parent::onHide( $member );
		if ( \IPS\Application::appIsEnabled('forums') and $topic = $this->topic() )
		{
			$topic->hide( $member );
		}
	}
	
	/**
	 * Syncing to run when publishing something previously pending publishing
	 *
	 * @param	\IPS\Member|NULL|FALSE	$member	The member doing the action (NULL for currently logged in member, FALSE for no member)
	 * @return	void
	 */
	public function onPublish( $member )
	{
		parent::onPublish( $member );

		/* If last topic/review columns are in the future, reset them or the content will indefinitely show as unread */
		$this->record_last_review = ( $this->record_last_review > $this->record_publish_date ) ? $this->record_publish_date : $this->record_last_review;
		$this->record_last_comment = ( $this->record_last_comment > $this->record_publish_date ) ? $this->record_publish_date : $this->record_last_comment;
		$this->save();

		if ( \IPS\Application::appIsEnabled('forums') )
		{
			if ( $topic = $this->topic() )
			{
				if ( $topic->hidden() )
				{
					$topic->unhide( $member );
				}
			}
			else if ( $this->_forum_forum )
			{
				try
				{
					$this->syncTopic();
				}
				catch( \Exception $ex ) { }
			}
		}
	}
	
	/**
	 * Syncing to run when unpublishing an item (making it a future dated entry when it was already published)
	 *
	 * @param	\IPS\Member|NULL|FALSE	$member	The member doing the action (NULL for currently logged in member, FALSE for no member)
	 * @return	void
	 */
	public function onUnpublish( $member )
	{
		parent::onUnpublish( $member );
		if ( \IPS\Application::appIsEnabled('forums') AND $topic = $this->topic() )
		{
			$topic->hide( $member );
		}
	}
	
	/**
	 * Syncing to run when unhiding
	 *
	 * @param	bool					$approving	If true, is being approved for the first time
	 * @param	\IPS\Member|NULL|FALSE	$member	The member doing the action (NULL for currently logged in member, FALSE for no member)
	 * @return	void
	 */
	public function onUnhide( $approving, $member )
	{
		parent::onUnhide( $approving, $member );
		
		if ( $this->record_expiry_date )
		{
			$this->record_expiry_date = 0;
			$this->save();
		}
		
		if ( \IPS\Application::appIsEnabled('forums') )
		{
			if ( $topic = $this->topic() )
			{ 
				$topic->unhide( $member );
			}
			elseif ( $this->_forum_forum and ! $this->isFutureDate() )
			{
				try
				{
					$this->syncTopic();
				}
				catch( \Exception $ex ) { };
			}
		}
	}

	/**
	 * Change Author
	 *
	 * @param	\IPS\Member	$newAuthor	The new author
	 * @param	bool		$log		If TRUE, action will be logged to moderator log
	 * @return	void
	 */
	public function changeAuthor( \IPS\Member $newAuthor, $log=TRUE )
	{
		parent::changeAuthor( $newAuthor, $log );

		$topic = $this->topic();

		if ( $topic )
		{
			$topic->changeAuthor( $newAuthor, $log );
		}
	}
	
	/**
	 * Get last comment author
	 * Overloaded for the bump on edit shenanigans 
	 *
	 * @return	\IPS\Member
	 * @throws	\BadMethodCallException
	 */
	public function lastCommenter()
	{
		if ( ( static::database()->_comment_bump & ( \IPS\cms\Databases::BUMP_ON_EDIT + \IPS\cms\Databases::BUMP_ON_COMMENT ) and $this->record_edit_time > 0 and $this->record_edit_time > $this->record_last_comment ) OR
			 ( ( static::database()->_comment_bump & \IPS\cms\Databases::BUMP_ON_EDIT ) and !( static::database()->_comment_bump & ( \IPS\cms\Databases::BUMP_ON_EDIT + \IPS\cms\Databases::BUMP_ON_COMMENT ) ) and $this->record_edit_time > 0 ) )
		{
			try
			{
				$this->_lastCommenter = \IPS\Member::load( $this->record_edit_member_id );
				return $this->_lastCommenter;
			}
			catch( \Exception $e ) { }
		}
		
		return parent::lastCommenter();
	}

	/**
	 * Is this topic linked to a record?
     *
     * @param   \IPS\forums\Topic   $topic  Forums topic
	 * @return boolean
	 */
	public static function topicIsLinked( $topic )
	{
		return ( static::getLinkedRecord( $topic ) === NULL ) ? FALSE : TRUE;
	}

	/**
	 * @brief	Cached linked record checks to prevent duplicate queries
	 */
	protected static $linkedRecordLookup = array();
	
	/**
	 * Is this topic linked to a record?
     *
     * @param   \IPS\forums\Topic   $topic  Forums topic
	 * @return  \IPS\cms\Records|NULL
	 */
	public static function getLinkedRecord( $topic )
	{
		if( array_key_exists( $topic->tid, static::$linkedRecordLookup ) )
		{
			return static::$linkedRecordLookup[ $topic->tid ];
		}

		static::$linkedRecordLookup[ $topic->tid ] = NULL;

		foreach( \IPS\cms\Databases::databases() as $database )
		{
			try
			{
				if ( $database->forum_record and $database->forum_forum == $topic->container()->_id )
				{
					$class = '\IPS\cms\Records' . $database->id;
					$record = $class::load( $topic->tid, 'record_topicid' );
				
					if ( $record->_forum_record )
					{
						static::$linkedRecordLookup[ $topic->tid ] = $record;
					}
				}
			}
			catch( \Exception $e ) { }
		}
		
		return static::$linkedRecordLookup[ $topic->tid ];
	}
	
	/**
	 * Get Topic (checks member's permissions)
	 *
	 * @param	bool	$checkPerms		Should check if the member can read the topic?
	 * @return	\IPS\forums\Topic|NULL
	 */
	public function topic( $checkPerms=TRUE )
	{
		if ( \IPS\Application::appIsEnabled('forums') and $this->_forum_record and $this->record_topicid )
		{
			try
			{
				return $checkPerms ? \IPS\forums\Topic::loadAndCheckPerms( $this->record_topicid ) : \IPS\forums\Topic::load( $this->record_topicid );
			}
			catch ( \OutOfRangeException $e )
			{
				return NULL;
			}
		}
	
		return NULL;
	}

	/**
	 * Post this record as a forum topic
	 *
	 * @param	bool		$commentCheck	Check if comments need synced as well.
	 * @return void
	 */
	public function syncTopic( $commentCheck = TRUE )
	{
		if ( ! \IPS\Application::appIsEnabled( 'forums' ) )
		{
			throw new \UnexpectedValueException('content_record_no_forum_app_for_topic');
		}

		/* If we're in the middle of creating a record, don't sync the topic yet */
		if( static::$creatingRecord === TRUE )
		{
			return;
		}

		/* Fetch the forum */
		try
		{
			$forum = \IPS\forums\Forum::load( $this->_forum_forum );
		}
		catch( \OutOfRangeException $ex )
		{
			throw new \UnexpectedValueException('content_record_bad_forum_for_topic');
		}

		/* Run a test for the record url, this call will throw an LogicException if the database isn't associated to a page */
		try
		{
			$this->url();
		}
		catch ( \LogicException $e )
		{
			$idColumn = static::$databaseColumnId;

			\IPS\Log::log( sprintf( "Record %s in database %s tried to sync the topic, but failed because it has no valid url", $this->$idColumn , static::$customDatabaseId), 'cms_topicsync' );
			return;
		}

		/* Existing topic */
		if ( $this->record_topicid )
		{
			/* Get */
			try
			{
				$topic = \IPS\forums\Topic::load( $this->record_topicid );
				if ( !$topic )
				{
					return;
				}
				/* Reset cache */
				$this->displayTitle = NULL;
				$topic->title = $this->_forum_prefix . $this->_title . $this->_forum_suffix;
				if ( \IPS\Settings::i()->tags_enabled )
				{
					$topic->setTags( $this->prefix() ? array_merge( $this->tags(), array( 'prefix' => $this->prefix() ) ) : $this->tags() );
				}
				
				if ( $this->hidden() )
				{
					$topic->hide( FALSE );
				}
				else if ( $topic->hidden() )
				{
					$topic->unhide( FALSE );
				}

				$topic->save();
				$firstPost = $topic->comments( 1 );

				$content = \IPS\Theme::i()->getTemplate( 'submit', 'cms', 'front' )->topic( $this );
				\IPS\Member::loggedIn()->language()->parseOutputForDisplay( $content );

				$firstPost->post = $content;
				$firstPost->save();
				
				/* Reindex to update search index */
				\IPS\Content\Search\Index::i()->index( $firstPost );
			}
			catch ( \OutOfRangeException $e )
			{
				return;
			}
		}
		/* New topic */
		else
		{
			/* Create topic */
			$topic = \IPS\forums\Topic::createItem( $this->author(), \IPS\Request::i()->ipAddress(), \IPS\DateTime::ts( $this->record_publish_date ? $this->record_publish_date : $this->record_saved ), \IPS\forums\Forum::load( $this->_forum_forum ), $this->hidden() );
			$topic->title = $this->_forum_prefix . $this->_title . $this->_forum_suffix;
			$topic->topic_archive_status = \IPS\forums\Topic::ARCHIVE_EXCLUDE;
			$topic->save();

			if ( \IPS\Settings::i()->tags_enabled )
			{
				$topic->setTags( $this->prefix() ? array_merge( $this->tags(), array( 'prefix' => $this->prefix() ) ) : $this->tags() );
			}

			/* Create post */
			$content = \IPS\Theme::i()->getTemplate( 'submit', 'cms', 'front' )->topic( $this );
			\IPS\Member::loggedIn()->language()->parseOutputForDisplay( $content );

			$post = \IPS\forums\Topic\Post::create( $topic, $content, TRUE, NULL, NULL, $this->author(), \IPS\DateTime::ts( $this->record_publish_date ? $this->record_publish_date : $this->record_saved ) );
			$post->save();

			$topic->topic_firstpost = $post->pid;
			$topic->save();
			
			if( $this->isAnonymous() )
			{
				$topic->setAnonymous( TRUE, $this->author() );
				$post->setAnonymous( TRUE, $this->author() );
			}

			$topic->markRead();

			/* Send notifications */
			if ( !$topic->isFutureDate() AND !$topic->hidden() )
			{
				$topic->sendNotifications();
			}
			
			/* Update file */
			$this->record_topicid = $topic->tid;
			$this->save();
			
			/* Do any comments need moving over? */
			if ( $commentCheck AND $this->useForumComments() AND (bool) \IPS\Db::i()->select( 'COUNT(*)', 'cms_database_comments', array( "comment_database_id=? AND comment_record_id=?", static::$customDatabaseId, $this->primary_id_field ) )->first() )
			{
				\IPS\Task::queue( 'cms', 'MoveSingleRecord', array( 'databaseId' => static::$customDatabaseId, 'recordId' => $this->primary_id_field, 'to' => 'forums' ), 3, array( 'databaseId', 'recordId', 'to' ) );
			}
			
			/* Reindex to update search index */
			\IPS\Content\Search\Index::i()->index( $post );
		}
	}

	/**
	 * Sync topic details to the record
	 *
	 * @param   \IPS\forums\Topic   $topic  Forums topic
	 * @return  void
	 */
	public function syncRecordFromTopic( $topic )
	{
		if ( $this->_forum_record and $this->_forum_forum and $this->_forum_comments )
		{
			$this->record_last_comment_by   = $topic->last_poster_id;
			$this->record_last_comment_name = $topic->last_poster_name;
			$this->record_last_comment      = $topic->last_post;
			$this->record_comments_queued   = $topic->topic_queuedposts;
			$this->record_comments_hidden 	= $topic->topic_hiddenposts;
			$this->record_comments          = $topic->posts - 1;
			$this->save();
		}
	}

	/**
	 * Get fields for the topic
	 * 
	 * @return array
	 */
	public function topicFields()
	{
		$fieldsClass = 'IPS\cms\Fields' . static::$customDatabaseId;
		$fieldData   = $fieldsClass::data( 'view', $this->container() );
		$fieldValues = $fieldsClass::display( $this->fieldValues(), 'record', $this->container(), 'id' );

		$fields = array();
		foreach( $fieldData as $id => $data )
		{
			if ( $data->topic_format )
			{
				if ( isset( $fieldValues[ $data->id ] ) )
				{
					$html = str_replace( '{title}'  , $data->_title, $data->topic_format );
					$html = str_replace( '{content}', $fieldValues[ $data->id ], $html );
					$html = str_replace( '{value}'  , $fieldValues[ $data->id ], $html );
				
					$fields[ $data->id ] = $html;
				}
			}
		}

		if ( ! \count( $fields ) )
		{
			$fields[ static::database()->field_content ] = $fieldValues['content'];
		}

		return $fields;
	}
	
	/**
	 * @brief	Store the comment page count otherwise $topic->posts is reduced by 1 each time it is called
	 */
	protected $recordCommentPageCount = NULL;
	
	/**
	 * Get comment page count
	 *
	 * @param	bool		$recache		TRUE to recache the value
	 * @return	int
	 */
	public function commentPageCount( $recache=FALSE )
	{
		if ( $this->recordCommentPageCount === NULL or $recache === TRUE )
		{
			if ( $this->useForumComments() )
			{
				try
				{
					$topic = $this->topic();
	
					if( $topic !== NULL )
					{
						/* Store the real count so it is not accidentally written as the actual value */
						$realCount = $topic->posts;
						
						/* Compensate for the first post (which is actually the record) */
						$topic->posts = ( $topic->posts - 1 ) > 0 ? $topic->posts - 1 : 0;
						
						/* Get our page count considering all of that */
						$this->recordCommentPageCount = $topic->commentPageCount();
						
						/* Reset the count back to the real count */
						$topic->posts = $realCount;
					}
					else
					{
						$this->recordCommentPageCount = 1;
					}
				}
				catch( \Exception $e ) { }
			}
			else
			{
				$this->recordCommentPageCount = parent::commentPageCount( $recache );
			}
		}
		
		return $this->recordCommentPageCount;
	}

	/**
	 * Log for deletion later
	 *
	 * @param	\IPS\Member|NULL 	$member	The member, NULL for currently logged in, or FALSE for no member
	 * @return	void
	 */
	public function logDelete( $member = NULL )
	{
		parent::logDelete( $member );

		if ( $topic = $this->topic() and $this->_forum_delete )
		{
			$topic->logDelete( $member );
		}
	}
	
	/**
	 * Delete Record
	 *
	 * @return	void
	 */
	public function delete()
	{
		$topic        = $this->topic();
		$commentClass = static::$commentClass;
		
		if ( $this->topic() and $this->_forum_delete )
		{
			$topic->delete();
		}
		else if ( $this->topic() )
		{
			/* We have an attached topic, but we don't want to delete the topic so remove commentClass otherwise we'll delete posts */
			static::$commentClass = NULL;
		}

		/* Remove Record Image And Record Thumb Image */
		if ( $this->record_image )
		{
			try
			{
				\IPS\File::get( 'cms_Records', $this->record_image )->delete();
			}
			catch( \Exception $e ){}
		}

		if ( $this->record_image_thumb )
		{
			try
			{
				\IPS\File::get( 'cms_Records', $this->record_image_thumb )->delete();
			}
			catch ( \Exception $e ) { }
		}

		/* Clean up any other uploaded files */
		$fieldsClass = '\IPS\cms\Fields' . static::$customDatabaseId;
		foreach( $fieldsClass::roots( NULL ) as $id => $field )
		{
			if( $field->type == 'Upload' )
			{
				$fieldName = 'field_' . $field->id;

				if ( $this->$fieldName )
				{
					try
					{
						\IPS\File::get( 'cms_Records', $this->$fieldName )->delete();
					}
					catch( \Exception $e ){}
				}

				/* Delete thumbnails */
				foreach( \IPS\Db::i()->select( '*', 'cms_database_fields_thumbnails', array( array( 'thumb_field_id=? AND thumb_record_id=?', $field->id, $this->primary_id_field ) ) ) as $thumb )
				{
					try
					{
						\IPS\File::get( 'cms_Records', $thumb['thumb_location'] )->delete();
					}
					catch( \Exception $e ){}
				}
			}
		}

		/* Remove any reciprocal linking */
		\IPS\Db::i()->delete( 'cms_database_fields_reciprocal_map', array( 'map_origin_database_id=? and map_origin_item_id=?', static::database()->id, $this->_id ) );
		
		parent::delete();
		
		if ( $this->topic() )
		{
			static::$commentClass = $commentClass;
		}
	}

	/**
	 * Can view?
	 *
	 * @param	\IPS\Member|NULL	$member	The member to check for or NULL for the currently logged in member
	 * @return	bool
	 */
	public function canView( $member=NULL )
	{
		if ( !parent::canView( $member ) )
		{
			return FALSE;
		}

		/* This prevents auto share and notifications being sent out */
		try
		{
			$page = \IPS\cms\Pages\Page::loadByDatabaseId( static::database()->id );
			if ( !$page->can( 'view', $member ) )
			{
				return FALSE;
			}
		}
		catch( \OutOfRangeException $e )
		{
			/* If the database isn't assigned to a page they won't be able to view the record */
			return FALSE;
		}

		$member = $member ?: \IPS\Member::loggedIn();

		if ( !$this->container()->can_view_others and !$member->modPermission( 'can_content_view_others_records' ) )
		{
			if ( $member != $this->author() )
			{
				return FALSE;
			}
		}

		return TRUE;
	}

	/**
	 * Could edit an item?
	 * Useful to see if one can edit something even if the cut off has expired
	 *
	 * @param	\IPS\Member|NULL	$member	The member to check for (NULL for currently logged in member)
	 * @return	bool
	 */
	public function couldEdit( $member=NULL )
	{
		$couldEdit = parent::couldEdit( $member );
		if ( $couldEdit )
		{
			return TRUE;
		}
		else
		{
			$member = $member ?: \IPS\Member::loggedIn();
			if ( ( ( static::database()->options['indefinite_own_edit'] AND $member->member_id === $this->member_id ) OR ( $member->member_id and static::database()->all_editable ) ) AND ! $this->locked() AND \in_array( $this->hidden(), array(  0, 1 ) ) )
			{
				return TRUE;
			}
		}

		return FALSE;
	}

	/* ! Moderation */
	
	/**
	 * Can edit?
	 *
	 * @param	\IPS\Member|NULL	$member	The member to check for (NULL for currently logged in member)
	 * @return	bool
	 */
	public function canEdit( $member=NULL )
	{
		$member = $member ?: \IPS\Member::loggedIn();
		if ( ( ( static::database()->options['indefinite_own_edit'] AND $member->member_id === $this->member_id ) OR ( $member->member_id and static::database()->all_editable ) ) AND ! $this->locked() AND \in_array( $this->hidden(), array(  0, 1 ) ) )
		{
			return TRUE;
		}

		/* Am I a Moderator with edit permissions ?*/
		if ( static::modPermission( 'edit', $member, $this->containerWrapper() ) )
		{
			return TRUE;
		}
		
		if ( parent::canEdit( $member ) )
		{
			/* Test against specific perms for this category */
			return $this->container()->can( 'edit', $member );
		}

		return FALSE;
	}
	
	/**
	 * Can edit title?
	 *
	 * @param	\IPS\Member|NULL	$member	The member to check for (NULL for currently logged in member)
	 * @return	bool
	 */
	public function canEditTitle( $member=NULL )
	{
		if ( $this->canEdit( $member ) )
		{
			try
			{
				$class = '\IPS\cms\Fields' .  static::database()->id;
				$field = $class::load( static::database()->field_title );
				return $field->can( 'edit', $member );
			}
			catch( \Exception $e )
			{
				return FALSE;
			}
		}
		return FALSE;
	}

	/**
	 * Can move?
	 *
	 * @param	\IPS\Member|NULL	$member	The member to check for (NULL for currently logged in member)
	 * @return	bool
	 */
	public function canMove( $member=NULL )
	{
		if ( ! static::database()->use_categories )
		{
			return FALSE;
		}
		
		return parent::canMove( $member );
	}

	/**
	 * Can manage revisions?
	 *
	 * @param	\IPS\Member|NULL		$member		The member to check for (NULL for currently logged in member)
	 * @return	bool
	 * @throws	\BadMethodCallException
	 */
	public function canManageRevisions( \IPS\Member $member = NULL )
	{
		return static::database()->revisions and static::modPermission( 'content_revisions', $member );
	}

	/**
	 * Can comment?
	 *
	 * @param	\IPS\Member\NULL	$member							The member (NULL for currently logged in member)
	 * @param	bool				$considerPostBeforeRegistering	If TRUE, and $member is a guest, will return TRUE if "Post Before Registering" feature is enabled
	 * @return	bool
	 */
	public function canComment( $member=NULL, $considerPostBeforeRegistering = TRUE )
	{
		return ( static::database()->options['comments'] and parent::canComment( $member, $considerPostBeforeRegistering ) );
	}

	/**
	 * Can review?
	 *
	 * @param	\IPS\Member\NULL	$member							The member (NULL for currently logged in member)
	 * @param	bool				$considerPostBeforeRegistering	If TRUE, and $member is a guest, will return TRUE if "Post Before Registering" feature is enabled
	 * @return	bool
	 */
	public function canReview( $member=NULL, $considerPostBeforeRegistering = TRUE )
	{
		return ( static::database()->options['reviews'] and parent::canReview( $member, $considerPostBeforeRegistering ) );
	}

	/**
	 * During canCreate() check, verify member can access the module too
	 *
	 * @param	\IPS\Member	$member		The member
	 * @note	The only reason this is abstracted at this time is because Pages creates dynamic 'modules' with its dynamic records class which do not exist
	 * @return	bool
	 */
	protected static function _canAccessModule( \IPS\Member $member )
	{
		/* Can we access the module */
		return $member->canAccessModule( \IPS\Application\Module::get( static::$application, 'database', 'front' ) );
	}

	/**
	 * Already reviewed?
	 *
	 * @param	\IPS\Member\NULL	$member	The member (NULL for currently logged in member)
	 * @return	bool
	 */
	public function hasReviewed( $member=NULL )
	{
		$member = $member ?: \IPS\Member::loggedIn();

		/* Check cache */
		if( isset( $this->_hasReviewed[ $member->member_id ] ) and $this->_hasReviewed[ $member->member_id ] !== NULL )
		{
			return $this->_hasReviewed[ $member->member_id ];
		}

		$reviewClass = static::$reviewClass;
		$idColumn    = static::$databaseColumnId;

		$where = array();
		$where[] = array( $reviewClass::$databasePrefix . $reviewClass::$databaseColumnMap['item'] . '=?', $this->$idColumn );
		$where[] = array( $reviewClass::$databasePrefix . $reviewClass::$databaseColumnMap['author'] . '=?', $member->member_id );
		$where[] = array( $reviewClass::$databasePrefix . 'database_id=?', static::$customDatabaseId );


		if ( \in_array( 'IPS\Content\Hideable', class_implements( $reviewClass ) ) )
		{
			/* Exclude content pending deletion, as it will not be shown inline  */
			if ( isset( $reviewClass::$databaseColumnMap['approved'] ) )
			{
				$where[] = array( $reviewClass::$databasePrefix . $reviewClass::$databaseColumnMap['approved'] . '<>?', -2 );
			}
			elseif( isset( $reviewClass::$databaseColumnMap['hidden'] ) )
			{
				$where[] = array( $reviewClass::$databasePrefix . $reviewClass::$databaseColumnMap['hidden'] . '<>?', -2 );
			}
		}

		$this->_hasReviewed[ $member->member_id ] = \IPS\Db::i()->select(
			'COUNT(*)', $reviewClass::$databaseTable, $where
		)->first();

		return $this->_hasReviewed[ $member->member_id ];
	}

	/* ! Rating */
	
	/**
	 * Can Rate?
	 *
	 * @param	\IPS\Member|NULL		$member		The member to check for (NULL for currently logged in member)
	 * @return	bool
	 * @throws	\BadMethodCallException
	 */
	public function canRate( \IPS\Member $member = NULL )
	{
		return parent::canRate( $member ) and ( $this->container()->allow_rating );
	}
	
	/* ! Comments */
	/**
	 * Add the comment form elements
	 *
	 * @return	array
	 */
	public function commentFormElements()
	{
		return parent::commentFormElements();
	}

	/**
	 * Add a comment when the filtes changed. If they changed.
	 *
	 * @param   array   $values   Array of new form values
	 * @return  \IPS\cms\Records\Comment|bool
	 */
	public function addCommentWhenFiltersChanged( $values )
	{
		if ( ! $this->canComment() )
		{
			return FALSE;
		}

		$currentValues = $this->fieldValues();
		$commentClass  = 'IPS\cms\Records\Comment' . static::$customDatabaseId;
		$categoryClass = 'IPS\cms\Categories' . static::$customDatabaseId;
		$fieldsClass   = 'IPS\cms\Fields' . static::$customDatabaseId;
		$newValues     = array();
		$fieldsFields  = $fieldsClass::fields( $values, 'edit', $this->category_id ?  $categoryClass::load( $this->category_id ) : NULL, $fieldsClass::FIELD_DISPLAY_COMMENTFORM );

		foreach( $currentValues as $name => $data )
		{
			$id = mb_substr( $name, 6 );
			if ( $id == static::database()->field_title or $id == static::database()->field_content )
			{
				unset( $currentValues[ $name ] );
			}

			/* Not filterable? */
			if ( ! isset( $fieldsFields[ $id ] ) )
			{
				unset( $currentValues[ $name ] );
			}
		}

		foreach( $fieldsFields as $key => $field )
		{
			$newValues[ 'field_' . $key ] = $field::stringValue( isset( $values[ $field->name ] ) ? $values[  $field->name ] : NULL );
		}

		$diff = array_diff_assoc( $currentValues, $newValues );

		if ( \count( $diff ) )
		{
			$show    = array();
			$display = $fieldsClass::display( $newValues, NULL, NULL, 'id' );

			foreach( $diff as $name => $value )
			{
				$id = mb_substr( $name, 6 );

				if ( $display[ $id ] )
				{
					$show[ $name ] = sprintf( \IPS\Member::loggedIn()->language()->get( 'cms_record_field_changed' ), \IPS\Member::loggedIn()->language()->get( 'content_field_' . $id ), $display[ $id ] );
				}
			}

			if ( \count( $show ) )
			{
				$post = \IPS\cms\Theme::i()->getTemplate( static::database()->template_display, 'cms', 'database' )->filtersAddComment( $show );
				\IPS\Member::loggedIn()->language()->parseOutputForDisplay( $post );
				
				if ( $this->useForumComments() )
				{
					$topic = $this->topic();
					$post  = \IPS\forums\Topic\Post::create( $topic, $post, FALSE );
					
					$commentClass = 'IPS\cms\Records\CommentTopicSync' . static::$customDatabaseId;
					
					$comment = $commentClass::load( $post->pid );
					$this->resyncLastComment();

					return $comment;
				}
				else
				{
					return $commentClass::create( $this, $post, FALSE );
				}
			}
		}

		return TRUE;
	}

	/* ! Tags */
	
	/**
	 * Can tag?
	 *
	 * @param	\IPS\Member|NULL		$member		The member to check for (NULL for currently logged in member)
	 * @param	\IPS\Node\Model|NULL	$container	The container to check if tags can be used in, if applicable
	 * @return	bool
	 */
	public static function canTag( \IPS\Member $member = NULL, \IPS\Node\Model $container = NULL )
	{
		return parent::canTag( $member, $container ) and ( static::database()->tags_enabled );
	}
	
	/**
	 * Can use prefixes?
	 *
	 * @param	\IPS\Member|NULL		$member		The member to check for (NULL for currently logged in member)
	 * @param	\IPS\Node\Model|NULL	$container	The container to check if tags can be used in, if applicable
	 * @return	bool
	 */
	public static function canPrefix( \IPS\Member $member = NULL, \IPS\Node\Model $container = NULL )
	{
		return parent::canPrefix( $member, $container ) and ( ! static::database()->tags_noprefixes );
	}
	
	/**
	 * Defined Tags
	 *
	 * @param	\IPS\Node\Model|NULL	$container	The container to check if tags can be used in, if applicable
	 * @return	array
	 */
	public static function definedTags( \IPS\Node\Model $container = NULL )
	{
		if ( static::database()->tags_predefined )
		{
			return explode( ',', static::database()->tags_predefined );
		}
	
		return parent::definedTags( $container );
	}

	/**
	 * Use a custom table helper when building content item tables
	 *
	 * @param	\IPS\Helpers\Table	$table	Table object to modify
	 * @param	string				$currentClass	Current class
	 * @return	\IPS\Helpers\Table
	 */
	public function reputationTableCallback( $table, $currentClass )
	{
		return $table;
	}
	
	/* !Notifications */
	
	/**
	 * @brief	Custom Field Notification Excludes
	 */
	protected $_fieldNotificationExcludes = array();
	
	/**
	 * Set notification exclusions for custom field updates.
	 *
	 * @param	string	$exclude		Predetermined array of member IDs to exclude
	 * @return	void
	 */
	public function setFieldQuoteAndMentionExcludes( array $exclude = array() )
	{
		$className = 'IPS\cms\Fields' . static::$customDatabaseId;
		foreach( $className::data() AS $field )
		{
			if ( $field->type == 'Editor' )
			{
				$key = "field_{$field->id}";
				$_data  = static::_getQuoteAndMentionIdsFromContent( $this->$key );
				foreach( $_data AS $type => $memberIds )
				{
					$this->_fieldNotificationExcludes = array_merge( $this->_fieldNotificationExcludes, $memberIds );
				}
			}
		}
		
		$this->_fieldNotificationExcludes = array_unique( $this->_fieldNotificationExcludes );
	}
	
	/**
	 * Send notifications for custom field updates
	 *
	 * @return array
	 */
	public function sendFieldQuoteAndMentionNotifications(): array
	{
		return $this->sendQuoteAndMentionNotifications( $this->_fieldNotificationExcludes );
	}
	
	/**
	 * Send quote and mention notifications
	 *
	 * @param	array	$exclude		An array of member IDs *not* to send notifications to
	 * @return	array	Member IDs sent to
	 */
	protected function sendQuoteAndMentionNotifications( $exclude=array() )
	{
		$data = array( 'quotes' => array(), 'mentions' => array(), 'embeds' => array() );
		
		$className = 'IPS\cms\Fields' .  static::$customDatabaseId;
		foreach ( $className::data() as $field )
		{
			if ( $field->type == 'Editor' )
			{
				$key = "field_{$field->id}";
				
				$_data = static::_getQuoteAndMentionIdsFromContent( $this->$key );
				foreach ( $_data as $type => $memberIds )
				{
					$_data[ $type ] = array_filter( $memberIds, function( $memberId ) use ( $field )
					{
						return $field->can( 'view', \IPS\Member::load( $memberId ) );
					} );
				}
				
				$data = array_map( 'array_unique', array_merge_recursive( $data, $_data ) );
			}
		}
		
		return $this->_sendQuoteAndMentionNotifications( $data, $exclude );
	}

    /**
     * Get average review rating
     *
     * @return	int
     */
    public function averageReviewRating()
    {
        if( $this->_averageReviewRating !== NULL )
        {
            return $this->_averageReviewRating;
        }

        $reviewClass = static::$reviewClass;
        $idColumn = static::$databaseColumnId;

        $where = array();
        $where[] = array( $reviewClass::$databasePrefix . $reviewClass::$databaseColumnMap['item'] . '=? AND review_database_id=?', $this->$idColumn, static::$customDatabaseId );
        if ( \in_array( 'IPS\Content\Hideable', class_implements( $reviewClass ) ) )
        {
            if ( isset( $reviewClass::$databaseColumnMap['approved'] ) )
            {
                $where[] = array( $reviewClass::$databasePrefix . $reviewClass::$databaseColumnMap['approved'] . '=?', 1 );
            }
            elseif ( isset( $reviewClass::$databaseColumnMap['hidden'] ) )
            {
                $where[] = array( $reviewClass::$databasePrefix . $reviewClass::$databaseColumnMap['hidden'] . '=?', 0 );
            }
        }

        $this->_averageReviewRating = round( \IPS\Db::i()->select( 'AVG(' . $reviewClass::$databasePrefix . $reviewClass::$databaseColumnMap['rating'] . ')', $reviewClass::$databaseTable, $where )->first(), 1 );

        return $this->_averageReviewRating;
    }

	/**
	 * If, when making a post, we should merge with an existing comment, this method returns the comment to merge with
	 *
	 * @return	\IPS\Content\Comment|NULL
	 */
	public function mergeConcurrentComment()
	{
		$lastComment = parent::mergeConcurrentComment();

		/* If we sync to the forums, make sure that the "last comment" is not actually the first post */
		if( $this->record_topicid AND $lastComment !== NULL )
		{
			$firstComment = \IPS\forums\Topic::load( $this->record_topicid )->comments( 1, 0, 'date', 'asc' );

			if( $firstComment->pid == $lastComment->pid )
			{
				return NULL;
			}
		}

		return $lastComment;
	}
	
	/**
	 * Deletion log Permissions
	 * Usually, this is the same as searchIndexPermissions. However, some applications may restrict searching but
	 * still want to allow delayed deletion log viewing and searching
	 *
	 * @return	string	Comma-delimited values or '*'
	 * 	@li			Number indicates a group
	 *	@li			Number prepended by "m" indicates a member
	 *	@li			Number prepended by "s" indicates a social group
	 */
	public function deleteLogPermissions()
	{
		if( ! $this->container()->can_view_others )
		{
			$return = $this->container()->searchIndexPermissions();
			/* If the search index permissions are empty, just return now because no one can see content in this forum */
			if( !$return )
			{
				return $return;
			}

			$return = $this->container()->permissionsThatCanAccessAllRecords();

			if ( $this->member_id )
			{
				$return[] = "m{$this->member_id}";
			}

			return implode( ',', $return );
		}
		
		try
		{
			return parent::searchIndexPermissions();
		}
		catch ( \LogicException $e )
		{
			return NULL;
		}
	}
	
	/**
	 * Search Index Permissions
	 * If we don't have a page, we don't want to add this to the search index
	 *
	 * @return	string	Comma-delimited values or '*'
	 * 	@li			Number indicates a group
	 *	@li			Number prepended by "m" indicates a member
	 *	@li			Number prepended by "s" indicates a social group
	 */
	public function searchIndexPermissions()
	{
		/* We don't want to index items in databases with search disabled */
		if( ! static::database()->search )
		{
			return NULL;
		}
		
		return $this->deleteLogPermissions();
	}

	/**
	 * Online List Permissions
	 *
	 * @return	string	Comma-delimited values or '*'
	 * 	@li			Number indicates a group
	 *	@li			Number prepended by "m" indicates a member
	 *	@li			Number prepended by "s" indicates a social group
	 */
	public function onlineListPermissions()
	{
		/* If search is disabled for this database, we want to use the page/category permissions
		instead of falling back to searchIndexPermissions */
		if( ! static::database()->search )
		{
			return $this->container()->readPermissionMergeWithPage();
		}

		return parent::onlineListPermissions();
	}

	/**
	 * Get output for API
	 *
	 * @param	\IPS\Member|NULL	$authorizedMember	The member making the API request or NULL for API Key / client_credentials
	 * @return	array
	 * @apiresponse	int						id				ID number
	 * @apiresponse	string					title			Title
	 * @apiresponse	\IPS\cms\Categories		category		Category
	 * @apiresponse	object					fields			Field values
	 * @apiresponse	\IPS\Member				author			The member that created the event
	 * @apiresponse	datetime				date			When the record was created
	 * @apiresponse	string					description		Event description
	 * @apiresponse	int						comments		Number of comments
	 * @apiresponse	int						reviews			Number of reviews
	 * @apiresponse	int						views			Number of posts
	 * @apiresponse	string					prefix			The prefix tag, if there is one
	 * @apiresponse	[string]				tags			The tags
	 * @apiresponse	bool					locked			Event is locked
	 * @apiresponse	bool					hidden			Event is hidden
	 * @apiresponse	bool					pinned			Event is pinned
	 * @apiresponse	bool					featured		Event is featured
	 * @apiresponse	string|NULL				url				URL, or NULL if the database has not been embedded onto a page
	 * @apiresponse	float					rating			Average Rating
	 * @apiresponse	string					image			Record Image
	 * @apiresponse	\IPS\forums\Topic		topic			The topic
	 */
	public function apiOutput( \IPS\Member $authorizedMember = NULL )
	{
		/* You can have a database that is not embedded onto a page */
		try
		{
			$url = (string) $this->url();
		}
		catch( \LogicException $e )
		{
			$url = NULL;
		}

		/* Remove Lazyload from fields */
		$fields = [];
		foreach( $this->fieldValues() as $k => $v )
		{
			$fields[ $k ] = \is_string( $v ) ? \IPS\Text\Parser::removeLazyLoad( $v ) : $v;
		}

		$return = array(
			'id'			=> $this->primary_id_field,
			'title'			=> $this->_title,
			'category'		=> $this->container() ? $this->container()->apiOutput() : null,
			'fields'		=> $fields,
			'author'		=> $this->author()->apiOutput( $authorizedMember ),
			'date'			=> \IPS\DateTime::ts( $this->record_saved )->rfc3339(),
			'description'	=> \IPS\Text\Parser::removeLazyLoad( $this->content() ),
			'comments'		=> $this->record_comments,
			'reviews'		=> $this->record_reviews,
			'views'			=> $this->record_views,
			'prefix'		=> $this->prefix(),
			'tags'			=> $this->tags(),
			'locked'		=> (bool) $this->locked(),
			'hidden'		=> (bool) $this->hidden(),
			'pinned'		=> (bool) $this->mapped('pinned'),
			'featured'		=> (bool) $this->mapped('featured'),
			'url'			=> $url,
			'rating'		=> $this->averageRating(),
			'image'			=> $this->record_image ? (string) \IPS\File::get( 'cms_Records', $this->record_image )->url : null,
			'topic'			=> $this->topicid ? $this->topic()->apiOutput( $authorizedMember ) : NULL,
		);

		if ( \IPS\IPS::classUsesTrait( $this, 'IPS\Content\Reactable' ) )
		{
			if ( $reactions = $this->reactions() )
			{
				$enabledReactions = \IPS\Content\Reaction::enabledReactions();
				$finalReactions = [];
				foreach( $reactions as $memberId => $array )
				{
					foreach( $array as $reaction )
					{
						$finalReactions[ $memberId ][] = [
							'title' => $enabledReactions[ $reaction ]->_title,
							'id'    => $reaction,
							'value' => $enabledReactions[ $reaction ]->value,
							'icon'  => (string) $enabledReactions[ $reaction ]->_icon->url
						];
					}
				}

				$return['reactions'] = $finalReactions;
			}
			else
			{
				$return['reactions'] = [];
			}
		}

		return $return;
	}

	/**
	 * Get items with permission check
	 *
	 * @param	array		$where				Where clause
	 * @param	string		$order				MySQL ORDER BY clause (NULL to order by date)
	 * @param	int|array	$limit				Limit clause
	 * @param	string|NULL	$permissionKey		A key which has a value in the permission map (either of the container or of this class) matching a column ID in core_permission_index or NULL to ignore permissions
	 * @param	mixed		$includeHiddenItems	Include hidden items? NULL to detect if currently logged in member has permission, -1 to return public content only, TRUE to return unapproved content and FALSE to only return unapproved content the viewing member submitted
	 * @param	int			$queryFlags			Select bitwise flags
	 * @param	\IPS\Member	$member				The member (NULL to use currently logged in member)
	 * @param	bool		$joinContainer		If true, will join container data (set to TRUE if your $where clause depends on this data)
	 * @param	bool		$joinComments		If true, will join comment data (set to TRUE if your $where clause depends on this data)
	 * @param	bool		$joinReviews		If true, will join review data (set to TRUE if your $where clause depends on this data)
	 * @param	bool		$countOnly			If true will return the count
	 * @param	array|null	$joins				Additional arbitrary joins for the query
	 * @param	mixed		$skipPermission		If you are getting records from a specific container, pass the container to reduce the number of permission checks necessary or pass TRUE to skip conatiner-based permission. You must still specify this in the $where clause
	 * @param	bool		$joinTags			If true, will join the tags table
	 * @param	bool		$joinAuthor			If true, will join the members table for the author
	 * @param	bool		$joinLastCommenter	If true, will join the members table for the last commenter
	 * @param	bool		$showMovedLinks		If true, moved item links are included in the results
	 * @param	array|null	$location			Array of item lat and long
	 * @return	\IPS\Patterns\ActiveRecordIterator|int
	 */
	public static function getItemsWithPermission( $where=array(), $order=NULL, $limit=10, $permissionKey='read', $includeHiddenItems=\IPS\Content\Hideable::FILTER_AUTOMATIC, $queryFlags=0, \IPS\Member $member=NULL, $joinContainer=FALSE, $joinComments=FALSE, $joinReviews=FALSE, $countOnly=FALSE, $joins=NULL, $skipPermission=FALSE, $joinTags=TRUE, $joinAuthor=TRUE, $joinLastCommenter=TRUE, $showMovedLinks=FALSE, $location=NULL )
	{
		$where = static::getItemsWithPermissionWhere( $where, $permissionKey, $member, $joinContainer, $skipPermission );
		return parent::getItemsWithPermission( $where, $order, $limit, $permissionKey, $includeHiddenItems, $queryFlags, $member, $joinContainer, $joinComments, $joinReviews, $countOnly, $joins, $skipPermission, $joinTags, $joinAuthor, $joinLastCommenter, $showMovedLinks );
	}
	
		/**
	 * WHERE clause for getItemsWithPermission
	 *
	 * @param	array		$where				Current WHERE clause
	 * @param	string		$permissionKey		A key which has a value in the permission map (either of the container or of this class) matching a column ID in core_permission_index
	 * @param	\IPS\Member	$member				The member (NULL to use currently logged in member)
	 * @param	bool		$joinContainer		If true, will join container data (set to TRUE if your $where clause depends on this data)
	 * @param	mixed		$skipPermission		If you are getting records from a specific container, pass the container to reduce the number of permission checks necessary or pass TRUE to skip container-based permission. You must still specify this in the $where clause
	 * @return	array
	 */
	public static function getItemsWithPermissionWhere( $where, $permissionKey, $member, &$joinContainer, $skipPermission=FALSE )
	{
		/* Don't show records from categories in which records only show to the poster */
		if ( $skipPermission !== TRUE and \in_array( $permissionKey, array( 'view', 'read' ) ) )
		{
			$member = $member ?: \IPS\Member::loggedIn();
			if ( !$member->modPermission( 'can_content_view_others_records' ) )
			{
				if ( $skipPermission instanceof \IPS\cms\Categories )
				{
					if ( !$skipPermission->can_view_others )
					{
						$where['item'][] = array( 'cms_custom_database_' . static::database()->id . '.member_id=?', $member->member_id );
					}
				}
				else
				{
					$joinContainer = TRUE;

					$where[] = array( '( category_can_view_others=1 OR cms_custom_database_' . static::database()->id . '.member_id=? )', $member->member_id );
				}
			}
		}
		
		/* Return */
		return $where;
	}
	
	/**
	 * Reaction Type
	 *
	 * @return	string
	 */
	public static function reactionType()
	{
		$databaseId = static::database()->_id;
		return "record_id_{$databaseId}";
	}
	
	/**
	 * Supported Meta Data Types
	 *
	 * @return	array
	 */
	public static function supportedMetaDataTypes()
	{
		return array( 'core_FeaturedComments', 'core_ContentMessages' );
	}

	/**
	 * Get content for embed
	 *
	 * @param	array	$params	Additional parameters to add to URL
	 * @return	string
	 */
	public function embedContent( $params )
	{
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'embed.css', 'cms', 'front' ) );
		return \IPS\Theme::i()->getTemplate( 'global', 'cms' )->embedRecord( $this, $this->url()->setQueryString( $params ) );
	}

	/**
	 * Give a content item the opportunity to filter similar content
	 * 
	 * @note Intentionally blank but can be overridden by child classes
	 * @return array|NULL
	 */
	public function similarContentFilter()
	{
		if( $this->record_topicid )
		{
			return array(
				array( '!(tag_meta_app=? and tag_meta_area=? and tag_meta_id=?)', 'forums', 'forums', $this->record_topicid )
			);
		}

		return NULL;
	}

	/**
	 * Get a count of the database table
	 *
	 * @param   bool    $approximate     Accept an approximate result if the table is large (approximate results are faster on large tables)
	 * @return  int
	 */
	public static function databaseTableCount( bool $approximate=FALSE ): int
	{
		if ( static::$databaseTable == NULL )
		{
			return 0;
		}
		else
		{
			return parent::databaseTableCount( $approximate );
		}
	}

	/**
	 * Get the last modification date for the sitemap
	 *
	 * @return \IPS\DateTime|null		timestamp of the last modification time for the sitemap
	 */
	public function lastModificationDate()
	{
		$lastMod = parent::lastModificationDate();

		if ( !$lastMod AND $this->record_updated )
		{
			$lastMod = \IPS\DateTime::ts( $this->record_updated );
		}

		return $lastMod;
	}

	/**
	 * Returns the earliest publish date for the new content item, we can have past items for records.
	 *
	 * @return \IPS\DateTime|null
	 */
	protected static function getMinimumPublishDate(): ?\IPS\DateTime
	{
		return NULL;
	}

	/**
	 * Can the publish date be changed while editing the item?
	 * 
	 * @var bool
	 */
	public static bool $allowPublishDateWhileEditing = TRUE;
	
}