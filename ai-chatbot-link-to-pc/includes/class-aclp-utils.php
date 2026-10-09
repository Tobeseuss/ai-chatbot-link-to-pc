<?php
/**
 * توابع کمکی عمومی.
 *
 * @package ACLP
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

class ACLP_Utils {

        /**
         * زمان فعلی UTC در فرمت MySQL.
         *
         * @return string
         */
        public static function now() {
                return gmdate( 'Y-m-d H:i:s' );
        }

        /**
         * تولید شناسه یکتا برای فرمان‌ها.
         *
         * @return string
         */
        public static function uid() {
                return wp_generate_uuid4();
        }

        /**
         * بررسی آنلاین بودن کلاینت بر اساس آخرین دیده‌شدن.
         *
         * @param object $client ردیف کلاینت.
         * @return bool
         */
        public static function client_is_online( $client ) {
                if ( empty( $client->last_seen_at ) || '0000-00-00 00:00:00' === $client->last_seen_at ) {
                        return false;
                }
                $timeout = (int) ACLP_Settings::get( 'online_timeout', 90 );
                return ( time() - strtotime( $client->last_seen_at . ' UTC' ) ) <= $timeout;
        }

        /**
         * قالب‌بندی حجم فایل به شکل خوانا.
         *
         * @param int $bytes بایت.
         * @return string
         */
        public static function human_size( $bytes ) {
                $bytes = max( 0, (int) $bytes );
                $units = array( 'B', 'KB', 'MB', 'GB', 'TB' );
                $i     = 0;
                $v     = $bytes;
                while ( $v >= 1024 && $i < count( $units ) - 1 ) {
                        $v /= 1024;
                        $i++;
                }
                return round( $v, 2 ) . ' ' . $units[ $i ];
        }

        /**
         * برچسب فارسی وضعیت فرمان.
         *
         * @param string $status وضعیت.
         * @return string
         */
        public static function status_label( $status ) {
                $map = array(
                        'pending'   => 'در صف',
                        'sent'      => 'ارسال‌شده',
                        'running'   => 'در حال اجرا',
                        'completed' => 'تکمیل‌شده',
                        'failed'    => 'ناموفق',
                );
                return isset( $map[ $status ] ) ? $map[ $status ] : $status;
        }

        /**
         * برچسب فارسی نوع فرمان.
         *
         * @param string $type نوع.
         * @return string
         */
        public static function type_label( $type ) {
                $map = array(
                        'shell'         => 'اجرای دستور',
                        'file_read'     => 'خواندن فایل',
                        'file_write'    => 'نوشتن فایل',
                        'file_list'     => 'لیست فایل‌ها',
                        'file_delete'   => 'حذف فایل',
                        'file_mkdir'    => 'ساخت پوشه',
                        'file_move'     => 'انتقال/کپی فایل',
                        'file_download' => 'دانلود فایل به سیستم',
                        'upload_file'   => 'ارسال فایل از سیستم',
                        'open_url'      => 'باز کردن مرورگر',
                        'http_request'  => 'درخواست HTTP',
                        'screenshot'    => 'اسکرین‌شات',
                        'sysinfo'       => 'اطلاعات سیستم',
                        'process_list'  => 'لیست پردازه‌ها',
                        'kill_process'  => 'بستن پردازه',
                        'install'       => 'نصب برنامه',
                        'run_python'    => 'اجرای کد پایتون',
                        'ping'          => 'تست اتصال',
                        'privilege_status' => 'وضعیت دسترسی مدیر',
                        'privilege_run'    => 'اجرا با دسترسی مدیر',
                );
                return isset( $map[ $type ] ) ? $map[ $type ] : $type;
        }

        /**
         * دایرکتوری ذخیره فایل‌های پلاگین.
         *
         * @return string مسیر بدون اسلش انتهایی.
         */
        public static function storage_dir() {
                $up = wp_upload_dir();
                return trailingslashit( $up['basedir'] ) . 'aclp-files';
        }

        /**
         * متن آماده و رسمی «معرفی پل به ایجنت هوش مصنوعی» (اصل اساسی شماره ۴).
         *
         * این متن باید با هر تغییر بروزرسانی شود و در سه جا هماهنگ بماند:
         *   1) همین تابع (منبع واحد حقیقت — رندر در داشبورد پلاگین + GET /agent-prompt)
         *   2) بخش ابتدای README.md ریپو
         *   3) docs/AGENT-API.md (مرجع کامل)
         *
         * @param string $site_url آدرس سایت (خالی = سایت فعلی).
         * @return string
         */
        public static function agent_prompt( $site_url = '' ) {
                if ( ! $site_url ) {
                        $site_url = home_url();
                }
                $api_key_ph = 'PASTE_YOUR_REAL_API_KEY_HERE';
                $prompt     = <<<PROMPT
# INSTRUCTION FOR THE AI AGENT (PC bridge — ACLP v{VERSION})

You are connected to the user's real computer through the "AI Chatbot Link to PC" (ACLP) bridge.
Through it you can FULLY control the user's PC: run any shell command, read/write/move/delete any file,
install software, upload/download files in both directions, open the browser, make HTTP requests from
the user's network, take screenshots, and more. There are no artificial restrictions by design —
the user explicitly grants you this access and everything you do is visible to them in a history panel.

## Connection settings
- Base URL:  {SITE_URL}/wp-json/aclp/v1
- API key:   {API_KEY_PH}
- If the API key above is empty or invalid, STOP and ask the user to copy a key from
  WordPress admin -> AI-PC Link -> API Keys. Never guess or invent credentials.
- HTTPS is preferred. If HTTPS fails with a connection/SSL error, automatically retry the same
  request over HTTP (the server supports both).

## How to call the PC (always use these two headers)
    X-ACLP-Key: <the API key>
    Content-Type: application/json

1) Send one action:  POST {SITE_URL}/wp-json/aclp/v1/commands
   Body: {"type": "<action>", "payload": {...}, "wait": true, "timeout": 25}
   - "wait": true makes the HTTP call block (max 25s) and return the finished result.
