<?php
/**
 * @brief		Club Model
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		13 Feb 2017
 */

namespace IPS\Member;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Club Model
 */
class _Club extends \IPS\Patterns\ActiveRecord implements \IPS\Content\Embeddable
{
	/**
	 * @brief	Club: public
	 */
	const TYPE_PUBLIC = 'public';

	/**
	 * @brief	Club: open
	 */
	const TYPE_OPEN = 'open';

	/**
	 * @brief	Club: closed
	 */
	const TYPE_CLOSED = 'closed';

	/**
	 * @brief	Club: private
	 */
	const TYPE_PRIVATE = 'private';

	/**
	 * @brief	Club: read-only
	 */
	const TYPE_READONLY = 'readonly';

	/**
	 * @brief	Status: member
	 */
	const STATUS_MEMBER = 'member';

	/**
	 * @brief	Status: invited
	 */
	const STATUS_INVITED = 'invited';

	/**
	 * @brief	Status: invited (bypassing payment)
	 */
	const STATUS_INVITED_BYPASSING_PAYMENT = 'invited_bypassing_payment';

	/**
	 * @brief	Status: requested
	 */
	const STATUS_REQUESTED = 'requested';

	/**
	 * @brief	Status: awaiting payment
	 */
	const STATUS_WAITING_PAYMENT = 'payment_pending';

	/**
	 * @brief	Status: expired
	 */
	const STATUS_EXPIRED = 'expired';

	/**
	 * @brief	Status: expired moderator
	 */
	const STATUS_EXPIRED_MODERATOR = 'expired_moderator';

	/**
	 * @brief	Status: declined
	 */
	const STATUS_DECLINED = 'declined';

	/**
	 * @brief	Status: banned
	 */
	const STATUS_BANNED = 'banned';

	/**
	 * @brief	Status: moderator
	 */
	const STATUS_MODERATOR = 'moderator';

	/**
	 * @brief	Status: leader
	 */
	const STATUS_LEADER = 'leader';
	
	/**
	 * @brief	[ActiveRecord] Multiton Store
	 */
	protected static $multitons;
	
	/**
	 * @brief	[ActiveRecord] Database Table
	 */
	public static $databaseTable = 'core_clubs';
	
	/**
	 * @brief	Use a default cover photo
	 */
	public static $coverPhotoDefault = true;
	
	/* !Fetch Clubs */
	
	/**
	 * Construct ActiveRecord from database row
	 *
	 * @param	array	$data							Row from database table
	 * @param	bool	$updateMultitonStoreIfExists	Replace current object in multiton store if it already exists there?
	 * @return	static
	 */
	public static function constructFromData( $data, $updateMultitonStoreIfExists = TRUE )
	{
		$return = parent::constructFromData( $data, $updateMultitonStoreIfExists );
		
		if ( isset( $data['member_id'] ) and isset( $data['status'] ) )
		{
			$return->memberStatuses[ $data['member_id'] ] = $data['status'];
		}
		
		return $return;
	}
		
	/**
	 * Get all clubs a member can see
	 *
	 * @param	\IPS\Member				$member		The member to base permission off or NULL for all clubs
	 * @param	int						$limit		Number to get
	 * @param	string					$sortOption	The sort option ('last_activity', 'members', 'content' or 'created')
	 * @param	bool|\IPS\Member|array	$mineOnly	Limit to clubs a particular member has joined (TRUE to use the same value as $member). Can also provide an array as array( 'member' => \IPS\Member, 'statuses' => array( STATUS_MEMBER... ) ) to limit to certain member statuses
	 * @param	array					$filters	Custom field filters
	 * @param	mixed					$extraWhere	Additional WHERE clause
	 * @param	bool					$countOnly	Only return a count, instead of an iterator
	 * @return	\IPS\Patterns\ActiveRecordIterator|array|int
	 */
	public static function clubs( ?\IPS\Member $member, $limit, $sortOption, $mineOnly=FALSE, $filters=array(), $extraWhere=NULL, $countOnly=FALSE )
	{
		$where = array();
		$joins = array();
		
		/* Restrict to clubs we can see */
		if ( $member and !$member->modPermission('can_access_all_clubs') )
		{
			/* Exclude clubs which are pending approval, unless we are the owner */
			if ( \IPS\Settings::i()->clubs_require_approval )
			{
				$where[] = array( '( approved=1 OR owner=? )', $member->member_id );
			}
			
			/* Specify our memberships */
			if ( $member->member_id )
			{
				$joins['membership'] = array( array( 'core_clubs_memberships', 'membership' ), array( 'membership.club_id=core_clubs.id AND membership.member_id=?', $member->member_id ) );
				$where[] = array( "( type<>? OR membership.status IN('" . static::STATUS_MEMBER .  "','" . static::STATUS_MODERATOR . "','" . static::STATUS_LEADER . "','" . static::STATUS_EXPIRED . "','" . static::STATUS_EXPIRED_MODERATOR . "') )", static::TYPE_PRIVATE );
			}
			else
			{
				$where[] = array( 'type<>?', static::TYPE_PRIVATE );
			}
		}
		
		/* Restrict to clubs we have joined */
		if ( $mineOnly )
		{
			if ( \is_array( $mineOnly ) )
			{
				$statuses = $mineOnly['statuses'];
				$mineOnly = $mineOnly['member'];
			}
			else
			{			
				$mineOnly = ( $mineOnly === TRUE ) ? $member : $mineOnly;
				$statuses = array( static::STATUS_MEMBER, static::STATUS_MODERATOR, static::STATUS_LEADER, static::STATUS_EXPIRED, static::STATUS_EXPIRED_MODERATOR );
			}
			if ( !$mineOnly->member_id )
			{
				return array();
			}
			
			if ( $member and $mineOnly->member_id === $member->member_id and isset( $joins['membership'] ) )
			{
				$where[] = array( "membership.status IN('" . static::STATUS_MEMBER .  "','" . static::STATUS_MODERATOR . "','" . static::STATUS_LEADER . "','" . static::STATUS_EXPIRED . "','" . static::STATUS_EXPIRED_MODERATOR . "')" );
			}
			else
			{
				$joins['others_membership'] = array( array( 'core_clubs_memberships', 'others_membership' ), array( 'others_membership.club_id=core_clubs.id AND others_membership.member_id=?', $mineOnly->member_id ) );
				$where[] = array( "others_membership.status IN('" . static::STATUS_MEMBER .  "','" . static::STATUS_MODERATOR . "','" . static::STATUS_LEADER . "','" . static::STATUS_EXPIRED . "','" . static::STATUS_EXPIRED_MODERATOR . "')" );
			}
		}
		
		/* Other filters */
		if ( $filters )
		{
			$joins['core_clubs_fieldvalues'] = array( 'core_clubs_fieldvalues', array( 'core_clubs_fieldvalues.club_id=core_clubs.id' ) );
			foreach ( $filters as $k => $v )
			{
				if ( \is_array( $v ) )
				{
					$where[] = array( \IPS\Db::i()->findInSet( "field_{$k}", $v ) );
				}
				else
				{
					$where[] = array( "field_{$k}=?", $v );
				}
			}
		}
		
		/* Additional where clause */
		if ( $extraWhere )
		{
			if ( \is_array( $extraWhere ) )
			{
				$where = array_merge( $where, $extraWhere );
			}
			else
			{
				$where[] = array( $extraWhere );
			}
		}
		
		/* Query */
		if( $countOnly )
		{
			$select = \IPS\Db::i()->select( 'COUNT(*)', 'core_clubs', $where );
		}
		else
		{
			$select = \IPS\Db::i()->select( '*', 'core_clubs', $where, ( $sortOption === 'name' ? "{$sortOption} ASC" : "{$sortOption} DESC" ), $limit );
		}
		
		foreach ( $joins as $join )
		{
			$select->join( $join[0], $join[1] );
		}

		if( $countOnly )
		{
			return $select->first();
		}

		$select->setKeyField( 'id' );

		/* Return */
		return new \IPS\Patterns\ActiveRecordIterator( $select, 'IPS\Member\Club' );
	}	
	
	/**
	 * Get number clubs a member is leader of
	 *
	 * @param	\IPS\Member	$member	The member
	 * @return	int
	 */
	public static function numberOfClubsMemberIsLeaderOf( \IPS\Member $member )
	{
		return \IPS\Db::i()->select( 'COUNT(*)', 'core_clubs_memberships', array( 'member_id=? AND status=?', $member->member_id, static::STATUS_LEADER ) );
	}
		
	/* !ActiveRecord */
	
	/**
	 * Set Default Values
	 *
	 * @return	void
	 */
	public function setDefaultValues()
	{
		$this->type = static::TYPE_OPEN;
		$this->created = new \IPS\DateTime;
		$this->last_activity = time();
		$this->members = 1;
		$this->owner = NULL;
		$this->approved = \IPS\Settings::i()->clubs_require_approval ? 0 : 1;
	}
	
	/**
	 * Get owner
	 *
	 * @return	\IPS\Member|NULL
	 */
	public function get_owner()
	{
		try
		{
			$owner = \IPS\Member::load( $this->_data['owner'] );
			return $owner->member_id ? $owner : NULL;
		}
		catch( \OutOfRangeException $e )
		{
			return NULL;
		}
	}
	
	/**
	 * Set member
	 *
	 * @param	\IPS\Member	$owner	The owner
	 * @return	void
	 */
	public function set_owner( \IPS\Member $owner = NULL )
	{
		$this->_data['owner'] = $owner ? ( (int) $owner->member_id ) : NULL;
	}
	
	/**
	 * Get created date
	 *
	 * @return	\IPS\DateTime
	 */
	public function get_created()
	{
		return \IPS\DateTime::ts( $this->_data['created'] );
	}
	
	/**
	 * Set created date
	 *
	 * @param	\IPS\DateTime	$date	The creation date
	 * @return	void
	 */
	public function set_created( \IPS\DateTime $date )
	{
		$this->_data['created'] = $date->getTimestamp();
	}
			
