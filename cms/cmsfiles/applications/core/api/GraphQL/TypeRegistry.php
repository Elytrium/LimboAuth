<?php
/**
 * @brief		GraphQL: Core type registry
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @since		7 May 2017
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\core\api\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use IPS\Api\GraphQL\Types;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Core type registry. GraphQL requires exactly one instance of each type,
 * so we'll generate singletons here.
 * @todo automate this somehow?
 */
class _TypeRegistry
{
	protected static $activeUsers;
	protected static $activeUser;
	protected static $attachment;
	protected static $attachmentPermissions;
	protected static $clubNode;
	protected static $club;
	protected static $contentReaction;
	protected static $contentSearchResult;
	protected static $group;
	protected static $ignoreOption;
	protected static $member;
	protected static $language;
	protected static $login;
	protected static $messengerConversation;
	protected static $messengerFolder;
	protected static $messengerParticipant;
	protected static $messengerReply;
	protected static $notification;
	protected static $notificationMethod;
	protected static $notificationType;
	protected static $poll;
	protected static $pollQuestion;
	protected static $popularContributor;
	protected static $profileFieldGroup;
	protected static $profileField;
	protected static $promotedItem;
	protected static $report;
	protected static $reportReason;
	protected static $search;
	protected static $searchResult;
	protected static $stats;
	protected static $stream;
	protected static $tag;
	protected static $settings;
	protected static $uploadProgress;

	/**
	 * Constructor
	 */
	public function __construct()
	{
		// Defined to suppress static warnings
	}

	/**
	 * @return \IPS\core\api\GraphQL\Types\ActiveUsersType
	 */
	public static function activeUsers()
	{
		return self::$activeUsers ?: (self::$activeUsers = new \IPS\core\api\GraphQL\Types\ActiveUsersType());
	}

	/**
	 * @return \IPS\core\api\GraphQL\Types\ActiveUserType
	 */
	public static function activeUser()
	{
		return self::$activeUser ?: (self::$activeUser = new \IPS\core\api\GraphQL\Types\ActiveUserType());
	}
	
	/**
	 * @return \IPS\core\api\GraphQL\Types\AttachmentType
	 */
	public static function attachment()
	{
		return self::$attachment ?: (self::$attachment = new \IPS\core\api\GraphQL\Types\AttachmentType());
	}
	
	/**
	 * @return \IPS\core\api\GraphQL\Types\AttachmentPermissionsType
	 */
	public static function attachmentPermissions()
	{
		return self::$attachmentPermissions ?: (self::$attachmentPermissions = new \IPS\core\api\GraphQL\Types\AttachmentPermissionsType());
	}

	/**
	 * @return \IPS\core\api\GraphQL\Types\ClubNodeType
	 */
	public static function clubNode()
	{
		return self::$clubNode ?: (self::$clubNode = new \IPS\core\api\GraphQL\Types\ClubNodeType());
	}

	/**
	 * @return \IPS\core\api\GraphQL\Types\ClubType
	 */
	public static function club()
	{
		return self::$club ?: (self::$club = new \IPS\core\api\GraphQL\Types\ClubType());
	}

	/**
	 * @return \IPS\core\api\GraphQL\Types\ContentReactionType
	 */
	public static function contentReaction()
	{
		return self::$contentReaction ?: (self::$contentReaction = new \IPS\core\api\GraphQL\Types\ContentReactionType());
	}

	/**
	 * @return \IPS\core\api\GraphQL\Types\GroupType
	 */
	public static function group()
	{
		return self::$group ?: (self::$group = new \IPS\core\api\GraphQL\Types\GroupType());
	}

	/**
	 * @return \IPS\core\api\GraphQL\Types\IgnoreOptionType
	 */
	public static function ignoreOption()
	{
		return self::$ignoreOption ?: (self::$ignoreOption = new \IPS\core\api\GraphQL\Types\IgnoreOptionType());
	}

	/**
	 * @return \IPS\core\api\GraphQL\Types\MemberType
	 */
	public static function member()
	{
		return self::$member ?: (self::$member = new \IPS\core\api\GraphQL\Types\MemberType());
	}

	/**
	 * @return \IPS\core\api\GraphQL\Types\LanguageType
	 */
	public static function language()
	{
		return self::$language ?: (self::$language = new \IPS\core\api\GraphQL\Types\LanguageType());
	}

	/**
	 * @return \IPS\core\api\GraphQL\Types\LoginType
	 */
	public static function login()
	{
		return self::$login ?: (self::$login = new \IPS\core\api\GraphQL\Types\LoginType());
	}

	/**
	 * @return \IPS\core\api\GraphQL\Types\MessengerConversationType
	 */
	public static function messengerConversation()
	{
		return self::$messengerConversation ?: (self::$messengerConversation = new \IPS\core\api\GraphQL\Types\MessengerConversationType());
	}

	/**
	 * @return \IPS\core\api\GraphQL\Types\MessengerFolderType
	 */
	public static function messengerFolder()
	{
		return self::$messengerFolder ?: (self::$messengerFolder = new \IPS\core\api\GraphQL\Types\MessengerFolderType());
	}

	/**
	 * @return \IPS\core\api\GraphQL\Types\MessengerParticipantType
	 */
	public static function messengerParticipant()
	{
		return self::$messengerParticipant ?: (self::$messengerParticipant = new \IPS\core\api\GraphQL\Types\MessengerParticipantType());
	}

	/**
	 * @return \IPS\core\api\GraphQL\Types\MessengerReplyType
	 */
	public static function messengerReply()
	{
		return self::$messengerReply ?: (self::$messengerReply = new \IPS\core\api\GraphQL\Types\MessengerReplyType());
	}

