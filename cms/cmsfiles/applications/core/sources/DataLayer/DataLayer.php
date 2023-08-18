<?php
/**
 * @brief		Datalayer Class
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		25 Jun 2013
 */

namespace IPS\core;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Datalayer Class
 */
class _DataLayer extends \IPS\Patterns\Singleton
{
	/**
	 * @brief	Singleton Instance
	 */
	protected static $instance = NULL;

	/**
	 * @brief events as an associative array
	 */
	protected $events = array();

	/**
	 * @brief   Key store for the context
	 */
	protected $context = array();

	/**
	 *
	 * @brief These are the available events. The setting changes the 'enabled' and 'formatted_name' properties before processing the event on the front end
	 */
	protected $_eventConfiguration;
	protected static $defaultEventConfiguration = array(
		'content_create'    => [
			'enabled'           => false,
			'description'       => 'a Content Item is created. This could be a Forum Topic, Blog Post, Gallery Image etc.',
			'short'             => 'Content Item was created',
			'formatted_name'    => 'content_create',
		],
		'content_view'      => [
			'enabled'           => false,
			'description'       => 'a Content Item is viewed. Can be either the full page or the Hovercard that shows in the Content Item listings.',
			'short'             => 'Content Item was viewed',
			'formatted_name'    => 'content_view',
		],
		'content_comment'      => [
			'enabled'           => false,
			'description'       => 'a Content Item is commented on (or replied to).',
			'short'             => 'Reply or Comment was posted',
			'formatted_name'    => 'content_comment',
		],
		'content_react'     => [
			'enabled'           => false,
			'description'       => 'content is reacted to (like, dislike, etc).',
			'short'             => 'User reacted to content',
			'formatted_name'    => 'content_react',
		],
		'account_login'     => [
			'enabled'           => true,
			'description'       => 'a Member successfully logs in. Note this will not fire unless they previously visited the site logged out.',
			'short'             => 'Member logged in',
			'formatted_name'    => 'account_login',
		],
		'account_logout'     => [
			'enabled'           => true,
			'description'       => 'a Member logs out.',
			'short'             => 'Member logged out',
			'formatted_name'    => 'account_logout',
		],
		'account_register'     => [
			'enabled'           => true,
			'description'       => 'a Member successfully registers a new Account. Note this is different than completing Profile Registration. This event will fire on the first page load after registration.',
			'short'             => 'New Account is registered',
			'formatted_name'    => 'account_register',
		],
		'search'     => [
			'enabled'           => true,
			'description'       => 'a site search is executed.',
			'short'             => 'Something was searched for',
			'formatted_name'    => 'search',
		]
	);