	/**
	 * Get club URL
	 *
	 * @return	\IPS\Http\Url
	 */
	public function url()
	{
		return \IPS\Http\Url::internal( "app=core&module=clubs&controller=view&id={$this->id}", 'front', 'clubs_view', \IPS\Http\Url\Friendly::seoTitle( $this->name ) );
	}
	
	/**
	 * Columns needed to query for search result / stream view
	 *
	 * @return	array
	 */
	public static function basicDataColumns()
	{
		return array( 'id', 'name' );
	}
	
	/**
	 * Edit Club Form
	 *
	 * @param	bool	$acp			TRUE if editing in the ACP
	 * @param	bool	$new			TRUE if creating new
	 * @param	array	$availableTypes	If creating new, the available types
	 * @return	\IPS\Helpers\Form|NULL
	 */
	public function form( $acp=FALSE, $new=FALSE, $availableTypes=NULL )
	{
		$form = new \IPS\Helpers\Form;
		
		$form->add( new \IPS\Helpers\Form\Text( 'club_name', $this->name, TRUE, array( 'maxLength' => 255 ) ) );
		
		if ( $acp or ( $new and \count( $availableTypes ) > 1 ) )
		{
			$form->add( new \IPS\Helpers\Form\Radio( 'club_type', $this->type, TRUE, array(
				'options' => $new ? $availableTypes : array(
					\IPS\Member\Club::TYPE_PUBLIC	=> 'club_type_' . \IPS\Member\Club::TYPE_PUBLIC,
					\IPS\Member\Club::TYPE_OPEN	=> 'club_type_' . \IPS\Member\Club::TYPE_OPEN,
					\IPS\Member\Club::TYPE_CLOSED	=> 'club_type_' . \IPS\Member\Club::TYPE_CLOSED,
					\IPS\Member\Club::TYPE_PRIVATE	=> 'club_type_' . \IPS\Member\Club::TYPE_PRIVATE,
					\IPS\Member\Club::TYPE_READONLY	=> 'club_type_' . \IPS\Member\Club::TYPE_READONLY,
				),
				'toggles'	=> array(
					\IPS\Member\Club::TYPE_OPEN		=> array( 'club_membership_fee', 'club_show_membertab' ),
					\IPS\Member\Club::TYPE_CLOSED	=> array( 'club_membership_fee', 'club_show_membertab' ),
					\IPS\Member\Club::TYPE_PRIVATE	=> array( 'club_membership_fee', 'club_show_membertab' ),
					\IPS\Member\Club::TYPE_READONLY	=> array( 'club_membership_fee', 'club_show_membertab' ),
				)
			) ) );
			
			if ( $acp )
			{
				$form->add( new \IPS\Helpers\Form\Member( 'club_owner', $this->owner, TRUE ) );
			}
		}
		
		$form->add( new \IPS\Helpers\Form\TextArea( 'club_about', $this->about ) );

		$memberTabFieldPosition = '';

		if ( \IPS\Application::appIsEnabled( 'nexus' ) and \IPS\Settings::i()->clubs_paid_on and \IPS\Member::loggedIn()->group['gbw_paid_clubs'] )
		{
			$form->add( new \IPS\Helpers\Form\Radio( 'club_membership_fee', ( $this->id and $this->fee ) ? 'paid' : 'free', TRUE, array(
				'options' => array(
					'free'	=> 'club_membership_free',
					'paid'	=> 'club_membership_paid'
				),
				'toggles' => array(
					'paid'	=> array( 'club_fee', 'club_renewals' )
				)
			), NULL, NULL, NULL, 'club_membership_fee' ) );
			
			$commissionBlurb = NULL;
			$fees = NULL;
			if ( $_fees = \IPS\Settings::i()->clubs_paid_transfee )
			{
				$fees = array();
				foreach ( $_fees as $fee )
				{
					$fees[] = (string) ( new \IPS\nexus\Money( $fee['amount'], $fee['currency'] ) );
				}
				$fees = \IPS\Member::loggedIn()->language()->formatList( $fees, \IPS\Member::loggedIn()->language()->get('or_list_format') );
			}
			if ( \IPS\Settings::i()->clubs_paid_commission and $fees )
			{
				$commissionBlurb = \IPS\Member::loggedIn()->language()->addToStack( 'club_fee_desc_both', FALSE, array( 'sprintf' => array( \IPS\Settings::i()->clubs_paid_commission, $fees ) ) );
			}
			elseif ( \IPS\Settings::i()->clubs_paid_commission )
			{
				$commissionBlurb = \IPS\Member::loggedIn()->language()->addToStack('club_fee_desc_percent', FALSE, array( 'sprintf' => \IPS\Settings::i()->clubs_paid_commission ) );
			}
			elseif ( $fees )
			{
				$commissionBlurb = \IPS\Member::loggedIn()->language()->addToStack('club_fee_desc_fee', FALSE, array( 'sprintf' => $fees ) );
			}
			
			\IPS\Member::loggedIn()->language()->words['club_fee_desc'] = $commissionBlurb;			
			$form->add( new \IPS\nexus\Form\Money( 'club_fee', $this->id ? json_decode( $this->fee, TRUE ) : array(), NULL, array(), function( $value ){

				if ( \count( $value ) == 0 )
				{
					throw new \DomainException( 'form_required' );
				}
				
				foreach( $value as $currency => $fee )
				{
					if( !$fee->amount->isGreaterThanZero() )
					{
						throw new \DomainException( 'form_required' );
					}
				}
			}, NULL, NULL, 'club_fee' ) );
			$form->add( new \IPS\Helpers\Form\Radio( 'club_renewals', $this->id ? ( $this->renewal_term ? 1 : 0 ) : 0, TRUE, array(
				'options'	=> array( 0 => 'club_renewals_off', 1 => 'club_renewals_on' ),
				'toggles'	=> array( 1 => array( 'club_renewal_term' ) )
			), NULL, NULL, NULL, 'club_renewals' ) );
			\IPS\Member::loggedIn()->language()->words['club_renewal_term_desc'] = $commissionBlurb;
			$renewTermForEdit = NULL;
			if ( $this->id and $this->renewal_term )
			{
				$renewPrices = array();
				foreach ( json_decode( $this->renewal_price, TRUE ) as $currency => $data )
				{
					$renewPrices[ $currency ] = new \IPS\nexus\Money( $data['amount'], $currency );
				}
				$renewTermForEdit = new \IPS\nexus\Purchase\RenewalTerm( $renewPrices, new \DateInterval( 'P' . $this->renewal_term . mb_strtoupper( $this->renewal_units ) ) );
			}
			$form->add( new \IPS\nexus\Form\RenewalTerm( 'club_renewal_term', $renewTermForEdit, NULL, array( 'allCurrencies' => TRUE ), NULL, NULL, NULL, 'club_renewal_term' ) );

			$memberTabFieldPosition = 'club_renewal_term';
		}
		
		$form->add( new \IPS\Helpers\Form\Upload( 'club_profile_photo', $this->profile_photo ? \IPS\File::get( 'core_Clubs', $this->profile_photo ) : NULL, FALSE, array( 'storageExtension' => 'core_Clubs', 'allowStockPhotos' => TRUE, 'image' => array( 'maxWidth' => 200, 'maxHeight' => 200 ) ) ) );
		
		if ( \IPS\Settings::i()->clubs_locations )
		{
			$form->add( new \IPS\Helpers\Form\Address( 'club_location', $this->location_json ? \IPS\GeoLocation::buildFromJson( $this->location_json ) : NULL, FALSE, array( 'requireFullAddress' => FALSE, 'minimize' => ( $this->location_json ) ? FALSE : TRUE, 'preselectCountry' => FALSE ) ) );
		}
		
		$fieldValues = $this->fieldValues();
		foreach ( \IPS\Member\Club\CustomField::roots() as $field )
		{
			if ( $field->type === 'Editor' )
			{
				if ( $field->allow_attachments AND !$new )
				{
					$field::$editorOptions = array_merge( $field::$editorOptions, array( 'attachIds' => array( $this->id, $field->id, NULL ), 'autoSaveKey' => "clubs-field{$field->id}-{$this->id}" ) );
				}
			}
			$helper = $field->buildHelper( isset( $fieldValues["field_{$field->id}"] ) ? $fieldValues["field_{$field->id}"] : NULL );
			if ( $field->type === 'Editor' )
			{
				if ( $field->allow_attachments AND $new )
				{
					$field::$editorOptions = array_merge( $field::$editorOptions, array( 'attachIds' => array( NULL ), 'autoSaveKey' => "clubs-field{$field->id}-new" ) );
				}
			}
			$form->add( $helper );
		}


		if ( $acp or ( $new and \count( $availableTypes ) > 1 ) )
		{
			$form->add( new \IPS\Helpers\Form\Radio( 'club_show_membertab', ( $this AND $this->show_membertab ) ? $this->show_membertab : 'nonmember', TRUE, array( 'options' => array(
				'nonmember'	=> 'club_membertab_everyone',
				'member'		=> 'club_membertab_members',
				'moderator'	=> 'club_membertab_moderators'
			) ),  NULL, NULL, NULL, 'club_show_membertab' ),$memberTabFieldPosition );
		}
		/* We want to show the member page configuration also while editing the club */
		else if ( !$new )
		{
			$form->add( new \IPS\Helpers\Form\Radio( 'club_show_membertab', ( $this AND $this->show_membertab ) ? $this->show_membertab : 'nonmember', TRUE, array( 'options' => array(
				'nonmember'	=> 'club_membertab_everyone',
				'member'		=> 'club_membertab_members',
				'moderator'	=> 'club_membertab_moderators'
			) ),  NULL, NULL, NULL, 'club_show_membertab' ), $memberTabFieldPosition );
		}
		
		$form->add( new \IPS\Helpers\Form\YesNo( 'show_rules', ( !$new AND $this->rules ) ? TRUE : FALSE, FALSE, array(
			'togglesOn'	=> array(
				'club_rules',
				'club_rules_required'
			)
		) ) );
		$form->add( new \IPS\Helpers\Form\Editor( 'club_rules', ( $this->rules ) ? $this->rules : NULL, FALSE, array(
			'app'			=> 'core',
			'key'			=> 'ClubRules',
			'attachIds'		=> ( $new ) ? array( NULL, NULL, NULL ) : array( $this->id, NULL, 'rules' ),
			'autoSaveKey'	=> ( $new ) ? "club-rules-new" : "club-rules-{$this->id}"
		), NULL, NULL, NULL, 'club_rules' ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'club_rules_required', $this->rules_required, FALSE, array( 'togglesOn' => array( 'club_rules_reacknowledge' ) ), NULL, NULL, NULL, 'club_rules_required' ) );
		
		/* Only show this if we're editing a club */
		if ( !$new )
		{
			$form->add( new \IPS\Helpers\Form\YesNo( 'club_rules_reacknowledge', FALSE, FALSE, [], NULL, NULL, NULL, 'club_rules_reacknowledge' ) );
		}

		return $form;
	}

