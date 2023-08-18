<?php
/**
 * @brief		Content Item Feed Widget
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	forums
 * @since		16 Oct 2014
 */

namespace IPS\Content;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Content Item Feed Widget
 */
abstract class _Widget extends \IPS\Widget\PermissionCache
{
	/**
	 * Class
	 */
	protected static $class;
	
	/**
	 * Skip the getItemsWithPermission check?
	 */
	protected static $skipPermissions = FALSE;
			
	/**
	 * Specify widget configuration
	 *
	 * @param	null|\IPS\Helpers\Form	$form	Form object
	 * @return	\IPS\Helpers\Form
	 */
	public function configuration( &$form=null )
	{
		$form = parent::configuration( $form );

		foreach( $this->formElements() as $element )
		{
			$form->add( $element );
		}

		return $form;
 	}

 	/**
 	 * Return the form elements to use
 	 *
 	 * @return array
 	 */
 	protected function formElements()
 	{
		/* Init */
		$class	= static::$class;
		$return	= array();

 		/* Block title */ 		
		$return['title'] = new \IPS\Helpers\Form\Translatable( 'widget_feed_title', isset( $this->configuration['language_key'] ) ? NULL : \IPS\Member::loggedIn()->language()->addToStack( $class::$title . '_pl' ), FALSE, array( 'app' => 'core', 'key' => ( isset( $this->configuration['language_key'] ) ? $this->configuration['language_key'] : NULL ) ) );

		/* Container */
		if ( isset( $class::$containerNodeClass ) )
		{
			$return['container'] = new \IPS\Helpers\Form\Node( 'widget_feed_container_' . $class::$title, isset( $this->configuration['widget_feed_container'] ) ? $this->configuration['widget_feed_container'] : 0, FALSE, array(
				'class'           => $class::$containerNodeClass,
				'zeroVal'         => 'all',
				'permissionCheck' => 'view',
				'multiple'        => true,
				'forceOwner'	  => false,
				'clubs'			  => TRUE
			) );
		}
		
		/* Use permissions? */
		if ( \in_array( 'IPS\Content\Permissions', class_implements( $class ) ) )
		{
			$return['permissions'] = new \IPS\Helpers\Form\YesNo( 'widget_feed_use_perms', isset( $this->configuration['widget_feed_use_perms'] ) ? $this->configuration['widget_feed_use_perms'] : TRUE, FALSE );
		}
		
		/* Types */
		if ( \in_array( 'IPS\Content\Lockable', class_implements( $class ) ) )
		{
			$types = array(
				'any'    => 'mod_confirm_either',
				'open'   => 'mod_confirm_unlock',
				'closed' => 'mod_confirm_lock'
			);

			$return['locked'] = new \IPS\Helpers\Form\Radio( 'widget_feed_status_locked', isset( $this->configuration['widget_feed_status_locked'] ) ? $this->configuration['widget_feed_status_locked'] : 'any', FALSE, array( 'options' => $types ) );
		}
		if ( \in_array( 'IPS\Content\Pinnable', class_implements( $class ) ) )
		{
			$types = array(
				'any'       => 'mod_confirm_either',
				'pinned'    => 'mod_confirm_pin',
				'notpinned' => 'mod_confirm_unpin'
			);

			$return['pinned'] = new \IPS\Helpers\Form\Radio( 'widget_feed_status_pinned', isset( $this->configuration['widget_feed_status_pinned'] ) ? $this->configuration['widget_feed_status_pinned'] : 'any', FALSE, array( 'options' => $types ) );
		}
		if ( \in_array( 'IPS\Content\Featurable', class_implements( $class ) ) )
		{
			$types = array(
				'any'         => 'mod_confirm_either',
				'featured'    => 'mod_confirm_feature',
				'notfeatured' => 'mod_confirm_unfeature'
			);

			$return['featured'] = new \IPS\Helpers\Form\Radio( 'widget_feed_status_featured', isset( $this->configuration['widget_feed_status_featured'] ) ? $this->configuration['widget_feed_status_featured'] : 'any', FALSE, array( 'options' => $types ) );
		}
		if ( \in_array( 'IPS\Content\Hideable', class_implements( $class ) ) )
		{
			$types = array(
				'any'         => 'mod_confirm_either',
				'visible'     => 'mod_confirm_visible',
				'hidden'      => 'mod_confirm_hidden'
			);
	
			$return['hidden'] = new \IPS\Helpers\Form\Radio( 'widget_feed_comment_status_visible', isset( $this->configuration['widget_feed_comment_status_visible'] ) ? $this->configuration['widget_feed_comment_status_visible'] : 'any', FALSE, array( 'options' => $types ) );
		}
		if ( \IPS\IPS::classUsesTrait( $class, 'IPS\Content\Solvable' ) )
		{
			$types = array(
				'any'       => 'solved_either',
				'solved'    => 'solved_solved',
				'unsolved'  => 'solved_unsolved'
			);
	
			$return['solved'] = new \IPS\Helpers\Form\Radio( 'widget_feed_status_solved', isset( $this->configuration['widget_feed_status_solved'] ) ? $this->configuration['widget_feed_status_solved'] : 'any', FALSE, array( 'options' => $types ) );
		}

		if ( \in_array( 'IPS\Content\FuturePublishing', class_implements( $class ) ) )
		{
			$types = array(
				'any'			=> 'mod_confirm_either',
				'published'		=> 'mod_confirm_publish',
				'unpublished'	=> 'mod_confirm_unpublish'
			);

			$return['published'] = new \IPS\Helpers\Form\Radio( 'widget_feed_status_published', isset( $this->configuration['widget_feed_status_published'] ) ? $this->configuration['widget_feed_status_published'] : 'any', FALSE, array( 'options' => $types ) );
		}
		
		/* Author */
		$author = NULL;
		try
		{
			if ( isset( $this->configuration['widget_feed_author'] ) and \is_array( $this->configuration['widget_feed_author'] ) )
			{
				foreach( $this->configuration['widget_feed_author']  as $id )
				{
					$author[ $id ] = \IPS\Member::load( $id );
				}
			}
		}
		catch( \OutOfRangeException $ex ) { }
		$return['author'] = new \IPS\Helpers\Form\Member( 'widget_feed_author', $author, FALSE, array( 'multiple' => NULL ) );
		
		/* Minimum comments/reviews */
		if ( isset( $class::$commentClass ) )
		{
			if ( $class::$firstCommentRequired )
			{
				$return['min_posts'] = new \IPS\Helpers\Form\Number( 'widget_feed_min_posts', isset( $this->configuration['widget_feed_min_posts'] ) ? $this->configuration['widget_feed_min_posts'] : 0, FALSE, array( 'unlimitedLang' => 'any', 'unlimited' => 0 ) );
			}
			else
			{
				$return['min_comments'] = new \IPS\Helpers\Form\Number( 'widget_feed_min_comments', isset( $this->configuration['widget_feed_min_comments'] ) ? $this->configuration['widget_feed_min_comments'] : 0, FALSE, array( 'unlimitedLang' => 'any', 'unlimited' => 0 ) );
			}
		}
		if ( isset( $class::$reviewClass ) )
		{
			$return['min_reviews'] = new \IPS\Helpers\Form\Number( 'widget_feed_min_reviews', isset( $this->configuration['widget_feed_min_reviews'] ) ? $this->configuration['widget_feed_min_reviews'] : 0, FALSE, array( 'unlimitedLang' => 'any', 'unlimited' => 0 ) );
		}
		
		/* Rating */
		if ( \in_array( 'IPS\Content\Ratings', class_implements( $class ) ) and isset( $class::$databaseColumnMap['rating_average'] ) )
		{
			$ratingOptions = array(
				0 => 'any'
			);
			for( $i=1; $i<=\IPS\Settings::i()->reviews_rating_out_of; $i++ )
			{
				$ratingOptions[$i] = $i;
			}

			$return['rating'] = new \IPS\Helpers\Form\Select( 'widget_feed_min_rating', isset( $this->configuration['widget_feed_min_rating'] ) ? $this->configuration['widget_feed_min_rating'] : 0, FALSE, array(
				'options' => $ratingOptions
			) );
		}
		
		/* Number to show */
 		$return['show'] = new \IPS\Helpers\Form\Number( 'widget_feed_show', isset( $this->configuration['widget_feed_show'] ) ? $this->configuration['widget_feed_show'] : 5, TRUE );
 		
 		/* Date restrict */
 		$options = array( 'unlimited' => -1 );

 		if ( $class::databaseTableCount( TRUE ) > \IPS\UPGRADE_LARGE_TABLE_SIZE )
 		{
	 		$options['unlimitedLang'] = 'search_year';
 		}
 		
 		$return['date_restrict'] = new \IPS\Helpers\Form\Number( 'widget_feed_restrict_days', isset( $this->configuration['widget_feed_restrict_days'] ) ? $this->configuration['widget_feed_restrict_days'] : -1, FALSE, $options, NULL, NULL, \IPS\Member::loggedIn()->language()->addToStack('widget_feed_restrict_days_suffix') );
 		
		/* Sort */
		$sortOptions = $class::getWidgetSortOptions();

		$dateColumn = $class::$databasePrefix . ( isset( $class::$databaseColumnMap['updated'] ) ? $class::$databaseColumnMap['updated'] : $class::$databaseColumnMap['date'] );
		$return['sort'] = new \IPS\Helpers\Form\Select( 'widget_feed_sort_on', $this->configuration['widget_feed_sort_on'] ?? $dateColumn, FALSE, array( 'options' => $sortOptions ), NULL, NULL, NULL, 'widget_feed_sort_on' );

		$return['direction'] = new \IPS\Helpers\Form\Select( 'widget_feed_sort_dir', isset( $this->configuration['widget_feed_sort_dir'] ) ? $this->configuration['widget_feed_sort_dir'] : 'desc', FALSE, array(
            'options' => array(
	            'desc'   => 'descending',
	            'asc'    => 'ascending'
            )
        ) );

		/* Tags */
		if( \IPS\Settings::i()->tags_enabled )
		{
			if ( \IPS\Settings::i()->tags_force_lower )
			{
				$options['autocomplete']['forceLower'] = TRUE;
			}

			if ( \IPS\Settings::i()->tags_clean )
			{
				$options['autocomplete']['filterProfanity'] = TRUE;
			}

			$options['autocomplete']['prefix'] = FALSE;
			$options['autocomplete']['minimized'] = FALSE;

			$return['tags'] = new \IPS\Helpers\Form\Text( 'widget_feed_tags', ( isset( $this->configuration['widget_feed_tags'] ) ? $this->configuration['widget_feed_tags'] : array( 'tags' => NULL ) ), FALSE, $options );

		}

		return $return;
 	}
 	
