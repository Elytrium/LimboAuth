<?php
/**
 * @brief		GraphQL: Report type
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @since		14 Jun 2019
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\core\api\GraphQL\Types;
use GraphQL\Type\Definition\ObjectType;
use IPS\Api\GraphQL\TypeRegistry;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * ReportType for GraphQL API
 */
class _ReportType extends ObjectType
{
	/**
	 * Get object type
	 *
	 * @return	ObjectType
	 */
	public function __construct()
	{
		$config = [
			'name' => 'core_Report',
			'description' => 'Report type',
			'fields' => function () {
				return [
					'id' => [
						'type' => TypeRegistry::id(),
						'description' => "Report ID",
						'resolve' => function ($content) {                            
                            if( $this->hasReported( $content ) ) {
                                return $content->reportData['id'];
                            } else {
                                return NULL;
                            }
						}
					],
					'hasReported' => [
                        'type' => TypeRegistry::boolean(),
                        'description' => "Has the user reported this?",
                        'resolve' => function ($content) {
                            return $this->hasReported($content);
                        }
                    ],
                    'reportType' => [
                        'type' => TypeRegistry::int(),
                        'description' => "If the user has reported, the report type they made",
                        'resolve' => function ($content) {
                            if( $this->hasReported( $content ) ) {
                                return $content->reportData['report_type'];
                            } else {
                                return NULL;
                            }
                        }
                    ],
                    'reportDate' => [
                        'type' => TypeRegistry::int(),
                        'description' => "If the user has reported, the date of the report",
                        'resolve' => function ($content) {
                            if( $this->hasReported( $content ) ) {
                                return $content->reportData['date_reported'];
                            } else {
                                return NULL;
                            }
                        }
                    ],
                    'reportContent' => [
                        'type' => TypeRegistry::string(),
                        'description' => "If the user has reported, the report message if any",
                        'resolve' => function ($content) {
                            if( $this->hasReported( $content ) ) {
                                return $content->reportData['report'];
                            } else {
                                return NULL;
                            }
                        }
                    ]
				];
			}
		];

		parent::__construct($config);
    }
    
    /**
	 * Returns whether the user has reported this content
	 *
	 * @return	boolean
	 */
    protected function hasReported($content)
    {
        return ( $content->canReport( \IPS\Member::loggedIn() ) === 'report_err_already_reported' );
    }
}