	/**
	 * Process Club Form
	 *
	 * @param $values
	 * @param bool $acp TRUE if editing in the ACP
	 * @param bool $new TRUE if creating new
	 * @param array|null $availableTypes If creating new, the available types
	 * @return    \IPS\Helpers\Form|NULL
	 */
	public function processForm($values, bool $acp, bool $new = FALSE, array $availableTypes = NULL): ?\IPS\Helpers\Form
	{
		$this->name = $values['club_name'];

		/* If there is only one type available, set it. */
		if( \is_array( $availableTypes ) AND \count( $availableTypes ) == 1 )
		{
			$values['club_type'] = key( $availableTypes );
		}

		$needToUpdatePermissions = FALSE;
		if ( $acp )
		{
			if ( $this->type != $values['club_type'] )
			{
				$this->type = $values['club_type'];
				$needToUpdatePermissions = TRUE;
			}
			if ( $this->owner != $values['club_owner'] )
			{
				$this->owner = $values['club_owner'];
				$this->addMember( $values['club_owner'], Club::STATUS_LEADER, TRUE );

				/* Update purchases for commission */
				if( \IPS\Application::appIsEnabled( 'nexus' ) and \IPS\Settings::i()->clubs_paid_on )
				{
					\IPS\Db::i()->update( 'nexus_purchases', array( 'ps_pay_to' => $this->owner ), array( "ps_pay_to IS NOT NULL and ps_app=? and ps_type=? and ps_item_id=?", 'core', 'club', $this->id ) );
				}
			}
		}
		elseif ( $new )
		{
			$this->type = $values['club_type'];
			$this->owner = \IPS\Member::loggedIn();
		}

		$this->about = $values['club_about'];
		$this->profile_photo = (string) $values['club_profile_photo'];

		if( $values['club_profile_photo'] )
		{
			$this->profile_photo_uncropped = (string) $values['club_profile_photo'];
		}

		if ( isset( $values['club_location'] ) )
		{
			$this->location_json = json_encode( $values['club_location'] );
			if ( $values['club_location']->lat and $values['club_location']->long )
			{
				$this->location_lat = $values['club_location']->lat;
				$this->location_long = $values['club_location']->long;
			}
			else
			{
				$this->location_lat = NULL;
				$this->location_long = NULL;
			}
		}
		else
		{
			$this->location_json = NULL;
			$this->location_lat = NULL;
			$this->location_long = NULL;
		}

		if ( \IPS\Application::appIsEnabled( 'nexus' ) and \IPS\Settings::i()->clubs_paid_on and \IPS\Member::loggedIn()->group['gbw_paid_clubs'] )
		{
			switch ( $values['club_membership_fee'] )
			{
				case 'free':
					$this->fee = NULL;
					$this->renewal_term = 0;
					$this->renewal_units = NULL;
					$this->renewal_price = NULL;
					break;

				case 'paid':
					$this->fee = json_encode( $values['club_fee'] );

					if ( $values['club_renewals'] and $values['club_renewal_term'] )
					{
						$term = $values['club_renewal_term']->getTerm();
						$this->renewal_term = $term['term'];
						$this->renewal_units = $term['unit'];
						$this->renewal_price = json_encode( $values['club_renewal_term']->cost );
					}
					else
					{
						$this->renewal_term = 0;
						$this->renewal_units = NULL;
						$this->renewal_price = NULL;
					}
					break;
			}
		}

		if ( array_key_exists( 'club_show_membertab', $values ) )
		{
			$this->show_membertab		= $values['club_show_membertab'];
		}
		else
		{
			/* Default is "Everybody" */
			$this->show_membertab		= "nonmember";
		}

		$this->save();

		if ( $values['show_rules'] )
		{
			$this->rules = $values['club_rules'];
			\IPS\File::claimAttachments( ( $new ) ? "club-rules-new" : "club-rules-{$this->id}", $this->id, NULL, 'rules' );
			$this->rules_required = (bool) $values['club_rules_required'];

			/* Do we need to reset the acknowledge flags? */
			if ( !$new AND \array_key_exists( 'club_rules_reacknowledge', $values ) )
			{
				if ( $values['club_rules_reacknowledge'] )
				{
					\IPS\Db::i()->update( 'core_clubs_memberships', array( "rules_acknowledged" => FALSE ), array( "club_id=?", $this->id ) );
				}
			}
		}
		else
		{
			$this->rules			= NULL;
			$this->rules_required	= FALSE;

			if ( !$new )
			{
				/* If this isn't a new club, update all memberships in case they decide to re-add rules later on */
				\IPS\Db::i()->update( "core_clubs_memberships", array( 'rules_acknowledged' => FALSE ), array( "club_id=?", $this->id ) );
			}
		}

		if ( $new )
		{
			$this->addMember( \IPS\Member::loggedIn(), Club::STATUS_LEADER );
			$this->acknowledgeRules( \IPS\Member::loggedIn() );
		}
		$this->recountMembers();

		$customFieldValues = array();
		foreach ( \IPS\Member\Club\CustomField::roots() as $field )
		{
			if ( isset( $values["core_clubfield_{$field->id}"] ) )
			{
				$helper							 			= $field->buildHelper();

				if ( $helper instanceof \IPS\Helpers\Form\Upload )
				{
					$customFieldValues[ "field_{$field->id}" ] = (string) $values["core_clubfield_{$field->id}"];
				}
				else
				{
					$customFieldValues[ "field_{$field->id}" ]	= $helper::stringValue( $values["core_clubfield_{$field->id}"] );
				}

				if ( $field->type === 'Editor' )
				{
					$field->claimAttachments( $this->id );
				}
			}
		}
		if ( \count( $customFieldValues ) )
		{
			$customFieldValues['club_id'] = $this->id;
			\IPS\Db::i()->insert( 'core_clubs_fieldvalues', $customFieldValues, TRUE );
		}

		if ( $needToUpdatePermissions )
		{
			foreach ( $this->nodes() as $node )
			{
				try
				{
					$nodeClass = $node['node_class'];
					$node = $nodeClass::load( $node['node_id'] );
					$node->setPermissionsToClub( $this );
				}
				catch ( \Exception $e ) { }
			}
		}

		if( $new and \IPS\Settings::i()->clubs_require_approval and !$this->approved )
		{
			$this->sendModeratorApprovalNotification();
		}

		if( $new and $this->approved )
		{
			\IPS\Api\Webhook::fire( 'club_created', $this );
		}
		else if( !$new AND isset( $this->changed['approved'] ) )
		{
			$this->onApprove();
		}

		return NULL;
	}

	/**
	 * Redirect after save
	 *
	 * @param \IPS\Member\Club $old A clone of the club as it was before
	 * @param \IPS\Member\Club\ $new The club now
	 * @return    array
	 */
	public static function renewalChanges(Club $old, Club $new)
	{
		$changes = array();

		foreach ( array( 'renewal_term', 'renewal_units', 'renewal_price' ) as $k )
		{
			if ( $old->$k != $new->$k )
			{
				$changes[ $k ] = $old->$k;
			}
		}

		return $changes;
	}

	/**
	 * Send moderator notice of new club pending approval
	 *
	 * @param	\IPS\Member|NULL	$savingMember		Member saving the club or NULL for currently logged in member
	 * @return void
	 */
	public function sendModeratorApprovalNotification( $savingMember = NULL )
	{
		$savingMember = $savingMember ?? \IPS\Member::loggedIn();

		/* Send notification to mods */
		$moderators = array( 'm' => array(), 'g' => array() );
		foreach ( \IPS\Db::i()->select( '*', 'core_moderators' ) as $mod )
		{
			$canView = FALSE;
			if ( $mod['perms'] == '*' )
			{
				$canView = TRUE;
			}
			if ( $canView === FALSE )
			{
				$perms = json_decode( $mod['perms'], TRUE );

				if ( isset( $perms['can_access_all_clubs'] ) AND $perms['can_access_all_clubs'] === TRUE )
				{
					$canView = TRUE;
				}
			}
			if ( $canView === TRUE )
			{
				$moderators[ $mod['type'] ][] = $mod['id'];
			}
		}
		$notification = new \IPS\Notification( \IPS\Application::load('core'), 'unapproved_club', $this, array( $this ) );
		foreach ( \IPS\Db::i()->select( '*', 'core_members', ( \count( $moderators['m'] ) ? \IPS\Db::i()->in( 'member_id', $moderators['m'] ) . ' OR ' : '' ) . \IPS\Db::i()->in( 'member_group_id', $moderators['g'] ) . ' OR ' . \IPS\Db::i()->findInSet( 'mgroup_others', $moderators['g'] ) ) as $member )
		{
			if( $member['member_id'] != $savingMember->member_id )
			{
				$notification->recipients->attach( \IPS\Member::constructFromData( $member ) );
			}
		}

		if( \count( $notification->recipients ) )
		{
			$notification->send();
		}
	}
	
	/**
	 * Custom Field Values
	 *
	 * @return	array
	 */
	public function fieldValues()
	{
		try
		{
			return \IPS\Db::i()->select( '*', 'core_clubs_fieldvalues', array( 'club_id=?', $this->id ) )->first();
		}
		catch ( \UnderflowException $e )
		{
			return array();
		}
	}
	