	/**
	 * @brief These are the available properties. The setting changes the 'enabled' and 'formatted_name' properties before processing the event on the front end
	 */
	protected $_propertiesConfiguration;
	protected static $defaultPropertiesConfiguration = array(
		'content_id'    => [
			'event_keys'        => ['content_*', 'file_download'],
			'pii'               => false,
			'formatted_name'    => 'content_id',
			'description'       => 'ID of a Content Item.',
			'enabled'           => true,
			'type'              => 'number',
			'page_level'        => true,
		],
		'content_title'    => [
			'event_keys'        => ['content_*', 'file_download'],
			'pii'               => true,
			'formatted_name'    => 'content_title',
			'description'       => 'Title of a Content Item.<br><br>It is <strong>possible</strong> for this Property to contain PII since a Member could add identifying personal details in the title of a Content Item. Therefore, it is treated as PII and will be removed if the Content Author\'s PII is not being collected.',
			'short'             => 'Title of a Content Item',
			'enabled'           => true,
			'type'              => 'string',
			'page_level'        => true,
		],
		'content_url'    => [
			'event_keys'        => ['content_*', 'file_download'],
			'pii'               => false,
			'formatted_name'    => 'content_url',
			'description'       => 'URL of a Content Item',
			'enabled'           => true,
			'type'              => 'string',
			'page_level'        => true,
		],
		'author_id'    => [
			'event_keys'        => ['content_*', 'social_reply', 'file_download'],
			'pii'               => true,
			'formatted_name'    => 'author_id',
			'description'       => 'Member ID of the author of a Content Item or Comment. Will be a number unless SSO IDs are being used. The software will try to convert SSO Identifiers to a number, using a string if it cannot be converted.',
			'short'             => 'Content Author\'s Member ID',
			'enabled'           => true,
			'type'              => 'number | string',
			'replace_with_sso'  => true,
			'page_level'        => true,
		],
		'author_name'    => [
			'event_keys'        => ['content_*', 'social_reply', 'file_download'],
			'pii'               => true,
			'formatted_name'    => 'author_name',
			'description'       => 'Display Name of the Author of a Content Item or Comment.',
			'short'             => 'Content Author\'s Name',
			'enabled'           => true,
			'type'              => 'string',
			'page_level'        => true,
		],
		'page_number'    => [
			'event_keys'        => ['content_view', 'query', 'filter', 'sort'],
			'pii'               => false,
			'formatted_name'    => 'page_number',
			'description'       => 'For Paginated areas such as Topic Listings, Comment Feeds, Search Results, and Activity Streams, this is the <span class="ipsType_monospace">page_number</span> that the event occurred on. Starts counting at 1. If there is only one page, will be 1.',
			'short'             => 'Currently viewed page',
			'enabled'           => true,
			'type'              => 'number',
			'page_level'        => true,
		],
		'comment_id'    => [
			'event_keys'        => ['content_comment', 'content_react', 'content_quote'],
			'pii'               => false,
			'formatted_name'    => 'comment_id',
			'description'       => 'The internal ID of a Comment, Reply, or Review. Note this is type specific, so two Comments with different <span class="ipsType_monospace">comment_type</span>s can possibly have the same ID.',
			'short'             => 'ID of a Comment, Reply or Review',
			'enabled'           => true,
			'type'              => 'number',
			'page_level'        => false,
		],
		'comment_url'    => [
			'event_keys'        => ['content_comment', 'content_react', 'content_quote'],
			'pii'               => false,
			'formatted_name'    => 'comment_url',
			'description'       => 'The URL of a Comment, Reply, or Review. Note this can change under certain circumstances, so use the <span class="ipsType_monospace">comment_id</span> and <span class="ipsType_monospace">comment_type</span> if you need to identify the Comment/Review/Reply.',
			'short'             => 'URL of a Comment, Reply, or Review',
			'enabled'           => true,
			'type'              => 'string',
			'page_level'        => false,
		],
		'content_age'    => [
			'event_keys'        => ['content_*', 'file_download'],
			'pii'               => false,
			'formatted_name'    => 'content_age',
			'description'       => 'Age (in days) of a Content Item being interacted with.',
			'short'             => 'Age in days',
			'enabled'           => true,
			'type'              => 'number',
			'page_level'        => true,
		],
		'file_name'    => [
			'event_keys'        => ['file_download'],
			'pii'               => false,
			'formatted_name'    => 'file_name',
			'description'       => 'Name of the downloaded File (as opposed to the name of the File\'s Content Item).',
			'short'             => 'File Name',
			'enabled'           => true,
			'type'              => 'string',
			'page_level'        => false,
		],
		'content_area'    => [
			'event_keys'        => ['content_*', 'filter_*', 'sort', 'file_download'],
			'pii'               => false,
			'formatted_name'    => 'content_area',
			'description'       => 'The site Area (app) in which the Content being interacted with is located. Different from <span class="ipsType_monospace">community_area</span> which is the Area that the Member is viewing. For example, if a member hovers over a Topic located in search results to see the Hovercard, the Topic\'s <span class="ipsType_monospace">content_area</span> is Forums, while the page itself is Search.',
			'short'             => 'Area of the content',
			'enabled'           => true,
			'type'              => 'string',
			'page_level'        => true,
		],
		'community_area'    => [
			'event_keys'        => ['filter_*', 'sort'],
			'pii'               => false,
			'formatted_name'    => 'community_area',
			'description'       => 'The Area (app) the user is viewing. Different from <span class="ipsType_monospace">content_area</span> which is the area that the content is in. For example, if a member hovers over a Topic located in search results to see the Hovercard, the Topic\'s <span class="ipsType_monospace">content_area</span> is Forums, while the <span>community_area</span> of the page itself is Search.',
			'short'             => 'Area of the page being viewed',
			'enabled'           => true,
			'type'              => 'string',
			'page_level'        => true,
		],
		'content_container_path'  => [
			'event_keys'        => ['content_*'],
			'pii'               => false,
			'formatted_name'    => 'content_container_path',
			'description'       => <<<HTML
An array of JavaScript objects representing, all the Containers and Subcontainers leading to the current Content Item/Container. For example, if you have a container Forum "Support" and someone's browsing a Q/A Subforum "FAQ", you will see something like this pushed to the data layer:
<pre class="ipsCode prettyprint lang-javascript prettyprinted">
[
  {
    type: 'forum',
    id: 1,
    name: 'Support'
  },
  {
    type: 'forum',
    id: 7,
    name: 'FAQ'
  }
]
</pre>
HTML,
			'short'             => 'An array of all the ancestors of the current Content Item or Container.',
			'enabled'           => false,
			'type'              => 'array',
			'page_level'        => true,
			'default'           => []
		],
		'content_type'    => [
			'event_keys'        => ['content_*', 'filter_*', 'sort', 'file_download'],
			'pii'               => false,
			'formatted_name'    => 'content_type',
			'description'       => 'The type of the Content <strong>Item</strong>: Topic, Entry, Record, etc.',
			'short'             => 'Type of Content Item',
			'enabled'           => true,
			'type'              => 'string',
			'page_level'        => true,
		],
		'comment_type'    => [
			'event_keys'        => ['content_comment', 'content_react', 'content_quote'],
			'pii'               => false,
			'formatted_name'    => 'comment_type',
			'description'       => 'The type of the Content <strong>Comment</strong>: Reply, Comment, Answer, Review, etc.',
			'short'             => 'Type of Content Comment',
			'enabled'           => true,
			'type'              => 'string',
			'page_level'        => false,
		],
		'content_container_id'    => [
			'event_keys'        => ['content_*', 'filter_*', 'sort', 'file_download'],
			'pii'               => false,
			'formatted_name'    => 'content_container_id',
			'description'       => 'The internal ID of the Content Container, such as the Forum <strong>containing</strong> a Topic.',
			'short'             => 'ID of the Content\'s Container',
			'enabled'           => true,
			'type'              => 'number',
			'page_level'        => true,
		],
		'content_container_name'    => [
			'event_keys'        => ['content_*', 'filter_*', 'sort', 'file_download'],
			'pii'               => false,
			'formatted_name'    => 'content_container_name',
			'description'       => 'The name of the Content Container, such as the Forum <strong>containing</strong> a Topic.',
			'short'             => 'Name of the Content\'s Container',
			'enabled'           => true,
			'type'              => 'string',
			'page_level'        => true,
		],
		'content_container_type'    => [
			'event_keys'        => ['content_*', 'filter_*', 'sort', 'file_download'],
			'pii'               => false,
			'formatted_name'    => 'content_container_type',
			'description'       => 'The actual type of the Content Container, lowercase without spaces (snake case), such as <span class="ipsType_monospace">forum</span>, <span class="ipsType_monospace">blog_category</span>, etc.',
			'short'             => 'Type of the Content\'s Container',
			'enabled'           => true,
			'type'              => 'string',
			'page_level'        => true,
		],
		'content_container_url'    => [
			'event_keys'        => ['content_*', 'filter_*', 'sort', 'file_download'],
			'pii'               => false,
			'formatted_name'    => 'content_container_url',
			'description'       => 'The URL to the Container of Content (Front end not AdminCP).',
			'short'             => 'URL of the Content\'s Container',
			'enabled'           => true,
			'type'              => 'string',
			'page_level'        => true,
		],
		'ips_key'    => [
			'event_keys'        => ['*'],
			'pii'               => false,
			'formatted_name'    => 'ips_key',
			'description'       => 'This property is a pseudo-random token used to distinguish the page or event (to prevent duplicates). Out of the box, our GTM Integration only pushes events once so <strong>the only case on which you might need to use this is if you have set up other Custom Data Layer Handlers</strong>.',
			'short'             => 'Pseudo-random, Event-specific ID',
			'enabled'           => false,
			'type'              => 'string',
			'page_level'        => true,
		],
		'ips_time'    => [
			'event_keys'        => ['*'],
			'pii'               => false,
			'formatted_name'    => 'ips_time',
			'description'       => 'The Unix Timestamp of when the event was created by IPS. Though we try to keep this as accurate as possible, it is not guaranteed to be the exact time the event occurred. It differs from the timestamp GTM generates in that it\'s generated server-side, so things like Registration where the Member might not load a page (and trigger the registration event) for a few minutes after they actually register.',
			'short'             => 'Numeric Timestamp (Unix)',
			'enabled'           => true,
			'type'              => 'number',
			'page_level'        => true,
		],
		'logged_in'    => [
			'event_keys'        => [],
			'pii'               => false,
			'formatted_name'    => 'logged_in',
			'description'       => 'Either <span class="ipsType_monospace">0</span> or <span class="ipsType_monospace">1</span>, denoting whether or the user viewing the page is Guest or a logged in Member.',
			'short'             => 'Whether the visitor is logged in',
			'enabled'           => true,
			'type'              => 'number',
			'page_level'        => true,
		],
		'logged_in_time'    => [
			'event_keys'        => [],
			'pii'               => false,
			'formatted_name'    => 'logged_in_time',
			'description'       => 'Number of minutes since the currently logged in Member\'s session started. This is <span class="ipsType_monospace">undefined</span> if the member is not logged in.',
			'short'             => 'Minutes since the visitor logged in',
			'enabled'           => true,
			'type'              => 'number',
			'page_level'        => true,
		],
		'member_group'    => [
			'event_keys'        => [],
			'pii'               => false,
			'formatted_name'    => 'member_group',
			'description'       => 'The logged in Member\'s Primary Group Name.',
			'short'             => 'Logged in Member\'s Group',
			'enabled'           => true,
			'type'              => 'string',
			'page_level'        => true,
		],
		'member_group_id'    => [
			'event_keys'        => [],
			'pii'               => false,
			'formatted_name'    => 'member_group_id',
			'description'       => 'The internal ID of the logged in Member\'s Primary Group.',
			'short'             => 'Logged in Member\'s Group ID',
			'enabled'           => true,
			'type'              => 'number',
			'page_level'        => true,
		],
		'member_id'    => [
			'event_keys'        => [],
			'pii'               => true,
			'formatted_name'    => 'member_id',
			'description'       => 'The ID of the Member viewing the page or executing an event. If the user is logged out, this will have the value of <span class="ipsType_monospace">undefined</span>.',
			'short'             => 'Logged in Member\'s ID',
			'enabled'           => true,
			'type'              => 'number | string',
			'page_level'        => true,
			'replace_with_sso'  => true,
		],
		'member_name'    => [
			'event_keys'        => [],
			'pii'               => true,
			'formatted_name'    => 'member_name',
			'description'       => 'The Display Name of the Member viewing the page or executing an event',
			'short'             => 'Logged on Member\'s Name',
			'enabled'           => true,
			'type'              => 'string',
			'page_level'        => true,
		],
		'profile_id'    => [
			'event_keys'        => ['social_*'],
			'pii'               => true,
			'formatted_name'    => 'profile_id',
			'description'       => 'The ID of the Member who\'s Profile is being acted on (viewed or updated).',
			'short'             => 'ID of a Profile\'s Member',
			'enabled'           => true,
			'type'              => 'number | string',
			'page_level'        => true,
			'replace_with_sso'  => true,
		],
		'profile_name'    => [
			'event_keys'        => ['social_*'],
			'pii'               => true,
			'formatted_name'    => 'profile_name',
			'description'       => 'The Display Name of the Member who\'s Profile is being acted on (viewed or updated).',
			'short'             => 'Name of a Profile\'s Member',
			'enabled'           => true,
			'type'              => 'string',
			'page_level'        => true,
		],
		'view_location'    => [
			'event_keys'        => ['*_view'],
			'pii'               => false,
			'formatted_name'    => 'view_location',
			'description'       => 'How the Content or Profile is being viewed. \'page\' for the actual page or \'hovercard\' for the Hovercard that appears when hovering over Content or a Profile.',
			'short'             => 'Is a full page or Hovercard being viewed',
			'enabled'           => true,
			'type'              => 'string',
			'page_level'        => true,
			'default'           => 'page'
		],
		'profile_group'    => [
			'event_keys'        => ['social_*'],
			'pii'               => false,
			'formatted_name'    => 'profile_group',
			'description'       => 'The name of the Primary Group of the Member whose Profile is being acted on.',
			'short'             => 'Member group of a Profile\'s Member',
			'enabled'           => true,
			'type'              => 'string',
			'page_level'        => true,
		],
		'profile_group_id'    => [
			'event_keys'        => ['social_*'],
			'pii'               => false,
			'formatted_name'    => 'profile_group_id',
			'description'       => 'The internal ID of the Primary Group of the Member whose Profile is being acted on.',
			'short'             => 'Member group ID of a Profile\'s Member',
			'enabled'           => true,
			'type'              => 'number',
			'page_level'        => true,
		],
		'reaction_type'    => [
			'event_keys'        => ['content_react'],
			'pii'               => false,
			'formatted_name'    => 'reaction_type',
			'description'       => 'The \'type\' of Reaction, which is the title given to the Reaction in AdminCP > Members > Reputation & Reactions.',
			'short'             => 'Which type of Reaction',
			'enabled'           => true,
			'type'              => 'string',
			'page_level'        => false,
		],
		'sort_by'    => [
			'event_keys'        => ['*sort'],
			'pii'               => false,
			'formatted_name'    => 'sort_by',
			'description'       => 'The field or property a listing is being sorted by. Could be <span class="ipsType_monospace">start_date</span>, <span class="ipsType_monospace">name</span>, <span class="ipsType_monospace">views</span>, etc.',
			'short'             => 'Property being sorted by',
			'enabled'           => true,
			'type'              => 'string',
			'page_level'        => true,
		],
		'sort_direction'    => [
			'event_keys'        => ['*sort'],
			'pii'               => false,
			'formatted_name'    => 'sort_direction',
			'description'       => 'The direction in which a listing was sorted, ascending or descending denoted as <span class="ipsType_monospace">asc</span> or <span class="ipsType_monospace">desc</span>.',
			'short'             => 'Direction being sorted in',
			'enabled'           => true,
			'type'              => 'string',
			'page_level'        => true,
		],
		'query'    => [
			'event_keys'        => ['search'],
			'pii'               => false,
			'formatted_name'    => 'query',
			'description'       => 'The search text entered into a site search.',
			'short'             => 'Text being searched',
			'enabled'           => true,
			'type'              => 'string',
			'page_level'        => false,
		],
		'filter_title'    => [
			'event_keys'        => ['filter_*'],
			'pii'               => false,
			'formatted_name'    => 'filter_title',
			'description'       => 'The title of the filter added or removed by the Member.',
			'short'             => 'Property filtered by',
			'enabled'           => true,
			'type'              => 'string',
			'page_level'        => false,
		],
	);