 	/**
 	 * Ran before saving widget configuration
 	 *
 	 * @param	array	$values	Values from form
 	 * @return	array
 	 */
 	public function preConfig( $values )
 	{
	 	$class = static::$class;
	 	
 		if ( \is_array( $values[ 'widget_feed_container_' . $class::$title ] ) )
 		{
	 		$values['widget_feed_container'] = array_keys( $values[ 'widget_feed_container_' . $class::$title ] );
			unset( $values[ 'widget_feed_container_' . $class::$title ] );
 		}
 		
 		if ( \is_array( $values['widget_feed_author'] ) )
 		{
	 		$members = array();
	 		foreach( $values['widget_feed_author'] as $member )
	 		{
		 		$members[] = $member->member_id;
	 		}
	 		
	 		$values['widget_feed_author'] = $members;
 		}
 		
 		if ( !isset( $this->configuration['language_key'] ) )
 		{
	 		$this->configuration['language_key'] = 'widget_title_' . md5( mt_rand() );
 		}
		$values['language_key'] = $this->configuration['language_key'];

 		\IPS\Lang::saveCustom( 'core', $this->configuration['language_key'], $values['widget_feed_title'] );
 		unset( $values['widget_feed_title'] );
 		
 		return $values;
 	}
 	
 	/**
	 * Get where clause
	 *
	 * @return	array
	 */
	protected function buildWhere()
	{
		$class = static::$class;
		$where = array();
		
		/* Reset now as this could have been previously set to TRUE if using multiple Content widgets */
		static::$skipPermissions = FALSE;
		
		/* Container */
		if ( isset( $class::$containerNodeClass ) and !empty( $this->configuration['widget_feed_container'] ) )
		{
			$nodeIds = array();
			
			if ( ! empty( $this->configuration['widget_feed_use_perms'] ) )
			{
				static::$skipPermissions = TRUE;
			}

			foreach( $this->configuration['widget_feed_container'] as $id )
			{
				try
				{
					if ( ! empty( $this->configuration['widget_feed_use_perms'] ) )
					{
						if ( $class::$containerNodeClass::load( $id )->can('read') )
						{
							$nodeIds[] = $id;
						}
					}
					else
					{
						$nodeIds[] = $id;
					}
				}
				catch( \Exception $e )
				{
					
				}
			}
			
			$where[] = array( \IPS\Db::i()->in( $class::$databaseTable . '.' .  $class::$databasePrefix . $class::$databaseColumnMap['container'], $nodeIds ) );
		}
		
		/* Status */
		if ( isset( $this->configuration['widget_feed_status_locked'] ) and \in_array( 'IPS\Content\Lockable', class_implements( $class ) ) )
		{
			if ( $this->configuration['widget_feed_status_locked'] == 'closed' )
			{
				$where[] = isset( $class::$databaseColumnMap['locked'] ) ? array( $class::$databaseTable . '.' .  $class::$databasePrefix . $class::$databaseColumnMap['locked'] . '=?', 1 ) : array( $class::$databaseTable . '.' . $class::$databasePrefix . $class::$databaseColumnMap['status'] . '=?', 'closed' );
			}
			elseif ( $this->configuration['widget_feed_status_locked'] == 'open' )
			{
				$where[] = isset( $class::$databaseColumnMap['locked'] ) ? array( $class::$databaseTable . '.' .  $class::$databasePrefix . $class::$databaseColumnMap['locked'] . '=?', 0 ) : array( $class::$databaseTable . '.' . $class::$databasePrefix . $class::$databaseColumnMap['status'] . '=?', 'open' );
			}
		}

		if ( isset( $this->configuration['widget_feed_status_featured'] ) and \in_array( 'IPS\Content\Featurable', class_implements( $class ) ) )
		{
			if ( $this->configuration['widget_feed_status_featured'] == 'notfeatured' )
			{
				$where[] = array( $class::$databaseTable . '.' . $class::$databasePrefix . $class::$databaseColumnMap['featured'] . '=0' );
			}
			elseif ( $this->configuration['widget_feed_status_featured'] == 'featured' )
			{
				$where[] = array( $class::$databaseTable . '.' . $class::$databasePrefix . $class::$databaseColumnMap['featured'] . '=1' );
			}
		}
		
		if ( isset( $this->configuration['widget_feed_status_solved'] ) and $this->configuration['widget_feed_status_solved'] != 'any' )
		{
			$where['item'][] = $this->configuration['widget_feed_status_solved'] == 'solved' ? array( $class::$databaseTable . '.' . $class::$databasePrefix . $class::$databaseColumnMap['solved_comment_id'] . '>0' ) : array( $class::$databaseTable . '.' . $class::$databasePrefix . $class::$databaseColumnMap['solved_comment_id'] . '=0' );
		}
		
		if ( isset( $this->configuration['widget_feed_status_pinned'] ) and \in_array( 'IPS\Content\Pinnable', class_implements( $class ) ) )
		{
			if ( $this->configuration['widget_feed_status_pinned'] == 'notpinned' )
			{
				$where[] = array( $class::$databasePrefix . $class::$databaseColumnMap['pinned'] . '=0' );
			}
			elseif ( $this->configuration['widget_feed_status_pinned'] == 'pinned' )
			{
				$where[] = array( $class::$databaseTable . '.' . $class::$databasePrefix . $class::$databaseColumnMap['pinned'] . '=1' );
			}
		}

		if( isset( $this->configuration['widget_feed_status_published'] ) AND $this->configuration['widget_feed_status_published'] )
		{
			if ( $this->configuration['widget_feed_status_published'] == 'published' )
			{
				$where[] = array( $class::$databaseTable . '.' .  $class::$databasePrefix . $class::$databaseColumnMap['is_future_entry'] . '=?', 0 ) ;
			}
			elseif ( $this->configuration['widget_feed_status_published'] == 'unpublished' )
			{
				$where[] = array( $class::$databaseTable . '.' .  $class::$databasePrefix . $class::$databaseColumnMap['is_future_entry'] . '!=?', 0 ) ;
			}
		}

		/* Author */
		if ( isset( $this->configuration['widget_feed_author'] ) and \is_array( $this->configuration['widget_feed_author'] ) and \count( $this->configuration['widget_feed_author'] ) )
		{
			$where[] = array( \IPS\Db::i()->in( $class::$databaseTable . '.' . $class::$databasePrefix . $class::$databaseColumnMap['author'], $this->configuration['widget_feed_author'] ) );
		}
		
		/* Min comments/reviews */
		if ( isset( $this->configuration['widget_feed_min_posts'] ) and $this->configuration['widget_feed_min_posts'] )
		{
			$where[] = array( $class::$databaseTable . '.' . $class::$databasePrefix . $class::$databaseColumnMap['num_comments'] . '>=?', (int) $this->configuration['widget_feed_min_posts'] );
		}
		if ( isset( $this->configuration['widget_feed_min_comments'] ) and $this->configuration['widget_feed_min_comments'] )
		{
			$where[] = array(  $class::$databaseTable . '.' . $class::$databasePrefix . $class::$databaseColumnMap['num_comments'] . '>=?', (int) $this->configuration['widget_feed_min_comments'] );
		}
		if ( isset( $this->configuration['widget_feed_min_reviews'] ) and $this->configuration['widget_feed_min_reviews'] )
		{
			$where[] = array(  $class::$databaseTable . '.' . $class::$databasePrefix . $class::$databaseColumnMap['num_reviews'] . '>=?', (int) $this->configuration['widget_feed_min_reviews'] );
		}
		
		/* Rating */
		if ( isset( $this->configuration['widget_feed_min_rating'] ) and $this->configuration['widget_feed_min_rating'] )
		{
			$where[] = array(  $class::$databaseTable . '.' . $class::$databasePrefix . $class::$databaseColumnMap['rating_average'] . '>?', (int) $this->configuration['widget_feed_min_rating'] );
		}
		
		/* Limit to days */
		if ( $class::databaseTableCount( TRUE ) > \IPS\UPGRADE_LARGE_TABLE_SIZE )
		{
			if ( isset( $this->configuration['widget_feed_restrict_days'] ) and $this->configuration['widget_feed_restrict_days'] > 0 and $this->configuration['widget_feed_restrict_days'] <= 365 )
			{
				$where[] = array(  $class::$databaseTable . '.' . $class::$databasePrefix . $class::$databaseColumnMap['date'] . '>?',  \IPS\DateTime::create()->sub( new \DateInterval( 'P' . $this->configuration['widget_feed_restrict_days'] . 'D' ) )->getTimestamp() );
			}
			else
			{
				$where[] = array(  $class::$databaseTable . '.' . $class::$databasePrefix . $class::$databaseColumnMap['date'] . '>?',  \IPS\DateTime::create()->sub( new \DateInterval( 'P1Y' ) )->getTimestamp() );
			}
		}
		else
		{
			if ( isset( $this->configuration['widget_feed_restrict_days'] ) and $this->configuration['widget_feed_restrict_days'] > 0 )
			{
				$where[] = array(  $class::$databaseTable . '.' . $class::$databasePrefix . $class::$databaseColumnMap['date'] . '>?',  \IPS\DateTime::create()->sub( new \DateInterval( 'P' . $this->configuration['widget_feed_restrict_days'] . 'D' ) )->getTimestamp() );
			}
		}

		return $where;
	}
 	
