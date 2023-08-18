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

namespace IPS\blog\extensions\core\RssImport;

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

	public $fileStorage = 'blog_Blogs';

	/**
	 * Constructor
	 *
	 * @return	void
	 */
	public function __construct()
	{
		$this->classes = array( 'IPS\\blog\\Entry' );
	}

	/**
	 * Return available options for a Form\Select
	 *
	 * @return array
	 */
	public function availableOptions()
	{
		/* We don't want to set up Blog feeds in the ACP */
		return array();
	}

	/**
	 * Show in the Admin CP?
	 *
	 * @param	Object 	$class	The class to check
	 * @return boolean
	 */
	public function showInAdminCp( $class ): bool
	{
		return false;
	}

	/**
	 * Node selector options
	 *
	 * @param 	\IPS\core\Rss\Import|null	$rss	Existing RSS object if editing|NULL if not
	 * @return array
	 */
	public function nodeSelectorOptions( $rss )
	{
		return array( 'class' => 'IPS\blog\Blog', 'permissionCheck' => 'view' );
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
		$entry = $class::createItem( $member, NULL, $article['date'], $container );
		$entry->name = $article['title'];
		$entry->content = $content;
		$entry->status = 'published';
		$entry->save();

		/* Add to search index */
		\IPS\Content\Search\Index::i()->index( $entry );

		/* Send notifications */
		$entry->sendNotifications();

		$entry->setTags( $settings['tags'], $member );

		return $entry;
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
		/* Blogs has it's own front end controller */
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
		/* Blogs has it's own front end controller */
		return array( $values );
	}
}