	/**
	 * @breif These groups of properties each pertain to the same member and should all be removed if that member has elected to not have their pii collected
	 */
	protected static $_relatedPiiProperties = array(
		array(
			'author_id',
			'author_name',
		),
		array(
			'member_id',
			'member_name',
		),
		array(
			'profile_id',
			'profile_name',
		),
	);

	/**
	 * Get value from data store
	 *
	 * @param	mixed	$key	Key
	 * @return	mixed	Value from the datastore
	 */
	public function __get( $key )
	{
		$property = parent::__get( $key );

		if ( $property === null AND method_exists( $this, 'get_' . $key ) )
		{
			$method = 'get_' . $key;
			$property = $this->$method();
			parent::__set( $key, $property );
		}

		return $property;
	}

	/**
	 * Cache these in the session if we're not a guest
	 */
	public function cache()
	{
		if ( \IPS\Member::loggedIn()->member_id )
		{
			$_SESSION['ipsDataLayerEvents'] = $this->events;
		}
	}

	/**
	 * Clear the cached events
	 *
	 * @return void
	 */
	public function clearCache()
	{
		$_SESSION['ipsDataLayerEvents'] = array();
	}

	/**
	 * Instance
	 *
	 * @return \IPS\cloud\DataLayer | static
	 */
	public static function i()
	{
		if ( empty( static::$instance ) )
		{
			if ( ( \IPS\IN_DEV OR \IPS\CIC ) AND \class_exists( '\IPS\cloud\DataLayer' ) )
			{
				static::$instance = new \IPS\cloud\DataLayer;
			}
			else
			{
				static::$instance = new static;
			}

			static::$instance->init();

			if ( \IPS\Member::loggedIn()->member_id )
			{
				static::$instance->events = $_SESSION['ipsDataLayerEvents'] ?? array();
			}
		}

		return static::$instance;
	}

	/**
	 * Initialize this instance
	 *
	 * @return void
	 */
	protected function init()
	{
		$this->_eventConfiguration = static::$defaultEventConfiguration;
		$this->_propertiesConfiguration = static::$defaultPropertiesConfiguration;
	}

