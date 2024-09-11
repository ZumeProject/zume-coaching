<?php
if ( !defined( 'ABSPATH' ) ) { exit; } // Exit if accessed directly.

class Zume_Charts_API
{
    public $leadership_permissions = [ 'manage_dt' ];
    public $coach_permissions = [ 'access_contacts' ];
    public $namespace = 'zume_funnel/v1';
    private static $_instance = null;
    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }
    public function __construct() {
        if ( self::dt_is_rest() ) {
            add_action( 'rest_api_init', [ $this, 'add_api_routes' ] );
            add_filter( 'dt_allow_rest_access', [ $this, 'authorize_url' ], 10, 1 );
        }
    }
    public function add_api_routes() {
        $namespace = $this->namespace;

        register_rest_route(
            $namespace, '/total', [
                'methods'  => [ 'GET', 'POST' ],
                'callback' => [ $this, 'total' ],
                'permission_callback' => function () {
                    return $this->has_permission( $this->coach_permissions );
                },
            ]
        );
        register_rest_route(
            $namespace, '/stats_list', [
                'methods'  => [ 'GET', 'POST' ],
                'callback' => [ $this, 'stats_list' ],
                'permission_callback' => function () {
                    return $this->has_permission( $this->coach_permissions );
                },
            ]
        );
        register_rest_route(
            $namespace, '/location', [
                'methods'  => [ 'GET', 'POST' ],
                'callback' => [ $this, 'location' ],
                'permission_callback' => function () {
                    return $this->has_permission( $this->coach_permissions );
                },
            ]
        );
        register_rest_route(
            $namespace, '/map', [
                'methods'  => [ 'GET', 'POST' ],
                'callback' => [ $this, 'map_switcher' ],
                'permission_callback' => function () {
                    return $this->has_permission( $this->coach_permissions );
                },
            ]
        );
        register_rest_route(
            $namespace, '/map_list', [
                'methods'  => [ 'GET', 'POST' ],
                'callback' => [ $this, 'map_list_switcher' ],
                'permission_callback' => function () {
                    return $this->has_permission( $this->coach_permissions );
                },
            ]
        );
        register_rest_route(
            $namespace, '/list', [
                'methods'  => [ 'GET', 'POST' ],
                'callback' => [ $this, 'list' ],
                'permission_callback' => function () {
                    return $this->has_permission( $this->coach_permissions );
                },
            ]
        );
        register_rest_route(
            $namespace, '/training_elements', [
                'methods'  => [ 'GET', 'POST' ],
                'callback' => [ $this, 'training_elements' ],
                'permission_callback' => function () {
                    return $this->has_permission( $this->coach_permissions );
                },
            ]
        );
        register_rest_route(
            $this->namespace, '/location_funnel', [
                [
                    'methods'  => 'GET',
                    'callback' => [ $this, 'location_funnel' ],
                    'permission_callback' => function () {
                        return $this->has_permission( $this->coach_permissions );
                    },
                ],
            ]
        );
        register_rest_route(
            $this->namespace, '/location_goals', [
                [
                    'methods'  => 'GET',
                    'callback' => [ $this, 'location_goals' ],
                    'permission_callback' => function () {
                        return $this->has_permission( $this->coach_permissions );
                    },
                ],
            ]
        );
        register_rest_route(
            $this->namespace, '/mawl', [
                [
                    'methods'  => 'GET',
                    'callback' => [ $this, 'list_mawl' ],
                    'permission_callback' => function () {
                        return $this->has_permission( $this->coach_permissions );
                    },
                ],
            ]
        );
        register_rest_route(
            $this->namespace, '/mawl', [
                [
                    'methods'  => 'POST',
                    'callback' => [ $this, 'create_mawl' ],
                    'permission_callback' => function () {
                        return $this->has_permission( $this->coach_permissions );
                    },
                ],
            ]
        );
        register_rest_route(
            $this->namespace, '/mawl', [
                [
                    'methods'  => 'DELETE',
                    'callback' => [ $this, 'delete_mawl' ],
                    'permission_callback' => function () {
                        return $this->has_permission( $this->coach_permissions );
                    },
                ],
            ]
        );
    }
    public function has_permission( $permissions = [] ) {
        $pass = false;
        foreach ( $permissions as $permission ){
            if ( current_user_can( $permission ) ){
                $pass = true;
            }
        }
        return $pass;
    }

    /**
     * @param $stage
     * @param $days
     * @param $type  value, description
     * @return void
     */
    public static function _pace_calculator( $stage, $days, $type = 'value', $value = 0, $trend = 0 ) {
        $data =  [
            'value' => 0,
            'description' => '',
            'pace' => ''
        ];
        switch ( $stage ) {
            case 'anonymous':
            case '0';
                $data['value'] = 0;
                $data['description'] = '';
                $data['pace'] = '';
                break;
            case 'registrant':
            case '1';
                $value = 5;
                $data['value'] = intval( $days * $value ); // events per day
                $data['description'] = 'People who have registered but have not progressed into training.';
                $data['pace'] = $value .' registrations per day';
                break;
            case 'active_training_trainee':
            case '2';
                $value = 2;
                $data['value'] = intval( $days * $value ); // events per day
                $data['description'] = 'People who are actively working a training plan or have only partially completed the training.';
                $data['pace'] = $value .' trainees launching training groups per day';
                break;
            case 'post_training_trainee':
            case '3';
                $value = 4;
                $data['value'] = intval( $days / $value ); // events per days
                $data['description'] = 'People who have completed the training and are working on a post training plan.';
                $data['pace'] = '1 trainee completing training every '.$value.' days';
                break;
            case 'partial_practitioner':
            case '4';
                $value = 6;
                $data['value'] = intval( $days / $value ); // events per days
                $data['description'] = 'Learning through doing. Implementing partial checklist';
                $data['pace'] = '1 trainee becoming practitioner every '.$value.' days';
                break;
            case 'full_practitioner':
            case '5';
                $value = 20;
                $data['value'] = intval( $days / $value ); // events per days
                $data['description'] = 'People who are seeking multiplicative movement and are completely skilled with the coaching checklist.';
                $data['pace'] = '1 practitioner completing HOST/MAWL every '.$value.' days';
                break;
            case 'multiplying_practitioner':
            case '6';
                $value = 30;
                $data['value'] = intval( $days / $value ); // events per days
                $data['description'] = 'People who are seeking multiplicative movement and are stewarding generational fruit.';
                $data['pace'] = '1 multiplying practitioner every '.$value.' days';
                break;
            default:
                break;
        }

        if ( $type === 'value' ) {
            return $data['value'];
        }
        else if ( $type === 'pace' ) {
            return $data['pace'];
        }
        else if ( $type === 'description' ) {
            return $data['description'];
        }
        else {
            return $data;
        }

    }


    /**
     * @param WP_REST_Request $request
     * @return array|WP_Error
     */
    public function total( WP_REST_Request $request ) {
        $params = dt_recursive_sanitize_array( $request->get_params() );
        if ( ! isset( $params['stage'] ) ) {
            return new WP_Error( 'no_stage', __( 'No stage key provided.', 'zume' ), array( 'status' => 400 ) );
        }

        if ( ! isset( $params['negative_stat'] ) ) {
            $params['negative_stat'] = false;
        }

        if ( ! isset( $params['range'] ) ) {
            $params['range'] = false;
        }


        switch ( $params['stage'] ) {
            case 'anonymous':
                return $this->total_anonymous( $params );
            case 'registrant':
                return $this->total_registrants( $params );
            case 'active_training_trainee':
                return $this->total_active_training_trainee( $params );
            case 'post_training_trainee':
                return $this->total_post_training_trainee( $params );
            case 'partial_practitioner':
                return $this->total_partial_practitioner( $params );
            case 'full_practitioner':
                return $this->total_full_practitioner( $params );
            case 'multiplying_practitioner':
                return $this->total_multiplying_practitioner( $params );
            case 'facilitator':
                return $this->total_facilitator( $params );
            case 'early':
                return $this->total_early( $params );
            case 'advanced':
                return $this->total_advanced( $params );
            case 'practitioners':
                return $this->total_practitioners( $params );
            case 'churches':
                return $this->total_churches( $params );
            case 'stats_list':
                return $this->stats_list( $params );
            default:
                return $this->general( $params );
        }
    }
    public function total_anonymous( $params ) {
        $range = sanitize_text_field( $params['range'] );
        $negative_stat = $params['negative_stat'] ?? false;

        $label = '';
        $description = '';
        $link = '';
        $value = 0;
        $goal = 0;
        $trend = 0;
        $valence = null;
        $days = 0;
        if ( $range > 0 ) {
            $days = (int) $range;
        }

        switch ( $params['key'] ) {
            case 'registrations':
                $label = 'Registrations';
                $description = 'Total registrations to the system.';
                $value = Zume_Queries::registrations( $range );
                $goal = $days * 3;
                $trend = Zume_Queries::registrations( $range, true );
                break;
            default:
                break;
        }

        return [
            'key' => $params['key'],
            'stage' => $params['stage'],
            'range' => (float) $range,
            'label' => $label,
            'description' => $description,
            'link' => $link,
            'value' => zume_format_int( $value ),
            'valence' => $valence ?? zume_get_valence( $value, $goal, $negative_stat ),
            'goal' => $goal,
            'goal_valence' => zume_get_valence( $value, $goal, $negative_stat ),
            'goal_percent' => zume_get_percent( $value, $goal ),
            'trend' => $trend,
            'trend_valence' => zume_get_valence( $value, $trend, $negative_stat ),
            'trend_percent' => zume_get_percent( $value, $trend ),
            'negative_stat' => $negative_stat,
        ];
    }
    public function total_registrants( $params ) {
        $range = sanitize_text_field( $params['range'] );
        $stage = 1;
        $negative_stat = $params['negative_stat'] ?? false;

        $all_stages_totols = Zume_Queries::stage_totals_by_range( $range );
//        $stage_total = $all_stages_totols[$stage]['total'];

        $label = '';
        $description = '';
        $link = '';
        $value = 0;
        $goal = 0;
        $trend = 0;
        $valence = null;
        $goal_valence = null;
        $trend_valence = null;
        $days = 0;
        if ( $range > 0 ) {
            $days = (int) $range;
        }

        switch ( $params['key'] ) {

            case 'total_registrants':
                $label = 'Registrants';
                $link = 'registrants';
                $value = Zume_Queries::stage_total( $stage, $range );
                $trend = Zume_Queries::stage_total( $stage, $range, true );
                $description = self::_pace_calculator( $stage, $days, 'description' );
                $goal = self::_pace_calculator( $stage, $days );
                break;
            case 'locations':
                $label = 'Locations';
                $value = Zume_Queries::locations( [ $stage ], $range );
                $trend = Zume_Queries::locations( [ $stage ], $range, true );
                $description = 'Grid locations. (Previous period '.zume_format_int($trend).')';
                $goal = $trend;
                break;
            case 'languages':
                $label = 'Languages';
                $value = Zume_Queries::languages( [ $stage ], $range );
                $trend = Zume_Queries::languages( [ $stage ], $range, true );
                $description = 'Languages used. (Previous period '.zume_format_int($trend).')';
                $goal = $trend;
                break;
            case 'has_no_coach':
                $negative_stat = true;
                $label = 'Has No Coach';
                $value = Zume_Queries::has_coach( $stage, $range, false, true );
                $trend = Zume_Queries::has_coach( $stage, $range, true, true );
                $description = 'People who have no coach. (Previous period '.zume_format_int($trend).')';
                $goal = $trend;
                break;
            case 'coach_requests':
                $negative_stat = false;
                $label = 'Coach Requests';
                $value = Zume_Queries::query_stage_by_type_and_subtype( $stage, $range, 'coaching', 'requested_a_coach' );
                $trend = Zume_Queries::query_stage_by_type_and_subtype( $stage, $range, 'coaching', 'requested_a_coach', true );
                $description = 'Coach requests in this period of time. (Previous period '.zume_format_int($trend).')';
                $goal = $trend;
                break;
            case 'has_not_completed_profile':
                $negative_stat = true;
                $label = 'Has Not Completed Profile';
                $value = Zume_Queries::query_stage_by_type_and_subtype( $stage, $range, 'system', 'set_profile', false, true );
                $trend = Zume_Queries::query_stage_by_type_and_subtype( $stage, $range, 'system', 'set_profile', true, true );
                $description = 'Total number of registrants who have not completed their profile. (Previous period '.zume_format_int($trend).')';
                $goal = $trend;
                break;
            case 'not_set_phone':
                $negative_stat = true;
                $label = 'Has No Phone';
                $value = Zume_Queries::query_stage_by_type_and_subtype( $stage, $range, 'system', 'set_profile_phone', false, true );
                $trend = Zume_Queries::query_stage_by_type_and_subtype( $stage, $range, 'system', 'set_profile_phone', true, true );
                $description = 'Total number of registrants who have not completed their profile. (Previous period '.zume_format_int($trend).')';
                $goal = $trend;
                break;
            case 'not_set_name':
                $negative_stat = true;
                $label = 'Has Not Set Name';
                $value = Zume_Queries::query_stage_by_type_and_subtype( $stage, $range, 'system', 'set_profile_name', false, true );
                $trend = Zume_Queries::query_stage_by_type_and_subtype( $stage, $range, 'system', 'set_profile_name', true, true );
                $description = 'Total number of registrants who have not set their profile name. (Previous period '.zume_format_int($trend).')';
                $goal = $trend;
                break;
            case 'not_set_location':
                $negative_stat = true;
                $label = 'Has Not Set Location';
                $value = Zume_Queries::query_stage_by_type_and_subtype( $stage, $range, 'system', 'set_profile_location', false, true );
                $trend = Zume_Queries::query_stage_by_type_and_subtype( $stage, $range, 'system', 'set_profile_location', true, true );
                $description = 'Total number of registrants who have not set their profile location. (Previous period '.zume_format_int($trend).')';
                $goal = $trend;
                break;
            case 'in_and_out':
                return [
                    'key' => $params['key'],
                    'stage' => $params['stage'],
                    'label' => 'Flow',
                    'description' => 'People moving in and out of this stage.',
                    'link' => '',
                    'value_in' => zume_format_int( Zume_Queries::flow( $stage, 'in', $range )  ),
                    'value_idle' => zume_format_int( Zume_Queries::flow( $stage, 'idle', $range ) ),
                    'value_out' => zume_format_int( Zume_Queries::flow( 2, 'in', $range )  ),
                ];
            default:
                break;
        }

        return [
            'key' => $params['key'],
            'stage' => $params['stage'],
            'range' => (float) $range,
            'label' => $label,
            'description' => $description,
            'link' => $link,
            'value' => zume_format_int( $value ),
            'valence' => $valence ?? zume_get_valence( $value, $goal, $negative_stat ),
            'goal' => $goal,
            'goal_valence' => $goal_valence ?? zume_get_valence( $value, $goal, $negative_stat ),
            'goal_percent' => zume_get_percent( $value, $goal ),
            'trend' => $trend,
            'trend_valence' => $trend_valence ?? zume_get_valence( $value, $trend, $negative_stat ),
            'trend_percent' => zume_get_percent( $value, $trend ),
            'negative_stat' => $negative_stat,
        ];
    }
    public function total_active_training_trainee( $params ) {
        $range = sanitize_text_field( $params['range'] );
        $negative_stat = $params['negative_stat'] ?? false;
        $stage = 2;

        $label = '';
        $description = '';
        $link = '';
        $value = 0;
        $goal = 0;
        $trend = 0;
        $valence = null;
        $goal_valence = null;
        $trend_valence = null;
        $days = 0;
        if ( $range > 0 ) {
            $days = (int) $range;
        }

        switch ( $params['key'] ) {

            case 'total_active_training_trainee':
                $label = 'Active Training Trainees';
                $link = 'active';
                $value = Zume_Queries::stage_total( $stage, $range );
                $trend = Zume_Queries::stage_total( $stage, $range, true );
                $description = self::_pace_calculator( $stage, $days, 'description' );
                $goal = self::_pace_calculator( $stage, $days );
                break;
            case 'locations':
                $label = 'Locations';
                $value = Zume_Queries::locations( [ $stage ], $range );
                $trend = Zume_Queries::locations( [ $stage ], $range, true );
                $description = 'Grid locations. (Previous period '.zume_format_int($trend).')';
                $goal = $trend;
                break;
            case 'languages':
                $label = 'Languages';
                $value = Zume_Queries::languages( [ $stage ], $range );
                $trend = Zume_Queries::languages( [ $stage ], $range, true );
                $description = 'Languages used. (Previous period '.zume_format_int($trend).')';
                $goal = $trend;
                break;
            case 'has_no_coach':
                $negative_stat = true;
                $label = 'Has No Coach';
                $value = Zume_Queries::has_coach( $stage, $range, false, true );
                $trend = Zume_Queries::has_coach( $stage, $range, true, true );
                $description = 'People who have no coach. (Previous period '.zume_format_int($trend).')';
                $goal = $trend;
                break;
            case 'has_not_completed_profile':
                $negative_stat = true;
                $label = 'Has Not Completed Profile';
                $value = Zume_Queries::query_stage_by_type_and_subtype( $stage, $range, 'system', 'set_profile', false, true );
                $trend = Zume_Queries::query_stage_by_type_and_subtype( $stage, $range, 'system', 'set_profile', true, true );
                $description = 'Total number of trainees who have not completed their profile. (Previous period '.zume_format_int($trend).')';
                $goal = $trend;
                break;
            case 'plans':
                $label = 'New Plans Created';
                $value = Zume_Queries::query_stage_by_type_and_subtype( $stage, $range, 'training', 'plan_created' );
                $trend = Zume_Queries::query_stage_by_type_and_subtype( $stage, $range, 'training', 'plan_created', true );
                $description = 'New training plans created by active training trainees. (Previous period '.zume_format_int($trend).')';
                $goal = $trend;
                break;
            case 'total_checkins':
                $label = 'Checkins';
                $value = Zume_Queries::checkins( $stage, $range );
                $trend = Zume_Queries::checkins( $stage, $range, true );
                $description = 'Checkins. (Previous period '.zume_format_int($trend).')';
                $goal = $trend;
                break;
            case 'new_coaching_requests':
                $label = 'New Coaching Requests';
                $value = Zume_Queries::query_stage_by_type_and_subtype( $stage, $range, 'coaching', 'requested_a_coach' );
                $trend = Zume_Queries::query_stage_by_type_and_subtype( $stage, $range, 'coaching', 'requested_a_coach', true );
                $description = 'New coaching requests from post training trainees. (Previous period '.zume_format_int($trend).')';
                $goal = $trend;
                break;
            case 'post_training_plans':
                $label = '3-Month Plans';
                $value = Zume_Queries::query_stage_by_type_and_subtype( $stage, $range, 'training', 'made_post_training_plan' );
                $trend = Zume_Queries::query_stage_by_type_and_subtype( $stage, $range, 'training', 'made_post_training_plan', true );
                $description = '3 month plans. (Previous period '.zume_format_int($trend).')';
                $goal = $trend;
                break;
            case 'in_and_out':
                return [
                    'key' => $params['key'],
                    'stage' => $params['stage'],
                    'label' => 'Flow',
                    'description' => 'People moving in and out of this stage.',
                    'link' => 'active',
                    'value_in' => zume_format_int( Zume_Queries::flow( $stage, 'in', $range )  ),
                    'value_idle' => zume_format_int( Zume_Queries::flow( $stage, 'idle', $range ) ),
                    'value_out' => zume_format_int( Zume_Queries::flow( 3, 'in', $range )  ),
                ];

            default:
                break;
        }

        return [
            'key' => $params['key'],
            'stage' => $params['stage'],
            'range' => (float) $range,
            'label' => $label,
            'description' => $description,
            'link' => $link,
            'value' => zume_format_int( $value ),
            'valence' => $valence ?? zume_get_valence( $value, $goal, $negative_stat ),
            'goal' => $goal,
            'goal_valence' => $goal_valence ?? zume_get_valence( $value, $goal, $negative_stat ),
            'goal_percent' => zume_get_percent( $value, $goal ),
            'trend' => $trend,
            'trend_valence' => $trend_valence ?? zume_get_valence( $value, $trend, $negative_stat ),
            'trend_percent' => zume_get_percent( $value, $trend ),
            'negative_stat' => $negative_stat,
        ];
    }
    public function total_post_training_trainee( $params ) {
        $range = sanitize_text_field( $params['range'] );
        $negative_stat = $params['negative_stat'] ?? false;
        $stage = 3;

        $label = '';
        $description = '';
        $link = '';
        $value = 0;
        $goal = 0;
        $trend = 0;
        $valence = null;
        $goal_valence = null;
        $trend_valence = null;
        $days = 0;
        if ( $range > 0 ) {
            $days = (int) $range;
        }

        switch ( $params['key'] ) {
            case 'total_post_training_trainee':
                $label = 'Post-Training Trainees';
                $link = 'post';
                $value = Zume_Queries::stage_total( $stage, $range );
                $trend = Zume_Queries::stage_total( $stage, $range, true );
                $description = self::_pace_calculator( $stage, $days, 'description' );
                $goal = self::_pace_calculator( $stage, $days );
                break;
            case 'locations':
                $label = 'Locations';
                $value = Zume_Queries::locations( [ $stage ], $range );
                $trend = Zume_Queries::locations( [ $stage ], $range, true );
                $description = 'Grid locations. (Previous period '.zume_format_int($trend).')';
                $goal = $trend;
                break;
            case 'languages':
                $label = 'Languages';
                $value = Zume_Queries::languages( [ $stage ], $range );
                $trend = Zume_Queries::languages( [ $stage ], $range, true );
                $description = 'Languages used. (Previous period '.zume_format_int($trend).')';
                $goal = $trend;
                break;
            case 'has_no_coach':
                $negative_stat = true;
                $label = 'Has No Coach';
                $value = Zume_Queries::has_coach( $stage, $range, false, true );
                $trend = Zume_Queries::has_coach( $stage, $range, true, true );
                $description = 'People who have no coach. (Previous period '.zume_format_int($trend).')';
                $goal = $trend;
                break;
            case 'has_not_completed_profile':
                $negative_stat = true;
                $label = 'Has Not Completed Profile';
                $value = Zume_Queries::query_stage_by_type_and_subtype( $stage, $range, 'system', 'set_profile', false, true );
                $trend = Zume_Queries::query_stage_by_type_and_subtype( $stage, $range, 'system', 'set_profile', true, true );
                $description = 'Total number of trainees who have not completed their profile. (Previous period '.zume_format_int($trend).')';
                $goal = $trend;
                break;
            case 'needs_3_month_plan':
                $negative_stat = true;
                $label = 'Needs 3 Month Plan';
                $value = Zume_Queries::query_stage_by_type_and_subtype( $stage, $range, 'training', 'made_post_training_plan', false, true );
                $trend = Zume_Queries::query_stage_by_type_and_subtype( $stage, $range, 'training', 'made_post_training_plan', true, true );
                $description = 'Needs a 3 month plan. (Previous period '.zume_format_int($trend).')';
                $goal = $trend;
                break;
            case 'new_coaching_requests':
                $label = 'Coaching Requests';
                $value = Zume_Queries::has_coach( $stage, $range );
                $trend = Zume_Queries::has_coach( $stage, $range, true );
                $description = 'Has made a coaching requests. (Previous period '.zume_format_int($trend).')';
                $goal = $trend;
                break;
            case 'new_3_month_plans':
                $label = '3 Month Plans';
                $value = Zume_Queries::query_stage_by_type_and_subtype( $stage, $range, 'training', 'made_post_training_plan' );
                $trend = Zume_Queries::query_stage_by_type_and_subtype( $stage, $range, 'training', 'made_post_training_plan', true );
                $description = 'Has a 3 month plan. (Previous period '.zume_format_int($trend).')';
                $goal = $trend;
                break;
            case 'in_and_out':
                return [
                    'key' => $params['key'],
                    'stage' => $params['stage'],
                    'label' => 'Flow',
                    'description' => 'People moving in and out of this stage.',
                    'link' => '',
                    'value_in' => zume_format_int( Zume_Queries::flow( $stage, 'in', $range )  ),
                    'value_idle' => zume_format_int( Zume_Queries::flow( $stage, 'idle', $range ) ),
                    'value_out' => zume_format_int( Zume_Queries::flow( 4, 'in', $range )  ),
                ];
            default:
                break;
        }

        return [
            'key' => $params['key'],
            'stage' => $params['stage'],
            'range' => (float) $range,
            'label' => $label,
            'description' => $description,
            'link' => $link,
            'value' => zume_format_int( $value ),
            'valence' => $valence ?? zume_get_valence( $value, $goal, $negative_stat ),
            'goal' => $goal,
            'goal_valence' => $goal_valence ?? zume_get_valence( $value, $goal, $negative_stat ),
            'goal_percent' => zume_get_percent( $value, $goal ),
            'trend' => $trend,
            'trend_valence' => $trend_valence ?? zume_get_valence( $value, $trend, $negative_stat ),
            'trend_percent' => zume_get_percent( $value, $trend ),
            'negative_stat' => $negative_stat,
        ];
    }
    public function total_partial_practitioner( $params ) {
        $range = sanitize_text_field( $params['range'] );
        $negative_stat = $params['negative_stat'] ?? false;
        $stage = 4;

        $label = '';
        $description = '';
        $link = '';
        $value = 0;
        $goal = 0;
        $trend = 0;
        $valence = null;
        $goal_valence = null;
        $trend_valence = null;
        $days = 0;
        if ( $range > 0 ) {
            $days = (int) $range;
        }

        switch ( $params['key'] ) {
            case 'total_partial_practitioner':
                $label = '(S1) Partial Practitioners';
                $link = 'partial_practitioner_practitioners';
                $value = Zume_Queries::stage_total( $stage, $range );
                $trend = Zume_Queries::stage_total( $stage, $range, true );
                $description = self::_pace_calculator( $stage, $days, 'description' );
                $goal = self::_pace_calculator( $stage, $days );
                break;
            case 'locations':
                $label = 'Locations';
                $value = Zume_Queries::locations( [ $stage ], $range );
                $trend = Zume_Queries::locations( [ $stage ], $range, true );
                $description = 'Grid locations. (Previous period '.zume_format_int($trend).')';
                $goal = $trend;
                break;
            case 'languages':
                $label = 'Languages';
                $value = Zume_Queries::languages( [ $stage ], $range );
                $trend = Zume_Queries::languages( [ $stage ], $range, true );
                $description = 'Languages used. (Previous period '.zume_format_int($trend).')';
                $goal = $trend;
                break;
            case 'has_no_coach':
                $negative_stat = true;
                $label = 'Has No Coach';
                $value = Zume_Queries::has_coach( $stage, $range, false, true );
                $trend = Zume_Queries::has_coach( $stage, $range, true, true );
                $description = 'People who have no coach. (Previous period '.zume_format_int($trend).')';
                $goal = $trend;
                break;
            case 'joined_community':
                $label = 'Joined Community';
                $value = Zume_Queries::query_stage_by_type_and_subtype( $stage, $range, 'practicing', 'join_community');
                $trend = Zume_Queries::query_stage_by_type_and_subtype( $stage, $range, 'practicing', 'join_community', true );
                $description = 'Joined community. (Previous period '.zume_format_int($trend).')';
                $goal = $trend;
                break;
            case 'reporting_churches':
                $label = 'Reporting Churches';
                $value = Zume_Queries::query_stage_by_type_and_subtype( $stage, $range, 'practicing', 'new_church');
                $trend = Zume_Queries::query_stage_by_type_and_subtype( $stage, $range, 'practicing', 'new_church', true );
                $description = 'Practitioners who are reporting new churches. (Previous period '.zume_format_int($trend).')';
                $goal = $trend;
                break;

            case 'in_and_out':
                return [
                    'key' => $params['key'],
                    'stage' => $params['stage'],
                    'label' => 'Flow',
                    'description' => 'People moving in and out of this stage.',
                    'link' => '',
                    'value_in' => zume_format_int( Zume_Queries::flow( $stage, 'in', $range )  ),
                    'value_idle' => zume_format_int( Zume_Queries::flow( $stage, 'idle', $range ) ),
                    'value_out' => zume_format_int( Zume_Queries::flow( 5, 'in', $range )  ),
                ];
            default:
                break;
        }

        return [
            'key' => $params['key'],
            'stage' => $params['stage'],
            'range' => (float) $range,
            'label' => $label,
            'description' => $description,
            'link' => $link,
            'value' => zume_format_int( $value ),
            'valence' => $valence ?? zume_get_valence( $value, $goal, $negative_stat ),
            'goal' => $goal,
            'goal_valence' => $goal_valence ?? zume_get_valence( $value, $goal, $negative_stat ),
            'goal_percent' => zume_get_percent( $value, $goal ),
            'trend' => $trend,
            'trend_valence' => $trend_valence ?? zume_get_valence( $value, $trend, $negative_stat ),
            'trend_percent' => zume_get_percent( $value, $trend ),
            'negative_stat' => $negative_stat,
        ];
    }
    public function total_full_practitioner( $params ) {
        $range = sanitize_text_field( $params['range'] );
        $negative_stat = $params['negative_stat'] ?? false;
        $stage = 5;

        $label = '';
        $description = '';
        $link = '';
        $value = 0;
        $goal = 0;
        $trend = 0;
        $valence = null;
        $goal_valence = null;
        $trend_valence = null;
        $days = 0;
        if ( $range > 0 ) {
            $days = (int) $range;
        }

        switch ( $params['key'] ) {

            case 'total_full_practitioner':
                $label = 'Full Practitioners';
                $link = 'full_practitioner_practitioners';
                $value = Zume_Queries::stage_total( $stage, $range );
                $trend = Zume_Queries::stage_total( $stage, $range, true );
                $description = self::_pace_calculator( $stage, $days, 'description' );
                $goal = self::_pace_calculator( $stage, $days );
                break;
            case 'locations':
                $label = 'Locations';
                $value = Zume_Queries::locations( [ $stage ], $range );
                $trend = Zume_Queries::locations( [ $stage ], $range, true );
                $description = 'Grid locations. (Previous period '.zume_format_int($trend).')';
                $goal = $trend;
                break;
            case 'languages':
                $label = 'Languages';
                $value = Zume_Queries::languages( [ $stage ], $range );
                $trend = Zume_Queries::languages( [ $stage ], $range, true );
                $description = 'Languages used. (Previous period '.zume_format_int($trend).')';
                $goal = $trend;
                break;
            case 'has_no_coach':
                $negative_stat = true;
                $label = 'Has No Coach';
                $value = Zume_Queries::has_coach( $stage, $range, false, true );
                $trend = Zume_Queries::has_coach( $stage, $range, true, true );
                $description = 'People who have no coach. (Previous period '.zume_format_int($trend).')';
                $goal = $trend;
                break;
            case 'reporting_churches':
                $label = 'Reporting Churches';
                $value = Zume_Queries::query_stage_by_type_and_subtype( $stage, $range, 'practicing', 'new_church');
                $trend = Zume_Queries::query_stage_by_type_and_subtype( $stage, $range, 'practicing', 'new_church', true );
                $description = 'Practitioners who are reporting new churches. (Previous period '.zume_format_int($trend).')';
                $goal = $trend;
                break;
            case 'has_reported':
                $label = 'Has Reported';
                $value = Zume_Queries::query_stage_by_type_and_subtype( $stage, $range, 'practicing', 'first_practitioner_report', false, true );
                $trend = Zume_Queries::query_stage_by_type_and_subtype( $stage, $range, 'practicing', 'first_practitioner_report', true, true );
                $description = 'Has reported as a practitioner. (Previous period '.zume_format_int($trend).')';
                $goal = $trend;
                break;


            case 'in_and_out':
                return [
                    'key' => $params['key'],
                    'stage' => $params['stage'],
                    'label' => 'Flow',
                    'description' => 'People moving in and out of this stage.',
                    'link' => '',
                    'value_in' => zume_format_int( Zume_Queries::flow( $stage, 'in', $range )  ),
                    'value_idle' => zume_format_int( Zume_Queries::flow( $stage, 'idle', $range ) ),
                    'value_out' => zume_format_int( Zume_Queries::flow( 6, 'in', $range )  ),
                ];
            default:
                break;
        }

        return [
            'key' => $params['key'],
            'stage' => $params['stage'],
            'range' => (float) $range,
            'label' => $label,
            'description' => $description,
            'link' => $link,
            'value' => zume_format_int( $value ),
            'valence' => $valence ?? zume_get_valence( $value, $goal, $negative_stat ),
            'goal' => $goal,
            'goal_valence' => $goal_valence ?? zume_get_valence( $value, $goal, $negative_stat ),
            'goal_percent' => zume_get_percent( $value, $goal ),
            'trend' => $trend,
            'trend_valence' => $trend_valence ?? zume_get_valence( $value, $trend, $negative_stat ),
            'trend_percent' => zume_get_percent( $value, $trend ),
            'negative_stat' => $negative_stat,
        ];
    }
    public function total_multiplying_practitioner( $params ) {
        $range = (float) sanitize_text_field( $params['range'] );
        $negative_stat = $params['negative_stat'] ?? false;
        $stage = 6;

        $label = '';
        $description = '';
        $link = '';
        $value = 0;
        $goal = 0;
        $trend = 0;
        $valence = null;
        $goal_valence = null;
        $trend_valence = null;
        $days = 0;
        if ( $range > 0 ) {
            $days = (int) $range;
        }

        switch ( $params['key'] ) {
            case 'total_multiplying_practitioner':
                $label = 'Multiplying Practitioners';
                $link = 'multiplying_practitioner_practitioners';
                $value = Zume_Queries::stage_total( $stage, $range );
                $trend = Zume_Queries::stage_total( $stage, $range, true );
                $description = self::_pace_calculator( $stage, $days, 'description' );
                $goal = self::_pace_calculator( $stage, $days );
                break;
            case 'locations':
                $label = 'Locations';
                $value = Zume_Queries::locations( [ $stage ], $range );
                $trend = Zume_Queries::locations( [ $stage ], $range, true );
                $description = 'Grid locations. (Previous period '.zume_format_int($trend).')';
                $goal = $trend;
                break;
            case 'languages':
                $label = 'Languages';
                $value = Zume_Queries::languages( [ $stage ], $range );
                $trend = Zume_Queries::languages( [ $stage ], $range, true );
                $description = 'Languages used. (Previous period '.zume_format_int($trend).')';
                $goal = $trend;
                break;
            case 'reporting_churches':
                $label = 'Reporting Churches';
                $value = Zume_Queries::query_stage_by_type_and_subtype( $stage, $range, 'practicing', 'new_church');
                $trend = Zume_Queries::query_stage_by_type_and_subtype( $stage, $range, 'practicing', 'new_church', true );
                $description = 'Practitioners who are reporting new churches. (Previous period '.zume_format_int($trend).')';
                $goal = $trend;
                break;
            case 'has_not_reported':
                $negative_stat = true;
                $label = 'Has Not Reported';
                $value = Zume_Queries::query_stage_by_type_and_subtype( $stage, $range, 'practicing', 'first_practitioner_report', false, true );
                $trend = Zume_Queries::query_stage_by_type_and_subtype( $stage, $range, 'practicing', 'first_practitioner_report', true, true );
                $description = 'Has not reported as a practitioner. (Previous period '.zume_format_int($trend).')';
                $goal = $trend;
                break;
            case 'in_and_out':
                return [
                    'key' => $params['key'],
                    'stage' => $params['stage'],
                    'label' => 'Flow',
                    'description' => 'People moving in and out of this stage.',
                    'link' => '',
                    'value_in' => zume_format_int( Zume_Queries::flow( $stage, 'in', $range )  ),
                    'value_idle' => zume_format_int( Zume_Queries::flow( $stage, 'idle', $range ) ),
                    'value_out' => '',
                ];
            default:
                break;
        }

        return [
            'key' => $params['key'],
            'stage' => $params['stage'],
            'range' => (float) $range,
            'label' => $label,
            'description' => $description,
            'link' => $link,
            'value' => zume_format_int( $value ),
            'valence' => $valence ?? zume_get_valence( $value, $goal, $negative_stat ),
            'goal' => $goal,
            'goal_valence' => $goal_valence ?? zume_get_valence( $value, $goal, $negative_stat ),
            'goal_percent' => zume_get_percent( $value, $goal ),
            'trend' => $trend,
            'trend_valence' => $trend_valence ?? zume_get_valence( $value, $trend, $negative_stat ),
            'trend_percent' => zume_get_percent( $value, $trend ),
            'negative_stat' => $negative_stat,
        ];
    }
    public function total_facilitator( $params ) {
        $range = sanitize_text_field( $params['range'] );
        $negative_stat = $params['negative_stat'] ?? false;

        $label = '';
        $description = '';
        $link = '';
        $value = 0;
        $goal = 0;
        $trend = 0;
        $valence = null;
        $goal_valence = null;
        $trend_valence = null;
        $days = 0;
        if ( $range > 0 ) {
            $days = (int) $range;
        }

        switch ( $params['key'] ) {

            case 'new_coaching_requests':
                $label = 'New Coaching Requests';
                $description = 'Total number of new coaching requests submitted to Facilitator Coaches.';
                $value = 0;
                $goal = 0;
                $trend = 0;
                $valence = 'valence-grey';
                break;
            case 'languages':
                $label = 'Languages';
                $description = 'Number of languages from requests';
                $value = 0;
                $goal = 0;
                $trend = 0;
                $valence = 'valence-grey';
                break;
            case 'locations':
                $label = 'Locations';
                $description = 'Locations from requests.';
                $value = 0;
                $goal = 0;
                $trend = 0;
                $valence = 'valence-grey';
                break;
            default:
                break;
        }

        return [
            'key' => $params['key'],
            'stage' => $params['stage'],
            'label' => $label,
            'description' => $description,
            'link' => $link,
            'value' => zume_format_int( $value ),
            'valence' => $valence ?? zume_get_valence( $value, $goal, $negative_stat ),
            'goal' => $goal,
            'goal_valence' => $goal_valence ?? zume_get_valence( $value, $goal, $negative_stat ),
            'goal_percent' => zume_get_percent( $value, $goal ),
            'trend' => $trend,
            'trend_valence' => $trend_valence ?? zume_get_valence( $value, $trend, $negative_stat ),
            'trend_percent' => zume_get_percent( $value, $trend ),
            'negative_stat' => $negative_stat,
        ];
    }
    public function total_early( $params ) {
        $negative_stat = $params['negative_stat'] ?? false;

        $label = '';
        $description = '';
        $link = '';
        $value = 0;
        $goal = 0;
        $trend = 0;
        $valence = null;
        $goal_valence = null;
        $trend_valence = null;


        switch ( $params['key'] ) {
            case 'new_coaching_requests':
                $label = 'Languages';
                $description = '';
                $value = 0;
                $goal = 0;
                $trend = 0;
                $valence = 'valence-grey';
                break;
            case 'languages':
                $label = 'New Coaching Requests';
                $description = 'Number of languages from requests';
                $value = 0;
                $goal = 0;
                $trend = 0;
                break;
            case 'locations':
                $label = 'Locations';
                $description = 'Locations from requests.';
                $value = 0;
                $goal = 0;
                $trend = 0;
                $valence = 'valence-grey';
                break;
            case 'total_multiplying_practitioner':
            default:
                $label = '(S3) Multiplying Practitioners';
                $description = 'People who are seeking multiplicative movement and are stewarding generational fruit.';
                $link = 'multiplying_practitioner_practitioners';
                $value = 0;
                $goal = 0;
                $trend = 0;
                break;
        }

        return [
            'key' => $params['key'],
            'stage' => $params['stage'],
            'label' => $label,
            'description' => $description,
            'link' => $link,
            'value' => zume_format_int( $value ),
            'valence' => $valence ?? zume_get_valence( $value, $goal, $negative_stat ),
            'goal' => $goal,
            'goal_valence' => $goal_valence ?? zume_get_valence( $value, $goal, $negative_stat ),
            'goal_percent' => zume_get_percent( $value, $goal ),
            'trend' => $trend,
            'trend_valence' => $trend_valence ?? zume_get_valence( $value, $trend, $negative_stat ),
            'trend_percent' => zume_get_percent( $value, $trend ),
            'negative_stat' => $negative_stat,
        ];
    }
    public function total_advanced( $params ) {
        $negative_stat = $params['negative_stat'] ?? false;

        $label = '';
        $description = '';
        $link = '';
        $value = 0;
        $goal = 0;
        $trend = 0;
        $valence = null;
        $goal_valence = null;
        $trend_valence = null;

        switch ( $params['key'] ) {
            case 'total_churches':
                $label = 'Total Churches';
                $description = 'Total number of churches reported by S2 Practitioners.';
                $value = 0;
                $goal = 0;
                $trend = 0;
                break;
            case 'total_locations':
                $label = 'Total Locations';
                $description = 'Total number of locations reported by S2 Practitioners.';
                $value = 0;
                $goal = 0;
                $trend = 0;
                break;
            case 'total_active_reporters':
                $label = 'Total Active Reporters';
                $description = 'Total number of active reporters.';
                $link = 'partial_practitioner_practitioners';
                $value = 0;
                $goal = 0;
                $trend = 0;
                break;
            case 'new_practitioners':
                $label = 'New Practitioners';
                $description = 'Total number of new practitioners.';
                $value = 0;
                $goal = 0;
                $trend = 0;
                break;
            case 'new_reporters':
                $label = 'New Reporters';
                $description = 'Total number of new reporters.';
                $value = 0;
                $goal = 0;
                $trend = 0;
                break;
            case 'new_churches':
                $label = 'New Churches';
                $description = 'Total number of new churches reported by S2 Practitioners.';
                $value = 0;
                $goal = 0;
                $trend = 0;
                break;
            case 'new_locations':
                $label = 'New Locations';
                $description = 'Total number of new locations reported by S2 Practitioners.';
                $value = 0;
                $goal = 0;
                $trend = 0;
                break;
            case 'has_no_coach':
                $label = 'Has No Coach';
                $description = 'Total number of S2 Practitioners who have not yet been assigned a coach.';
                $value = 0;
                $goal = 0;
                $trend = 0;
                break;
            case 'has_not_reported':
                $label = 'Has Not Reported';
                $description = 'Total number of S2 Practitioners who have not yet reported.';
                $value = 0;
                $goal = 0;
                $trend = 0;
                break;
            case 'total_multiplying_practitioner':
                $label = '(S3) Multiplying Practitioners';
                $description = 'People who are seeking multiplicative movement and are stewarding generational fruit.';
                $link = 'multiplying_practitioner_practitioners';
                $value = 0;
                $goal = 0;
                $trend = 0;
                break;
            default:
                $value = 0;
                $goal = 0;
                $trend = 0;
                break;
        }

        return [
            'key' => $params['key'],
            'stage' => $params['stage'],
            'label' => $label,
            'description' => $description,
            'link' => $link,
            'value' => zume_format_int( $value ),
            'valence' => $valence ?? zume_get_valence( $value, $goal, $negative_stat ),
            'goal' => $goal,
            'goal_valence' => $goal_valence ?? zume_get_valence( $value, $goal, $negative_stat ),
            'goal_percent' => zume_get_percent( $value, $goal ),
            'trend' => $trend,
            'trend_valence' => $trend_valence ?? zume_get_valence( $value, $trend, $negative_stat ),
            'trend_percent' => zume_get_percent( $value, $trend ),
            'negative_stat' => $negative_stat,
        ];
    }
    public function general( $params ) {
        $negative_stat = $params['negative_stat'] ?? false;

        $label = '';
        $description = '';
        $link = '';
        $value = 0;
        $goal = 0;
        $trend = 0;
        $valence = null;
        $goal_valence = null;
        $trend_valence = null;

        switch ( $params['key'] ) {

            case 'active_coaches':
                $label = 'Active Coaches';
                $description = 'Number of active coaches';
                $value = 0;
                $goal = 0;
                $trend = 0;
                $valence = 'valence-grey';
                break;
            case 'total_people_in_coaching':
                $label = 'People in Coaching';
                $description = 'Number of people in coaching';
                $value = 0;
                $goal = 0;
                $trend = 0;
                $valence = 'valence-grey';
                break;
            case 'people_in_coaching':
                $label = 'People in Coaching';
                $description = 'Number of people in coaching';
                $value = 0;
                $goal = 0;
                $trend = 0;
                $valence = 'valence-grey';
                break;
            case 'coaching_engagements':
                $label = 'Coaching Engagements';
                $description = 'Number of coaching engagements';
                $value = 0;
                $goal = 0;
                $trend = 0;
                $valence = 'valence-grey';
                break;
            case 'in_and_out':
                return [
                    'key' => $params['key'],
                    'label' => '',
                    'description' => 'Description',
                    'link' => '',
                    'value_in' => zume_format_int( 0 ),
                    'value_idle' => zume_format_int( 0 ),
                    'value_out' => zume_format_int( 0 ),
                ];
            case 'all_time_stats':
                global $wpdb;
                $list = [];

                $thirty_days_ago = strtotime( date( 'Y-m-d H:i:s', time() ) . ' -30 days' );
                $sql_30_days_ago = date( 'Y-m-d H:i:s', $thirty_days_ago );
                $year_to_date = strtotime( 'first day of january this year' );
                $year_ago = strtotime( '365 days ago' );
                $world_sql = Zume_Queries::world_grid_sql();

//                dt_write_log( $thirty_days_ago );
//                dt_write_log( $year_to_date );
//                dt_write_log( $year_ago );

                # individual visitors last 30 days
                $number = $wpdb->get_var("
                    SELECT COUNT( DISTINCT(user_id) )
                    FROM zume_dt_reports
                    WHERE timestamp > $thirty_days_ago;
                ");
                $list[] = 'Logged in users engaging training in the last 30 days: '.zume_format_int( $number );

                # (probably) individual views of Pieces pages this month
                $number = $wpdb->get_var("
                    SELECT COUNT(*)
                    FROM (
                    SELECT subtype, lng, count(*)
                        FROM zume_dt_reports_anonymous
                        WHERE timestamp > $thirty_days_ago AND type = 'studying'
                    GROUP BY subtype, lng
                    ) as tb
                ");
                $list[] = 'Pieces pages being studied in the last 30 days: '.zume_format_int( $number );

                # (individual) registrations this month

                $number = $wpdb->get_var("
                    SELECT COUNT( DISTINCT(user_id) )
                    FROM zume_dt_reports
                    WHERE timestamp > $thirty_days_ago AND subtype = 'registered';
                ");
                $list[] = 'Registrations in the last 30 days: '.zume_format_int( $number );

                # (usually probably group) sessions completed this month
                $number = $wpdb->get_var("
                    SELECT COUNT( DISTINCT(user_id) )
                    FROM zume_dt_reports
                    WHERE timestamp > $thirty_days_ago AND ( subtype LIKE 'set_a_%' OR subtype LIKE 'set_b_%' OR subtype LIKE 'set_c_%' );
                ");
                $list[] = 'Individual session checkins in the last 30 days: '.zume_format_int( $number );

                # (usually probably group) session 10 completions this month
                $number = $wpdb->get_var("
                    SELECT COUNT( DISTINCT(user_id) )
                    FROM zume_dt_reports
                    WHERE timestamp > $thirty_days_ago AND subtype = 'training_completed';
                ");
                $list[] = 'Individuals completing the training in the last 30 days: '.zume_format_int( $number );

                # (indiviudals in) countries this month
                $number = $wpdb->get_var("
                   SELECT COUNT( DISTINCT( admin0_grid_id) )
                    FROM (
                    SELECT lg.admin0_grid_id
                    FROM zume_dt_reports r
                    LEFT JOIN zume_dt_location_grid lg ON lg.grid_id=r.grid_id
                    WHERE r.timestamp > $thirty_days_ago
                    GROUP BY lg.admin0_grid_id
                    UNION ALL
                    SELECT lg.admin0_grid_id
                    FROM zume_dt_reports_anonymous ra
                    LEFT JOIN zume_dt_location_grid lg ON lg.grid_id=ra.grid_id
                    WHERE ra.timestamp > $thirty_days_ago
                    GROUP BY lg.admin0_grid_id
                    ) as tb
                ");
                $list[] = 'Countries and territories engaged in the last 30 days: '.zume_format_int( $number ). ' out of 248';

                // unique training locations
                $number = $wpdb->get_var("
                   SELECT COUNT( DISTINCT( r.grid_id ) )
                    FROM zume_dt_reports r
                    JOIN ( $world_sql ) as list ON list.grid_id=r.grid_id
                ");
                $list[] = 'Targeted training locations engaged: '.zume_format_int( $number ).' out of 44,395';

                //unique church locations
                $number = $wpdb->get_var("
                   SELECT COUNT( DISTINCT( r.grid_id ) )
                    FROM zume_dt_location_grid_meta r
                    JOIN ( $world_sql ) as list ON list.grid_id=r.grid_id
                    WHERE r.post_type = 'groups'");
                $list[] = 'Targeted church locations engaged: '.zume_format_int( $number ).' out of 44,395';

                //active contacts (YTD)
                $number = $wpdb->get_var("
                   SELECT COUNT( DISTINCT( r.user_id) )
                    FROM zume_dt_reports r
                    WHERE timestamp > $year_to_date
                ");
                $list[] = 'Active trainees since Jan 1 this year: '.zume_format_int( $number );

                $number = $wpdb->get_var("
                   SELECT COUNT( DISTINCT( r.user_id) )
                    FROM zume_dt_reports r
                    WHERE timestamp > $year_ago
                ");
                $list[] = 'Active trainees since 1 year ago: '.zume_format_int( $number );

                //coaching requests
                $number = $wpdb->get_var("
                    SELECT COUNT( DISTINCT(user_id) )
                    FROM zume_dt_reports
                    WHERE timestamp > $year_to_date AND subtype = 'requested_a_coach';
                ");
                $list[] = 'Coaching requests in since Jan 1 this year: '.zume_format_int( $number );

                //coaching requests
                $number = $wpdb->get_var("
                    SELECT COUNT( DISTINCT(user_id) )
                    FROM zume_dt_reports
                    WHERE timestamp > $year_ago AND subtype = 'requested_a_coach';
                ");
                $list[] = 'Coaching requests since 1 year ago: '.zume_format_int( $number );

                return [
                    'key' => 'all_time_stats',
                    'list' => $list,
                ];
            default:
                break;
        }

        return [
            'key' => $params['key'],
            'stage' => $params['stage'],
            'label' => $label,
            'description' => $description,
            'link' => $link,
            'value' => zume_format_int( $value ),
            'valence' => $valence ?? zume_get_valence( $value, $goal, $negative_stat ),
            'goal' => $goal,
            'goal_valence' => $goal_valence ?? zume_get_valence( $value, $goal, $negative_stat ),
            'goal_percent' => zume_get_percent( $value, $goal ),
            'trend' => $trend,
            'trend_valence' => $trend_valence ?? zume_get_valence( $value, $trend, $negative_stat ),
            'trend_percent' => zume_get_percent( $value, $trend ),
            'negative_stat' => $negative_stat,
        ];
    }


    public function total_practitioners( $params ) {
        $negative_stat = $params['negative_stat'] ?? false;

        $label = '';
        $description = '';
        $link = '';
        $value = 0;
        $goal = 0;
        $trend = 0;
        $valence = null;

        switch ( $params['key'] ) {

            case 'churches_total':
                $label = 'Total Registrations';
                $description = 'People who are seeking multiplicative movement and are stewarding generational fruit.';
                $value = Zume_Queries::query_total_churches();
                $goal = 0;
                $trend = 0;
                ;
                $valence = 'valence-grey';
                break;
            case 'practitioners_total':
                $label = 'Visitors';
                $description = 'People who are seeking multiplicative movement and are stewarding generational fruit.';
                $value = Zume_Queries::query_total_practitioners();
                $goal = 0;
                $trend = 0;
                $valence = 'valence-grey';
                break;
            default:
                $value = 0;
                $goal = 0;
                $trend = 0;
                break;

        }

        return [
            'key' => $params['key'],
            'stage' => $params['stage'],
            'label' => $label,
            'description' => $description,
            'link' => $link,
            'value' => zume_format_int( $value ),
            'valence' => $valence ?? zume_get_valence( $value, $goal, $negative_stat ),
            'goal' => $goal,
            'goal_valence' => zume_get_valence( $value, $goal, $negative_stat ),
            'goal_percent' => zume_get_percent( $value, $goal ),
            'trend' => $trend,
            'trend_valence' => zume_get_valence( $value, $trend, $negative_stat ),
            'trend_percent' => zume_get_percent( $value, $trend ),
            'negative_stat' => $negative_stat,
        ];
    }
    public function total_churches( $params ) {
        $negative_stat = $params['negative_stat'] ?? false;

        $label = '';
        $description = '';
        $link = '';
        $value = 0;
        $goal = 0;
        $trend = 0;
        $valence = null;

        switch ( $params['key'] ) {

            case 'churches_total':
                $label = 'Total Registrations';
                $description = 'People who are seeking multiplicative movement and are stewarding generational fruit.';
                $value = Zume_Queries::query_total_churches();
                $goal = 0;
                $trend = 0;
                $valence = 'valence-grey';
                break;
            case 'practitioners_total':
                $label = 'Visitors';
                $description = 'People who are seeking multiplicative movement and are stewarding generational fruit.';
                $value = Zume_Queries::query_total_practitioners();
                $goal = 0;
                $trend = 0;
                $valence = 'valence-grey';
                break;
            default:
                $value = 0;
                $goal = 0;
                $trend = 0;
                break;

        }

        return [
            'key' => $params['key'],
            'stage' => $params['stage'],
            'label' => $label,
            'description' => $description,
            'link' => $link,
            'value' => zume_format_int( $value ),
            'valence' => $valence ?? zume_get_valence( $value, $goal, $negative_stat ),
            'goal' => $goal,
            'goal_valence' => zume_get_valence( $value, $goal, $negative_stat ),
            'goal_percent' => zume_get_percent( $value, $goal ),
            'trend' => $trend,
            'trend_valence' => zume_get_valence( $value, $trend, $negative_stat ),
            'trend_percent' => zume_get_percent( $value, $trend ),
            'negative_stat' => $negative_stat,
        ];
    }
    public function stats_list( WP_REST_Request $request ) {
        $params = dt_recursive_sanitize_array( $request->get_params() );

        $list_of_keys = [
            'v5_ready_languages',
//            'number_of_coaches'
        ];

        switch ( $params['key'] ) {
            case 'all':
                return $list_of_keys;
            case 'v5_ready_languages':
                return [
                    'key' => $params['key'],
                    'label' => 'Zume 5 Ready Languages',
                    'value' => Zume_Queries_Stats::v5_ready_languages(),
                ];
            case 'number_of_coaches':
                return [
                    'key' => $params['key'],
                    'label' => 'Number of Coaches',
                    'value' => ''
                ];
            default:
                return [
                    'label' => '',
                    'value' => ''
                ];
        }

        return $params;
    }

    public function location_funnel() {
        $data = DT_Mapping_Module::instance()->data();
        $funnel = zume_funnel_stages();

        $data = $this->add_column( $data, $funnel['1']['key'], $funnel['1']['label'], [ '1' ] );
        $data = $this->add_column( $data, $funnel['2']['key'], $funnel['2']['label'], [ '2' ] );
        $data = $this->add_column( $data, $funnel['3']['key'], $funnel['3']['label'], [ '3' ] );
        $data = $this->add_column( $data, $funnel['4']['key'], $funnel['4']['label'], [ '4' ] );
        $data = $this->add_column( $data, $funnel['5']['key'], $funnel['5']['label'], [ '5' ] );
        $data = $this->add_column( $data, $funnel['6']['key'], $funnel['6']['label'], [ '6' ] );

        return $data;
    }

    public function list( WP_REST_Request $request ) {
        return true;
    }


    public function map_switcher( WP_REST_Request $request ) {
        $params = dt_recursive_sanitize_array( $request->get_params() );
        if ( ! isset( $params['stage'] ) ) {
            return new WP_Error( 'no_stage', __( 'No stage key provided.', 'zume' ), array( 'status' => 400 ) );
        }

        if ( ! isset( $params['range'] ) ) {
            $params['range'] = -1;
        }

        switch ( $params['stage'] ) {
            case 'anonymous':
                return $this->map_geojson( [ 0 ], $params['range'] );
            case 'registrant':
                return $this->map_geojson( [ 1 ], $params['range'] );
            case 'active_training_trainee':
                return $this->map_geojson( [ 2 ], $params['range'] );
            case 'post_training_trainee':
                return $this->map_geojson( [ 3 ], $params['range'] );
            case 'partial_practitioner':
                return $this->map_geojson( [ 4 ], $params['range'] );
            case 'full_practitioner':
                return $this->map_geojson( [ 5 ], $params['range'] );
            case 'multiplying_practitioner':
                return $this->map_geojson( [ 6 ], $params['range'] );
            case 'trainees':
                return $this->map_geojson( [ 1,2,3 ], $params['range'] );
            case 'practitioners':
                return $this->map_geojson( [ 4,5,6 ], $params['range'] );
            case 'churches':
                return $this->map_churches_geojson( 6 );
//            case 'facilitator':
//                return $this->total_facilitator( $params );
//            case 'early':
//                return $this->total_early( $params );
//            case 'advanced':
//                return $this->total_advanced( $params );
            default:
                return $this->general( $params );
        }
    }
    public function map_geojson( array $stage, $range ) {
        $results = Zume_Queries::stage_by_location( $stage, $range );

        $count = 0;
        $features = [];
        foreach ( $results as $result ) {

            $lat = $result['lat'];
            $lng = $result['lng'];

            $features[] = array(
                'type' => 'Feature',
                'properties' => [
                    'name' => $result['name'] ?? '',
                    'post_id' => $result['post_id'],
                    'post_type' => 'contacts',
                ],
                'geometry' => array(
                    'type' => 'Point',
                    'coordinates' => array(
                        (float) $lng,
                        (float) $lat,
                        1,
                    ),
                ),
            );

            $count++;
        }

        $new_data = array(
            'stage' => $stage,
            'range' => $range,
            'total' => $count,
            'type' => 'FeatureCollection',
            'features' => $features,
        );

        return $new_data;
    }
    public function map_list_switcher( WP_REST_Request $request ) {
        $params = dt_recursive_sanitize_array( $request->get_params() );
        if ( ! isset( $params['stage'], $params['north'], $params['south'], $params['east'], $params['west'] ) ) {
            return new WP_Error( 'no_stage', __( 'No stage key or complete boundaries provided.', 'zume' ), array( 'status' => 400 ) );
        }
        $range = -1;
        if ( isset( $params['range'] ) ) {
            $range = (float) $params['range'];
        }
        $params['north'] = (float) $params['north'];
        $params['south'] = (float) $params['south'];
        $params['east'] = (float) $params['east'];
        $params['west'] = (float) $params['west'];

        switch ( $params['stage'] ) {
            case 'anonymous':
                return Zume_Queries::stage_by_boundary( [ 0 ], $range, $params['north'], $params['south'], $params['east'], $params['west'] );
            case 'registrant':
                return Zume_Queries::stage_by_boundary( [ 1 ], $range, $params['north'], $params['south'], $params['east'], $params['west'] );
            case 'active_training_trainee':
                return Zume_Queries::stage_by_boundary( [ 2 ], $range, $params['north'], $params['south'], $params['east'], $params['west'] );
            case 'post_training_trainee':
                return Zume_Queries::stage_by_boundary( [ 3 ], $range, $params['north'], $params['south'], $params['east'], $params['west'] );
            case 'partial_practitioner':
                return Zume_Queries::stage_by_boundary( [ 4 ], $range, $params['north'], $params['south'], $params['east'], $params['west'] );
            case 'full_practitioner':
                return Zume_Queries::stage_by_boundary( [ 5 ], $range, $params['north'], $params['south'], $params['east'], $params['west'] );
            case 'multiplying_practitioner':
                return Zume_Queries::stage_by_boundary( [ 6 ], $range, $params['north'], $params['south'], $params['east'], $params['west'] );
            case 'practitioners':
                return Zume_Queries::stage_by_boundary( [ 4,5,6 ], $range, $params['north'], $params['south'], $params['east'], $params['west'] );
            case 'churches':
                return Zume_Queries::churches_by_boundary( $params['north'], $params['south'], $params['east'], $params['west'] );
//            case 'facilitator':
//                return $this->total_facilitator( $params );
//            case 'early':
//                return $this->total_early( $params );
//            case 'advanced':
//                return $this->total_advanced( $params );
            default:
                return $this->general( $params );
        }
    }

    public function map_churches_geojson() {
        global $wpdb;
        $results = Zume_Queries::churches_with_location();

        $features = [];
        foreach ( $results as $result ) {

            $lat = $result['lat'];
            $lng = $result['lng'];

            $features[] = array(
                'type' => 'Feature',
                'properties' => [
                    'name' => $result['name'],
                    'post_id' => $result['post_id'],
                    'post_type' => 'groups',
                ],
                'geometry' => array(
                    'type' => 'Point',
                    'coordinates' => array(
                        (float) $lng,
                        (float) $lat,
                        1,
                    ),
                ),
            );
        }

        $new_data = array(
            'type' => 'FeatureCollection',
            'features' => $features,
        );

        return $new_data;
    }

    public function location( WP_REST_Request $request ) {
        return DT_Ipstack_API::get_location_grid_meta_from_current_visitor();
    }
    public function training_elements( WP_REST_Request $request ) {
        return Zume_Views::training_elements( dt_recursive_sanitize_array( $request->get_params() ) );
    }

    public function query_location_funnel( array $range ) {
        global $wpdb;
        if ( count( $range ) > 1 ) {
            $range = '(' . implode( ',', $range ) . ')';
        } else {
            $range = '(' . $range[0] . ')';
        }

        //phpcs:disable
        $results = $wpdb->get_results( "
            SELECT t0.admin0_grid_id as grid_id, count(t0.admin0_grid_id) as count
            FROM (
                SELECT tb.grid_id, lg.admin0_grid_id, lg.admin1_grid_id, lg.admin2_grid_id, lg.admin3_grid_id
                FROM
                (
                   SELECT r.user_id, MAX(r.value) as stage, (
						SELECT grid_id FROM zume_dt_reports WHERE user_id = r.user_id AND grid_id IS NOT NULL ORDER BY id DESC LIMIT 1
					) as grid_id FROM zume_dt_reports r
                   WHERE r.type = 'system' AND r.subtype = 'current_level'
                   GROUP BY r.user_id
                ) as tb
                LEFT JOIN zume_dt_location_grid lg ON lg.grid_id=tb.grid_id
                WHERE tb.stage IN $range
            ) as t0
            GROUP BY t0.admin0_grid_id
            UNION
            SELECT t1.admin1_grid_id as grid_id, count(t1.admin1_grid_id) as count
            FROM (
               SELECT tb.grid_id, lg.admin0_grid_id, lg.admin1_grid_id, lg.admin2_grid_id, lg.admin3_grid_id
                FROM
                (
                   SELECT r.user_id, MAX(r.value) as stage, (
						SELECT grid_id FROM zume_dt_reports WHERE user_id = r.user_id AND grid_id IS NOT NULL ORDER BY id DESC LIMIT 1
					) as grid_id FROM zume_dt_reports r
                   WHERE r.type = 'system' AND r.subtype = 'current_level'
                   GROUP BY r.user_id
                ) as tb
                LEFT JOIN zume_dt_location_grid lg ON lg.grid_id=tb.grid_id
                WHERE tb.stage IN $range
            ) as t1
            GROUP BY t1.admin1_grid_id
            UNION
            SELECT  t2.admin2_grid_id as grid_id, count(t2.admin2_grid_id) as count
            FROM (
               SELECT tb.grid_id, lg.admin0_grid_id, lg.admin1_grid_id, lg.admin2_grid_id, lg.admin3_grid_id
                FROM
                (
                   SELECT r.user_id, MAX(r.value) as stage, (
						SELECT grid_id FROM zume_dt_reports WHERE user_id = r.user_id AND grid_id IS NOT NULL ORDER BY id DESC LIMIT 1
					) as grid_id FROM zume_dt_reports r
                   WHERE r.type = 'system' AND r.subtype = 'current_level'
                   GROUP BY r.user_id
                ) as tb
                LEFT JOIN zume_dt_location_grid lg ON lg.grid_id=tb.grid_id
                WHERE tb.stage IN $range
            ) as t2
            GROUP BY t2.admin2_grid_id
            UNION
            SELECT t3.admin3_grid_id as grid_id, count(t3.admin3_grid_id) as count
            FROM (
                SELECT tb.grid_id, lg.admin0_grid_id, lg.admin1_grid_id, lg.admin2_grid_id, lg.admin3_grid_id
                FROM
                (
                   SELECT r.user_id, MAX(r.value) as stage, (
						SELECT grid_id FROM zume_dt_reports WHERE user_id = r.user_id AND grid_id IS NOT NULL ORDER BY id DESC LIMIT 1
					) as grid_id FROM zume_dt_reports r
                   WHERE r.type = 'system' AND r.subtype = 'current_level'
                   GROUP BY r.user_id
                ) as tb
                LEFT JOIN zume_dt_location_grid lg ON lg.grid_id=tb.grid_id
                WHERE tb.stage IN $range
            ) as t3
            GROUP BY t3.admin3_grid_id;
            ", ARRAY_A );
        //phpcs:enable

        $list = [];
        if ( is_array( $results ) ) {
            foreach ( $results as $result ) {
                if ( empty( $result['grid_id'] ) ) {
                    continue;
                }
                $list[$result['grid_id']] = $result;
            }
        }

        return $list;
    }
    public function add_column( $data, $key, $label, $stage )
    {
        $column_labels = $data['custom_column_labels'] ?? [];
        $column_data = $data['custom_column_data'] ?? [];
        if ( empty( $column_labels ) ) {
            $next_column_number = 0;
        } else if ( count( $column_labels ) === 1 ) {
            $next_column_number = 1;
        } else {
            $next_column_number = count( $column_labels );
        }
        $column_labels[$next_column_number] = [
            'key' => $key,
            'label' => $label,
        ];
        if ( !empty( $column_data ) ) {
            foreach ( $column_data as $key => $value ) {
                $column_data[$key][$next_column_number] = 0;
            }
        }
        $results = $this->query_location_funnel( $stage );
        if ( !empty( $results ) ) {
            foreach ( $results as $result ) {
                if ( $result['count'] > 0 ) { // filter for only contact and positive counts
                    $grid_id = $result['grid_id'];

                    // test if grid_id exists, else prepare it with 0 values
                    if ( !isset( $column_data[$grid_id] ) ) {
                        $column_data[$grid_id] = [];
                        $i = 0;
                        while ( $i <= $next_column_number ) {
                            $column_data[$grid_id][$i] = 0;
                            $i++;
                        }
                    }

                    // add new record to column
                    $column_data[$grid_id][$next_column_number] = (int) $result['count'] ?? 0; // must be string
                }
            }
        }
        $data['custom_column_labels'] = $column_labels;
        $data['custom_column_data']   = $column_data;
        return $data;
    }
    public function location_goals() {
        $data = DT_Mapping_Module::instance()->data();

        $practitioners = $this->query_practitioner_funnel( [ '3','4','5','6' ] );
        $data = $this->add_goals_column( $data, 'trainees', 'Trainees', $practitioners );
        $data = $this->add_practitioners_goal_column( $data );

        $churches = $this->query_churches_funnel();
        $data = $this->add_goals_column( $data, 'churches', 'Churches', $churches );
        $data = $this->add_church_goal_column( $data );

        return $data;
    }
    public function query_practitioner_funnel( array $range ) {
        global $wpdb;
        if ( count( $range ) > 1 ) {
            $range = '(' . implode( ',', $range ) . ')';
        } else {
            $range = '(' . $range[0] . ')';
        }

        //phpcs:disable
        $results = $wpdb->get_results( "
            SELECT t0.admin0_grid_id as grid_id, count(t0.admin0_grid_id) as count
            FROM (
                SELECT tb.grid_id, lg.admin0_grid_id, lg.admin1_grid_id, lg.admin2_grid_id, lg.admin3_grid_id
                FROM
                (
                   SELECT r.user_id, MAX(r.value) as stage, (
						SELECT grid_id FROM zume_dt_reports WHERE user_id = r.user_id AND grid_id IS NOT NULL ORDER BY id DESC LIMIT 1
					) as grid_id FROM zume_dt_reports r
                   WHERE r.type = 'system' AND r.subtype = 'current_level'
                   GROUP BY r.user_id
                ) as tb
                LEFT JOIN zume_dt_location_grid lg ON lg.grid_id=tb.grid_id
                WHERE tb.stage IN $range
            ) as t0
            GROUP BY t0.admin0_grid_id
            UNION
            SELECT t1.admin1_grid_id as grid_id, count(t1.admin1_grid_id) as count
            FROM (
               SELECT tb.grid_id, lg.admin0_grid_id, lg.admin1_grid_id, lg.admin2_grid_id, lg.admin3_grid_id
                FROM
                (
                   SELECT r.user_id, MAX(r.value) as stage, (
						SELECT grid_id FROM zume_dt_reports WHERE user_id = r.user_id AND grid_id IS NOT NULL ORDER BY id DESC LIMIT 1
					) as grid_id FROM zume_dt_reports r
                   WHERE r.type = 'system' AND r.subtype = 'current_level'
                   GROUP BY r.user_id
                ) as tb
                LEFT JOIN zume_dt_location_grid lg ON lg.grid_id=tb.grid_id
                WHERE tb.stage IN $range
            ) as t1
            GROUP BY t1.admin1_grid_id
            UNION
            SELECT  t2.admin2_grid_id as grid_id, count(t2.admin2_grid_id) as count
            FROM (
               SELECT tb.grid_id, lg.admin0_grid_id, lg.admin1_grid_id, lg.admin2_grid_id, lg.admin3_grid_id
                FROM
                (
                   SELECT r.user_id, MAX(r.value) as stage, (
						SELECT grid_id FROM zume_dt_reports WHERE user_id = r.user_id AND grid_id IS NOT NULL ORDER BY id DESC LIMIT 1
					) as grid_id FROM zume_dt_reports r
                   WHERE r.type = 'system' AND r.subtype = 'current_level'
                   GROUP BY r.user_id
                ) as tb
                LEFT JOIN zume_dt_location_grid lg ON lg.grid_id=tb.grid_id
                WHERE tb.stage IN $range
            ) as t2
            GROUP BY t2.admin2_grid_id
            UNION
            SELECT t3.admin3_grid_id as grid_id, count(t3.admin3_grid_id) as count
            FROM (
                SELECT tb.grid_id, lg.admin0_grid_id, lg.admin1_grid_id, lg.admin2_grid_id, lg.admin3_grid_id
                FROM
                (
                   SELECT r.user_id, MAX(r.value) as stage, (
						SELECT grid_id FROM zume_dt_reports WHERE user_id = r.user_id AND grid_id IS NOT NULL ORDER BY id DESC LIMIT 1
					) as grid_id FROM zume_dt_reports r
                   WHERE r.type = 'system' AND r.subtype = 'current_level'
                   GROUP BY r.user_id
                ) as tb
                LEFT JOIN zume_dt_location_grid lg ON lg.grid_id=tb.grid_id
                WHERE tb.stage IN $range
            ) as t3
            GROUP BY t3.admin3_grid_id;
            ", ARRAY_A );
        //phpcs:enable

        $list = [];
        if ( is_array( $results ) ) {
            foreach ( $results as $result ) {
                if ( empty( $result['grid_id'] ) ) {
                    continue;
                }
                $list[$result['grid_id']] = $result;
            }
        }

        return $list;
    }
    public function query_churches_funnel() {
        global $wpdb;

        //phpcs:disable
        $results = $wpdb->get_results( "
            SELECT t0.admin0_grid_id as grid_id, count(t0.admin0_grid_id) as count
            FROM (
                SELECT lg.admin0_grid_id, lg.admin1_grid_id, lg.admin2_grid_id, lg.admin3_grid_id
                FROM zume_dt_location_grid_meta lgm
                LEFT JOIN zume_postmeta pm ON pm.post_id=lgm.post_id AND pm.meta_key = 'group_type' AND pm.meta_value = 'church'
                LEFT JOIN zume_dt_location_grid lg ON lg.grid_id=lgm.grid_id
                WHERE lgm.post_type = 'groups'
            ) as t0
            GROUP BY t0.admin0_grid_id
            UNION
            SELECT t1.admin1_grid_id as grid_id, count(t1.admin1_grid_id) as count
            FROM (
                SELECT lg.admin0_grid_id, lg.admin1_grid_id, lg.admin2_grid_id, lg.admin3_grid_id
                FROM zume_dt_location_grid_meta lgm
                LEFT JOIN zume_postmeta pm ON pm.post_id=lgm.post_id AND pm.meta_key = 'group_type' AND pm.meta_value = 'church'
                LEFT JOIN zume_dt_location_grid lg ON lg.grid_id=lgm.grid_id
                WHERE lgm.post_type = 'groups'
            ) as t1
            GROUP BY t1.admin1_grid_id
            UNION
            SELECT t2.admin2_grid_id as grid_id, count(t2.admin2_grid_id) as count
            FROM (
                SELECT lg.admin0_grid_id, lg.admin1_grid_id, lg.admin2_grid_id, lg.admin3_grid_id
                FROM zume_dt_location_grid_meta lgm
                LEFT JOIN zume_postmeta pm ON pm.post_id=lgm.post_id AND pm.meta_key = 'group_type' AND pm.meta_value = 'church'
                LEFT JOIN zume_dt_location_grid lg ON lg.grid_id=lgm.grid_id
                WHERE lgm.post_type = 'groups'
            ) as t2
            GROUP BY t2.admin2_grid_id
            UNION
            SELECT t3.admin3_grid_id as grid_id, count(t3.admin3_grid_id) as count
            FROM (
                SELECT lg.admin0_grid_id, lg.admin1_grid_id, lg.admin2_grid_id, lg.admin3_grid_id
                FROM zume_dt_location_grid_meta lgm
                LEFT JOIN zume_postmeta pm ON pm.post_id=lgm.post_id AND pm.meta_key = 'group_type' AND pm.meta_value = 'church'
                LEFT JOIN zume_dt_location_grid lg ON lg.grid_id=lgm.grid_id
                WHERE lgm.post_type = 'groups'
            ) as t3
            GROUP BY t3.admin3_grid_id;
            ", ARRAY_A );
        //phpcs:enable

        $list = [];
        if ( is_array( $results ) ) {
            foreach ( $results as $result ) {
                if ( empty( $result['grid_id'] ) ) {
                    continue;
                }
                $list[$result['grid_id']] = $result;
            }
        }

        return $list;
    }
    public function add_goals_column( $data, $key, $label, $results = [] )
    {
        $column_labels = $data['custom_column_labels'] ?? [];
        $column_data = $data['custom_column_data'] ?? [];
        if ( empty( $column_labels ) ) {
            $next_column_number = 0;
        } else if ( count( $column_labels ) === 1 ) {
            $next_column_number = 1;
        } else {
            $next_column_number = count( $column_labels );
        }
        $column_labels[$next_column_number] = [
            'key' => $key,
            'label' => $label,
        ];
        if ( !empty( $column_data ) ) {
            foreach ( $column_data as $key => $value ) {
                $column_data[$key][$next_column_number] = 0;
            }
        }

        if ( !empty( $results ) ) {
            foreach ( $results as $result ) {
                if ( $result['count'] > 0 ) { // filter for only contact and positive counts
                    $grid_id = $result['grid_id'];

                    // test if grid_id exists, else prepare it with 0 values
                    if ( !isset( $column_data[$grid_id] ) ) {
                        $column_data[$grid_id] = [];
                        $i = 0;
                        while ( $i <= $next_column_number ) {
                            $column_data[$grid_id][$i] = 0;
                            $i++;
                        }
                    }

                    // add new record to column
                    $column_data[$grid_id][$next_column_number] = number_format( $result['count'] ); // must be string
                }
            }
        }
        $data['custom_column_labels'] = $column_labels;
        $data['custom_column_data']   = $column_data;
        return $data;
    }
    public function add_practitioners_goal_column( $data ) {
        global $wpdb;
        $column_labels = $data['custom_column_labels'] ?? [];
        $column_data   = $data['custom_column_data'] ?? [];
        if ( empty( $column_labels ) ) {
            $next_column_number = 0;
        } else if ( count( $column_labels ) === 1 ) {
            $next_column_number = 1;
        } else {
            $next_column_number = count( $column_labels );
        }
        $column_labels[ $next_column_number ] = [
            'key'   => 'trainee_goal',
            'label' => __( 'Trainee Goal', 'zume_funnels' ),
        ];
        if ( ! empty( $column_data ) ) {
            foreach ( $column_data as $key => $value ) {
                $column_data[$key][$next_column_number] = 0;
            }
        }
        $results = $wpdb->get_results(
            "SELECT grid_id, population, country_code, 1 as count
                    FROM {$wpdb->prefix}dt_location_grid
                    WHERE population != '0'
                      AND population IS NOT NULL",
        ARRAY_A );
        if ( ! empty( $results ) ) {
            foreach ( $results as $result ) {
                $grid_id = $result['grid_id'];
                if ( $result['country_code'] === 'US' ) {
                    $result['count'] = round( intval( $result['population'] ) / 5000 );
                } else {
                    $result['count'] = round( intval( $result['population'] ) / 50000 );
                }

                if ( ! isset( $column_data[ $grid_id ] ) ) {
                    $column_data[ $grid_id ] = [];
                    $i = 0;
                    while ( $i <= $next_column_number ) {
                        $column_data[$grid_id][$i] = 0;
                        $i++;
                    }
                }

                if ( $result['count'] == 0 ) {
                    $result['count'] = 1;
                }

                $column_data[$grid_id][$next_column_number] = number_format( $result['count'] ); // must be string
            }
        }
        $data['custom_column_labels'] = $column_labels;
        $data['custom_column_data']   = $column_data;
        return $data;
    }
    public function add_church_goal_column( $data ) {
        global $wpdb;
        $column_labels = $data['custom_column_labels'] ?? [];
        $column_data   = $data['custom_column_data'] ?? [];
        if ( empty( $column_labels ) ) {
            $next_column_number = 0;
        } else if ( count( $column_labels ) === 1 ) {
            $next_column_number = 1;
        } else {
            $next_column_number = count( $column_labels );
        }
        $column_labels[ $next_column_number ] = [
            'key'   => 'church_goal',
            'label' => __( 'Church Goal', 'zume_funnels' ),
        ];
        if ( ! empty( $column_data ) ) {
            foreach ( $column_data as $key => $value ) {
                $column_data[$key][$next_column_number] = 0;
            }
        }
        $results = $wpdb->get_results(
            "SELECT grid_id, population, country_code, 1 as count
                    FROM {$wpdb->prefix}dt_location_grid
                    WHERE population != '0'
                      AND population IS NOT NULL",
        ARRAY_A );
        if ( ! empty( $results ) ) {
            foreach ( $results as $result ) {
                $grid_id = $result['grid_id'];
                if ( $result['country_code'] === 'US' ) {
                    $result['count'] = round( intval( $result['population'] ) / 2500 );
                } else {
                    $result['count'] = round( intval( $result['population'] ) / 25000 );
                }

                if ( ! isset( $column_data[ $grid_id ] ) ) {
                    $column_data[ $grid_id ] = [];
                    $i = 0;
                    while ( $i <= $next_column_number ) {
                        $column_data[$grid_id][$i] = 0;
                        $i++;
                    }
                }

                if ( $result['count'] == 0 ) {
                    $result['count'] = 1;
                }

                $column_data[$grid_id][$next_column_number] = number_format( $result['count'] ); // must be string
            }
        }
        $data['custom_column_labels'] = $column_labels;
        $data['custom_column_data']   = $column_data;
        return $data;
    }

    public function list_mawl( WP_REST_Request $request ) {
        $params = dt_recursive_sanitize_array( $request->get_params() );
        if ( ! isset( $params['user_id'] ) ) {
            return new WP_Error( __METHOD__, 'User_id required.', array( 'status' => 401 ) );
        }
        $user_id = zume_validate_user_id_request( $params['user_id'] );

        return zume_get_user_host( $user_id );
    }
    public function create_mawl( WP_REST_Request $request ) {
        $params = dt_recursive_sanitize_array( $request->get_params() );
        if ( ! isset( $params['type'], $params['subtype'], $params['user_id'] ) ) {
            return new WP_Error( __METHOD__, 'Type, subtype, and user_id required.', array( 'status' => 401 ) );
        }
        if ( 'coaching' !== $params['type'] ) {
            return new WP_Error( __METHOD__, 'Type must be coaching.', array( 'status' => 401 ) );
        }
        $user_id = zume_validate_user_id_request( $params['user_id'] );

        return zume_log_insert( $params['type'], $params['subtype'], [ 'user_id' => $user_id ] );
    }
    public function delete_mawl( WP_REST_Request $request ) {
        global $wpdb;
        $params = dt_recursive_sanitize_array( $request->get_params() );
        if ( ! isset( $params['type'], $params['subtype'], $params['user_id'] ) ) {
            return new WP_Error( __METHOD__, 'Type, subtype, and user_id required.', array( 'status' => 401 ) );
        }
        if ( 'coaching' !== $params['type'] ) {
            return new WP_Error( __METHOD__, 'Type must be coaching.', array( 'status' => 401 ) );
        }
        $user_id = zume_validate_user_id_request( $params['user_id'] );

        $fields = [
            'type' => $params['type'],
            'subtype' => $params['subtype'],
            'user_id' => $user_id,
        ];

        $delete = $wpdb->delete( 'zume_dt_reports', $fields );

        return $delete;
    }

    public function authorize_url( $authorized ){
        if ( isset( $_SERVER['REQUEST_URI'] ) && strpos( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ), $this->namespace ) !== false ) {
            $authorized = true;
        }
        return $authorized;
    }
    public function dt_is_rest( $namespace = null ) {
        // https://github.com/DiscipleTools/disciple-tools-theme/blob/a6024383e954cec2ac4e7a1a31fb4601c940f485/dt-core/global-functions.php#L60
        // Added here so that in non-dt sites there is no dependency.
        $prefix = rest_get_url_prefix();
        if ( defined( 'REST_REQUEST' ) && REST_REQUEST
            || isset( $_GET['rest_route'] )
            && strpos( trim( sanitize_text_field( wp_unslash( $_GET['rest_route'] ) ), '\\/' ), $prefix, 0 ) === 0 ) {
            return true;
        }
        $rest_url    = wp_parse_url( site_url( $prefix ) );
        $current_url = wp_parse_url( add_query_arg( array() ) );
        $is_rest = strpos( $current_url['path'], $rest_url['path'], 0 ) === 0;
        if ( $namespace ){
            return $is_rest && strpos( $current_url['path'], $namespace ) != false;
        } else {
            return $is_rest;
        }
    }
}
Zume_Charts_API::instance();
