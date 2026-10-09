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
 * دروازه URL (اصل اساسی ۵ — v2.0.0؛ تعامل فقط با «باز کردن آدرس»):
 *   GET  /url/run                (دستور در انتهای URL؛ نتیجه در همان صفحه — text/plain)
 *   GET  /url/result             (بررسی نتیجه با ticket پس از چند ثانیه)
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

                // ---------- گفتگو (کاربر <-> هوش مصنوعی از طریق ایجنت) — v1.3.0 ----------
                register_rest_route( self::NS, '/chat/send', array(
                        'methods'             => 'POST',
                        'callback'            => array( __CLASS__, 'route_chat_send' ),
                        'permission_callback' => '__return_true',
                ) );
                register_rest_route( self::NS, '/chat/pending', array(
                        'methods'             => 'GET',
                        'callback'            => array( __CLASS__, 'route_chat_pending' ),
                        'permission_callback' => '__return_true',
                ) );
                register_rest_route( self::NS, '/chat/reply', array(
                        'methods'             => 'POST',
                        'callback'            => array( __CLASS__, 'route_chat_reply' ),
                        'permission_callback' => '__return_true',
                ) );
                register_rest_route( self::NS, '/chat/replies', array(
                        'methods'             => 'GET',
                        'callback'            => array( __CLASS__, 'route_chat_replies' ),
                        'permission_callback' => '__return_true',
                ) );

                // ---------- دروازه URL (اصل اساسی ۵ — v2.0.0) ----------
                // برای ایجنت‌هایی که «امکان تعامل با API ندارند» ولی می‌توانند یک آدرس وب را
                // باز و مرور کنند: دستور در انتهای URL قرار می‌گیرد و نتیجه در همان صفحه
                // (text/plain) نمایش داده می‌شود. با &format=json پاسخ JSON استاندارد است.
                register_rest_route( self::NS, '/url/run', array(
                        'methods'             => 'GET',
                        'callback'            => array( __CLASS__, 'route_url_run' ),
                        'permission_callback' => '__return_true',
                ) );
                register_rest_route( self::NS, '/url/result', array(
                        'methods'             => 'GET',
                        'callback'            => array( __CLASS__, 'route_url_result' ),
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
                        'chat_supported'        => version_compare( ACLP_VERSION, '1.3.0', '>=' ),
                        // دروازه URL (اصل اساسی ۵) — تعامل فقط با باز کردن آدرس (v2.0.0).
                        'url_gate'              => version_compare( ACLP_VERSION, '2.0.0', '>=' ),
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
         * پارامتر اختیاری api_key: کلید واقعی را داخل متن جای‌گذاری می‌کند.
         */
        public static function route_agent_prompt( $request ) {
                $api_key = (string) $request->get_param( 'api_key' );
                if ( '' !== $api_key ) {
                        $check = ACLP_API_Keys::get_by_plain( $api_key );
                        if ( ! $check ) {
                                return new WP_Error( 'aclp_invalid_key', 'کلید API نامعتبر است.', array( 'status' => 401 ) );
                        }
                }
                return rest_ensure_response( array(
                        'ok'        => true,
                        'version'   => ACLP_VERSION,
                        'usage'     => 'Feed this text to any AI/LLM agent so it can use the PC bridge. Pass ?api_key=... to embed a real key, or leave it out so the agent asks the user.',
                        'docs_url'  => untrailingslashit( (string) ACLP_Settings::get( 'github_repo_url' ) ) . '/blob/main/docs/AGENT-API.md',
                        'repo_url'  => untrailingslashit( (string) ACLP_Settings::get( 'github_repo_url' ) ),
                        'prompt'    => ACLP_Utils::agent_prompt( '', $api_key ),
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
                        'ai_model'       => isset( $body['ai_model'] ) ? $body['ai_model'] : '',
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
                        $caps = json_decode( (string) $c->capabilities, true );
                        $clients[] = array(
                                'client_uid'     => $c->client_uid,
                                'name'           => $c->name,
                                'os'             => $c->os . ( $c->os_version ? ' ' . $c->os_version : '' ),
                                'hostname'       => $c->hostname,
                                'ip'             => $c->ip,
                                'online'         => ACLP_Utils::client_is_online( $c ),
                                'last_seen_at'   => $c->last_seen_at,
                                'commands_total' => (int) $c->commands_total,
                                'registered_at'  => $c->registered_at,
                                'agent_version'  => $c->agent_version,
                                'python_version' => $c->python_version,
                                'ai_model'       => $c->ai_model,
                                'capabilities'   => is_array( $caps ) ? $caps : array(),
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
                $key  = ACLP_Auth::authenticate( $request );
                $file = ACLP_Files::get( (int) $request['file_id'] );

                $authorized = false;
                if ( ! is_wp_error( $key ) && $file && (int) $file->key_id === (int) $key->id ) {
                        $authorized = true;
                } elseif ( $file && hash_equals( ACLP_Files::sign( (int) $file->id ), (string) $request->get_param( 'aclp_token' ) ) ) {
                        // لینک دانلود امضاشده — برای هوش مصنوعی‌هایی که هدر کلید را نمی‌توانند بفرستند.
                        $authorized = true;
                }
                if ( ! $authorized ) {
                        if ( is_wp_error( $key ) ) {
                                return $key;
                        }
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
         * گفتگو: کاربر <-> هوش مصنوعی (v1.3.0)
         * ------------------------------------------------------------------- */

        /**
         * ایجنت: ارسال پیام کاربر (+ فایل‌های آپلودشده) به هوش مصنوعی.
         * احراز هویت: کلید + client_uid.
         */
        public static function route_chat_send( $request ) {
                $key    = ACLP_Auth::authenticate( $request );
                if ( is_wp_error( $key ) ) {
                        return $key;
                }
                $client = ACLP_Auth::client_from_request( $request, $key );
                if ( is_wp_error( $client ) ) {
                        return $client;
                }
                $body = self::body( $request );
                $text = trim( (string) ( $body['text'] ?? $body['message'] ?? '' ) );
                if ( '' === $text ) {
                        return new WP_Error( 'aclp_invalid', 'متن پیام (text) الزامی است.', array( 'status' => 400 ) );
                }

                // فایل‌های پیوست: فقط فایل‌های متعلق به همین کلید و جهت from_pc.
                $files_out = array();
                global $wpdb;
                foreach ( (array) ( $body['files'] ?? array() ) as $f ) {
                        $fid = is_array( $f ) ? (int) ( $f['file_id'] ?? 0 ) : (int) $f;
                        if ( $fid <= 0 ) {
                                continue;
                        }
                        $row = $wpdb->get_row( $wpdb->prepare(
                                "SELECT id, original_name, size, direction FROM {$wpdb->prefix}aclp_files WHERE id = %d AND key_id = %d",
                                $fid, (int) $key->id
                        ) );
                        if ( $row && 'from_pc' === $row->direction ) {
                                $files_out[] = array(
                                        'file_id'  => (int) $row->id,
                                        'url'      => ACLP_Files::download_url( (int) $row->id ),
                                        'filename' => $row->original_name,
                                        'size'     => (int) $row->size,
                                );
                        }
                }

                $msg_id = ACLP_Chat::add( array(
                        'key_id'    => (int) $key->id,
                        'client_id' => (int) $client->id,
                        'direction' => 'to_ai',
                        'body'      => $text,
                        'files'     => $files_out,
                        'source'    => 'user:' . ( $client->name ?: $client->hostname ),
                        'ip'        => $_SERVER['REMOTE_ADDR'] ?? '',
                ) );
                if ( ! $msg_id ) {
                        return new WP_Error( 'aclp_db_error', 'ثبت پیام ناموفق بود.', array( 'status' => 500 ) );
                }
                ACLP_Logger::add( 'chat_sent', 'پیام کاربر به هوش مصنوعی ارسال شد', array( 'files' => count( $files_out ) ), (int) $key->id, (int) $client->id );
                ACLP_Clients::touch_online( $client->id );

                return rest_ensure_response( array(
                        'ok'         => true,
                        'message_id' => $msg_id,
                        'files'      => $files_out,
                ) );
        }

        /**
         * هوش مصنوعی: دریافت پیام‌های جدید کاربر (long-poll اختیاری با wait).
         * احراز هویت: فقط کلید.
         */
        public static function route_chat_pending( $request ) {
                $key = ACLP_Auth::authenticate( $request );
                if ( is_wp_error( $key ) ) {
                        return $key;
                }
                $wait     = min( 25, max( 0, (int) $request->get_param( 'wait' ) ) );
                $deadline = microtime( true ) + $wait;

                do {
                        $rows = ACLP_Chat::pending_for_key( $key->id, 50 );
                        if ( $rows ) {
                                break;
                        }
                        if ( $wait <= 0 || microtime( true ) >= $deadline ) {
                                break;
                        }
                        sleep( 1 );
                } while ( microtime( true ) < $deadline );

                $messages = array();
                $ids      = array();
                foreach ( $rows as $row ) {
                        $client = $row->client_id ? ACLP_Clients::get( $row->client_id ) : null;
                        $shape  = ACLP_Chat::api_shape( $row );
                        $shape['client'] = $client ? array(
                                'client_uid' => $client->client_uid,
                                'name'       => $client->name,
                                'hostname'   => $client->hostname,
                        ) : null;
                        $messages[] = $shape;
                        $ids[]      = (int) $row->id;
                }
                if ( $ids ) {
                        ACLP_Chat::mark_delivered( $ids );
                }
                if ( $ids ) {
                        ACLP_Logger::add( 'chat_delivered', 'پیام(های) کاربر به هوش مصنوعی تحویل شد', array( 'count' => count( $ids ) ), (int) $key->id );
                }

                return rest_ensure_response( array(
                        'ok'       => true,
                        'messages' => $messages,
                        'count'    => count( $messages ),
                        'server_time' => ACLP_Utils::now(),
                ) );
        }

        /**
         * هوش مصنوعی: پاسخ به پیام کاربر.
         * احراز هویت: فقط کلید. client_uid اختیاری برای اتصال پاسخ به یک سیستم مشخص.
         */
        public static function route_chat_reply( $request ) {
                $key  = ACLP_Auth::authenticate( $request );
                if ( is_wp_error( $key ) ) {
                        return $key;
                }
                $body = self::body( $request );
                $text = trim( (string) ( $body['text'] ?? $body['message'] ?? '' ) );
                if ( '' === $text ) {
                        return new WP_Error( 'aclp_invalid', 'متن پاسخ (text) الزامی است.', array( 'status' => 400 ) );
                }
                $client_id = 0;
                $uid = trim( (string) ( $body['client_uid'] ?? '' ) );
                if ( '' !== $uid ) {
                        $c = ACLP_Clients::get_by_uid( $uid );
                        if ( $c && (int) $c->key_id === (int) $key->id ) {
                                $client_id = (int) $c->id;
                        }
                }
                $source = trim( (string) ( $body['source'] ?? '' ) );
                if ( '' === $source ) {
                        $source = 'ai';
                }

                $msg_id = ACLP_Chat::add( array(
                        'key_id'    => (int) $key->id,
                        'client_id' => $client_id,
                        'direction' => 'from_ai',
                        'body'      => $text,
                        'files'     => (array) ( $body['files'] ?? array() ),
                        'source'    => $source,
                        'ip'        => $_SERVER['REMOTE_ADDR'] ?? '',
                ) );
                if ( ! $msg_id ) {
                        return new WP_Error( 'aclp_db_error', 'ثبت پاسخ ناموفق بود.', array( 'status' => 500 ) );
                }
                ACLP_Logger::add( 'chat_reply', 'پاسخ هوش مصنوعی ثبت شد', array( 'source' => $source ), (int) $key->id, $client_id );

                return rest_ensure_response( array( 'ok' => true, 'message_id' => $msg_id ) );
        }

        /**
         * ایجنت: دریافت پاسخ‌های هوش مصنوعی (since برای polling).
         * احراز هویت: کلید + client_uid.
         */
        public static function route_chat_replies( $request ) {
                $key    = ACLP_Auth::authenticate( $request );
                if ( is_wp_error( $key ) ) {
                        return $key;
                }
                $client = ACLP_Auth::client_from_request( $request, $key );
                if ( is_wp_error( $client ) ) {
                        return $client;
                }
                $since = (int) $request->get_param( 'since' );
                $limit = (int) $request->get_param( 'limit' );
                $order = (string) $request->get_param( 'order' );
                $rows  = ACLP_Chat::replies_for_client( $key->id, $client->id, $since, $limit > 0 ? $limit : 50, $order );

                $messages = array();
                foreach ( $rows as $row ) {
                        $messages[] = ACLP_Chat::api_shape( $row );
                }
                ACLP_Clients::touch_online( $client->id );

                return rest_ensure_response( array(
                        'ok'       => true,
                        'messages' => $messages,
                        'server_time' => ACLP_Utils::now(),
                ) );
        }

        /* ---------------------------------------------------------------------
         * دروازه URL (اصل اساسی ۵ — v2.0.0)
         *
         * خواسته مالک: «هوش‌های مصنوعی اعلام می‌کنند امکان تعامل با API را ندارند ولی
         * می‌توانند یک آدرس وب را باز و مرور کنند» — پس حالت سوم تعامل: دستور در انتهای
         * URL قرار می‌گیرد، آدرس باز می‌شود و نتیجه در همان صفحه دیده و پردازش می‌شود.
         * لحن پاسخ‌ها عمداً خنثی و انگلیسی است (مخاطب: ایجنت‌های هوش مصنوعی).
         * ------------------------------------------------------------------- */

        /**
         * احراز هویت دروازه URL: کلید از پارامتر key (یا api_key).
         *
         * @param WP_REST_Request $request درخواست.
         * @return array{0: object|WP_Error, 1: string} [کلید یا خطا, کلید خام]
         */
        private static function url_gate_auth( $request ) {
                $raw = trim( (string) $request->get_param( 'key' ) );
                if ( '' === $raw ) {
                        $raw = trim( (string) $request->get_param( 'api_key' ) );
                }
                if ( '' === $raw ) {
                        return array(
                                new WP_Error( 'aclp_missing_key', 'API key missing — add &key=aclp_live_... to the URL.', array( 'status' => 401 ) ),
                                '',
                        );
                }
                // احراز هویت استاندارد (شامل محدودیت نرخ + touch) روی همان کلید.
                $request->set_param( 'api_key', $raw );
                return array( ACLP_Auth::authenticate( $request ), $raw );
        }

        /**
         * خروجی text/plain صفحه دروازه URL (خروجی مستقیم — بدون پوشش JSON وردپرس).
         *
         * @param string $text متن صفحه.
         */
        private static function url_gate_text( $text ) {
                nocache_headers();
                header( 'Content-Type: text/plain; charset=utf-8' );
                header( 'X-Content-Type-Options: nosniff' );
                echo $text;
                exit;
        }

        /**
         * خطای دروازه URL: در حالت json به‌شکل WP_Error استاندارد، در حالت text صفحه خطا.
         *
         * @param string $code   کد خطا.
         * @param string $en     پیام انگلیسی (مخاطب: ایجنت).
         * @param int    $status وضعیت HTTP.
         * @param string $format text|json.
         * @return WP_Error|null (در حالت text خروجی چاپ و exit می‌شود).
         */
        private static function url_gate_error( $code, $en, $status, $format ) {
                if ( 'json' === $format ) {
                        return new WP_Error( $code, $en, array( 'status' => $status ) );
                }
                self::url_gate_text(
                        "ACLP URL GATE — ERROR\n" .
                        str_repeat( '-', 62 ) . "\n" .
                        'STATUS:  error (' . (int) $status . ')' . "\n" .
                        'CODE:    ' . $code . "\n" .
                        'REASON:  ' . $en . "\n"
                );
                return null;
        }

        /**
         * ترجمه خطاهای احراز هویت استاندارد (پیام فارسی) به پیام انگلیسی دروازه URL.
         *
         * @param WP_Error $err    خطا.
         * @param string   $format text|json.
         * @return WP_Error|null
         */
        private static function url_gate_error_from_wp_error( $err, $format ) {
                $code   = $err->get_error_code();
                $status = 401;
                $data   = $err->get_error_data();
                if ( is_array( $data ) && isset( $data['status'] ) ) {
                        $status = (int) $data['status'];
                }
                $map = array(
                        'aclp_missing_key'  => 'API key missing — add &key=aclp_live_... to the URL.',
                        'aclp_invalid_key'  => 'invalid API key — copy the full key from WordPress admin -> AI-PC Link -> API Keys.',
                        'aclp_key_inactive' => 'this API key is deactivated — ask the operator to enable it.',
                        'aclp_rate_limited' => 'rate limit reached — wait about a minute and re-open this URL.',
                );
                $msg = isset( $map[ $code ] ) ? $map[ $code ] : $err->get_error_message();
                return self::url_gate_error( $code, $msg, $status, $format );
        }

        /**
         * نگاشت پارامتر cmd به کلید اصلی payload هر نوع فرمان.
         * (shell→command، run_python→code، open_url/http_request→url)
         *
         * @param string $type نوع فرمان.
         * @param string $cmd  متن دستور از URL.
         * @return array
         */
        private static function url_gate_cmd_payload( $type, $cmd ) {
                switch ( $type ) {
                        case 'run_python':
                                return array( 'code' => $cmd );
                        case 'open_url':
                        case 'http_request':
                                return array( 'url' => $cmd );
                        default:
                                return array( 'command' => $cmd );
                }
        }

        /**
         * متن راهنمای دروازه URL (وقتی هیچ cmd/type/payload داده نشده باشد).
         *
         * @return string
         */
        private static function url_gate_usage() {
                $site = untrailingslashit( home_url() );
                $repo = untrailingslashit( (string) ACLP_Settings::get( 'github_repo_url', 'https://github.com/Tobeseuss/ai-chatbot-link-to-pc' ) );
                return "ACLP URL GATE — usage (ACLP Bridge v" . ACLP_VERSION . ")\n" .
                        str_repeat( '=', 62 ) . "\n\n" .
                        "Run a shell command and read the result from the opened page:\n" .
                        "  " . $site . "/wp-json/aclp/v1/url/run?key=YOUR_API_KEY&cmd=echo%20hello&wait=15\n\n" .
                        "Non-shell job (no cmd — type only):\n" .
                        "  " . $site . "/wp-json/aclp/v1/url/run?key=YOUR_API_KEY&type=sysinfo&wait=15\n\n" .
                        "Fetch a previous result by ticket:\n" .
                        "  " . $site . "/wp-json/aclp/v1/url/result?key=YOUR_API_KEY&ticket=JOB_UID\n\n" .
                        "Parameters:\n" .
                        "  key      (required) API key — aclp_live_...\n" .
                        "  cmd      command text, URL-encoded (spaces = %20, & = %26, quotes = %22)\n" .
                        "  type     job type (default shell) — e.g. sysinfo, screenshot, process_list\n" .
                        "  payload  one line of URL-encoded JSON for advanced jobs\n" .
                        "  client   client_uid — required only when several nodes share this key\n" .
                        "  wait     0-25 seconds the page keeps collecting the result (default 15)\n" .
                        "  format   text (default) | json\n\n" .
                        "Full API reference: " . $repo . "/blob/main/docs/AGENT-API.md\n";
        }

        /**
         * گزارش فرمان در قالب صفحه متنی (یا JSON استاندارد با command_shape).
         *
         * @param object $row     ردیف فرمان.
         * @param string $format  text|json.
         * @param string $raw_key کلید خام (برای ساخت لینک نتیجه در حالت pending).
         * @return WP_REST_Response|null (در حالت text خروجی چاپ و exit می‌شود)
         */
        private static function url_gate_report( $row, $format, $raw_key ) {
                if ( 'json' === $format ) {
                        return rest_ensure_response( self::command_shape( $row ) );
                }

                $lines   = array();
                $lines[] = 'ACLP URL GATE — job report (ACLP Bridge v' . ACLP_VERSION . ')';
                $lines[] = str_repeat( '-', 62 );
                $lines[] = 'JOB:      ' . $row->command_uid . ' (' . $row->type . ')';
                $lines[] = 'STATUS:   ' . $row->status;

                if ( in_array( $row->status, array( 'completed', 'failed' ), true ) ) {
                        $lines[] = 'DURATION: ' . (int) $row->duration_ms . ' ms';
                        $err = trim( (string) $row->error );
                        if ( '' !== $err ) {
                                $lines[] = 'ERROR:    ' . $err;
                        }
                        $lines[] = '';
                        $result = json_decode( (string) $row->result, true );
                        $lines[] = 'RESULT:';
                        $lines[] = ( null === $result || '' === (string) $row->result )
                                ? '(empty)'
                                : wp_json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
                } else {
                        $lines[] = '';
                        $lines[] = 'The job is still being processed (' . $row->status . ').';
                        $lines[] = 'Open this URL in about 5 seconds to read the result:';
                        $lines[] = untrailingslashit( home_url() ) . '/wp-json/aclp/v1/url/result?key=' . rawurlencode( $raw_key ) . '&ticket=' . rawurlencode( $row->command_uid );
                }

                $files = array();
                foreach ( (array) ACLP_Files::for_command( $row->id ) as $f ) {
                        $files[] = ACLP_Files::api_shape( $f );
                }
                $lines[] = '';
                if ( $files ) {
                        $lines[] = 'FILES (signed links — open or download without any header):';
                        foreach ( $files as $f ) {
                                $link = ! empty( $f['download_url'] ) ? $f['download_url'] : $f['url'];
                                $lines[] = '  - ' . $f['filename'] . ' (' . ACLP_Utils::human_size( $f['size'] ) . '): ' . $link;
                        }
                } else {
                        $lines[] = 'FILES: (none)';
                }

                self::url_gate_text( implode( "\n", $lines ) . "\n" );
                return null;
        }

        /**
         * GET /url/run — ثبت فرمان از طریق خودِ URL + انتظار تا ۲۵ ثانیه + نمایش نتیجه
         * در همان صفحه بازشده (اصل اساسی ۵).
         *
         * پارامترها: key (یا api_key) | cmd | type | payload | client (یا client_uid) |
         * wait (0-25، پیش‌فرض ۱۵) | format (text|json) | source (اختیاری).
         *
         * @param WP_REST_Request $request درخواست.
         * @return WP_REST_Response|WP_Error|null
         */
        public static function route_url_run( $request ) {
                $format = ( 'json' === strtolower( trim( (string) $request->get_param( 'format' ) ) ) ) ? 'json' : 'text';

                $cmd         = trim( (string) $request->get_param( 'cmd' ) );
                $type        = sanitize_key( (string) $request->get_param( 'type' ) );
                $payload_raw = trim( (string) $request->get_param( 'payload' ) );

                // راهنما: هیچ دستوری داده نشده — صفحه راهنما نمایش داده می‌شود (بدون نیاز به کلید).
                if ( '' === $cmd && '' === $type && '' === $payload_raw ) {
                        if ( 'json' === $format ) {
                                return new WP_Error( 'aclp_invalid', 'Provide cmd=<url-encoded command> or type=<job type>.', array( 'status' => 400 ) );
                        }
                        self::url_gate_text( self::url_gate_usage() );
                }

                list( $key, $raw_key ) = self::url_gate_auth( $request );
                if ( is_wp_error( $key ) ) {
                        return self::url_gate_error_from_wp_error( $key, $format );
                }

                // تعیین گره مقصد — همان قواعد POST /commands (تک‌گره؛ broadcast اینجا نیست).
                $client_param = trim( (string) $request->get_param( 'client' ) );
                if ( '' === $client_param ) {
                        $client_param = trim( (string) $request->get_param( 'client_uid' ) );
                }
                if ( '' !== $client_param ) {
                        $c = ACLP_Clients::get_by_uid( $client_param );
                        if ( ! $c || (int) $c->key_id !== (int) $key->id ) {
                                return self::url_gate_error( 'aclp_unknown_client', 'unknown node for this API key — check the &client= value (the /clients endpoint lists nodes).', 404, $format );
                        }
                        $target = $c;
                } else {
                        $all    = ACLP_Clients::for_key( $key->id );
                        $online = array_filter( $all, array( 'ACLP_Utils', 'client_is_online' ) );
                        if ( 0 === count( $all ) ) {
                                return self::url_gate_error( 'aclp_no_clients', 'no node is connected to this API key yet — start the ACLP agent first.', 409, $format );
                        } elseif ( 1 === count( $all ) || 1 === count( $online ) ) {
                                $target = 1 === count( $all ) ? reset( $all ) : reset( $online );
                        } else {
                                if ( 'json' === $format ) {
                                        $list = array();
                                        foreach ( $all as $c ) {
                                                $list[] = array( 'client_uid' => $c->client_uid, 'name' => $c->name, 'online' => ACLP_Utils::client_is_online( $c ) );
                                        }
                                        return new WP_Error( 'aclp_select_client', 'Several nodes share this API key — add &client=<client_uid> to the URL.', array( 'status' => 400, 'clients' => $list ) );
                                }
                                $lines = array(
                                        'ACLP URL GATE — select_node',
                                        str_repeat( '-', 62 ),
                                        'Several nodes are connected to this API key.',
                                        'Re-open your URL adding &client=<client_uid>:',
                                        '',
                                );
                                foreach ( $all as $c ) {
                                        $lines[] = '  client=' . $c->client_uid . '   name=' . $c->name . '   online=' . ( ACLP_Utils::client_is_online( $c ) ? 'yes' : 'no' );
                                }
                                self::url_gate_text( implode( "\n", $lines ) . "\n" );
                        }
                }

                // ساخت payload از cmd یا payload یا type.
                if ( '' !== $payload_raw ) {
                        $decoded = json_decode( $payload_raw, true );
                        if ( ! is_array( $decoded ) ) {
                                return self::url_gate_error( 'aclp_invalid', 'payload must be ONE line of URL-encoded JSON, e.g. payload=%7B%22path%22%3A%22C%3A%2F%22%7D', 400, $format );
                        }
                        $payload = $decoded;
                        if ( '' === $type ) {
                                $type = 'shell';
                        }
                } elseif ( '' !== $cmd ) {
                        $type    = ( '' !== $type ) ? $type : 'shell';
                        $payload = self::url_gate_cmd_payload( $type, $cmd );
                } else {
                        $payload = array();
                }

                $source_param = trim( (string) $request->get_param( 'source' ) );
                $source       = ( '' !== $source_param ) ? $source_param : 'url-gate';

                $row = ACLP_Commands::create( $key->id, $target->id, $type, $payload, $source );
                if ( ! $row ) {
                        return self::url_gate_error( 'aclp_db_error', 'could not queue the job — try again.', 500, $format );
                }
                ACLP_Logger::add( 'command_created', 'فرمان جدید از دروازه URL (اصل ۵) در صف قرار گرفت: ' . $type, array( 'source' => $source ), (int) $key->id, (int) $target->id, (int) $row->id );

                // انتظار برای نتیجه (long-poll ساده — همسان با POST /commands).
                $wait_raw = $request->get_param( 'wait' );
                $wait     = ( null === $wait_raw || '' === $wait_raw ) ? 15 : min( 25, max( 0, (int) $wait_raw ) );
                $deadline = microtime( true ) + $wait;
                while ( true ) {
                        $fresh = ACLP_Commands::get( $row->id );
                        if ( $fresh ) {
                                $row = $fresh;
                        }
                        if ( in_array( $row->status, array( 'completed', 'failed' ), true ) || microtime( true ) >= $deadline ) {
                                break;
                        }
                        usleep( 500000 );
                }

                return self::url_gate_report( $row, $format, $raw_key );
        }

        /**
         * GET /url/result — بررسی نتیجه یک فرمان با ticket پس از چند ثانیه (اصل اساسی ۵).
         *
         * پارامترها: key (یا api_key) | ticket (یا job یا command_uid) |
         * wait (0-25، پیش‌فرض ۱۰) | format (text|json).
         *
         * @param WP_REST_Request $request درخواست.
         * @return WP_REST_Response|WP_Error|null
         */
        public static function route_url_result( $request ) {
                $format = ( 'json' === strtolower( trim( (string) $request->get_param( 'format' ) ) ) ) ? 'json' : 'text';

                $ticket = trim( (string) $request->get_param( 'ticket' ) );
                if ( '' === $ticket ) {
                        $ticket = trim( (string) $request->get_param( 'job' ) );
                }
                if ( '' === $ticket ) {
                        $ticket = trim( (string) $request->get_param( 'command_uid' ) );
                }

                list( $key, $raw_key ) = self::url_gate_auth( $request );
                if ( is_wp_error( $key ) ) {
                        return self::url_gate_error_from_wp_error( $key, $format );
                }

                if ( '' === $ticket ) {
                        return self::url_gate_error( 'aclp_invalid', 'ticket is missing — open /url/run first; its page prints the result URL containing the ticket.', 400, $format );
                }

                $row = ACLP_Commands::get_by_uid( $ticket );
                if ( ! $row || (int) $row->key_id !== (int) $key->id ) {
                        return self::url_gate_error( 'aclp_not_found', 'job not found for this API key — check the ticket value.', 404, $format );
                }

                // انتظار اختیاری: صفحه تا رسیدن نتیجه می‌ماند (پیش‌فرض ۱۰ ثانیه).
                $wait_raw = $request->get_param( 'wait' );
                $wait     = ( null === $wait_raw || '' === $wait_raw ) ? 10 : min( 25, max( 0, (int) $wait_raw ) );
                $deadline = microtime( true ) + $wait;
                while ( ! in_array( $row->status, array( 'completed', 'failed' ), true ) && microtime( true ) < $deadline ) {
                        usleep( 500000 );
                        $fresh = ACLP_Commands::get( $row->id );
                        if ( $fresh ) {
                                $row = $fresh;
                        }
                }

                return self::url_gate_report( $row, $format, $raw_key );
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