	/**
	 * Adds event to be loaded in the front end. Note that this does not check if the event is enabled (the check is done on front end however).
	 *
	 * @see $_eventConfiguration, $_propertiesConfiguration for key and property options
	 *
	 * @param   string  $key                The event's key, will be added to $values.
	 * @param   array   $values             Array of values. On output, $key is added as a property, so $values['event'] will be overridden
	 * @param   bool    $odkUpdate          Update if the key is already declared for this output? (only one event per key per request)
	 * @param   bool    $validateProperties Only retain a property in $values if it is recognized.
	 *
	 * @return  void
	 */
	public function addEvent( string $key, array $values, $odkUpdate=true )
	{
		/* Is it set */
		if ( !$odkUpdate AND isset( $this->events[$key] ) )
		{
			return;
		}

		/* Skip if it's disabled */
		$events = $this->eventConfiguration;
		if ( empty( $events[$key]['enabled'] ) )
		{
			return;
		}

		/* This one is special since we want to set it to the server/ips system time */
		$values['ips_time'] = time();

		/* Done by the JS on the front end to prevent the key from being cached.
		$values['ips_key']  = uniqid( '', true );
		*/

		/* Pull out only the properties that are configured to be used */
		$_values    = array();
		$properties = $this->getEventProperties( $key, true );
		$piiProperties = array();
		foreach( $values as $propertyKey => $value )
		{
			/* Is this property valid */
			if ( !isset( $properties[ $propertyKey ] ) )
			{
				continue;
			}

			/* It this property contains PII, make sure PII is allowed */
			if ( ( $properties[$propertyKey]['pii'] ?? 0 ) AND ! \IPS\Settings::i()->core_datalayer_include_pii )
			{
				continue;
			}

			/* Get SSO id if needed */
			if ( $properties[$propertyKey]['replace_with_sso'] ?? 0 )
			{
				/* If we know this member doesn't want their PII exposed, don't expose it */
				if (
					\IPS\Settings::i()->core_datalayer_member_pii_choice AND
					$value AND
					( $member = \IPS\Member::load( $value ) ) AND
					$member->member_id AND
					$member->members_bitoptions['datalayer_pii_optout']
				)
				{
					$piiProperties = array_merge( $piiProperties, $this->relatedPiiProperties( $propertyKey ) );
					continue;
				}
				$value = $this->getSsoId( $value );
			}

			$_values[$propertyKey] = $value;
		}

		/* Make sure these are omitted */
		foreach ( $piiProperties as $property )
		{
			unset( $_values[$property] );
		}

		$values = $_values;

		$this->events[$key] = array_merge( ( $this->events[$key] ?? [] ), $values );
	}

	/**
	 * Filters out dataLayer properties that are not enabled or cannot be used for PII reasons. Does not verify properties have a valid type and/or value as this is done by the JS on the front end
	 *
	 * @param   array   $properties     The properties to be parsed. Member identifiers should have been passed to getSsoId() already
	 *
	 * @return  array
	 */
	public function filterProperties( array $properties )
	{
		$output         = array();
		$piiExcludes    = array();
		$pii            = \IPS\Settings::i()->core_datalayer_include_pii;
		foreach ( $this->propertiesConfiguration as $key => $property )
		{
			if ( !$property['enabled'] OR !isset( $properties[$key] ) )
			{
				continue;
			}

			if ( !$pii AND ( $property['pii'] ?? 0 ) )
			{
				continue;
			}

			/* Check the member's pii preference. If we can verify the member's PII should NOT be exposed, don't allow it to be added */
			if ( \IPS\Settings::i()->core_datalayer_member_pii_choice AND
			     @$property['replace_with_sso'] AND
			     ( $originalID = array_search( $properties[$key], $this->ssoIds ) )
			)
			{
				$member = \IPS\Member::load( $originalID );
				if ( $member->member_id AND $member->members_bitoptions['datalayer_pii_optout'] )
				{
					$piiExcludes = array_merge( $piiExcludes, $this->relatedPiiProperties( $key ) );
					continue;
				}
			}

			$output[$key] = $properties[$key];
		}

		/* Actually make sure these all get taken out */
		foreach ( $piiExcludes as $key )
		{
			$output[$key] = null;
		}

		return $output;
	}

	/**
	 * The PII properties can be grouped based on the member they relate to (logged in member, author, profile owner)
	 *
	 * @param   string      $property       The pii containing property
	 *
	 * @return  array
	 */
	public function relatedPiiProperties( string $property )
	{
		$return = array();

		foreach ( static::$_relatedPiiProperties as $group )
		{
			if ( \in_array( $property, $group ) )
			{
				$return = array_merge( $return, $group );
			}
		}

		return array_unique( $return );
	}

	/**
	 * Unsets an event
	 *
	 * @param   string     $key     Event key to unset since the array is protected
	 *
	 * @return  void
	 */
	public function unsetEvent( string $key )
	{
		unset( $this->events[$key] );
	}

	/**
	 * Adds a property to be loaded as page level. The property must be specified in propertiesConfiguration as page level
	 *
	 * @param   string  $key    the key
	 * @param   mixed   $value  the page level value. Will be cast to string or number in the JS if the property's config specifies it
	 * @param   bool    $odkUpdate  Update if it's set already?
	 *
	 * @return void
	 */
	public function addContextProperty( string $key, $value, bool $odkUpdate=true )
	{
		/* Did we set it already? */
		if ( !$odkUpdate AND isset( $this->context[$key] ) )
		{
			return;
		}

		$properties = $this->propertiesConfiguration;

		/* Verify it exists, is recognized as enabled and page level */
		if ( !isset( $properties[$key] ) OR !( $properties[$key]['enabled'] ?? 0 ) OR !( $properties[$key]['page_level'] ?? 0 ) )
		{
			return;
		}

		/* If this has PII, is PII allowed? */
		if ( ( $properties[$key]['pii'] ?? 0 ) AND ! \IPS\Settings::i()->core_datalayer_include_pii )
		{
			return;
		}

		/* Replace with sso id if needed */
		if ( $properties[$key]['replace_with_sso'] ?? 0 )
		{
			$value = $this->getSsoId( $value );
		}
		elseif ( \is_null( $value ) AND isset( $properties[$key]['default'] ) )
		{
			$value = $properties[$key]['default'];
		}

		/* Add it */
		$this->context[$key] = $value;
	}

	/**
	 * @brief   field cache for the sso ids
	 */
	protected $ssoIds = array();


	/**
	 * Get the selected SSO Handler's Identifier associated with a Member's ID.
	 *      If the selected SSO Handler doesn't have an Identifier associated with the Member ID, returns NULL
	 *      If no SSO Handler has been selected, this just returns the internal ID
	 *
	 * Use this method if Data Layer Properties are not first being passed to addEvent() or addContextProperty()
	 *
	 * @param   int|null    $member_id      The internal member's id, or null for the logged in member
	 *
	 * @return  int|string|null
	 */
	public function getSsoId( ?int $member_id=null )
	{
		$member_id = $member_id ?? \IPS\Member::loggedIn()->member_id;

		/* We want NULL for lack of ID */
		if ( !$member_id )
		{
			return null;
		}

		if ( !isset( $this->ssoIds[$member_id] ) )
		{
			if ( $sso = \IPS\Settings::i()->core_datalayer_replace_with_sso )
			{
				try
				{
					$this->ssoIds[$member_id] = \IPS\Db::i()->select( 'token_identifier', 'core_login_links', ['token_member=? AND token_login_method=?', $member_id, $sso], null, 1 )->first();
				}
				catch ( \UnderflowException $e )
				{
					$this->ssoIds[$member_id] = null;
				}
			}
			else
			{
				$this->ssoIds[$member_id] = $member_id;
			}
		}

		return $this->ssoIds[$member_id];
	}

