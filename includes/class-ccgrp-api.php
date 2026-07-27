<?php
/**
 * CCGRP_API — Planning Center Groups (v2) client.
 *
 * PCO speaks JSON:API, so every response is { data: [...], included: [...], links, meta }.
 * Relationships are id-references into `included`, which is why `flatten()` exists:
 * callers should never have to walk the include graph themselves.
 *
 * Attribute names are read defensively (null-coalesced). Verify the real shape for a
 * given account with Settings → PCO Groups → API Inspector rather than trusting memory —
 * PCO versions its API by date and fields do move.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class CCGRP_API {

    const BASE_URL = 'https://api.planningcenteronline.com/groups/v2';

    /** Hard ceiling so a runaway/huge account can't hang a page render. */
    const MAX_PAGES = 20;

    private $app_id;
    private $secret;

    public function __construct() {
        $this->app_id = get_option( 'ccgrp_app_id', '' );
        $this->secret = get_option( 'ccgrp_secret', '' );
    }

    public function is_configured() {
        return ! empty( $this->app_id ) && ! empty( $this->secret );
    }

    /**
     * Authenticated GET. Returns the decoded body, or WP_Error (status in error data).
     */
    public function get( $endpoint, $params = array() ) {
        if ( ! $this->is_configured() ) {
            return new WP_Error(
                'ccgrp_not_configured',
                __( 'Planning Center API credentials are not configured.', 'comms-church-groups' )
            );
        }

        $url = add_query_arg( $params, self::BASE_URL . $endpoint );

        $response = wp_remote_get( $url, array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode( $this->app_id . ':' . $this->secret ),
                'Content-Type'  => 'application/json',
            ),
            'timeout' => 15,
        ) );

        if ( is_wp_error( $response ) ) return $response;

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( 200 !== $code ) {
            $msg = $body['errors'][0]['detail'] ?? $body['errors'][0]['title'] ?? __( 'Unknown API error.', 'comms-church-groups' );
            /* 401/403 are almost always bad credentials or a token missing the Groups scope —
               say so plainly, since the raw PCO message is unhelpful. */
            if ( in_array( $code, array( 401, 403 ), true ) ) {
                $msg = __( 'Planning Center rejected the credentials (check the App ID / Secret, and that the token has access to Groups).', 'comms-church-groups' );
            }
            return new WP_Error( 'ccgrp_api_error', $msg, array( 'status' => $code ) );
        }

        return $body;
    }

    /**
     * GET that follows `links.next` until exhausted, merging `data` and `included`.
     * PCO caps per_page at 100.
     */
    public function get_all( $endpoint, $params = array() ) {
        $params = wp_parse_args( $params, array( 'per_page' => 100 ) );
        $offset = 0;
        $data     = array();
        $included = array();

        for ( $page = 0; $page < self::MAX_PAGES; $page++ ) {
            $params['offset'] = $offset;
            $body = $this->get( $endpoint, $params );
            if ( is_wp_error( $body ) ) {
                /* Partial success beats a hard failure on page 3 of 5 — return what we have. */
                if ( $data ) break;
                return $body;
            }

            $data     = array_merge( $data, $body['data'] ?? array() );
            $included = array_merge( $included, $body['included'] ?? array() );

            if ( empty( $body['links']['next'] ) ) break;
            $offset += $params['per_page'];
        }

        return array( 'data' => $data, 'included' => $included );
    }

    // ---- Endpoints ----------------------------------------------------------

    /**
     * Groups list. `$args` passes through to PCO (filter, where[...], order, include).
     */
    public function get_groups( $args = array() ) {
        return $this->get_all( '/groups', wp_parse_args( $args, array(
            'include' => 'location,group_type',
            'order'   => 'name',
        ) ) );
    }

    public function get_group( $id, $include = array( 'location', 'group_type' ) ) {
        $params = array();
        if ( $include ) $params['include'] = implode( ',', $include );
        return $this->get( '/groups/' . rawurlencode( $id ), $params );
    }

    public function get_group_types() {
        return $this->get_all( '/group_types', array( 'order' => 'position' ) );
    }

    public function get_group_events( $group_id, $args = array() ) {
        return $this->get_all( '/groups/' . rawurlencode( $group_id ) . '/events',
            wp_parse_args( $args, array( 'order' => 'starts_at' ) ) );
    }

    /** Cheap authenticated call used by the settings page to validate credentials. */
    public function test_connection() {
        $res = $this->get( '/groups', array( 'per_page' => 1 ) );
        if ( is_wp_error( $res ) ) return $res;
        return array( 'ok' => true, 'total' => $res['meta']['total_count'] ?? null );
    }

    // ---- Shaping ------------------------------------------------------------

    /**
     * Turn one JSON:API resource + the response's `included` array into a flat array
     * the renderer can use without knowing anything about JSON:API.
     *
     * Unknown/absent attributes come back as null or '' rather than throwing, so a
     * PCO schema change degrades the card instead of fataling the page.
     */
    public static function flatten( $resource, $included = array() ) {
        if ( empty( $resource['id'] ) ) return null;

        $a = $resource['attributes'] ?? array();
        $r = $resource['relationships'] ?? array();

        $lookup = function ( $type, $id ) use ( $included ) {
            foreach ( $included as $inc ) {
                if ( ( $inc['type'] ?? '' ) === $type && (string) ( $inc['id'] ?? '' ) === (string) $id ) {
                    return $inc;
                }
            }
            return null;
        };

        $rel_id = function ( $key ) use ( $r ) {
            return $r[ $key ]['data']['id'] ?? null;
        };

        $location = null;
        if ( $loc_id = $rel_id( 'location' ) ) {
            $loc = $lookup( 'Location', $loc_id );
            if ( $loc ) {
                $la = $loc['attributes'] ?? array();
                $location = array(
                    'id'        => $loc['id'],
                    'name'      => $la['name'] ?? '',
                    /* PCO hides exact addresses when a group is set to "approximate" —
                       respect that and fall back to the fuzzy string. */
                    'address'   => $la['full_formatted_address'] ?? ( $la['approximate_address'] ?? '' ),
                    'latitude'  => $la['latitude'] ?? null,
                    'longitude' => $la['longitude'] ?? null,
                );
            }
        }

        $group_type = null;
        if ( $gt_id = $rel_id( 'group_type' ) ) {
            $gt = $lookup( 'GroupType', $gt_id );
            if ( $gt ) {
                $group_type = array(
                    'id'   => $gt['id'],
                    'name' => $gt['attributes']['name'] ?? '',
                );
            }
        }

        /* header_image is an object of size variants; pick the largest available. */
        $image = '';
        $img   = $a['header_image'] ?? array();
        if ( is_array( $img ) ) {
            foreach ( array( 'original', 'large', 'medium', 'thumbnail' ) as $size ) {
                if ( ! empty( $img[ $size ] ) ) { $image = $img[ $size ]; break; }
            }
        } elseif ( is_string( $img ) ) {
            $image = $img;
        }

        return array(
            'id'            => (string) $resource['id'],
            'name'          => $a['name'] ?? '',
            'description'   => $a['description'] ?? '',
            'schedule'      => $a['schedule'] ?? '',
            'image'         => $image,
            'contact_email' => $a['contact_email'] ?? '',
            'members'       => $a['memberships_count'] ?? null,
            'enrollment'    => $a['enrollment'] ?? ( $a['enrollment_strategy'] ?? '' ),
            'signup_url'    => $a['public_church_center_web_url'] ?? '',
            'location'      => $location,
            'group_type'    => $group_type,
            'raw'           => $a,
        );
    }

    /** flatten() across a full list response. */
    public static function flatten_all( $body ) {
        $included = $body['included'] ?? array();
        $out = array();
        foreach ( $body['data'] ?? array() as $row ) {
            $flat = self::flatten( $row, $included );
            if ( $flat ) $out[] = $flat;
        }
        return $out;
    }
}