2) If the result is not ready yet (status "pending"/"running"), poll:
   GET {SITE_URL}/wp-json/aclp/v1/commands/{command_uid}   every 3-5 seconds.
3) List machines:  GET /clients  (use "client_uid" in the body when several PCs are online;
   or "broadcast": true to target all online PCs at once).
4) Send a file TO the PC:  POST /files (multipart field "file") -> get file_id ->
   send command {"type": "file_download", "payload": {"file_id": <id>, "save_path": "C:/.../name.ext"}}.
5) Files the PC produced appear in result.files[] with a "url"; download them with
   GET /files/{file_id} using the same X-ACLP-Key header.

## Available action types (the "type" field)
ping, sysinfo, shell {"command": "..."}, run_python {"code": "..."},
file_read {"path": "..."}, file_write {"path": "...", "content_base64": "..."},
file_list {"path": "..."}, file_delete {"path": "...", "recursive": false},
file_mkdir {"path": "..."}, file_move {"src": "...", "dst": "...", "copy": false},
upload_file {"path": "..."} (PC -> you), file_download {"file_id": N, "save_path": "..."} (you -> PC),
open_url {"url": "..."}, http_request {"url": "...", "method": "GET"},
screenshot {}, process_list {}, kill_process {"pid": N},
install {"packages": ["name"], "manager": "auto"},
privilege_status {}, privilege_run {"command": "..."} (elevated/administrator run — optional),
shell also accepts {"elevated": true} to run with elevation when the user configured credentials.

## Rules
- Prefer "wait": true + polling instead of guessing results.
- Explain to the user what you are about to run on their PC before running it.
- Full API reference (open it if unsure): {REPO_URL}/blob/main/docs/AGENT-API.md
- Project repository: {REPO_URL}
- If you need a capability that the bridge does not have yet, tell the user; qualified agents
  may extend the project itself (see docs/AGENT-CONTRIBUTION.md in the repository).
PROMPT;

                $prompt = str_replace(
                        array( '{VERSION}', '{SITE_URL}', '{API_KEY_PH}', '{REPO_URL}' ),
                        array(
                                defined( 'ACLP_VERSION' ) ? ACLP_VERSION : '1.1.0',
                                untrailingslashit( (string) $site_url ),
                                $api_key_ph,
                                untrailingslashit( (string) ACLP_Settings::get( 'github_repo_url', 'https://github.com/Tobeseuss/ai-chatbot-link-to-pc' ) ),
                        ),
                        $prompt
                );
                return $prompt;
        }
}