	/**
	 * Gets the page level context as a valid JavaScript const assignment. The JS Object's properties will be the formatted name of the datalayer property
	 *
	 * @return string
	 */
	protected function get_jsContext()
	{
		/* What has been added during this request? */
		$_context = $this->context;
		$properties = $this->propertiesConfiguration;

		/* Generate system level values */
		if ( $properties['logged_in']['enabled'] )
		{
			$_context['logged_in'] = (int)( (bool)\IPS\Member::loggedIn()->member_id );
		}

		if ( $properties['logged_in_time']['enabled'] )
		{
			$duration = null;

			if ( \IPS\Member::loggedIn()->member_id AND $sessionFrontCookie = \IPS\Request::i()->cookie['IPSSessionFront'] ?? null )
			{
				$_dataLayerLogin = json_decode( base64_decode( @\IPS\Request::i()->cookie['dataLayerLogin'] ?: '' ), true ) ?: array();
				$dataLayerLogin = array(
					'session' => (int) $sessionFrontCookie,
					'time'    => time(),
				);

				if ( !isset( $_dataLayerLogin['time'] ) or !\is_numeric( $_dataLayerLogin['time'] ) or @$_dataLayerLogin['session'] !== $dataLayerLogin['session'] )
				{
					\IPS\Request::i()->setCookie( 'dataLayerLogin', base64_encode( json_encode( $dataLayerLogin ) ) );
					$_dataLayerLogin = $dataLayerLogin;
				}

				$duration = \intval( ( time() - \intval( $_dataLayerLogin['time'] ) ) / 60 );
			}

			$_context['logged_in_time'] = $duration;
		}

		/* Logged In Member PII */
		if ( \IPS\Settings::i()->core_datalayer_include_pii AND ( !\IPS\Settings::i()->core_datalayer_member_pii_choice OR !\IPS\Member::loggedIn()->members_bitoptions['datalayer_pii_optout'] ) )
		{
			if ( $properties['member_id']['enabled'] )
			{
				$_context['member_id'] = $this->getSsoId();
			}

			if ( $properties['member_name']['enabled'] )
			{
				$_context['member_name'] = \IPS\Member::loggedIn()->real_name ?: null;
			}
		}

		/* Logged In Member non-PII */
		if ( $properties['member_group']['enabled'] )
		{
			$gid = \IPS\Member::loggedIn()->group['g_id'] ?: \IPS\Settings::i()->guest_group;
			$_context['member_group'] = \IPS\Lang::load( \IPS\Lang::defaultLanguage() )->addToStack( "core_group_{$gid}" );
		}

		if ( $properties['member_group_id']['enabled'] )
		{
			$_context['member_group_id'] = \intval( $gid ?? \IPS\Member::loggedIn()->group['g_id'] ?: \IPS\Settings::i()->guest_group );
		}

		/* The Community/Content Area */
		if ( $properties['community_area']['enabled'] and !isset( $_context['community_area'] ) )
		{
			if ( \IPS\Dispatcher::i()->application )
			{
				$directory = \IPS\Dispatcher::i()->application->directory;
				$app = \strtolower( \IPS\Lang::load( \IPS\Lang::defaultLanguage() )->addToStack( "__app_{$directory}" ) );
			}
			else
			{
				$app = $directory ?? null;
				$app = ( $app === 'nexus' ) ? 'commerce' : $app;
			}

			$app = ( $app === 'system' ) ? 'general' : $app;

			$_context['community_area'] = $app;
		}

		if ( !isset( $_context['content_area'] ) and isset( $_context['community_area'] ) )
		{
			$_context['content_area'] = $_context['community_area'];
		}

		/* Pagination */
		if ( isset( $_context['page_number'] ) and \is_string( $_context['page_number'] ) )
		{
			$key = $_context['page_number'] ?? "";
			$_context['page_number'] = \intval( \IPS\Request::i()->$key ) ?: 1;
		}

		$context = array();
		$piiExcludes = array();
		foreach ( $properties as $key => $property )
		{
			/* Make sure all are page level, enabled, and valid */
			if ( !$property['enabled'] or !$property['page_level'] )
			{
				continue;
			}
			$fkey = $property['formatted_name'];

			/* If we can determine a member based on this property's PII, only include the related PII if we know that member hasn't opted out? */
			if (
				( $property['replace_with_sso'] ?? 0 ) AND
				\IPS\Settings::i()->core_datalayer_include_pii AND
				\IPS\Settings::i()->core_datalayer_member_pii_choice AND
				isset( $_context[ $key ] ) AND
				( $originalId = array_search( $_context[ $key ], $this->ssoIds ) )
			)
			{
				$member = \IPS\Member::load( $originalId );
				if ( !( $member->member_id AND !$member->members_bitoptions['datalayer_pii_optout'] ) )
				{
					$piiExcludes = array_merge( $piiExcludes, $this->relatedPiiProperties( $key ) );
					continue;
				}
			}

			/* Add context properties set by the system */
			if ( isset( $_context[ $key ] ) )
			{
				$context[ $fkey ] = $_context[ $key ];
				continue;
			}

			/* Get custom properties */
			if ( ( $property['custom'] ?? 0 ) AND ( $property['enabled'] ) )
			{
				$context[ $fkey ] = $property['value'] ?: null;
				continue;
			}

			$context[ $fkey ] = $property['default'] ?? null;
		}

		/* For the pii belonging to members who don't want it exposed, nullify it here */
		foreach ( $piiExcludes as $key )
		{
			$fkey           = $properties[$key]['formatted_name'];
			$context[$fkey] = null;
		}

		if ( \IPS\IN_DEV )
		{
			$out = "const IpsDataLayerContext = " . str_replace( "\n", "\n\t", json_encode( $context, JSON_PRETTY_PRINT ) ) . ';';
		}
		else
		{
			$out = "const IpsDataLayerContext = " . json_encode( $context ) . ';';
		}

		\IPS\Lang::load( \IPS\Lang::defaultLanguage() )->parseOutputForDisplay( $out );
		return str_replace( ["\u{2028}", "\u{2029}"], ['', ''], $out );
	}


	public static $configCacheKey = 'dataLayerConfig';

	/**
	 * Event and Property configuration as a valid JavaScript const assignment
	 *
	 * @return  string
	 */
	public function get_jsConfig()
	{
		$key = static::$configCacheKey . '_jsConfig';
		try
		{
			$cached = \IPS\Data\Store::i()->$key;
			if ( $cached )
			{
				return $cached;
			}
		}
		catch ( \OutOfRangeException $e ) {}

		$config = array(
			'_events'       => $this->eventConfiguration,
			'_properties'   => $this->propertiesConfiguration,
			'_pii'          => (bool) \IPS\Settings::i()->core_datalayer_include_pii,
			'_pii_groups'   => static::$_relatedPiiProperties,
		);

		/* Take out descriptions because they are not needed, and it reduces the size in the page source. Also, it reduces readability to general pop (people who this concerns can use acp) */
		foreach( [ '_events', '_properties' ] as $group )
		{
			foreach( array_keys( $config[$group] ) as $array_key )
			{
				unset( $config[$group][$array_key]['description'] );
				unset( $config[$group][$array_key]['short'] );
			}
		}

		if ( \IPS\IN_DEV )
		{
			$out = "const IpsDataLayerConfig = " . str_replace( "\n", "\n\t", json_encode( $config, JSON_PRETTY_PRINT ) ). ';';
		}
		else
		{
			$out = "const IpsDataLayerConfig = " . json_encode( $config ) . ';';
		}

		\IPS\Lang::load( \IPS\Lang::defaultLanguage() )->parseOutputForDisplay( $out );
		$out = str_replace( ["\u{2028}", "\u{2029}"], ['', ''], $out );

		\IPS\Data\Store::i()->$key = $out;
		return $out;
	}

