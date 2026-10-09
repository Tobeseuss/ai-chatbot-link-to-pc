<?php
/**
 * مسیرهای REST API پلاگین.
 *
 * Namespace: /wp-json/aclp/v1
 *
 * مسیرهای ایجنت (سیستم کاربر):
 *   POST /agent/register
 *   POST /agent/heartbeat
 *   GET  /agent/commands/pending
 *   POST /agent/commands/{uid}/status
 *   POST /agent/commands/{uid}/result
 *   POST /agent/files            (آپلود فایل از سیستم)
 * مسیرهای چت‌بات:
 *   GET  /ping
 *   GET  /clients
 *   POST /commands
 *   GET  /commands
 *   GET  /commands/{uid}
 *   POST /files                  (آپلود فایل برای ارسال به سیستم)
 * مشترک (کلید + مالکیت):
 *   GET  /files/{file_id}        (دانلود فایل)
 *
 * @package ACLP
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

class ACLP_REST {

        const NS = 'aclp/v1';

        /**
         * ثبت همه مسیرها.
         */
        public static function register_routes() {
                // ---------- عمومی ----------
                register_rest_route( self::NS, '/ping', array(
                        'methods'             => 'GET',
                        'callback'            => array( __CLASS__, 'route_ping' ),
                        'permission_callback' => '__return_true',
                ) );

                // متن آماده معرفی پل به ایجنت (اصل اساسی ۴) — عمومی و بدون نیاز به کلید.
                register_rest_route( self::NS, '/agent-prompt', array(
                        'methods'             => 'GET',
                        'callback'            => array( __CLASS__, 'route_agent_prompt' ),
                        'permission_callback' => '__return_true',
                ) );

                // تنظیمات گیت‌هاب برای ایجنت‌های توسعه‌دهنده (اصل ۳) — نیازمند کلید معتبر.
                register_rest_route( self::NS, '/github-integration', array(
                        'methods'             => 'GET',
                        'callback'            => array( __CLASS__, 'route_github_integration' ),
                        'permission_callback' => '__return_true',
                ) );

                // ---------- ایجنت ----------
                register_rest_route( self::NS, '/agent/register', array(
                        'methods'             => 'POST',
                        'callback'            => array( __CLASS__, 'route_agent_register' ),
                        'permission_callback' => '__return_true',
                ) );
                register_rest_route( self::NS, '/agent/heartbeat', array(
                        'methods'             => 'POST',
                        'callback'            => array( __CLASS__, 'route_agent_heartbeat' ),
                        'permission_callback' => '__return_true',
                ) );
                register_rest_route( self::NS, '/agent/commands/pending', array(
                        'methods'             => 'GET',
                        'callback'            => array( __CLASS__, 'route_agent_pending' ),
                        'permission_callback' => '__return_true',
                ) );
                register_rest_route( self::NS, '/agent/commands/(?P<command_uid>[A-Za-z0-9\-]+)/status', array(
                        'methods'             => 'POST',
                        'callback'            => array( __CLASS__, 'route_agent_status' ),
                        'permission_callback' => '__return_true',
                ) );
                register_rest_route( self::NS, '/agent/commands/(?P<command_uid>[A-Za-z0-9\-]+)/result', array(
                        'methods'             => 'POST',
                        'callback'            => array( __CLASS__, 'route_agent_result' ),
                        'permission_callback' => '__return_true',
                ) );
                register_rest_route( self::NS, '/agent/files', array(
                        'methods'             => 'POST',
                        'callback'            => array( __CLASS__, 'route_agent_upload' ),
                        'permission_callback' => '__return_true',
                ) );

                // ---------- چت‌بات ----------
                register_rest_route( self::NS, '/clients', array(
                        'methods'             => 'GET',
                        'callback'            => array( __CLASS__, 'route_clients' ),
                        'permission_callback' => '__return_true',
                ) );
                register_rest_route( self::NS, '/commands', array(
                        'methods'             => 'POST',
                        'callback'            => array( __CLASS__, 'route_create_command' ),
                        'permission_callback' => '__return_true',
                ) );
                register_rest_route( self::NS, '/commands', array(
                        'methods'             => 'GET',
                        'callback'            => array( __CLASS__, 'route_list_commands' ),
                        'permission_callback' => '__return_true',
                ) );
                register_rest_route( self::NS, '/commands/(?P<command_uid>[A-Za-z0-9\-]+)', array(
                        'methods'             => 'GET',
                        'callback'            => array( __CLASS__, 'route_get_command' ),
                        'permission_callback' => '__return_true',
                ) );
                register_rest_route( self::NS, '/files', array(
                        'methods'             => 'POST',
                        'callback'            => array( __CLASS__, 'route_chatbot_upload' ),
                        'permission_callback' => '__return_true',
                ) );
                register_rest_route( self::NS, '/files/(?P<file_id>\d+)', array(
                        'methods'             => 'GET',
                        'callback'            => array( __CLASS__, 'route_download_file' ),
                        'permission_callback' => '__return_true',
                ) );
        }

        /* ---------------------------------------------------------------------
         * عمومی
         * ------------------------------------------------------------------- */

        public static function route_ping( $request ) {
                $key = ACLP_Auth::authenticate( $request );
                if ( is_wp_error( $key ) ) {
                        return $key;
                }
                $site = untrailingslashit( home_url() );
                return rest_ensure_response( array(
                        'ok'                    => true,
                        'version'               => ACLP_VERSION,
                        'server_time'           => ACLP_Utils::now(),
                        'site'                  => get_bloginfo( 'name' ),
                        'site_url'              => $site,
                        // سایت هر دو پروتکل را پشتیبانی می‌کند؛ اگر HTTPS مشکل داشت، ایجنت با HTTP ادامه دهد.
                        'http_fallback_url'     => preg_replace( '#^https://#i', 'http://', $site ) . '/wp-json/aclp/v1',
                        'allow_http_fallback'   => true,
                        'docs_url'              => untrailingslashit( (string) ACLP_Settings::get( 'github_repo_url' ) ) . '/blob/main/docs/AGENT-API.md',
                ) );
        }

        /**
         * متن آماده معرفی پل به ایجنت هوش مصنوعی (اصل اساسی ۴).
         * عمومی است تا ضعیف‌ترین ایجنت‌ها هم بتوانند با یک GET آن را بگیرند.
         */
        public static function route_agent_prompt( $request ) {
                return rest_ensure_response( array(
                        'ok'        => true,
                        'version'   => ACLP_VERSION,
                        'usage'     => 'Feed this text to any AI/LLM agent so it can use the PC bridge. Replace the API key placeholder with a real key from WordPress admin, or leave it empty so the agent asks the user.',
                        'docs_url'  => untrailingslashit( (string) ACLP_Settings::get( 'github_repo_url' ) ) . '/blob/main/docs/AGENT-API.md',
                        'repo_url'  => untrailingslashit( (string) ACLP_Settings::get( 'github_repo_url' ) ),
                        'prompt'    => ACLP_Utils::agent_prompt(),
                ) );
        }

        /**
         * تنظیمات گیت‌هاب برای ایجنت‌های توسعه‌دهنده (اصل اساسی ۳).
         * PAT باید از پنل مدیریت ذخیره شده باشد؛ در غیر این صورت pat خالی برمی‌گردد
         * و ایجنت باید از کاربر بخواهد آن را در تنظیمات پلاگین وارد کند.
         */
        public static function route_github_integration( $request ) {
                $key = ACLP_Auth::authenticate( $request );
                if ( is_wp_error( $key ) ) {
                        return $key;
                }
                $repo = untrailingslashit( (string) ACLP_Settings::get( 'github_repo_url', '' ) );
                $pat  = (string) ACLP_Settings::get( 'github_pat', '' );
                return rest_ensure_response( array(
                        'ok'                  => true,
                        'repo_url'            => $repo,
                        'pat'                 => $pat,
                        'pat_set'             => '' !== $pat,
                        'pat_required_scopes' => 'Fine-grained PAT with full read/write (Contents, Metadata, Actions, Administration where possible). If the PAT belongs to the repo owner, push to main + publish a Release. Otherwise commit your changes to the main repo as commits so they can be merged (or open a PR if write access is denied).',
                        'workflow_doc'        => ( $repo ? $repo : 'https://github.com/Tobeseuss/ai-chatbot-link-to-pc' ) . '/blob/main/docs/AGENT-CONTRIBUTION.md',
                        'agent_api_doc'       => ( $repo ? $repo : 'https://github.com/Tobeseuss/ai-chatbot-link-to-pc' ) . '/blob/main/docs/AGENT-API.md',
                        'note'                => '' === $pat ? 'GitHub PAT is not configured yet. Ask the user to paste it in WordPress admin -> AI-PC Link -> Settings -> GitHub Integration.' : 'PAT available. Follow workflow_doc step by step. Before pushing, ALWAYS run git fetch and check origin/main for commits from other agents and report them to the user.',
                ) );
        }

        /* ---------------------------------------------------------------------
         * مسیرهای ایجنت
         * ------------------------------------------------------------------- */

        public static function route_agent_register( $request ) {
                $key  = ACLP_Auth::authenticate( $request );
                $body = self::body( $request );
                if ( is_wp_error( $key ) ) {
                        return $key;
                }
                if ( empty( $body['client_uid'] ) ) {
                        return new WP_Error( 'aclp_invalid', 'client_uid الزامی است.', array( 'status' => 400 ) );
                }

                $result = ACLP_Clients::register( $key->id, array(
                        'client_uid'     => $body['client_uid'],
                        'name'           => isset( $body['name'] ) ? $body['name'] : '',
                        'os'             => isset( $body['os'] ) ? $body['os'] : '',
                        'os_version'     => isset( $body['os_version'] ) ? $body['os_version'] : '',
                        'hostname'       => isset( $body['hostname'] ) ? $body['hostname'] : '',
                        'python_version' => isset( $body['python_version'] ) ? $body['python_version'] : '',
                        'agent_version'  => isset( $body['agent_version'] ) ? $body['agent_version'] : '',
                        'capabilities'   => isset( $body['capabilities'] ) ? $body['capabilities'] : array(),
                ) );

                if ( is_wp_error( $result ) ) {
                        return new WP_Error( $result->get_error_code(), $result->get_error_message(), array( 'status' => 409 ) );
                }

                return rest_ensure_response( array(
                        'ok'              => true,
                        'client_id'       => (int) $result['client']->id,
                        'created'         => (bool) $result['created'],
                        'poll_interval'   => (int) ACLP_Settings::get( 'poll_interval', 5 ),
                        'online_timeout'  => (int) ACLP_Settings::get( 'online_timeout', 90 ),
                        'command_timeout' => (int) ACLP_Settings::get( 'command_timeout', 300 ),
                        'server_time'     => ACLP_Utils::now(),
                        'version'         => ACLP_VERSION,
                ) );
        }

        public static function route_agent_heartbeat( $request ) {
                $key    = ACLP_Auth::authenticate( $request );
                if ( is_wp_error( $key ) ) {
                        return $key;
                }
                $client = ACLP_Auth::client_from_request( $request, $key );
                if ( is_wp_error( $client ) ) {
                        return $client;
                }
                return rest_ensure_response( array(
                        'ok'            => true,
                        'pending_count' => ACLP_Commands::pending_count( $client->id ),
                        'poll_interval' => (int) ACLP_Settings::get( 'poll_interval', 5 ),
                        'server_time'   => ACLP_Utils::now(),
                ) );
        }

        public static function route_agent_pending( $request ) {
                $key    = ACLP_Auth::authenticate( $request );
                if ( is_wp_error( $key ) ) {
                        return $key;
                }
                $client = ACLP_Auth::client_from_request( $request, $key );
                if ( is_wp_error( $client ) ) {
                        return $client;
                }

                $limit = (int) $request->get_param( 'limit' );
                $rows  = ACLP_Commands::pending_for_client( $client->id, $limit > 0 ? $limit : 10 );

                $commands = array();
                foreach ( $rows as $row ) {
                        ACLP_Commands::mark_sent( $row );
                        ACLP_Logger::add( 'command_sent', 'فرمان به سیستم ارسال شد: ' . $row->type, array(), (int) $key->id, (int) $client->id, (int) $row->id );

                        // فایل‌هایی که باید این فرمان دانلود کند (to_pc).
                        $files = array();
                        foreach ( ACLP_Files::for_command( $row->id ) as $f ) {
                                if ( 'to_pc' === $f->direction ) {
                                        $files[] = ACLP_Files::api_shape( $f );
                                }
                        }

                        $commands[] = array(
                                'command_uid' => $row->command_uid,
                                'type'        => $row->type,
                                'payload'     => json_decode( (string) $row->payload, true ),
                                'source'      => $row->source,
                                'files'       => $files,
                                'created_at'  => $row->created_at,
                        );
                }

                return rest_ensure_response( array(
                        'ok'            => true,
                        'commands'      => $commands,
                        'poll_interval' => (int) ACLP_Settings::get( 'poll_interval', 5 ),
                        'server_time'   => ACLP_Utils::now(),
                ) );
        }

        public static function route_agent_status( $request ) {
                $key    = ACLP_Auth::authenticate( $request );
                if ( is_wp_error( $key ) ) {
                        return $key;
                }
                $client = ACLP_Auth::client_from_request( $request, $key );
                if ( is_wp_error( $client ) ) {
                        return $client;
                }
                $uid  = $request['command_uid'];
                $body = self::body( $request );
                $status = isset( $body['status'] ) ? sanitize_key( $body['status'] ) : 'running';

                if ( 'running' === $status ) {
                        $ok = ACLP_Commands::mark_running( $uid, $client->id );
                        return rest_ensure_response( array( 'ok' => (bool) $ok ) );
                }
                return rest_ensure_response( array( 'ok' => false, 'message' => 'وضعیت پشتیبانی‌شده: running' ) );
        }

        public static function route_agent_result( $request ) {
                $key    = ACLP_Auth::authenticate( $request );
                if ( is_wp_error( $key ) ) {
                        return $key;
                }
                $client = ACLP_Auth::client_from_request( $request, $key );
                if ( is_wp_error( $client ) ) {
                        return $client;
                }

                $uid  = $request['command_uid'];
                $body = self::body( $request );
                $cmd  = ACLP_Commands::get_by_uid( $uid );
                if ( ! $cmd || (int) $cmd->client_id !== (int) $client->id ) {
                        return new WP_Error( 'aclp_not_found', 'فرمان یافت نشد.', array( 'status' => 404 ) );
                }

                $status = isset( $body['status'] ) ? $body['status'] : 'completed';
                $result = isset( $body['result'] ) ? $body['result'] : null;
                $error  = isset( $body['error'] ) ? $body['error'] : '';
                $dur    = isset( $body['duration_ms'] ) ? (int) $body['duration_ms'] : 0;
                $file_refs = isset( $body['files'] ) && is_array( $body['files'] ) ? $body['files'] : array();

                // اتصال فایل‌های آپلودشده به این فرمان (اگر خود ایجنت ارسالشان نکرده باشد).
                global $wpdb;
                foreach ( $file_refs as $fid ) {
                        $wpdb->update(
                                $wpdb->prefix . 'aclp_files',
                                array( 'command_id' => (int) $cmd->id ),
                                array( 'id' => (int) $fid ),
                                array( '%d' ),
                                array( '%d' )
                        );
                }

                ACLP_Commands::set_result( $uid, $client->id, $status, $result, $error, $dur );
                ACLP_Clients::touch_online( $client->id );

                return rest_ensure_response( array( 'ok' => true ) );
        }

        public static function route_agent_upload( $request ) {
                $key    = ACLP_Auth::authenticate( $request );
                if ( is_wp_error( $key ) ) {
                        return $key;
                }
                $client = ACLP_Auth::client_from_request( $request, $key );
                if ( is_wp_error( $client ) ) {
                        return $client;
                }

                $files = $request->get_file_params();
                if ( empty( $files['file'] ) ) {
                        return new WP_Error( 'aclp_no_file', 'فیلد multipart با نام file الزامی است.', array( 'status' => 400 ) );
                }

                $command_uid = (string) $request->get_param( 'command_uid' );
                $cmd = $command_uid ? ACLP_Commands::get_by_uid( $command_uid ) : null;

                $stored = ACLP_Files::store_upload( $files['file'], array(
                        'command_id' => $cmd ? $cmd->id : 0,
                        'key_id'     => $key->id,
                        'client_id'  => $client->id,
                        'direction'  => 'from_pc',
                ) );
                if ( is_wp_error( $stored ) ) {
                        return new WP_Error( $stored->get_error_code(), $stored->get_error_message(), array( 'status' => 400 ) );
                }

                return rest_ensure_response( array_merge( array( 'ok' => true ), ACLP_Files::api_shape( $stored['file'] ) ) );
        }

        /* ---------------------------------------------------------------------
         * مسیرهای چت‌بات
         * ------------------------------------------------------------------- */

        public static function route_clients( $request ) {
                $key = ACLP_Auth::authenticate( $request );
                if ( is_wp_error( $key ) ) {
                        return $key;
                }
                $clients = array();
                foreach ( ACLP_Clients::for_key( $key->id ) as $c ) {
                        $clients[] = array(
                                'client_uid'   => $c->client_uid,
                                'name'         => $c->name,
                                'os'           => $c->os . ( $c->os_version ? ' ' . $c->os_version : '' ),
                                'hostname'     => $c->hostname,
                                'online'       => ACLP_Utils::client_is_online( $c ),
                                'last_seen_at' => $c->last_seen_at,
                                'commands_total' => (int) $c->commands_total,
                                'registered_at'  => $c->registered_at,
                        );
                }
                return rest_ensure_response( array( 'ok' => true, 'clients' => $clients ) );
        }

        public static function route_create_command( $request ) {
                $key  = ACLP_Auth::authenticate( $request );
                $body = self::body( $request );
                if ( is_wp_error( $key ) ) {
                        return $key;
                }

                $type    = isset( $body['type'] ) ? sanitize_key( $body['type'] ) : '';
                $payload = isset( $body['payload'] ) && is_array( $body['payload'] ) ? $body['payload'] : array();
                $source  = isset( $body['source'] ) ? $body['source'] : 'chatbot';
                $wait    = ! empty( $body['wait'] );
                $timeout = isset( $body['timeout'] ) ? min( 25, max( 1, (int) $body['timeout'] ) ) : 20;
                $broadcast = ! empty( $body['broadcast'] );

                if ( '' === $type ) {
                        return new WP_Error( 'aclp_invalid', 'پارامتر type الزامی است.', array( 'status' => 400 ) );
                }

                // تعیین کلاینت(های) مقصد.
                $targets = array();
                if ( $broadcast ) {
                        foreach ( ACLP_Clients::for_key( $key->id ) as $c ) {
                                if ( ACLP_Utils::client_is_online( $c ) ) {
                                        $targets[] = $c;
                                }
                        }
                        if ( empty( $targets ) ) {
                                return new WP_Error( 'aclp_no_online_clients', 'هیچ سیستمی از این کلید در حال حاضر آنلاین نیست.', array( 'status' => 409 ) );
                        }
                } else {
                        $uid = isset( $body['client_uid'] ) ? trim( (string) $body['client_uid'] ) : '';
                        if ( '' !== $uid ) {
                                $c = ACLP_Clients::get_by_uid( $uid );
                                if ( ! $c || (int) $c->key_id !== (int) $key->id ) {
                                        return new WP_Error( 'aclp_unknown_client', 'سیستم موردنظر برای این کلید یافت نشد.', array( 'status' => 404 ) );
                                }
                                $targets[] = $c;
                        } else {
                                $all = ACLP_Clients::for_key( $key->id );
                                if ( 0 === count( $all ) ) {
                                        return new WP_Error( 'aclp_no_clients', 'هنوز سیستمی به این کلید متصل نشده است. ابتدا ایجنت را روی سیستم خود نصب و اجرا کنید.', array( 'status' => 409 ) );
                                }
                                $online = array_filter( $all, array( 'ACLP_Utils', 'client_is_online' ) );
                                if ( 1 === count( $all ) ) {
                                        $targets[] = reset( $all );
                                } elseif ( 1 === count( $online ) ) {
                                        $targets[] = reset( $online );
                                } else {
                                        $list = array();
                                        foreach ( $all as $c ) {
                                                $list[] = array(
                                                        'client_uid' => $c->client_uid,
                                                        'name'       => $c->name,
                                                        'hostname'   => $c->hostname,
                                                        'online'     => ACLP_Utils::client_is_online( $c ),
                                                );
                                        }
                                        return new WP_Error( 'aclp_select_client', 'چند سیستم به این کلید متصل هستند؛ client_uid مقصد را مشخص کنید یا broadcast=true بفرستید.', array( 'status' => 400, 'clients' => $list ) );
                                }
                        }
                }

                if ( $wait && count( $targets ) > 1 ) {
                        return new WP_Error( 'aclp_invalid', 'گزینه wait فقط برای یک کلاینت مقصد معتبر است.', array( 'status' => 400 ) );
                }

                $group_uid = count( $targets ) > 1 ? ACLP_Utils::uid() : '';
                $created   = array();
                $first_row = null;
                foreach ( $targets as $c ) {
                        $row = ACLP_Commands::create( $key->id, $c->id, $type, $payload, $source, $group_uid );
                        if ( $row ) {
                                $created[] = array(
                                        'command_uid' => $row->command_uid,
                                        'client_uid'  => $c->client_uid,
                                        'status'      => 'pending',
                                );
                                if ( null === $first_row ) {
                                        $first_row = $row;
                                }
                                ACLP_Logger::add( 'command_created', 'فرمان جدید در صف قرار گرفت: ' . $type, array( 'source' => $source ), (int) $key->id, (int) $c->id, (int) $row->id );
                        }
                }

                if ( empty( $created ) ) {
                        return new WP_Error( 'aclp_db_error', 'ایجاد فرمان ناموفق بود.', array( 'status' => 500 ) );
                }

                // حالت انتظار: تا دریافت نتیجه صبر می‌کنیم (long-poll ساده).
                if ( $wait && $first_row ) {
                        $deadline = microtime( true ) + $timeout;
                        while ( microtime( true ) < $deadline ) {
                                usleep( 500000 );
                                $row = ACLP_Commands::get( $first_row->id );
                                if ( $row && in_array( $row->status, array( 'completed', 'failed' ), true ) ) {
                                        return rest_ensure_response( self::command_shape( $row ) );
                                }
                        }
                        $row = ACLP_Commands::get( $first_row->id );
                        return rest_ensure_response( self::command_shape( $row ) );
                }

                return rest_ensure_response( array( 'ok' => true, 'commands' => $created ) );
        }

        public static function route_list_commands( $request ) {
                $key = ACLP_Auth::authenticate( $request );
                if ( is_wp_error( $key ) ) {
                        return $key;
                }
                global $wpdb;
                $t     = $wpdb->prefix . 'aclp_commands';
                $limit = min( 50, max( 1, (int) $request->get_param( 'limit' ) ?: 20 ) );
                $args  = array( $key->id, $limit );
                $rows  = $wpdb->get_results(
                        $wpdb->prepare(
                                "SELECT * FROM {$t} WHERE key_id = %d ORDER BY id DESC LIMIT %d",
                                $args
                        )
                );
                $out = array_map( array( __CLASS__, 'command_shape' ), (array) $rows );
                return rest_ensure_response( array( 'ok' => true, 'commands' => $out ) );
        }

        public static function route_get_command( $request ) {
                $key = ACLP_Auth::authenticate( $request );
                if ( is_wp_error( $key ) ) {
                        return $key;
                }
                $row = ACLP_Commands::get_by_uid( $request['command_uid'] );
                if ( ! $row || (int) $row->key_id !== (int) $key->id ) {
                        return new WP_Error( 'aclp_not_found', 'فرمان یافت نشد.', array( 'status' => 404 ) );
                }
                return rest_ensure_response( self::command_shape( $row ) );
        }

        public static function route_chatbot_upload( $request ) {
                $key = ACLP_Auth::authenticate( $request );
                if ( is_wp_error( $key ) ) {
                        return $key;
                }
                $files = $request->get_file_params();
                if ( empty( $files['file'] ) ) {
                        return new WP_Error( 'aclp_no_file', 'فیلد multipart با نام file الزامی است.', array( 'status' => 400 ) );
                }
                $command_uid = (string) $request->get_param( 'command_uid' );
                $cmd = $command_uid ? ACLP_Commands::get_by_uid( $command_uid ) : null;

                $stored = ACLP_Files::store_upload( $files['file'], array(
                        'command_id' => $cmd ? $cmd->id : 0,
                        'key_id'     => $key->id,
                        'direction'  => 'to_pc',
                ) );
                if ( is_wp_error( $stored ) ) {
                        return new WP_Error( $stored->get_error_code(), $stored->get_error_message(), array( 'status' => 400 ) );
                }
                return rest_ensure_response( array_merge( array( 'ok' => true ), ACLP_Files::api_shape( $stored['file'] ) ) );
        }

        public static function route_download_file( $request ) {
                $key = ACLP_Auth::authenticate( $request );
                if ( is_wp_error( $key ) ) {
                        return $key;
                }
                $file = ACLP_Files::get( (int) $request['file_id'] );
                if ( ! $file || (int) $file->key_id !== (int) $key->id ) {
                        return new WP_Error( 'aclp_not_found', 'فایل یافت نشد.', array( 'status' => 404 ) );
                }
                if ( empty( $file->stored_path ) || ! file_exists( $file->stored_path ) ) {
                        return new WP_Error( 'aclp_file_expired', 'فایل منقضی یا حذف‌شده است.', array( 'status' => 410 ) );
                }

                nocache_headers();
                header( 'Content-Type: application/octet-stream' );
                header( 'Content-Disposition: attachment; filename="' . rawurlencode( $file->original_name ) . '"' );
                header( 'Content-Length: ' . filesize( $file->stored_path ) );
                header( 'X-Content-Type-Options: nosniff' );
                readfile( $file->stored_path );
                exit;
        }

        /* ---------------------------------------------------------------------
         * ابزارهای داخلی
         * ------------------------------------------------------------------- */

        /**
         * پارس بدنه JSON یا پارامترهای فرم.
         *
         * @param WP_REST_Request $request درخواست.
         * @return array
         */
        public static function body( $request ) {
                $json = $request->get_json_params();
                if ( is_array( $json ) ) {
                        return $json;
                }
                $body = $request->get_body_params();
                if ( is_array( $body ) && $body ) {
                        return $body;
                }
                $raw = $request->get_body();
                if ( is_string( $raw ) && '' !== trim( $raw ) ) {
                        $decoded = json_decode( $raw, true );
                        if ( is_array( $decoded ) ) {
                                return $decoded;
                        }
                }
                return array();
        }

        /**
         * شکل استاندارد فرمان در پاسخ‌ها.
         *
         * @param object $row ردیف فرمان.
         * @return array
         */
        public static function command_shape( $row ) {
                $client = $row ? ACLP_Clients::get( $row->client_id ) : null;
                $files  = array();
                foreach ( (array) ACLP_Files::for_command( $row->id ) as $f ) {
                        $files[] = array_merge( ACLP_Files::api_shape( $f ), array( 'direction' => $f->direction ) );
                }
                return array(
                        'command_uid'  => $row->command_uid,
                        'type'         => $row->type,
                        'status'       => $row->status,
                        'payload'      => json_decode( (string) $row->payload, true ),
                        'result'       => json_decode( (string) $row->result, true ),
                        'error'        => $row->error,
                        'duration_ms'  => (int) $row->duration_ms,
                        'source'       => $row->source,
                        'client'       => $client ? array( 'client_uid' => $client->client_uid, 'name' => $client->name, 'hostname' => $client->hostname ) : null,
                        'files'        => $files,
                        'created_at'   => $row->created_at,
                        'sent_at'      => $row->sent_at,
                        'completed_at' => $row->completed_at,
                );
        }
}