	/**
	 * @return \IPS\core\api\GraphQL\Types\NotificationType
	 */
	public static function notification()
	{
		return self::$notification ?: (self::$notification = new \IPS\core\api\GraphQL\Types\NotificationType());
	}

	/**
	 * @return \IPS\core\api\GraphQL\Types\NotificationMethodType
	 */
	public static function notificationMethod()
	{
		return self::$notificationMethod ?: (self::$notificationMethod = new \IPS\core\api\GraphQL\Types\NotificationMethodType());
	}

	/**
	 * @return \IPS\core\api\GraphQL\Types\NotificationTypeType
	 */
	public static function notificationType()
	{
		return self::$notificationType ?: (self::$notificationType = new \IPS\core\api\GraphQL\Types\NotificationTypeType());
	}

	/**
	 * @return \IPS\core\api\GraphQL\Types\PollType
	 */
	public static function poll()
	{
		return self::$poll ?: (self::$poll = new \IPS\core\api\GraphQL\Types\PollType());
	}

	/**
	 * @return \IPS\core\api\GraphQL\Types\PollQuestionType
	 */
	public static function pollQuestion(): \IPS\core\api\GraphQL\Types\PollQuestionType
	{
		return self::$pollQuestion ?: (self::$pollQuestion = new \IPS\core\api\GraphQL\Types\PollQuestionType());
	}

	/**
	 * @return \IPS\core\api\GraphQL\Types\PopularContributorType
	 */
	public static function popularContributor(): \IPS\core\api\GraphQL\Types\PopularContributorType
	{
		return self::$popularContributor ?: (self::$popularContributor = new \IPS\core\api\GraphQL\Types\PopularContributorType());
	}

	/**
	 * @return \IPS\core\api\GraphQL\Types\ProfileFieldGroupType
	 */
	public static function profileFieldGroup(): \IPS\core\api\GraphQL\Types\ProfileFieldGroupType
    {
        return self::$profileFieldGroup ?: (self::$profileFieldGroup = new \IPS\core\api\GraphQL\Types\ProfileFieldGroupType());
    }

	/**
	 * @return \IPS\core\api\GraphQL\Types\ProfileFieldType
	 */
	public static function profileField(): \IPS\core\api\GraphQL\Types\ProfileFieldType
	{
		return self::$profileField ?: (self::$profileField = new \IPS\core\api\GraphQL\Types\ProfileFieldType());
	}

	/**
	 * @return \IPS\core\api\GraphQL\Types\PromotedItemType
	 */
	public static function promotedItem(): \IPS\core\api\GraphQL\Types\PromotedItemType
	{
		return self::$promotedItem ?: (self::$promotedItem = new \IPS\core\api\GraphQL\Types\PromotedItemType());
	}

	/**
	 * @return \IPS\core\api\GraphQL\Types\ContentSearchResultType
	 */
	public static function contentSearchResult(): \IPS\core\api\GraphQL\Types\ContentSearchResultType
	{
		return self::$contentSearchResult ?: (self::$contentSearchResult = new \IPS\core\api\GraphQL\Types\ContentSearchResultType());
	}

	/**
	 * @return \IPS\core\api\GraphQL\Types\ReportType
	 */
	public static function report(): \IPS\core\api\GraphQL\Types\ReportType
    {
        return self::$report ?: (self::$report = new \IPS\core\api\GraphQL\Types\ReportType());
    }

	/**
	 * @return \IPS\core\api\GraphQL\Types\ReportReasonType
	 */
	public static function reportReason(): \IPS\core\api\GraphQL\Types\ReportReasonType
    {
        return self::$reportReason ?: (self::$reportReason = new \IPS\core\api\GraphQL\Types\ReportReasonType());
    }

	/**
	 * @return \IPS\core\api\GraphQL\Types\SearchResultType
	 */
	public static function searchResult(): \IPS\core\api\GraphQL\Types\SearchResultType
	{
		return self::$searchResult ?: (self::$searchResult = new \IPS\core\api\GraphQL\Types\SearchResultType());
	}

	/**
	 * @return \IPS\core\api\GraphQL\Types\SearchType
	 */
	public static function search(): \IPS\core\api\GraphQL\Types\SearchType
	{
		return self::$search ?: (self::$search = new \IPS\core\api\GraphQL\Types\SearchType());
	}

	/**
	 * @return \IPS\core\api\GraphQL\Types\StatsType
	 */
	public static function stats(): \IPS\core\api\GraphQL\Types\StatsType
	{
		return self::$stats ?: (self::$stats = new \IPS\core\api\GraphQL\Types\StatsType());
	}

	/**
	 * @return \IPS\core\api\GraphQL\Types\StreamType
	 */
	public static function stream(): \IPS\core\api\GraphQL\Types\StreamType
	{
		return self::$stream ?: (self::$stream = new \IPS\core\api\GraphQL\Types\StreamType());
	}

	/**
	 * @return \IPS\core\api\GraphQL\Types\TagType
	 */
	public static function tag(): \IPS\core\api\GraphQL\Types\TagType
	{
		return self::$tag ?: (self::$tag = new \IPS\core\api\GraphQL\Types\TagType());
	}

	/**
	 * @return \IPS\core\api\GraphQL\Types\SettingsType
	 */
	public static function settings(): \IPS\core\api\GraphQL\Types\SettingsType
	{
		return self::$settings ?: (self::$settings = new \IPS\core\api\GraphQL\Types\SettingsType());
	}

	/**
	 * @return \IPS\core\api\GraphQL\Types\UploadProgressType
	 */
	public static function uploadProgress(): \IPS\core\api\GraphQL\Types\UploadProgressType
	{
		return self::$uploadProgress ?: (self::$uploadProgress = new \IPS\core\api\GraphQL\Types\UploadProgressType());
	}
}