	/**
	 * Clears cached configuration arrays and strings
	 *
	 * @param   array   $suffixes   The suffixes of the caches to clear. For example, if all that changed was pii, then you only to clear jsConfig, eventProperties and propertyEvents
	 *
	 * @return void
	 */
	public function clearCachedConfiguration( array $suffixes=array('_jsConfig','_events','_eventProperties','_properties','_propertyEvents') )
	{
		foreach( $suffixes as $suffix )
		{
			$key = static::$configCacheKey . $suffix;
			unset( \IPS\Data\Store::i()->$key );

			if ( $suffix === '_events' )
			{
				unset( $this->data['eventConfiguration']) ;
			}
			elseif ( $suffix === '_properties' )
			{
				unset( $this->data['propertiesConfiguration']) ;
			}
		}
	}

	/**
	 * Events added during this request as a valid JavaScript const assignment
	 *
	 * @return  string
	 */
	public function get_jsEvents()
	{
		$out = "const IpsDataLayerEvents = {$this->jsonEvents};";
		return str_replace( ["\u{2028}", "\u{2029}"], ['', ''], $out );
	}

	/**
	 * Events added during this request as a JSON Encoded Object
	 *
	 * @return  string
	 */
	public function get_jsonEvents()
	{
		/* We add the register event here */
		$member = \IPS\Member::loggedIn();
		if (
			$member->member_id AND
			!$member->members_bitoptions['datalayer_event_fired'] AND
			$this->eventConfiguration['account_register']['enabled'] AND
			!\IPS\Request::i()->isAjax() AND
			\IPS\Request::i()->do !== 'logout' )
		{
			$this->addEvent( 'account_register', array() );
			$member->members_bitoptions['datalayer_event_fired'] = 1;
			$member->save();
		}

		$events = array();
		foreach ( $this->events as $key => $event )
		{
			$events[] = array(
				'_key'          => $key,
				'_properties'   => $event,
			);
		}

		$out = \IPS\IN_DEV ? str_replace( "\n", "\n\t", json_encode( $events, JSON_PRETTY_PRINT ) ) : json_encode( $events );
		\IPS\Lang::load( \IPS\Lang::defaultLanguage() )->parseOutputForDisplay( $out );
		return $out;
	}

	/**
	 * @return bool
	 */
	public final function get_customPropertiesSupported()
	{
		return method_exists( $this, 'saveCustomProperty' );
	}

	/**
	 * Get properties that are used in conjunction with an event
	 *
	 * @param   string  $key        The key of the event object
	 * @param   bool    $onlyActive Only return active properties?
	 *
	 * @return array
	 */
	public function getEventProperties( string $key, bool $onlyActive=false )
	{
		/* Is it cached? */
		$cacheKey = static::$configCacheKey . '_eventProperties';
		$configuration = array();
		try
		{
			$configuration = json_decode( \IPS\Data\Store::i()->$cacheKey, true ) ?: array();
			if ( !empty( $configuration[$key][(int) $onlyActive] ) )
			{
				return $configuration[$key][(int) $onlyActive];
			}
		}
		catch ( \OutOfRangeException $e ) {}

		$return = array();
		$properties = $this->propertiesConfiguration;
		$pii = \IPS\Settings::i()->core_datalayer_include_pii;
		foreach ( $properties as $propertyKey => $property )
		{
			if ( !$onlyActive OR ( $property['enabled'] AND !( !$pii AND $property['pii'] ) ) )
			{
				foreach ( $property['event_keys'] as $event_key )
				{
					$pattern = str_replace( '*', '.*', $event_key );
					if ( $key === $event_key OR preg_match( "/$pattern/", $key ) )
					{
						$return[$propertyKey] = $property;
						break;
					}
				}
			}
		}

		/* Cache for next time */
		$configuration[$key][(int) $onlyActive] = $return;
		\IPS\Data\Store::i()->$cacheKey = json_encode( $configuration );
		return $return;
	}

	/**
	 * Get events that use a property in conjunction
	 *
	 * @param   string  $key        The key of the property
	 * @param   bool    $onlyActive Only return active events?
	 *
	 * @return array
	 */
	public function getPropertyEvents( string $key, bool $onlyActive=false )
	{
		/* Is it cached */
		$cacheKey = static::$configCacheKey . '_propertyEvents';
		$configuration = array();
		try
		{
			$configuration = json_decode( \IPS\Data\Store::i()->$cacheKey, true ) ?: array();
			if ( !empty( $configuration[$key][(int) $onlyActive] ) )
			{
				return $configuration[$key][(int) $onlyActive];
			}
			$configuration = array();
		}
		catch ( \OutOfRangeException $e ) {}

		$return = array();
		$events = $this->eventConfiguration;
		$property = $this->propertiesConfiguration[ $key ] ?? [];
		if ( empty( $property['event_keys'] ) )
		{
			return $return;
		}
		$propertyEvents = $property['event_keys'];

		foreach ( $events as $eventKey => $event )
		{
			if ( !$onlyActive OR $event['enabled'] )
			{
				foreach ( $propertyEvents as $propertyEvent )
				{
					$pattern = str_replace( '*', '.*', $propertyEvent );
					if ( $eventKey === $propertyEvent OR preg_match( "/$pattern/", $eventKey ) )
					{
						$return[$eventKey] = $event;
						break;
					}
				}
			}
		}

		$configuration[$key][(int) $onlyActive] = $return;
		\IPS\Data\Store::i()->$cacheKey = json_encode( $configuration );
		return $return;
	}

	/**
	 * All available events and their configuration
	 *
	 * @example array( key => [ 'enabled' => (bool), 'includeContext' => (bool) ] )
	 *
	 * @return array
	 */
	public function get_eventConfiguration()
	{
		/* Is it cached */
		$key = static::$configCacheKey . '_events';
		try
		{
			$configuration = json_decode( \IPS\Data\Store::i()->$key, true );
			if ( !empty( $configuration ) )
			{
				return $configuration;
			}
		}
		catch ( \OutOfRangeException $e ) {}

		/* Merge in our settings */
		$setting    = json_decode( \IPS\Settings::i()->core_datalayer_events, true ) ?? array();
		$events     = $this->_eventConfiguration;
		$setting    = array_filter( $setting, function( $_key ) use ( $events ) { return isset( $events[$_key] ); }, ARRAY_FILTER_USE_KEY );
		$this->_eventConfiguration  = array_replace_recursive( $this->_eventConfiguration, $setting );

		/* Make sure there's a "short" property */
		foreach ( array_keys( $this->_eventConfiguration ) as $event )
		{
			$this->_eventConfiguration[$event]['short'] = $this->_eventConfiguration[$event]['short'] ?? \mb_substr( $this->_eventConfiguration[$event]['description'] ?? '', 0, 70 );
		}

		\uasort( $this->_eventConfiguration, function( $a, $b ) { return \intval( $a['formatted_name'] > $b['formatted_name'] ); } );

		$this->clearCachedConfiguration([ '_jsConfig', '_eventProperties', '_propertyEvents' ]);
		\IPS\Data\Store::i()->$key = json_encode( $this->_eventConfiguration );

		return $this->_eventConfiguration;
	}