	/**
	 * Cover Photo
	 *
	 * @param	bool	$getOverlay	If FALSE, will not set the overlay, which saves queries if it will not be used (such as in clubCard)
	 * @param	string	$position	Position of cover photo
	 * @return	\IPS\Helpers\CoverPhoto
	 */
	public function coverPhoto( $getOverlay=TRUE, $position='full' )
	{
		$photo = new \IPS\Helpers\CoverPhoto;

		$photo->maxSize = \IPS\Settings::i()->club_max_cover;
		if ( $this->cover_photo )
		{
			$photo->file = \IPS\File::get( 'core_Clubs', $this->cover_photo );
			$photo->offset = $this->cover_offset;
		}
		if ( $getOverlay )
		{
			$photo->overlay = \IPS\Theme::i()->getTemplate( 'clubs', 'core', 'front' )->coverPhotoOverlay( $this, $position );
		}
		$photo->editable = $this->isLeader();
		$photo->object = $this;

		return $photo;
	}

	/**
	 * Produce a random hex color for a background
	 *
	 * @return string
	 */
	public function coverPhotoBackgroundColor()
	{
		return $this->staticCoverPhotoBackgroundColor( $this->name );
	}
	
	/**
	 * Location
	 *
	 * @return	\IPS\GeoLocation|NULL
	 */
	public function location()
	{
		if ( $this->location_json )
		{
			return \IPS\GeoLocation::buildFromJson( $this->location_json );
		}
		return NULL;
	}
	
	/**
	 * Is paid?
	 *
	 * @return	bool
	 */
	public function isPaid()
	{
		if ( \IPS\Application::appIsEnabled( 'nexus' ) and \IPS\Settings::i()->clubs_paid_on and $this->fee )
		{
			return TRUE;
		}
		
		return FALSE;
	}
	
	/**
	 * Message to explain paid club joining process
	 *
	 * @return	string
	 */
	public function memberFeeMessage()
	{
		if ( $this->type === static::TYPE_CLOSED )
		{
			return \IPS\Member::loggedIn()->language()->addToStack( 'club_closed_join_fee', FALSE, array( 'sprintf' => array( $this->priceBlurb() ) ) );
		}
		else
		{
			return \IPS\Member::loggedIn()->language()->addToStack( 'club_open_join_fee', FALSE, array( 'sprintf' => array( $this->priceBlurb() ) ) );
		}
	}
	
	/**
	 * Joining fee
	 *
	 * @param	string|NULL	$currency	Desired currency, or NULL to choose based on member's chosen currency
	 * @return	\IPS\nexus\Money|NULL
	 */
	public function joiningFee( $currency = NULL )
	{
		if ( $this->isPaid() )
		{
			if ( !$currency )
			{
				$currency = ( isset( \IPS\Request::i()->cookie['currency'] ) and \in_array( \IPS\Request::i()->cookie['currency'], \IPS\nexus\Money::currencies() ) ) ? \IPS\Request::i()->cookie['currency'] : \IPS\nexus\Customer::loggedIn()->defaultCurrency();
			}
			
			$costs = json_decode( $this->fee, TRUE );
			
			if ( \is_array( $costs ) and isset( $costs[ $currency ]['amount'] ) and $costs[ $currency ]['amount'] )
			{
				return new \IPS\nexus\Money( $costs[ $currency ]['amount'], $currency );
			}
		}
		
		return NULL;
	}
	
	/**
	 * Renewal fee
	 *
	 * @param	string|NULL	$currency	Desired currency, or NULL to choose based on member's chosen currency
	 * @return	\IPS\nexus\Money|NULL
	 * @throws	\OutOfRangeException
	 */
	public function renewalTerm( $currency = NULL )
	{
		if ( $this->renewal_price and $renewalPrices = json_decode( $this->renewal_price, TRUE ) )
		{
			if ( !$currency )
			{
				$currency = ( isset( \IPS\Request::i()->cookie['currency'] ) and \in_array( \IPS\Request::i()->cookie['currency'], \IPS\nexus\Money::currencies() ) ) ? \IPS\Request::i()->cookie['currency'] : \IPS\nexus\Customer::loggedIn()->defaultCurrency();
			}
			
			if ( isset( $renewalPrices[ $currency ] ) )
			{
				return new \IPS\nexus\Purchase\RenewalTerm( new \IPS\nexus\Money( $renewalPrices[ $currency ]['amount'], $currency ), new \DateInterval( 'P' . $this->renewal_term . mb_strtoupper( $this->renewal_units ) ) );
			}
			else
			{
				throw new \OutOfRangeException;
			}
		}
		
		return NULL;
	}
	
	/**
	 * Price Blurb
	 *
	 * @return	string|NULL
	 */
	public function priceBlurb()
	{
		if ( \IPS\Application::appIsEnabled( 'nexus' ) and \IPS\Settings::i()->clubs_paid_on )
		{
			if ( $this->isPaid() )
			{				
				if ( $fee = $this->joiningFee() )
				{
					/* Include tax? */
					$taxRate = NULL;
					if ( \IPS\Settings::i()->nexus_show_tax and \IPS\Settings::i()->clubs_paid_tax )
					{
						try
						{
							$taxRate = new \IPS\Math\Number( \IPS\nexus\Tax::load( \IPS\Settings::i()->clubs_paid_tax )->rate( \IPS\nexus\Customer::loggedIn()->estimatedLocation() ) );
						}
						catch ( \OutOfRangeException $e ) {}
					}
							
					if ( $taxRate )
					{
						$fee->amount = $fee->amount->add( $fee->amount->multiply( $taxRate ) );
					}
				
					try
					{
						$renewalTerm = $this->renewalTerm( $fee->currency );
						
						if ( $renewalTerm and $taxRate )
						{
							$renewalTerm->cost->amount = $renewalTerm->cost->amount->add( $renewalTerm->cost->amount->multiply( $taxRate ) );
						}
						
						if ( !$renewalTerm )
						{
							return $fee;
						}
						else if ( $renewalTerm AND $renewalTerm->cost->amount == $fee->amount )
						{
							return $renewalTerm;
						}
						else
						{
							return \IPS\Member::loggedIn()->language()->addToStack( 'club_fee_plus_renewal', FALSE, array( 'sprintf' => array( $fee, $renewalTerm ) ) );
						}
					}
					catch ( \OutOfRangeException $e )
					{
						return \IPS\Member::loggedIn()->language()->addToStack('club_paid_unavailable');
					}
				}
				else
				{
					return \IPS\Member::loggedIn()->language()->addToStack('club_paid_unavailable');
				}
			}
			else
			{
				return \IPS\Member::loggedIn()->language()->addToStack('club_membership_free');
			}
		}
		return NULL;
	}

	/**
	 * Generate invoice for a member
	 *
	 * @param	\IPS\nexus\Customer|null	$member	Member to generate the invoice for
	 * @return	\IPS\Http\Url
	 */
	public function generateInvoice( \IPS\nexus\Customer $member = NULL )
	{
		$member = $member ?: \IPS\nexus\Customer::loggedIn();

		$fee = $this->joiningFee();

		/* Create the item */		
		$item = new \IPS\core\extensions\nexus\Item\ClubMembership( $this->name, $fee );
		$item->id = $this->id;
		try
		{
			$item->tax = \IPS\Settings::i()->clubs_paid_tax ? \IPS\nexus\Tax::load( \IPS\Settings::i()->clubs_paid_tax ) : NULL;
		}
		catch ( \OutOfRangeException $e ) { }
		if ( \IPS\Settings::i()->clubs_paid_gateways )
		{
			$item->paymentMethodIds = explode( ',', \IPS\Settings::i()->clubs_paid_gateways );
		}
		$item->renewalTerm = $this->renewalTerm( $fee->currency );
		$item->payTo = $this->owner;
		$item->commission = \IPS\Settings::i()->clubs_paid_commission;
		if ( $fees = \IPS\Settings::i()->clubs_paid_transfee and isset( $fees[ $fee->currency ] ) )
		{
			$item->fee = new \IPS\nexus\Money( $fees[ $fee->currency ]['amount'], $fee->currency );
		}
		
		/* Generate the invoice */
		$invoice = new \IPS\nexus\Invoice;
		$invoice->currency = $fee->currency;
		$invoice->member = $member;
		$invoice->addItem( $item );
		$invoice->return_uri = "app=core&module=clubs&controller=view&id={$this->id}";
		$invoice->save();

		return $invoice->checkoutUrl();
	}
	
		
	/* !Manage Memberships */
		
