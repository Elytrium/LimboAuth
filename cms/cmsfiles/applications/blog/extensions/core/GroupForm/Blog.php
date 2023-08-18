<?php
/**
 * @brief		Admin CP Group Form
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Blog
 * @since		11 Jul 2014
 */

namespace IPS\blog\extensions\core\GroupForm;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Admin CP Group Form
 */
class _Blog
{
	/**
	 * Process Form
	 *
	 * @param	\IPS\Helpers\Form		$form	The form
	 * @param	\IPS\Member\Group		$group	Existing Group
	 * @return	void
	 */
	public function process( &$form, $group )
	{
        if( $group->g_id != \IPS\Settings::i()->guest_group )
        {
            $form->add(new \IPS\Helpers\Form\YesNo('g_blog_allowlocal', $group->g_blog_allowlocal, FALSE, array('togglesOn' => array('g_blog_maxblogs', 'g_blog_allowprivate', 'g_blog_preventpublish', 'g_blog_allowownmod', 'g_blog_allowdelete'))));
            $form->add(new \IPS\Helpers\Form\Number('g_blog_maxblogs', $group->g_blog_maxblogs, FALSE, array('unlimited' => 0), NULL, NULL, NULL, 'g_blog_maxblogs'));
            $form->add(new \IPS\Helpers\Form\YesNo('g_blog_allowprivate', $group->g_blog_allowprivate, FALSE, array(), NULL, NULL, NULL, 'g_blog_allowprivate'));
            $form->add(new \IPS\Helpers\Form\YesNo('g_blog_allowownmod', $group->g_blog_allowownmod, FALSE, array(), NULL, NULL, NULL, 'g_blog_allowownmod'));
            $form->add(new \IPS\Helpers\Form\YesNo('g_blog_allowdelete', $group->g_blog_allowdelete, FALSE, array(), NULL, NULL, NULL, 'g_blog_allowdelete'));
        }

        $form->add(new \IPS\Helpers\Form\YesNo('g_blog_allowcomment', $group->g_blog_allowcomment));
        if( $group->g_id == \IPS\Settings::i()->guest_group AND \IPS\Settings::i()->post_before_registering )
		{
			\IPS\Member::loggedIn()->language()->words['g_blog_allowcomment_desc'] = \IPS\Member::loggedIn()->language()->addToStack('g_blog_allowcomment_guestreg');
		}
	}
	
	/**
	 * Save
	 *
	 * @param	array				$values	Values from form
	 * @param	\IPS\Member\Group	$group	The group
	 * @return	void
	 */
	public function save( $values, &$group )
	{
        if( $group->g_id != \IPS\Settings::i()->guest_group )
        {
        	/* We intval here because (some of) the columns do not accept null values, but these are null when creating a new group */
            $group->g_blog_allowlocal = (int) $values['g_blog_allowlocal'];
            $group->g_blog_maxblogs = (int) $values['g_blog_maxblogs'];
            $group->g_blog_allowprivate = (int) $values['g_blog_allowprivate'];
            $group->g_blog_allowownmod = (int) $values['g_blog_allowownmod'];
            $group->g_blog_allowdelete = (int) $values['g_blog_allowdelete'];
        }

         $group->g_blog_allowcomment = $values['g_blog_allowcomment'];
	}
}