	/**
	 * All available events properties and their configuration
	 *
	 * @brief array( 'property_key' => ['event_keys' => (string[]), 'enabled' => (bool), 'pii' => (bool), 'description' => (string), 'custom' => (bool)], ... )
	 *
	 * @return array
	 */
	public function get_propertiesConfiguration()
	{
		/* Is it cached */
		$key = static::$configCacheKey . '_properties';
		try
		{
			$configuration = json_decode( \IPS\Data\Store::i()->$key, true );
			if ( !empty( $configuration ) )
			{
				return $configuration;
			}
		}
		catch ( \OutOfRangeException $e ) {}

		/* Merge in our settings and custom Properties */
		$setting    = json_decode( \IPS\Settings::i()->core_datalayer_properties, true ) ?? array();
		$properties = $this->_propertiesConfiguration;
		$setting    = array_filter( $setting, function( $value, $_key ) use ( $properties ) { return isset( $properties[$_key] ) OR $value['custom'] ?? 0; }, ARRAY_FILTER_USE_BOTH );
		$this->_propertiesConfiguration = array_replace_recursive( $this->_propertiesConfiguration, $setting );

		/* Make sure there's a "short" property */
		foreach ( array_keys( $this->_propertiesConfiguration ) as $property )
		{
			if ( $this->_propertiesConfiguration[$property]['custom'] ?? 0 )
			{
				$this->_propertiesConfiguration[$property]['short'] = "Custom property, it's value is " . ( isset( $this->_propertiesConfiguration[$property]['value'] ) ? "\"{$this->_propertiesConfiguration[$property]['value']}\"" : 'empty' );
			}
			elseif ( !isset( $this->_propertiesConfiguration[$property]['short'] ) )
			{
				$this->_propertiesConfiguration[$property]['short'] = \mb_substr( $this->_propertiesConfiguration[$property]['description'] ?? '', 0, 70 );
			}

		}

		\uasort( $this->_propertiesConfiguration, function( $a, $b ) { return \intval( $a['formatted_name'] > $b['formatted_name'] ); } );

		$this->clearCachedConfiguration([ '_jsConfig', '_eventProperties', '_propertyEvents' ]);
		\IPS\Data\Store::i()->$key = json_encode( $this->_propertiesConfiguration );

		return $this->_propertiesConfiguration;
	}

	/**
	 * Updates a property with the given changes
	 *
	 * @param   string  $key        The property's key
	 * @param   array   $changes    The property's changed fields and their values
	 * @param   bool    $add        Whether to add this property if it doesn't exist and is custom
	 *
	 * @return void
	 * @throws \InvalidArgumentException If $changes['formatted_name'] or $key contains something other than letters and underscores or if the new formatted_name is already taken
	 */
	public function savePropertyConfiguration( string $key, array $changes, bool $add=false )
	{
		$properties = $this->propertiesConfiguration;

		/* Make sure the key is valid */
		if ( preg_match( "/[^a-z,A-Z,\_]/", $key ) )
		{
			throw new \InvalidArgumentException( "The property key '$key' is invalid, property keys can only contain letters and underscores." );
		}

		/* If this is an unknown property, make sure that we specify it's custom and set default fields */
		if ( !isset( $properties[$key] ) OR ( ( $changes['custom'] ?? 0 ) AND $add ) )
		{
			/* Make sure the key is unique, formatted name can be preserved */
			$i = 0;
			while ( isset( $properties[$key] ) )
			{
				$key = $key . $i++;
			}

			$changes = array_replace(
				array(
					'event_keys'    => [],
					'pii'           => false,
					'formatted_name' => $key,
					'description'   => 'A custom property that will always have the same value.',
					'custom'        => true,
					'type'          => 'string',
					'page_level'    => false,
					'value'         => null,
				),
				$changes
			);
			$changes['custom'] = true;
		}
		else
		{
			unset( $changes['custom'] );
		}

		/* Check the formatted_name */
		if ( isset( $changes['formatted_name'] ) )
		{
			if ( preg_match( "/[^a-z,A-Z,\_]/", $changes['formatted_name'] ) )
			{
				throw new \InvalidArgumentException( "The formatted name '{$changes['formatted_name']}' is invalid, formatted names can only contain letters and underscores." );
			}

			foreach ( $properties as $property => $data )
			{
				if ( $property !== $key AND $data['formatted_name'] === $changes['formatted_name'] )
				{
					throw new \InvalidArgumentException( "The formatted name '{$changes['formatted_name']}' is already in use for the property '$property'" );
				}
			}
		}

		/* These should never be set in the setting since other code assumes these properties are what's specified in the arrays */
		$properties[$key] = $properties[$key] ?? array();
		unset( $changes['pii'] );
		if ( !( $properties[$key]['custom'] ?? 0 ) )
		{
			unset( $changes['event_keys'] );
			unset( $changes['description'] );
			unset( $changes['short'] );
			unset( $changes['type'] );
			unset( $changes['page_level'] );
		}

		$setting        = json_decode( \IPS\Settings::i()->core_datalayer_properties, true );
		$setting[$key]  = array_replace( $setting[$key] ?? array(), $changes );
		\IPS\Settings::i()->changeValues([ 'core_datalayer_properties' => json_encode( $setting ) ]);
		$this->clearCachedConfiguration([ ' _jsConfig', '_eventProperties', '_properties', '_propertyEvents' ]);
	}

	/**
	 * Updates an event with the given changes
	 *
	 * @param   string  $key        The event's key
	 * @param   array   $changes    The event's changed fields and their values
	 *
	 * @return  void
	 * @throws  \InvalidArgumentException If $changes['formatted_name'] or $key contains something other than letters and underscores or if the new formatted_name is already taken
	 */
	public function saveEventConfiguration( string $key, array $changes )
	{
		$events = $this->eventConfiguration;

		/* Make sure the key is valid */
		if ( preg_match( "/[^a-z,A-Z,\_]/", $key ) )
		{
			throw new \InvalidArgumentException( "The event key '$key' is invalid, event keys can only contain letters and underscores." );
		}

		/* No custom events, throw an exception if we don't know this one */
		if ( !isset( $events[$key] ) )
		{
			throw new \InvalidArgumentException( "The event '$key' is not recognized." );
		}

		/* Check the formatted_name */
		if ( isset( $changes['formatted_name'] ) )
		{
			if ( preg_match( "/[^a-z,A-Z,\_]/", $changes['formatted_name'] ) )
			{
				throw new \InvalidArgumentException( "The formatted name '{$changes['formatted_name']}' is invalid, formatted names can only contain letters and underscores." );
			}

			foreach ( $events as $event => $data )
			{
				if ( $event !== $key AND $data['formatted_name'] === $changes['formatted_name'] )
				{
					throw new \InvalidArgumentException( "The formatted name '{$changes['formatted_name']}' is already in use for the event '$event'." );
				}
			}
		}

		/* These should never be set in the setting since other code assumes these properties are what's specified in the arrays */
		unset( $changes['description'] );
		unset( $changes['short'] );

		$setting        = json_decode( \IPS\Settings::i()->core_datalayer_events, true );
		$setting[$key]  = array_replace( $setting[$key] ?? array(), $changes );
		unset( $setting[$key][$key] );
		\IPS\Settings::i()->changeValues([ 'core_datalayer_events' => json_encode( $setting ) ]);
		$this->clearCachedConfiguration();
	}