	/**
	 * Get members
	 *
	 * @param	array		$statuses			The membership statuses to get
	 * @param	array|int	$limit				Rows to fetch or array( offset, limit )
	 * @param	string		$order				ORDER BY clause
	 * @param	int			$returnType			0 = core_clubs_memberships rows, 1 = core_clubs_memberships plus \IPS\Member::columnsForPhoto(), 2 = full core_members rows, 3 = same as 1 but also getting name of adder/invitee, 4 = count only, 5 = same as 3 but also getting expire date
	 * @return	\IPS\Db\Select|int
	 */
	public function members( $statuses = array( 'member', 'moderator', 'leader' ), $limit = 25, $order = 'core_clubs_memberships.joined ASC', $returnType = 1 )
	{	
		if ( $returnType === 4 )
		{
			return \IPS\Db::i()->select( 'COUNT(*)', 'core_clubs_memberships', array( array( 'club_id=?', $this->id ), array( \IPS\Db::i()->in( 'status', $statuses ) ) ) )->first();
		}
		else
		{
			if ( $returnType === 2 )
			{
				$columns = 'core_members.*';
			}
			else
			{
				$columns = 'core_clubs_memberships.member_id,core_clubs_memberships.joined,core_clubs_memberships.status,core_clubs_memberships.added_by,core_clubs_memberships.invited_by';
				if ( $returnType === 1 or $returnType === 3 or $returnType === 5 )
				{
					$columns .= ',' . implode( ',', array_map( function( $column ) {
						return 'core_members.' . $column;
					}, \IPS\Member::columnsForPhoto() ) );
				}
				if ( $returnType === 3 or $returnType === 5 )
				{
					$columns .= ',added_by.name,invited_by.name';
					
					if ( $returnType === 5 and \IPS\Application::appIsEnabled('nexus') and \IPS\Settings::i()->clubs_paid_on and $this->isPaid() and $this->renewal_price )
					{
						$columns .= ',nexus_purchases.ps_active,nexus_purchases.ps_expire';
					}
				}
			}
			
			$select = \IPS\Db::i()->select( $columns, 'core_clubs_memberships', array( array( 'club_id=?', $this->id ), array( \IPS\Db::i()->in( 'status', $statuses ) ) ), $order, $limit, NULL, NULL, \IPS\Db::SELECT_MULTIDIMENSIONAL_JOINS );
		}

		if ( $returnType === 1 or $returnType === 2 or $returnType === 3 or $returnType === 5 )
		{
			$select->join( 'core_members', 'core_members.member_id=core_clubs_memberships.member_id' );
		}
		if ( $returnType === 3 or $returnType === 5 )
		{
			$select->join( array( 'core_members', 'added_by' ), 'added_by.member_id=core_clubs_memberships.added_by' );
			$select->join( array( 'core_members', 'invited_by' ), 'invited_by.member_id=core_clubs_memberships.invited_by' );
			
			if ( $returnType === 5 and \IPS\Application::appIsEnabled('nexus') and \IPS\Settings::i()->clubs_paid_on and $this->isPaid() and $this->renewal_price )
			{
				$select->join( 'nexus_purchases', array( 'ps_app=? AND ps_type=? AND ps_member=core_clubs_memberships.member_id AND ps_item_id=? AND ps_cancelled=0', 'core', 'club', $this->id ) );
			}
		}

		return $select;
	}	
	
	/**
	 * @brief	Cache of randomTenMembers()
	 */
	protected $_randomTenMembers = NULL;
	
	/**
	 * Get basic data of a random ten members in the club (for cards)
	 *
	 * @return	array
	 */
	public function randomTenMembers()
	{
		if ( !isset( $this->_randomTenMembers ) )
		{
			$this->_randomTenMembers = iterator_to_array( $this->members( array( 'leader', 'moderator', 'member' ), 10, 'RAND()' ) );
		}
		return $this->_randomTenMembers;
	}
	
	/**
	 * Add a member
	 *
	 * @param	\IPS\Member			$member		The member
	 * @param	string				$status		Status
	 * @param	bool				$update		Update membership if already a member?
	 * @param	\IPS\Member|NULL	$addedBy	The leader who added them, or NULL if joining themselves
	 * @param	\IPS\Member|NULL	$invitedBy	The member who invited them, or NULL if joining themselves
	 * @param	bool				$updateJoinedDate	Whether to update the joined date or not (FALSE by default, set to TRUE when an invited member accepts)
	 * @return	void
	 * @throws	\OverflowException	Member is already in the club and $update was FALSE
	 */
	public function addMember( \IPS\Member $member, $status = 'member', $update = FALSE, \IPS\Member $addedBy = NULL, \IPS\Member $invitedBy = NULL, $updateJoinedDate = FALSE )
	{
		try
		{
			\IPS\Db::i()->insert( 'core_clubs_memberships', array(
				'club_id'	=> $this->id,
				'member_id'	=> $member->member_id,
				'joined'	=> time(),
				'status'	=> $status,
				'added_by'	=> $addedBy ? $addedBy->member_id : NULL,
				'invited_by'=> $invitedBy ? $invitedBy->member_id : NULL
			) );

			$member->rebuildPermissionArray();
			if ( \IPS\Settings::i()->club_nodes_in_apps )
			{
				$member->create_menu = NULL;
				$member->save();
			}

			$this->memberStatuses[ $member->member_id ] = $status;
		}
		catch ( \IPS\Db\Exception $e )
		{
			if ( $e->getCode() === 1062 )
			{
				if ( $update )
				{
					$save = array( 'status'	=> $status );
					if ( $addedBy )
					{
						$save['added_by'] = $addedBy->member_id;
					}

					if ( $invitedBy )
					{
						$save['invited_by'] = $invitedBy->member_id;
					}
				
					if( $updateJoinedDate === TRUE )
					{
						$save['joined']	= time();
					}
								
					/* Log to Member History */
					if ( \in_array( $status, array( \IPS\Member\Club::STATUS_MEMBER, \IPS\Member\Club::STATUS_BANNED ) ) )
					{
						$memberStatus = $this->memberStatus( $member, 2 );
						
						/* Joining by invite */
						if ( $memberStatus['status'] == \IPS\Member\Club::STATUS_INVITED ) 
						{
							$addedBy = \IPS\Member::load( $memberStatus['invited_by'] );
						} 
						$member->logHistory( 'core', 'club_membership', array('club_id' => $this->id, 'type' => $status ), $addedBy );
					}
					\IPS\Db::i()->update( 'core_clubs_memberships', $save, array( 'club_id=? AND member_id=?', $this->id, $member->member_id ) );
					
					$member->rebuildPermissionArray();
					if ( \IPS\Settings::i()->club_nodes_in_apps )
					{
						$member->create_menu = NULL;
						$member->save();
					}
				}
				else
				{
					throw new \OverflowException;
				}
			}
			else
			{
				throw $e;
			}			
		}

		$params = [
			'club' => $this,
			'member' => $member,
			'status' => $status
		];

		\IPS\Api\Webhook::fire( 'club_member_added', $params );
		
		/* Achievements */
		if( \in_array( $status, array( static::STATUS_MEMBER, static::STATUS_MODERATOR, static::STATUS_LEADER, static::STATUS_EXPIRED, static::STATUS_EXPIRED_MODERATOR ) ) )
		{
			$member->achievementAction( 'core', 'JoinClub', $this );
		}		
	}

	/**
	 * Send an invitation to a member
	 *
	 * @param	\IPS\Member		$inviter	Person doing the inviting
	 * @param	array			$members	Array of members being invited
	 * @return	void
	 */
	public function sendInvitation( \IPS\Member $inviter, $members )
	{
		$notification = new \IPS\Notification( \IPS\Application::load('core'), 'club_invitation', $this, array( $this, $inviter ), array( 'invitedBy' => $inviter->member_id ) );
		foreach ( $members as $member )
		{
			if ( $member instanceof \IPS\Member )
			{
				$memberStatus = $this->memberStatus( $member );
				if ( !$memberStatus or \in_array( $memberStatus, array( \IPS\Member\Club::STATUS_INVITED, \IPS\Member\Club::STATUS_REQUESTED, \IPS\Member\Club::STATUS_DECLINED, \IPS\Member\Club::STATUS_BANNED ) ) )
				{
					$notification->recipients->attach( $member );
				}
			}
		}
		$notification->send();
	}
	
	/**
	 * Remove a member
	 *
	 * @param	\IPS\Member	$member		The member
	 * @return	void
	 */
	public function removeMember( \IPS\Member $member )
	{
		\IPS\Db::i()->delete( 'core_clubs_memberships', array( 'club_id=? AND member_id=?', $this->id, $member->member_id ) );

		\IPS\Api\Webhook::fire( 'club_member_removed', ['club' => $this, 'member' => $member] );

		
		$member->rebuildPermissionArray();
		if ( \IPS\Settings::i()->club_nodes_in_apps )
		{
			$member->create_menu = NULL;
			$member->save();
		}
	}
	
	/**
	 * Recount members
	 *
	 * @return	void
	 */
	public function recountMembers()
	{
		$this->members = \IPS\Db::i()->select( 'COUNT(*)', 'core_clubs_memberships', array( 'club_id=? AND ( status=? OR status=? OR status=? OR status=? OR status=? )', $this->id, static::STATUS_MEMBER, static::STATUS_MODERATOR, static::STATUS_LEADER, static::STATUS_EXPIRED, static::STATUS_EXPIRED_MODERATOR ) )->first();
		$this->save();
	}
	
	/* !Manage Nodes */
	
	/**
	 * Get available features
	 *
	 * @param	\IPS\Member|NULL	$member	If a member object is provided, will only get the types that member can create
	 * @return	array
	 */
	public static function availableNodeTypes( \IPS\Member $member = NULL )
	{
		$return = array();
						
		foreach ( \IPS\Application::allExtensions( 'core', 'ContentRouter' ) as $contentRouter )
		{
			foreach ( $contentRouter->classes as $class )
			{
				if ( isset( $class::$containerNodeClass ) and \IPS\IPS::classUsesTrait( $class::$containerNodeClass, 'IPS\Content\ClubContainer' ) )
				{					
					if ( $member === NULL or $member->group['g_club_allowed_nodes'] === '*' or \in_array( $class::$containerNodeClass, explode( ',', $member->group['g_club_allowed_nodes'] ) ) )
					{
						$return[] = $class::$containerNodeClass;
					}
				}
			}
		}
				
		return array_unique( $return );
	}
	
