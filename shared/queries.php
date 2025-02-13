<?php
if ( !defined( 'ABSPATH' ) ) { exit; } // Exit if accessed directly.

class Zume_Queries extends Zume_Queries_Base {

    public static $expiration = HOUR_IN_SECONDS;

    public static function stage_total( $stage, $range, $trend = false ) {
        // object cache
        $request_key = hash( 'md5', serialize( __METHOD__ . $stage . $range . $trend ) );
        $cached = get_transient( $request_key );
        if ( $cached ) {
            dt_write_log( __METHOD__ . ' cache hit' );
            return $cached;
        }

        global $wpdb;
        $query_for_user_stage = self::query_for_user_stage( $stage, $range, $trend );

        $end = time();
        if ( $range < 1 ) {
            $begin = 0;
        } else {
            $begin = strtotime( '-'. $range . ' days' );
            if ( $trend ) {
                $end = $begin;
                $begin = strtotime( '-'. ( $range * 2 ) . ' days' );
            }
        }

        $result = $wpdb->get_var(
            "SELECT COUNT(tb.user_id)
                FROM
                (
                   $query_for_user_stage
                ) as tb
                WHERE tb.timestamp > $begin
                  AND tb.timestamp < $end
                  AND tb.stage = $stage
                ;" );

        if ( empty( $result ) ) {
            $result = 0;
        } else {
            $result = (float) $result;
        }

        set_transient( $request_key, $result, self::$expiration );

        return $result;
    }

    public static function stage_total_list( $stage, $range, $trend = false ) {

        global $wpdb;
        $query_for_user_stage = self::$query_for_user_stage;

        $end = time();
        if ( $range < 1 ) {
            $begin = 0;
        } else {
            $begin = strtotime( '-'. $range . ' days' );
            if ( $trend ) {
                $end = $begin;
                $begin = strtotime( '-'. ( $range * 2 ) . ' days' );
            }
        }

        $sql = "SELECT *
                FROM
                (
                   $query_for_user_stage
                ) as tb
                WHERE tb.timestamp > $begin
                  AND tb.timestamp < $end
                  AND tb.stage = $stage
                ;";

//        dt_write_log($sql);

        $list = $wpdb->get_results( $sql, ARRAY_A );

        if ( empty( $list ) ) {
            $list = [];
        }

        return $list;
    }

    public static function stage_by_location( array $stages, $range, $trend = false ) {

        global $wpdb;
        $query_for_user_stage = self::$query_for_user_stage;

        $range = (float) $range;

        $end = time();
        if ( $range < 1 ) {
            $begin = 0;
        } else {
            $begin = strtotime( '-'. $range . ' days' );
            if ( $trend ) {
                $end = $begin;
                $begin = strtotime( '-'. ( $range * 2 ) . ' days' );
            }
        }

        if ( count( $stages ) > 1 ) {
            $stages = '(' . implode( ',', $stages ) . ')';
        } else {
            $stages = '(' . $stages[0] . ')';
        }

        $list = $wpdb->get_results(
            "SELECT p.post_title as name, tb.user_id, tb.post_id, tb.stage, lgm.label, lgm.grid_id, lgm.lng, lgm.lat, lgm.level
            FROM
            (
              $query_for_user_stage
            ) as tb
            LEFT JOIN zume_posts p ON p.ID=tb.post_id
            LEFT JOIN zume_dt_location_grid_meta lgm ON lgm.post_id=tb.post_id AND lgm.post_type='contacts'
            LEFT JOIN zume_dt_reports r1 ON r1.id=tb.rid
            WHERE tb.stage IN $stages
                  AND tb.timestamp > $begin
                  AND tb.timestamp < $end;", ARRAY_A );

        if ( empty( $list ) ) {
            $list = [];
        }



        return $list;
    }

    public static function stage_by_boundary( array $stages, $range, float $north, float $south, float $east, float $west, $trend = false ) {
        global $wpdb;
        $query_for_user_stage = self::$query_for_user_stage;

        $end = time();
        if ( $range < 1 ) {
            $begin = 0;
        } else {
            $begin = strtotime( '-'. $range . ' days' );
            if ( $trend ) {
                $end = $begin;
                $begin = strtotime( '-'. ( $range * 2 ) . ' days' );
            }
        }

        if ( count( $stages ) > 1 ) {
            $stages = '(' . implode( ',', $stages ) . ')';
        } else {
            $stages = '(' . $stages[0] . ')';
        }

        $sql = "SELECT p.post_title as name, tb.user_id, tb.post_id, tb.stage, lgm.label, lgm.grid_id, lgm.lng, lgm.lat, lgm.level
            FROM
            (
              $query_for_user_stage
            ) as tb
            LEFT JOIN zume_posts p ON p.ID=tb.post_id
            LEFT JOIN zume_dt_location_grid_meta lgm ON lgm.post_id=tb.post_id AND lgm.post_type='contacts'
            LEFT JOIN zume_dt_reports r1 ON r1.id=tb.rid
            WHERE tb.stage IN $stages
            AND lgm.lat > $south
            AND lgm.lat < $north
            AND lgm.lng > $west
            AND lgm.lng < $east
            AND tb.timestamp > $begin
            AND tb.timestamp < $end;
            ;";
        $results = $wpdb->get_results($sql, ARRAY_A );

        if ( empty( $results ) ) {
            return [];
        }

        return $results;
    }

    public static function churches_with_location() {
         // object cache
         $request_key = hash( 'md5', serialize( __METHOD__ . $stages . $range . $trend ) );
         $cached = wp_cache_get( $request_key, 'zume' );
         if ( $cached ) {
            dt_write_log( __METHOD__ . ' cache hit' );
             return $cached;
         }

        global $wpdb;

        $list = $wpdb->get_results(
            "SELECT p.ID as post_id, p.post_title as name, 'groups' as post_type, lgm.grid_id, lgm.lng, lgm.lat, lgm.level, lgm.source, lgm.label
            FROM zume_posts p
            LEFT JOIN zume_postmeta pm ON pm.post_id=p.ID AND pm.meta_key = 'location_grid_meta'
            LEFT JOIN zume_dt_location_grid_meta lgm ON lgm.grid_meta_id=pm.meta_value
            WHERE p.post_type = 'groups';", ARRAY_A );

        if ( empty( $list ) ) {
            $list = [];
        }

        wp_cache_set( $request_key, $list, 'zume' );

        return $list;
    }

    public static function churches_by_boundary( float $north, float $south, float $east, float $west ) {
        global $wpdb;

        $results = $wpdb->get_results(
            "SELECT p.ID, p.post_title as name, 'groups' as post_type, lgm.grid_id, lgm.lng, lgm.lat, lgm.level, lgm.source, lgm.label
            FROM zume_posts p
            LEFT JOIN zume_postmeta pm ON pm.post_id=p.ID AND pm.meta_key = 'location_grid_meta'
            LEFT JOIN zume_dt_location_grid_meta lgm ON lgm.grid_meta_id=pm.meta_value
            WHERE p.post_type = 'groups'
            AND lgm.lat > $south
            AND lgm.lat < $north
            AND lgm.lng > $west
            AND lgm.lng < $east
        ;", ARRAY_A );

        if ( empty( $results ) ) {
            return [];
        }

        return $results;
    }

    /**
     * Training subtype counts for all *heard* reports.
     *
     * subtype
     * value count
     * @return array
     */
    public static function training_subtype_counts() {
         // object cache
         $request_key = hash( 'md5', serialize( __METHOD__ . $stages . $range . $trend ) );
         $cached = wp_cache_get( $request_key, 'zume' );
         if ( $cached ) {
            dt_write_log( __METHOD__ . ' cache hit' );
             return $cached;
         }

        global $wpdb;

        $list = $wpdb->get_results( $wpdb->prepare(
            "SELECT subtype, COUNT(*) as value
            FROM zume_dt_reports
            WHERE type = 'training' AND subtype LIKE '%heard'
            GROUP BY subtype
            " ), ARRAY_A );

       if ( empty( $list ) ) {
            $list = [];
        }

        wp_cache_set( $request_key, $list, 'zume' );

        return $list;
    }

    /**
     * Returns the total number of churches in the system.
     * @return int
     */
    public static function query_total_churches(): int {
         // object cache
        $request_key = hash( 'md5', __METHOD__ );
        $cached = get_transient( $request_key );
        if ( $cached ) {
            dt_write_log( __METHOD__ . ' cache hit' );
            return $cached;
        }

        global $wpdb;
        $result = $wpdb->get_var(
            "SELECT count(*) as count
                    FROM zume_posts p
                    JOIN zume_postmeta pm ON pm.post_id=p.ID AND pm.meta_key = 'group_type' AND pm.meta_value = 'church'
                    JOIN zume_postmeta pm2 ON pm2.post_id=p.ID AND pm2.meta_key = 'group_status' AND pm2.meta_value = 'active'
                    WHERE post_type = 'groups';"
        );

        if ( empty( $result ) ) {
            $result = 0;
        } else {
            $result = (int) $result;
        }

        set_transient( $request_key, $result, self::$expiration );

        return $result;
    }

    /**
     * Returns the total number of practitioners in the system.
     * @return int
     */
    public static function query_total_practitioners( $stages = [ 3,4,5,6 ], $range = -1, $trend = false ): int {
         // transient cache
         $request_key = hash( 'md5', serialize( __METHOD__ . $stages . $range . $trend ) );
         $cached = get_transient( $request_key );
         if ( $cached ) {
            dt_write_log( __METHOD__ . ' cache hit' );
             return $cached;
         }

        global $wpdb;
        $query_for_user_stage = self::$query_for_user_stage;

        $end = time();
        if ( $range < 1 ) {
            $begin = 0;
        } else {
            $begin = strtotime( '-'. $range . ' days' );
            if ( $trend ) {
                $end = $begin;
                $begin = strtotime( '-'. ( $range * 2 ) . ' days' );
            }
        }

        $stages_list = dt_array_to_sql( $stages );

        $sql = "SELECT COUNT(tb.user_id)
                FROM
                (
                   $query_for_user_stage
                ) as tb
                WHERE tb.timestamp > $begin
                  AND tb.timestamp < $end
                  AND tb.stage IN ($stages_list)
                ";

        $result = $wpdb->get_var($sql);

        if ( empty( $result ) ) {
            $result = 0;
        } else {
            $result = (int) $result;
        }

        set_transient( $request_key, $result, self::$expiration );

        return $result;
    }

    public static function checkins( $stage, $range, $trend = false, $negative = false ) {
         $request_key = hash( 'md5', serialize( __METHOD__ . serialize(  $stages ) . $range . $trend . $negative ) );
         $cached = get_transient( $request_key );
         if ( $cached ) {
            dt_write_log( __METHOD__ . ' cache hit' );
             return $cached;
         }

        global $wpdb;
        $query_for_user_stage = self::$query_for_user_stage;

        $end = time();
        if ( $range < 1 ) {
            $begin = 0;
        } else {
            $begin = strtotime( '-'. $range . ' days' );
            if ( $trend ) {
                $end = $begin;
                $begin = strtotime( '-'. ( $range * 2 ) . ' days' );
            }
        }

        $sql = "
            SELECT COUNT(*)
            FROM
               (
                  $query_for_user_stage
                ) as tb
            JOIN zume_dt_reports r ON r.user_id=tb.user_id AND r.type = 'training' AND r.subtype LIKE 'set_%'
            WHERE tb.stage = $stage
              AND tb.timestamp > $begin
              AND tb.timestamp < $end
            ";
        $count = $wpdb->get_var( $sql );

        if ( $negative ) {
            $stage_total = self::stage_total( $stage, $range, $trend );
            $stage_total = (int) $stage_total;
            $count = (int) $count;
            $count =  $stage_total - $count;
            if ( $count < 1 ) {
                $count = $count * -1;
            }
        }

        if ( empty( $count ) ) {
            $count = 0;
        } else {
            $count = (int) $count;
        }

        set_transient( $request_key, $count, self::$expiration );

        return $count;
    }

    public static function query_stage_by_type_and_subtype( $stage, $range, $type, $subtype, $trend = false, $negative = false ) {
        // object cache
        $request_key = hash( 'md5', serialize( __METHOD__ . $stage . $range . $type . $subtype . $trend . $negative ) );
        $cached = get_transient( $request_key );
        if ( $cached ) {
            dt_write_log( __METHOD__ . ' cache hit' );
            return $cached;
        }

        global $wpdb;
        $query_for_user_stage = self::$query_for_user_stage;

        $end = time();
        if ( $range < 1 ) {
            $begin = 0;
        } else {
            $begin = strtotime( '-'. $range . ' days' );
            if ( $trend ) {
                $end = $begin;
                $begin = strtotime( '-'. ( $range * 2 ) . ' days' );
            }
        }

        $sql = "
            SELECT COUNT(*)
            FROM
               (
                  $query_for_user_stage
                ) as tb
            JOIN zume_dt_reports r ON r.user_id=tb.user_id AND r.type = '$type' AND r.subtype LIKE '$subtype'
            WHERE tb.stage = $stage
               AND r.timestamp > $begin
              AND r.timestamp < $end
            ";
        $count = $wpdb->get_var( $sql );

        if ( $count < 1 ) {
            return 0;
        }

        if ( $negative ) {
            $stage_total = self::stage_total( $stage, $range, $trend );
            $stage_total = (int) $stage_total;
            $count = (int) $count;
            $count =  $stage_total - $count;
            if ( $count < 1 ) {
                $count = $count * -1;
            }
        }

         if ( empty( $count ) ) {
            $count = 0;
        } else {
            $count = (int) $count;
        }

        set_transient( $request_key, $count, self::$expiration );

        return $count;
    }

    public static function query_stage_by_type_and_subtype_list( $stage, $range, $type, $subtype, $trend = false, $negative = false ) {

        global $wpdb;
        $query_for_user_stage = self::$query_for_user_stage;

        $end = time();
        if ( $range < 1 ) {
            $begin = 0;
        } else {
            $begin = strtotime( '-'. $range . ' days' );
            if ( $trend ) {
                $end = $begin;
                $begin = strtotime( '-'. ( $range * 2 ) . ' days' );
            }
        }

        $sql = "
            SELECT DISTINCT tb.user_id, tb.post_id, pm.meta_value as coaching_contact_id, p.post_title as name, pm1.meta_value as user_email, pm2.meta_value as user_phone, IF( r.id, true, false ) as logged
            FROM
               (
                  $query_for_user_stage
                ) as tb
            LEFT JOIN zume_dt_reports r ON r.user_id=tb.user_id AND r.type = '$type' AND r.subtype LIKE '$subtype'
			JOIN zume_posts p ON p.ID=tb.post_id
			LEFT JOIN zume_postmeta pm ON pm.post_id=tb.post_id AND pm.meta_key = 'coaching_contact_id'
			LEFT JOIN zume_postmeta pm1 ON pm1.post_id=tb.post_id AND pm1.meta_key = 'user_email'
			LEFT JOIN zume_postmeta pm2 ON pm2.post_id=tb.post_id AND pm2.meta_key = 'user_phone'
            WHERE tb.stage = $stage
               AND r.timestamp > $begin
              AND r.timestamp < $end
            ORDER BY p.post_title
            ";
        $list = $wpdb->get_results( $sql, ARRAY_A );

        if ( empty( $list ) ) {
            return [];
        }

        $data_list = [
            'negative' => [],
            'positive' => [],
        ];
        foreach( $list as $row ) {
            if ( $row['logged'] ) {
                $data_list['positive'][] = $row;
            } else {
                $data_list['negative'][] = $row;
            }
        }

        if ( $negative ) {
            return $data_list['negative'];
        } else {
            return $data_list['positive'];
        }
    }

    public static function query_stage_by_type_and_subtype_locations( $stage, $range, $type, $subtype, $trend = false, $negative = false ) {

        global $wpdb;
        $query_for_user_stage = self::$query_for_user_stage;

        $end = time();
        if ( $range < 1 ) {
            $begin = 0;
        } else {
            $begin = strtotime( '-'. $range . ' days' );
            if ( $trend ) {
                $end = $begin;
                $begin = strtotime( '-'. ( $range * 2 ) . ' days' );
            }
        }

        $sql = "
            SELECT DISTINCT tb.user_id, tb.post_id, pm.meta_value as coaching_contact_id, p.post_title as name, pm1.meta_value as user_email, pm2.meta_value as user_phone, IF( r.id, true, false ) as logged
            FROM
               (
                  $query_for_user_stage
                ) as tb
            LEFT JOIN zume_dt_reports r ON r.user_id=tb.user_id AND r.type = '$type' AND r.subtype LIKE '$subtype'
			JOIN zume_posts p ON p.ID=tb.post_id
			LEFT JOIN zume_postmeta pm ON pm.post_id=tb.post_id AND pm.meta_key = 'coaching_contact_id'
			LEFT JOIN zume_postmeta pm1 ON pm1.post_id=tb.post_id AND pm1.meta_key = 'user_email'
			LEFT JOIN zume_postmeta pm2 ON pm2.post_id=tb.post_id AND pm2.meta_key = 'user_phone'
            WHERE tb.stage = $stage
               AND r.timestamp > $begin
              AND r.timestamp < $end
            ORDER BY p.post_title
            ";
        $list = $wpdb->get_results( $sql, ARRAY_A );

        if ( empty( $list ) ) {
            return [];
        }

        $data_list = [
            'negative' => [],
            'positive' => [],
        ];
        foreach( $list as $row ) {
            if ( $row['logged'] ) {
                $data_list['positive'][] = $row;
            } else {
                $data_list['negative'][] = $row;
            }
        }

        if ( $negative ) {
            return $data_list['negative'];
        } else {
            return $data_list['positive'];
        }
    }

    public static function flow( $stage, $flow, $range = -1 ) {
        // object cache
        $request_key = hash( 'md5', serialize( __METHOD__ . $stage . $flow . $range ) );
        $cached = get_transient( $request_key );
        if ( $cached ) {
            dt_write_log( __METHOD__ . ' cache hit' );
            return $cached;
        }

        // flow = in, idle, out
        global $wpdb;
        $query_for_user_stage = self::$query_for_user_stage;

        if ( $range < 1 ) {
            $timestamp = 0;
        } else {
            $timestamp = strtotime( '-'. $range . ' days' );
        }

        if ( $flow === 'idle') {
            $sql = "
            SELECT COUNT(*)
            FROM
               (
                  $query_for_user_stage
                ) as tb
            WHERE tb.stage = $stage AND tb.timestamp < $timestamp;
            ";
        }
        else if ( $flow === 'in') {
            $sql = "
            SELECT COUNT(*)
            FROM
               (
                  $query_for_user_stage
                ) as tb
            WHERE tb.stage = $stage AND tb.timestamp > $timestamp;
            ";
        }
        else if ( $flow === 'out') {
            $next_stage = $stage++;
            $sql = "
            SELECT COUNT(*)
            FROM
               (
                  $query_for_user_stage
                ) as tb
            WHERE tb.stage = $next_stage AND tb.timestamp < $timestamp;
            ";
        }

        $count = $wpdb->get_var( $sql );

        if ( empty( $count ) ) {
            $count = 0;
        } else {
            $count = (int) $count;
        }

        set_transient( $request_key, $count, self::$expiration );

        return $count;
    }
}