	/**
	 * List the handlers
	 *
	 * @return string
	 */
	public function handlers()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'dataLayer_handlers_view' );
		$class = \IPS\core\modules\admin\settings\dataLayer::$handlerClass;
		if ( !( class_exists( $class ) AND method_exists( $class, 'handlerForm' ) AND method_exists( $class, 'loadWhere' ) ) )
		{
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=settings&controller=dataLayer&tab=main' ) );
		}

		if ( isset( $_SESSION['handler_saved'] ) )
		{
			unset( $_SESSION['handler_saved'] );
			\IPS\Output::i()->inlineMessage = 'Saved';
		}
		elseif ( isset( $_SESSION['handler_added'] ) )
		{
			unset( $_SESSION['handler_added'] );
			\IPS\Output::i()->inlineMessage = 'Created';
		}

		$handlers = $class::loadWhere();
		return \IPS\Theme::i()->getTemplate( 'settings', 'core', 'admin' )->handlers( $handlers );
	}



	/**
	 * Add a new handler
	 *
	 * @return string
	 */
	public function addHandler()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'dataLayer_handlers_edit' );
		$class = \IPS\core\modules\admin\settings\dataLayer::$handlerClass;
		if ( !( class_exists( $class ) AND method_exists( $class, 'handlerForm' ) AND method_exists( $class, 'loadWhere' ) ) )
		{
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=settings&controller=dataLayer&tab=main' ) );
		}

		$handler    = new $class;
		$form       = $handler->handlerForm();
		$handlersUrl = (string) \IPS\Http\Url::internal( 'app=core&module=settings&controller=dataLayer&tab=handlers' );

		\IPS\Output::i()->title         = \IPS\Member::loggedIn()->language()->get( 'datalayer_handler_form' );
		\IPS\Output::i()->breadcrumb    = array(
			[ \IPS\Http\Url::internal( 'app=core&module=settings&controller=dataLayer' ), \IPS\Member::loggedIn()->language()->get( 'menu__core_settings_dataLayer' ) ],
			[ $handlersUrl, \IPS\Member::loggedIn()->language()->get( 'datalayer_handlers' ) ],
			[ '#', 'Edit ' . \IPS\Output::i()->title ]
		);

		if ( $values = $form->values() )
		{
			$_SESSION['handler_added'] = 1;
			\IPS\Output::i()->redirect( $handlersUrl );
		}

		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'settings', 'core', 'admin' )->formWrapper( $form );
	}

	/**
	 * Modify an existing handler
	 *
	 * @return string
	 */
	public function saveHandler()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'dataLayer_handlers_view' );
		$class = \IPS\core\modules\admin\settings\dataLayer::$handlerClass;
		if ( !( class_exists( $class ) AND method_exists( $class, 'handlerForm' ) AND method_exists( $class, 'loadWhere' ) ) )
		{
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=settings&controller=dataLayer&tab=main' ) );
		}

		$handlersUrl = (string) \IPS\Http\Url::internal( 'app=core&module=settings&controller=dataLayer&tab=handlers' );
		try
		{
			if( !( $id = \intval( \IPS\Request::i()->id ) ) )
			{
				throw new \OutOfRangeException;
			}

			$handler        = $class::load( $id );
			$form           = $handler->handlerForm();

			\IPS\Output::i()->title         = "Edit {$handler->name}";
			\IPS\Output::i()->breadcrumb    = array(
				[ \IPS\Http\Url::internal( 'app=core&module=settings&controller=dataLayer' ), \IPS\Member::loggedIn()->language()->get( 'menu__core_settings_dataLayer' ) ],
				[ $handlersUrl, \IPS\Member::loggedIn()->language()->get( 'datalayer_handlers' ) ],
				[ '#', $handler->name ]
			);

			if ( ! \IPS\Member::loggedIn()->hasAcpRestriction( 'dataLayer_handlers_edit' ) )
			{
				\IPS\Output::i()->title = "Viewing {$handler->name}";
			}

			if ( $form->values() )
			{
				$_SESSION['handler_saved'] = 1;
				\IPS\Output::i()->redirect( $handlersUrl );
			}
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->redirect( $handlersUrl );
		}

		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'settings', 'core', 'admin' )->formWrapper( $form );
	}

	/**
	 * Delete handler
	 *
	 * @return string
	 */
	public function deleteHandler()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'dataLayer_handlers_edit' );
		if ( \IPS\Request::i()->isAjax() OR \IPS\Request::i()->confirm )
		{
			\IPS\Session::i()->csrfCheck();
		}

		$class = \IPS\core\modules\admin\settings\dataLayer::$handlerClass;
		if ( !( class_exists( $class ) AND method_exists( $class, 'handlerForm' ) AND method_exists( $class, 'loadWhere' ) ) )
		{
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=settings&controller=dataLayer&tab=main' ) );
		}

		$handlersUrl    = \IPS\Http\Url::internal( 'app=core&module=settings&controller=dataLayer&tab=handlers' );
		if ( \IPS\Request::i()->isAjax() )
		{
			$handlersUrl = $handlersUrl->setQueryString(['ct' => time()]);
		}
		try
		{
			if( !( $id = \intval( \IPS\Request::i()->id ) ) )
			{
				throw new \OutOfRangeException;
			}

			/* Make sure the user confirmed the deletion */
			\IPS\Request::i()->confirmedDelete();

			$handler = $class::load( $id );
			$handler->delete();
		}
		catch ( \OutOfRangeException $e ) {}
		\IPS\Output::i()->redirect( $handlersUrl );
	}

	/**
	 * This is for the enable/disable handler toggle
	 *
	 * @return void
	 */
	public function enableToggle()
	{
		\IPS\Session::i()->csrfCheck();
		\IPS\Dispatcher::i()->checkAcpPermission( 'datalayer_handlers_edit' );

		if ( isset( \IPS\Request::i()->id ) AND isset( \IPS\Request::i()->status ) )
		{
			$class = \IPS\core\modules\admin\settings\dataLayer::$handlerClass;
			if ( ( class_exists( $class ) AND method_exists( $class, 'handlerForm' ) AND method_exists( $class, 'loadWhere' ) ) )
			{
				try
				{
					$handler = $class::load( \IPS\Request::i()->id );
					$handler->enabled = (bool) \IPS\Request::i()->status;
					$handler->save();
					return;
				}
				catch ( \UnderflowException $e ) {}
			}

		}
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=settings&controller=dataLayer&tab=handlers' ) );
	}

	/**
	 * Get the data layer properties used when viewing a member's profile
	 *
	 * @param 	\IPS\Member 		$member					The member
	 * @param 	array 				$additionalProperties	Optional: Any additional properties to set (e.g. view location)
	 * @return array
	 */
	public function getMemberProfileEventProperties( \IPS\Member $member, array $additionalProperties=array() ) : array
	{
		$profileId = $member->members_bitoptions['datalayer_pii_optout'] ? '' : \IPS\core\DataLayer::i()->getSsoId($member->member_id);
		$properties = array(
			'profile_group' 	=> \IPS\Member\Group::load( $member->member_group_id )->formattedName,
			'profile_group_id' 	=> $member->member_group_id ?: null,
			'profile_name'	 	=> $profileId ? $member->real_name : null,
			'profile_id'		=> $profileId ?: null,
		);

		array_replace( $properties, $additionalProperties );
		return $properties;
	}
}