	/**
	 * Get Pages
	 *
	 * @return	array
	 */
	public function pages(): array
	{
		$return = array();
		foreach( new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'core_club_pages', array( "page_club=?", $this->id ) ), 'IPS\Member\Club\Page' ) AS $row )
		{
			$return['page-' . $row->id] = $row;
		}
		return $return;
	}

	/**
	 * @brief	Cached nodes
	 */
	protected $cachedNodes	= NULL;
	
	/**
	 * Get Node names and URLs
	 *
	 * @return	array
	 */
	public function nodes()
	{
		if( $this->cachedNodes === NULL )
		{
			$this->cachedNodes = array();
			
			foreach ( \IPS\Db::i()->select( '*', 'core_clubs_node_map', array( 'club_id=?', $this->id ) ) as $row )
			{
				$class		= $row['node_class'];
				$classBits	= explode( '\\', $class );

				if( !\IPS\Application::load( $classBits[1] )->_enabled )
				{
					continue;
				}

				try
				{
					$this->cachedNodes[ $row['id'] ] = array(
					'name'			=> $row['name'],
					'url'			=> $row['node_class']::load( $row['node_id'] )->url(),
					'node_class'	=> $row['node_class'],
					'node_id'		=> $row['node_id'],
					'public'		=> $row['public']
					);
				}
				catch( \OutOfRangeException $e )
				{
					\IPS\Log::log( 'Missing club node ' . $row['node_class'] . ' ' . $row['node_id'] . " is being loaded.", 'club_nodes');
				}

			}
		}

		return $this->cachedNodes;
	}
	
	/* !Permissions */
	
	/**
	 * Load and check permissions
	 *
	 * @param	mixed	$id		ID
	 * @return	static
	 * @throws	\OutOfRangeException
	 */
	public static function loadAndCheckPerms( $id )
	{
		$obj = static::load( $id );

		if ( !$obj->canView() )
		{
			throw new \OutOfRangeException;
		}

		return $obj;
	}
	
	/**
	 * Can a member see this club and who's in it?
	 *
	 * @param	\IPS\Member	$member	The member (NULL for currently logged in member)
	 * @return	bool
	 */
	public function canView( \IPS\Member $member = NULL )
	{
		$member = $member ?: \IPS\Member::loggedIn();
		
		/* If we can't access the module, stop here */
		if ( !$member->canAccessModule( \IPS\Application\Module::get( 'core', 'clubs', 'front' ) ) )
		{
			return FALSE;
		}

		/* If it's not approved, only moderators and the person who created it can see it */
		if ( \IPS\Settings::i()->clubs_require_approval and !$this->approved )
		{
			return ( $member->modPermission('can_access_all_clubs') or ( $this->owner AND $member->member_id == $this->owner->member_id ) );
		}
		
		/* Unless it's private, everyone can see it exists */
		if ( $this->type !== static::TYPE_PRIVATE )
		{
			return TRUE;
		}
		
		/* Moderators can see everything */
		if ( $member->modPermission('can_access_all_clubs') )
		{
			return TRUE;
		}
				
		/* Otherwise, only if they're a member or have been invited */		
		return \in_array( $this->memberStatus( $member ), array( static::STATUS_MEMBER, static::STATUS_MODERATOR, static::STATUS_LEADER, static::STATUS_INVITED, static::STATUS_INVITED_BYPASSING_PAYMENT, static::STATUS_EXPIRED, static::STATUS_EXPIRED_MODERATOR ) );
	}
	
	/**
	 * Can a member join (or ask to join) this club?
	 *
	 * @param	\IPS\Member	$member	The member (NULL for currently logged in member)
	 * @return	bool
	 */
	public function canJoin( \IPS\Member $member = NULL )
	{
		/* If it's not approved, nobody can join it */
		if ( \IPS\Settings::i()->clubs_require_approval and !$this->approved )
		{
			return FALSE;
		}
		
		/* Nobody can join public clubs */
		if ( $this->type === static::TYPE_PUBLIC )
		{
			return FALSE;
		}
		
		/* Guests cannot join clubs */
		$member = $member ?: \IPS\Member::loggedIn();
		if ( !$member->member_id )
		{
			return FALSE;
		}
		
		/* If they're already a member, or have aleready asked to join, they can't join again */
		$memberStatus = $this->memberStatus( $member );
		if ( \in_array( $memberStatus, array( static::STATUS_MEMBER, static::STATUS_MODERATOR, static::STATUS_LEADER, static::STATUS_REQUESTED, static::STATUS_DECLINED, static::STATUS_EXPIRED, static::STATUS_EXPIRED_MODERATOR ) ) )
		{
			return FALSE;
		}

		/* If they are banned, they cannot join */
		if ( $memberStatus === static::STATUS_BANNED )
		{
			return FALSE;
		}
		
		/* If it's private or read-only, they have to be invited */
		if ( $this->type === static::TYPE_PRIVATE or $this->type === static::TYPE_READONLY )
		{
			return \in_array( $memberStatus, array( static::STATUS_INVITED, static::STATUS_INVITED_BYPASSING_PAYMENT ) );
		}
		
		/* Otherwise they can join */
		return TRUE;
	}
	
	/**
	 * Can a member see the posts in this club?
	 *
	 * @param	\IPS\Member	$member	The member (NULL for currently logged in member)
	 * @return	bool
	 */
	public function canRead( \IPS\Member $member = NULL )
	{
		switch ( $this->type )
		{
			case static::TYPE_PUBLIC:
			case static::TYPE_OPEN:
			case static::TYPE_READONLY:
				return TRUE;
				
			case static::TYPE_CLOSED:
			case static::TYPE_PRIVATE:
				$member = $member ?: \IPS\Member::loggedIn();
				return ( $member->modPermission('can_access_all_clubs') or \in_array( $this->memberStatus( $member ), array( static::STATUS_MEMBER, static::STATUS_MODERATOR, static::STATUS_LEADER ) ) );
		}
	}
	
	/**
	 * Can a member participate this club?
	 *
	 * @param	\IPS\Member	$member	The member (NULL for currently logged in member)
	 * @return	bool
	 */
	public function canPost( \IPS\Member $member = NULL )
	{
		switch ( $this->type )
		{
			case static::TYPE_PUBLIC:
				return TRUE;
				
			case static::TYPE_OPEN:
			case static::TYPE_CLOSED:
			case static::TYPE_PRIVATE:
			case static::TYPE_READONLY:
				$member = $member ?: \IPS\Member::loggedIn();
				return $member->modPermission('can_access_all_clubs') or \in_array( $this->memberStatus( $member ), array( static::STATUS_MEMBER, static::STATUS_MODERATOR, static::STATUS_LEADER ) );
		}
	}
	
	/**
	 * Can a member invite other members
	 *
	 * @param	\IPS\Member	$member	The member (NULL for currently logged in member)
	 * @return	bool
	 */
	public function canInvite( \IPS\Member $member = NULL )
	{
		if ( \IPS\Settings::i()->clubs_require_approval and !$this->approved )
		{
			return FALSE;
		}
		
		switch ( $this->type )
		{
			case static::TYPE_PUBLIC:
				return FALSE;
				
			case static::TYPE_OPEN:
				$member = $member ?: \IPS\Member::loggedIn();
				return $member->modPermission('can_access_all_clubs') or \in_array( $this->memberStatus( $member ), array( static::STATUS_MEMBER, static::STATUS_MODERATOR, static::STATUS_LEADER ) );
				
			case static::TYPE_CLOSED:
			case static::TYPE_PRIVATE:
			case static::TYPE_READONLY:
				return $this->isLeader( $member );
		}
	}

	/**
	 * Does this user have permissions to manage the navigation
	 *
	 * @param	\IPS\Member	$member	The member (NULL for currently logged in member)
	 * @return	bool
	 */
	public function canManageNavigation( \IPS\Member $member = NULL )
	{
		$member = $member ?: \IPS\Member::loggedIn();
		return $this->isLeader( $member );
	}
	
	/**
	 * Does this user have leader permissions in the club?
	 *
	 * @param	\IPS\Member	$member	The member (NULL for currently logged in member)
	 * @return	bool
	 */
	public function isLeader( \IPS\Member $member = NULL )
	{
		$member = $member ?: \IPS\Member::loggedIn();
		return $member->modPermission('can_access_all_clubs') or $this->memberStatus( $member ) === static::STATUS_LEADER;
	}
	
	/**
	 * Does this user have moderator permissions in the club?
	 *
	 * @param	\IPS\Member	$member	The member (NULL for currently logged in member)
	 * @return	bool
	 */
	public function isModerator( \IPS\Member $member = NULL )
	{
		$member = $member ?: \IPS\Member::loggedIn();
		return $member->modPermission('can_access_all_clubs') or \in_array( $this->memberStatus( $member ), array( static::STATUS_MODERATOR, static::STATUS_LEADER ) );
	}

	
	/**
	 * @brief	Membership status cache
	 */
	public $memberStatuses = array();
	
	/**
	 * Get status of a particular member
	 *
	 * @param	\IPS\Member	$member		The member
	 * @param	int			$returnType	1 will return a string with the type or NULL if not applicable. 2 will return array with status, joined, accepted_by, invited_by
	 * @return	mixed
	 */
	public function memberStatus( \IPS\Member $member, $returnType = 1 )
	{
		if ( !$member->member_id )
		{
			return NULL;
		}

		if ( !array_key_exists( $member->member_id, $this->memberStatuses ) or $returnType === 2 )
		{
			try
			{
				$val = \IPS\Db::i()->select( $returnType === 2 ? '*' : array( 'status' ), 'core_clubs_memberships', array( 'club_id=? AND member_id=?', $this->id, $member->member_id ) )->first();
				
				if ( $returnType === 2 )
				{
					return $val;
				}
				else
				{
					$this->memberStatuses[ $member->member_id ] = $val;
				}
			}
			catch ( \UnderflowException $e )
			{
				$this->memberStatuses[ $member->member_id ] = NULL;
			}
		}
		
		return $this->memberStatuses[ $member->member_id ];
	}
	
	/* ! Following */

	/**
	 * @brief	Following publicly
	 */
	const FOLLOW_PUBLIC = 1;

	/**
	 * @brief	Following anonymously
	 */
	const FOLLOW_ANONYMOUS = 2;
	
	/**
	 * @brief	Cache for current follow data, used on "My Followed Content" screen
	 */
	public $_followData;
		
	/**
	 * @brief	Application
	 */
	public static $application = 'core';
	
	/**
	 * Followers Count
	 *
	 * @param	int						$privacy		static::FOLLOW_PUBLIC + static::FOLLOW_ANONYMOUS
	 * @param	array					$frequencyTypes	array( 'immediate', 'daily', 'weekly' )
	 * @param	\IPS\DateTime|int|NULL	$date			Only users who started following before this date will be returned. NULL for no restriction
	 * @return	int
	 */
	public function followersCount( $privacy=3, $frequencyTypes=array( 'none', 'immediate', 'daily', 'weekly' ), $date=NULL )
	{
		/* Return the count */
		return static::_followersCount( 'club', $this->id, $privacy, $frequencyTypes, $date );
	}
	
	/**
	 * Followers
	 *
	 * @param	int						$privacy		static::FOLLOW_PUBLIC + static::FOLLOW_ANONYMOUS
	 * @param	array					$frequencyTypes	array( 'immediate', 'daily', 'weekly' )
	 * @param	\IPS\DateTime|int|NULL	$date			Only users who started following before this date will be returned. NULL for no restriction
	 * @param	int|array				$limit			LIMIT clause
	 * @param	string					$order			Column to order by
	 * @return	\IPS\Db\Select|NULL
	 * @throws	\BadMethodCallException
	 */
	public function followers( $privacy=3, $frequencyTypes=array( 'none', 'immediate', 'daily', 'weekly' ), $date=NULL, $limit=array( 0, 25 ), $order=NULL )
	{		
		return static::_followers( 'club', $this->id, $privacy, $frequencyTypes, $date, $limit, $order );
	}
	
	/* ! Utility */

	/**
	 * [ActiveRecord] Delete Record
	 *
	 * @return	void
	 */
	public function delete()
	{
		parent::delete();

		\IPS\Api\Webhook::fire( 'club_deleted', $this );

		foreach( new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'core_club_pages', array( 'page_club=?', $this->id ) ), 'IPS\Member\Club\Page' ) as $page )
		{
			$page->delete( FALSE );
		}

		$this->coverPhoto( FALSE )->delete();
	}
	
	/**
	 * Remove nodes that are owned by a specific application. Used when uninstalling an app
	 *
	 * @param	\IPS\Application	$app	The application being deleted
	 * @return void
	 */
	public static function deleteByApplication( \IPS\Application $app )
	{
		foreach( \IPS\Db::i()->select( 'node_class', 'core_clubs_node_map', NULL, NULL, NULL, 'node_class' ) as $class )
		{
			if ( isset( $class::$contentItemClass ) )
			{
				$contentItemClass = $class::$contentItemClass;

				if ( $contentItemClass::$application == $app->directory )
				{
					\IPS\Db::i()->delete( 'core_clubs_node_map', array( 'node_class=?', $class  ) );
				}
			}
		}
	}

	/**
	 * Get output for API
	 *
	 * @param	\IPS\Member|NULL	$authorizedMember	The member making the API request or NULL for API Key / client_credentials
	 * @return		array
	 * @apiresponse	int			id				ID number
	 * @apiresponse	string		name			Name
	 * @apiresponse	string		url				URL to the club
	 * @apiresponse	string		type			Type of club (public, open, closed, private, readonly)
	 * @clientapiresponse	bool	approved	Whether the club is approved or not
	 * @apiresponse	datetime	created			Datetime the club was created
	 * @apiresponse	int			memberCount		Number of members in the club
	 * @apiresponse	\IPS\Member		owner		Club owner
	 * @apiresponse	string|null		photo			URL to the club's profile photo
	 * @apiresponse	bool		paid			Whether the club is paid or not
	 * @apiresponse	bool		featured		Whether the club is featured or not
	 * @apiresponse	\IPS\GeoLocation|NULL		location			Geolocation object representing the club's location, or NULL if no location is available
	 * @apiresponse	string		about			Club 'about' information supplied by owner
	 * @apiresponse	datetime	lastActivity	Datetime of last activity within the club
	 * @apiresponse	int		contentCount		Count of all content items + comments in the club
	 * @apiresponse	string|NULL		coverPhotoUrl		URL to the club's cover photo, or NULL if no cover photo is available
	 * @apiresponse	string		coverOffset			Cover photo offset
	 * @apiresponse	string		coverPhotoColor		Cover photo overlay background color
	 * @apiresponse	[\IPS\Member]		members		Club members
	 * @apiresponse	[\IPS\Member]		leaders		Club leaders
	 * @apiresponse	[\IPS\Member]		moderators		Club moderators
	 * @apiresponse	[\IPS\core\ProfileFields\Api\Field]		fieldValues			Club's custom field values
	 * @apiresponse	[\IPS\Node\Model]		nodes				Nodes created for this club
	 * @apiresponse	\IPS\nexus\Money|null	joiningFee	Cost to join the club, or null if there is no cost
	 * @apiresponse	\IPS\nexus\Purchase\RenewalTerm|null	renewalTerm	Renewal term for the club, or null if there are no renewals
	 * @note	When trying to determine all users who can access the club, the owner object should be combined with all leaders, moderators and members. Only up to 250 members will be returned (sorted by most recently joining the club) but the full member count can be seen with the memberCount property.
	 */
	public function apiOutput( \IPS\Member $authorizedMember = NULL )
	{
		$coverPhoto = NULL;

		if ( $this->cover_photo )
		{
			$coverPhoto = (string) \IPS\File::get( 'core_Clubs', $this->cover_photo )->url;
		}

		$members		= array();
		$leaders		= array();
		$moderators		= array();

		foreach( $this->members( array( 'member', 'moderator', 'leader' ), 250, 'core_clubs_memberships.joined DESC', 2 ) as $member )
		{
			$member = \IPS\Member::constructFromData( $member );

			if( $this->owner != $member )
			{
				if( $this->isLeader( $member ) )
				{
					$leaders[] = $member->apiOutput();
				}
				elseif( $this->isModerator( $member ) )
				{
					$moderators[] = $member->apiOutput();
				}
				else
				{
					$members[] = $member->apiOutput();
				}
			}
		}

		$customFields	= array();
		$fieldValues	= $this->fieldValues();

		if( \IPS\Member\Club\CustomField::roots() )
		{
			foreach( \IPS\Member\Club\CustomField::roots() as $field )
			{
				if( isset( $fieldValues['field_' . $field->id ] ) )
				{
					$fieldObject = new \IPS\core\ProfileFields\Api\Field( \IPS\Lang::load( \IPS\Lang::defaultLanguage() )->get( 'core_clubfield_' . $field->id ), $fieldValues[ 'field_' . $field->id ] );
					$customFields[] = $fieldObject->apiOutput( $authorizedMember );
				}
			}
		}

		$return = array(
			'id'				=> $this->id,
			'name'				=> $this->name,
			'url'				=> (string) $this->url(),
			'type'				=> $this->type,
			'created'			=> $this->created->rfc3339(),
			'memberCount'		=> $this->members,
			'owner'				=> $this->owner ? $this->owner->apiOutput() : NULL,
			'photo'				=> $this->profile_photo ? (string) \IPS\File::get( 'core_Clubs', $this->profile_photo )->url : NULL,
			'featured'			=> (bool) $this->featured,
			'paid'				=> (bool) $this->isPaid(),
			'location'			=> ( $location = $this->location() ) ? $location->apiOutput() : NULL,
			'about'				=> $this->about,
			'lastActivity'		=> \IPS\DateTime::ts( $this->last_activity )->rfc3339(),
			'contentCount'		=> $this->content,
			'coverPhotoUrl'		=> $coverPhoto,
			'coverOffset'		=> $this->cover_offset,
			'coverPhotoColor'	=> $this->coverPhotoBackgroundColor(),
			'members'			=> $members,
			'leaders'			=> $leaders,
			'moderators'		=> $moderators,
			'fieldValues'		=> $customFields,
			'nodes'				=> array_map( function( $node ){
					$node['url'] = (string) $node['url'];
					$node['id']  = $node['node_id'];
					$node['class'] = $node['node_class'];
					$node['public'] = $node['public'];

					unset( $node['node_id'], $node['node_class'] );

					return $node;
				}, $this->nodes() ),
		);

		if( !$authorizedMember )
		{
			$return['approved']	= (bool) $this->approved;
		}

		if ( \IPS\Application::appIsEnabled( 'nexus' ) )
		{
			$defaultCurrency = $authorizedMember ? \IPS\nexus\Customer::load( $authorizedMember->member_id )->defaultCurrency() : ( new \IPS\nexus\Customer )->defaultCurrency();

			$return['joiningFee']	= $this->joiningFee( $defaultCurrency ) ? $this->joiningFee( $defaultCurrency )->apiOutput() : NULL;
			try
			{
				$return['renewalTerm']	= $this->renewalTerm( $defaultCurrency ) ? $this->renewalTerm( $defaultCurrency )->apiOutput() : NULL;
			}
			catch( \OutOfRangeException $e )
			{
				$return['renewalTerm']	= NULL;
			}
		}
		else
		{
			$return['joiningFee'] = NULL;
			$return['renewalTerm']	= NULL;
		}


		return $return;
	}

	/**
	 * @brief	Cached tabs
	 */
	static $tabs = NULL;

	/**
	 * Get the club navbar tabs
	 *
	 * @param	\IPS\Node\Model|NULL	$container	Container
	 * @return	array
	 */
	public function tabs( \IPS\Node\Model $container = NULL )
	{
		if ( !static::$tabs )
		{
			$tabs = array();

			$tabs[ 'club_home' ] = array( 'href' => $this->url()->setQueryString('do', 'overview'), 'title' => \IPS\Member::loggedIn()->language()->addToStack( 'club_home' ), 'isActive' => ( \IPS\Request::i()->module == 'clubs' AND \IPS\Request::i()->do == 'overview' ) ? TRUE : FALSE );

			if  ( $this->canViewMembers() )
			{
				$tabs['club_members'] = array( 'href' => $this->url()->setQueryString('do', 'members'), 'title' => \IPS\Member::loggedIn()->language()->addToStack( 'club_members' ), 'isActive' => ( \IPS\Request::i()->module == 'clubs' AND \IPS\Request::i()->do == 'members' ) ? TRUE : FALSE );
			}

			foreach( $this->nodes() as $nodeID => $node )
			{
				if  ( $this->canRead() or $node['public'] )
				{
					$tabs[$nodeID] = array( 'href' => $node['url'] , 'title' => $node['name'], 'isActive' => ( isset( $container ) AND \get_class( $container ) === $node['node_class'] and $container->_id == $node['node_id'] ) ? TRUE : FALSE );
				}	
			}
				
			foreach( $this->pages() AS $pageId => $page )
			{
				if ( $page->canView() )
				{
					$tabs[$pageId] = array( 'href' => $page->url(), 'title' => $page->title, 'isActive' => ( \IPS\Request::i()->module == 'clubs' AND \IPS\Request::i()->controller == 'page' AND \IPS\Request::i()->id == $page->id ) );
				}
			}

			$tabs = $this->_tabs( $tabs, $container );

			$changed = FALSE;

			if ( $this->menu_tabs AND $this->menu_tabs != "" )
			{
				$order = array_values( json_decode( $this->menu_tabs , TRUE ) );

				uksort( $tabs, function( $a, $b ) use ( $order, &$changed ) {
					if ( \in_array( $a, $order ) and \in_array( $b, $order ) )
					{
						return ( array_search( $a, $order ) > array_search( $b, $order ) ? 1 : -1 );
					}
					elseif ( !\in_array( $b, $order) )
					{
						/* A new node was added, attach it to the end */
						$changed = TRUE;
						return -1;
					}
					else
					{
						return 0;
					}
				} );
			}

			/* If none of the tabs are active, set the first one as active */
			$hasActive = FALSE;

			foreach( $tabs as $tab )
			{
				if( $tab['isActive'] )
				{
					$hasActive = TRUE;
					break;
				}
			}

			if( !$hasActive )
			{
				reset( $tabs );
				$first = key( $tabs );

				$tabs[ $first ]['isActive'] = TRUE;
			}

			if ( $changed )
			{
				$this->menu_tabs = json_encode( array_keys( $tabs ) );
				$this->save();
			}

			static::$tabs = $tabs;
		}

		return static::$tabs;
	}

	/**
	 * Can a member view the members page
	 *
	 * @param \IPS\Member|null $member	The member (NULL for currently logged in member)
	 * @return bool
	 */
	public function canViewMembers( \IPS\Member $member = NULL ): bool
	{
		$member = $member ?: \IPS\Member::loggedIn();

		/* Public Clubs have no member list */
		if ( $this->type == \IPS\Member\Club::TYPE_PUBLIC )
		{
			return FALSE;
		}

		/* If NULL, everyone can view */
		if ( $this->show_membertab === NULL )
		{
			return TRUE;
		}

		/* Leader can see it always*/
		if (  $this->memberStatus( $member ) === \IPS\Member\Club::STATUS_LEADER )
		{
			return TRUE;
		}

		/* Moderator */
		if ( $this->show_membertab == 'moderator' AND ( $this->memberStatus( $member ) === \IPS\Member\Club::STATUS_MODERATOR ) )
		{
			return TRUE;
		}

		/* Members */
		if ( $this->show_membertab == 'member' AND \in_array( $this->memberStatus( $member ), array( \IPS\Member\Club::STATUS_MEMBER, \IPS\Member\Club::STATUS_INVITED, \IPS\Member\Club::STATUS_INVITED_BYPASSING_PAYMENT, \IPS\Member\Club::STATUS_EXPIRED, \IPS\Member\Club::STATUS_EXPIRED_MODERATOR ) ) )
		{
			return TRUE;
		}

		if ( $this->show_membertab == 'nonmember' )
		{
			return TRUE;
		}
		return FALSE;
	}

	/**
	 * Can be used by 3rd parties to add own club navigation tabs before they get sorted
	 *
	 * @param array $tabs	Tabs
	 * @param \IPS\Node\Model|NULL $container	Container
	 * @return array
	 */
	protected function _tabs( array $tabs, \IPS\Node\Model $container = NULL ) : array
	{
		return $tabs;
	}

	/**
	 * Get the first tab for the club page
	 *
	 * @return mixed
	 */
	public function firstTab()
	{
		$tabs =  $this->tabs();
		reset( $tabs );

		$first = key( $tabs );

		return array( $first => $tabs[ $first ] );
	}

	/**
	 * Number of members to show per page
	 *
	 * @return int
	 */
	public function membersPerPage()
	{
		return 24;
	}

	/**
	 * Set navigational breadcrumbs
	 *
	 * @param	\IPS\Node\Model	$node	The node we are viewing
	 * @return	void
	 */
	public function setBreadcrumbs( $node )
	{
		\IPS\core\FrontNavigation::$clubTabActive = TRUE;

		\IPS\Output::i()->breadcrumb = array();
		\IPS\Output::i()->breadcrumb[] = array( \IPS\Http\Url::internal( 'app=core&module=clubs&controller=directory', 'front', 'clubs_list' ), \IPS\Member::loggedIn()->language()->addToStack('module__core_clubs') );

		/* We have to prime the cache to ensure the correct club tab is selected */
		$this->tabs( $node );

		if( !( $firstTab = $this->firstTab() AND $firstTab = array_pop( $firstTab ) ) OR (string) $firstTab['href'] != (string) $node->url() )
		{
			\IPS\Output::i()->breadcrumb[] = array( $this->url(), $this->name );
			\IPS\Output::i()->breadcrumb[] = array( NULL, $node->_title );
		}
		else
		{
			\IPS\Output::i()->breadcrumb[] = array( NULL, $this->name );
		}
		
		if ( \IPS\Settings::i()->clubs_header == 'sidebar' )
		{
			\IPS\Output::i()->sidebar['contextual'] = \IPS\Theme::i()->getTemplate( 'clubs', 'core' )->header( $this, $node, 'sidebar' );
		}

		/* CSS */
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'styles/clubs.css', 'core', 'front' ) );
		if ( \IPS\Theme::i()->settings['responsive'] )
		{
			\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'styles/clubs_responsive.css', 'core', 'front' ) );
		}

		/* JS */
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'front_clubs.js', 'core', 'front' ) );
	}

	/**
	 * Helper method to determine if the member is in a club
	 *
	 * @return bool
	 */
	public static function userIsInClub() : bool
	{
		return \IPS\core\FrontNavigation::$clubTabActive or ( \IPS\Dispatcher::i()->application->directory === 'core' and \IPS\Dispatcher::i()->module->key === 'clubs' );
	}
	
	/* ! Rules */
	
	/**
	 * Rules have been acknowledged
	 *
	 * @param	\IPS\Member|NULL		$member		Member to check, or NULL for currently logged in member.
	 * @return	bool
	 */
	public function rulesAcknowledged( ?\IPS\Member $member = NULL ): bool
	{
		/* Rules must be acknowledged? */
		if ( !$this->rules_required )
		{
			return TRUE;
		}
		
		$member = $member ?: \IPS\Member::loggedIn();
		
		/* Owners are exempt. */
		if ( $this->owner == $member )
		{
			return TRUE;
		}
		
		/* Leaders and Moderators are exempt. */
		if ( \in_array( $this->memberStatus( $member ), array( static::STATUS_LEADER, static::STATUS_MODERATOR, static::STATUS_EXPIRED_MODERATOR ) ) )
		{
			return TRUE;
		}
		
		try
		{
			return (bool) \IPS\Db::i()->select( 'rules_acknowledged', 'core_clubs_memberships', array( "club_id=? AND member_id=?", $this->id, $member->member_id ) )->first();
		}
		catch( \UnderflowException $e )
		{
			/* If we can join the club return FALSE so we see the acknowledgement form. If we can't join, just return TRUE so the rules are returned but the user is not prompted to accept them. */
			return !$this->canJoin( $member );
		}
	}
	
	/**
	 * Acknowledge the rules
	 *
	 * @param	\IPS\Member|NULL		$member		Member to set, or NULL for currently logged in member.
	 * @return	void
	 * @throws \InvalidArgumentException
	 */
	public function acknowledgeRules( ?\IPS\Member $member = NULL )
	{
		$member = $member ?: \IPS\Member::loggedIn();
		
		if ( $this->memberStatus( $member ) === NULL )
		{
			throw new \InvalidArgumentException;
		}
		
		\IPS\Db::i()->update( 'core_clubs_memberships', array( 'rules_acknowledged' => TRUE ), array( "club_id=? AND member_id=?", $this->id, $member->member_id ) );
	}

	/**
	 * Called when a club requiring moderation gets approved
	 * 
	 * @return void
	 */
	public function onApprove()
	{
		\IPS\Api\Webhook::fire( 'club_created', $this );
		$this->owner->achievementAction( 'core', 'NewClub', $this );
	}

	/**
	 * Update existing purchases
	 *
	 * @param	\IPS\nexus\Purchase	$purchase							The purchase
	 * @param	array				$changes							The old values
	 * @param	bool				$cancelBillingAgreementIfNecessary	If making changes to renewal terms, TRUE will cancel associated billing agreements. FALSE will skip that change
	 * @return	void
	 */
	public function updatePurchase( \IPS\nexus\Purchase $purchase, $changes=array(), $cancelBillingAgreementIfNecessary=FALSE )
	{
		$tax = NULL;
		if ( $purchase->tax )
		{
			try
			{
				$tax = \IPS\nexus\Tax::load( $purchase->tax );
			}
			catch ( \OutOfRangeException $e ) { }
		}

		$currency = $purchase->renewal_currency ?: $purchase->member->defaultCurrency( );

		$price = json_decode( $this->renewal_price, TRUE );

		$purchase->renewals = new \IPS\nexus\Purchase\RenewalTerm(
			new \IPS\nexus\Money( $price[$currency]['amount'], $currency ),
			new \DateInterval( 'P' . $this->renewal_term . mb_strtoupper( $this->renewal_units ) ),
			$tax
		);

		if ( $cancelBillingAgreementIfNecessary and $billingAgreement = $purchase->billing_agreement )
		{
			if ( array_key_exists( 'renewal_price', $changes ) and !empty( $changes['renewal_price'] ) )
			{
				try
				{
					$billingAgreement->cancel();
					$billingAgreement->save();
				}
				catch ( \Exception $e ) { }
			}
		}

		$purchase->save();
	}
}
