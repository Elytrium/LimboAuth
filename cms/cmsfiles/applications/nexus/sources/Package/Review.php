<?php
/**
 * @brief		Product Review
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		5 May 2014
 */

namespace IPS\nexus\Package;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Package Review
 */
class _Review extends \IPS\Content\Review implements \IPS\Content\EditHistory, \IPS\Content\Hideable, \IPS\Content\Embeddable
{
	use \IPS\Content\Reportable;
	
	/**
	 * @brief	[ActiveRecord] Multiton Store
	 */
	protected static $multitons;
	
	/**
	 * @brief	[Content\Comment]	Item Class
	 */
	public static $itemClass = 'IPS\nexus\Package\Item';
	
	/**
	 * @brief	[ActiveRecord] Database Table
	 */
	public static $databaseTable = 'nexus_reviews';
	
	/**
	 * @brief	[ActiveRecord] Database Prefix
	 */
	public static $databasePrefix = 'review_';

	/**
	 * @brief	Title
	 */
	public static $title = 'product_reviews';
	
	/**
	 * @brief	Icon
	 */
	public static $icon = 'archive';
	
	/**
	 * @brief	Database Column Map
	 */
	public static $databaseColumnMap = array(
		'item'				=> 'product',
		'author'			=> 'author_id',
		'author_name'		=> 'author_name',
		'content'			=> 'text',
		'date'				=> 'date',
		'approved'			=> 'approved',
		'ip_address'		=> 'ip_address',
		'edit_time'			=> 'edit_date',
		'edit_show'			=> 'edit_show',
		'edit_member_name'	=> 'edit_member_name',
		'edit_reason'		=> 'edit_reason',
		'edit_member_id'	=> 'edit_member_id',
		'rating'			=> 'rating',
		'votes_total'		=> 'votes',
		'votes_helpful'		=> 'useful',
		'votes_data'		=> 'vote_data',
		'author_response'	=> 'author_response',
	);
	
	/**
	 * @brief	Application
	 */
	public static $application = 'nexus';
	
	/**
	 * @brief	Module
	 */
	public static $module = 'store';
	
	/**
	 * @brief	[Content]	Key for hide reasons
	 */
	public static $hideLogKey = 'nexus-products';
	
	/**
	 * @brief	[Content\Item]	First "comment" is part of the item?
	 */
	public static $firstCommentRequired = FALSE;
	
	/**
	 * @brief	Include In Sitemap
	 */
	public static $includeInSitemap = FALSE;
	
	/**
	 * Get content for header in content tables
	 *
	 * @return	callable
	 */
	public function contentTableHeader()
	{
		return \IPS\Theme::i()->getTemplate( 'global', static::$application )->commentTableHeader( $this, \IPS\nexus\Package::load( $this->item()->id ), $this->item() );
	}

	/**
	 * Get content for embed
	 *
	 * @param	array	$params	Additional parameters to add to URL
	 * @return	string
	 */
	public function embedContent( $params )
	{
		$memberCurrency = ( ( isset( \IPS\Request::i()->cookie['currency'] ) and \in_array( \IPS\Request::i()->cookie['currency'], \IPS\nexus\Money::currencies() ) ) ? \IPS\Request::i()->cookie['currency'] : \IPS\nexus\Customer::loggedIn()->defaultCurrency() );
		$package = \IPS\nexus\Package::load( $this->id );

		/* Do we have renewal terms? */
		$renewalTerm = NULL;
		$renewOptions = $package->renew_options ? json_decode( $package->renew_options, TRUE ) : array();
		if ( \count( $renewOptions ) )
		{
			$renewalTerm = TRUE;
			if ( \count( $renewOptions ) === 1 )
			{
				$renewalTerm = array_pop( $renewOptions );
				$renewalTerm = new \IPS\nexus\Purchase\RenewalTerm( new \IPS\nexus\Money( $renewalTerm['cost'][ $memberCurrency ]['amount'], $memberCurrency ), new \DateInterval( 'P' . $renewalTerm['term'] . mb_strtoupper( $renewalTerm['unit'] ) ), $package->tax ? \IPS\nexus\Tax::load( $package->tax ) : NULL, $renewalTerm['add'] );
			}
		}

		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'embed.css', 'nexus', 'front' ) );
		return \IPS\Theme::i()->getTemplate( 'global', 'nexus' )->embedProductReview( $this, $this->item(), $renewalTerm, $this->url()->setQueryString( $params ) );
	}
	
	/**
	 * Do stuff after creating (abstracted as comments and reviews need to do different things)
	 *
	 * @return	void
	 */
	public function postCreate()
	{
		parent::postCreate();
		
		/* If this review is moderated, then let the parent class do it's thing and if it didn't find a reason, see if this specific product requires reviews to be approved */
		if ( $this->hidden() === 1 )
		{
			try
			{
				\IPS\core\Approval::loadFromContent( \get_called_class(), $this->id );
			}
			catch( \OutOfRangeException $e )
			{
				/* No reason found - see if product requires approval of reviews */
				if ( $this->item()->review_moderate )
				{
					$log = new \IPS\core\Approval;
					$log->content_class	= \get_called_class();
					$log->content_id	= $this->id;
					$log->held_reason	= 'item';
					$log->save();
				}
			}
		}
	}
}