	/**
	 * Render a widget
	 *
	 * @return	string
	 */
	public function render()
	{
		$class = static::$class;
		$where = $this->buildWhere();
				
		if ( \IPS\Settings::i()->tags_enabled and isset( $this->configuration['widget_feed_tags'] ) and \is_array( $this->configuration['widget_feed_tags'] ) and \count( $this->configuration['widget_feed_tags'] ) )
		{
			$tagWhere = array();
			$binds    = array( $class::$application, $class::$module );
			foreach( $this->configuration['widget_feed_tags'] as $tag )
			{
				$tagWhere[] = "( tag_text=? )";
				$binds[] = $tag;
			}
			
			$where[] = array( $class::$databaseTable . '.' . $class::$databasePrefix . $class::$databaseColumnId . ' IN (?)', \IPS\Db::i()->select( 'tag_meta_id', 'core_tags', array( array_merge( array( 'tag_meta_app=? AND tag_meta_area=? AND (' . implode( ' OR ', $tagWhere ) . ')' ), $binds ) ) ) );
		}

		/* What visible status are we checking? */
		$hidden	= \IPS\Content\Hideable::FILTER_AUTOMATIC;

		if( isset( $this->configuration['widget_feed_comment_status_visible'] ) )
		{
			switch( $this->configuration['widget_feed_comment_status_visible'] )
			{
				case 'visible':
					$hidden	= \IPS\Content\Hideable::FILTER_PUBLIC_ONLY;
				break;

				case 'hidden':
					$hidden	= \IPS\Content\Hideable::FILTER_ONLY_HIDDEN;
				break;
			}
		}

		/* As the block config can try and search all rows in topics/records, etc, we can end up with a tmp table and block nested buffer on members table, so we just query members after separately */
		$skipPerms = ( !isset( $this->configuration['widget_feed_use_perms'] ) or $this->configuration['widget_feed_use_perms'] ) ? static::$skipPermissions : TRUE;
		$items = iterator_to_array( $class::getItemsWithPermission(
			$where,	/* Where clause */
			( isset( $this->configuration['widget_feed_sort_on'] ) and isset( $this->configuration['widget_feed_sort_dir'] ) ) ? ( ( $this->configuration['widget_feed_sort_on'] == '_rand' ) ? $this->configuration['widget_feed_sort_on'] : (  $class::$databaseTable . '.' . $class::$databasePrefix . $this->configuration['widget_feed_sort_on'] . ' ' . $this->configuration['widget_feed_sort_dir'] ) ) : ( $class::$databaseTable . '.' . $class::$databasePrefix . $class::$databaseColumnMap['updated'] . ' DESC' ), /* Order */

			( isset( $this->configuration['widget_feed_show'] ) AND $this->configuration['widget_feed_show'] ) ? $this->configuration['widget_feed_show'] : 5, /* Limit */
			( $skipPerms ) ? NULL : 'read', /* Permission key to check against */
			$hidden, /* Whether or not to include hidden items */
			$class::SELECT_IDS_FIRST
		) );

		if ( \count( $items ) )
		{
			$memberIds = array();
			$members   = array();
			foreach( $items as $item )
			{
				foreach( array( 'author', 'last_comment_by' ) as $field )
				{
					if ( $item->mapped( $field ) )
					{
						$memberIds[] = $item->mapped( $field );
					}
				}

				$memberIds = array_unique( $memberIds );
			}
			
			if ( \count( $memberIds ) )
			{
				$members = \IPS\Db::i()->select( '*', 'core_members', array( \IPS\Db::i()->in( 'member_id', $memberIds ) ) )->setKeyField('member_id');
				
				if ( \count( $members ) )
				{
					foreach( $members as $member )
					{
						\IPS\Member::constructFromData( $member, FALSE );
					}
				}
			}
				
			if ( isset( $this->configuration['language_key'] ) )
			{
				$title = \IPS\Member::loggedIn()->language()->addToStack( $this->configuration['language_key'], FALSE, array( 'escape' => TRUE ) );
			}
			elseif ( isset( $this->configuration['widget_feed_title'] ) )
			{
				$title = $this->configuration['widget_feed_title'];
			}
			else
			{
				$title = \IPS\Member::loggedIn()->language()->addToStack( $class::$title . '_pl' );
			}
			
			return $this->output( $items, $title );
		}
		else
		{
			return '';
		}
	}
}