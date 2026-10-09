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
         * @param string $site_url آدرس سایت (خالی = سایت فعلی) — همیشه خودکار جای‌گذاری می‌شود.
         * @param string $api_key  کلید API (خالی = جای‌نگهدار تا ایجنت از کاربر بپرسد).
         * @return string
         */
        public static function agent_prompt( $site_url = '', $api_key = '' ) {
                if ( ! $site_url ) {
                        $site_url = home_url();
                }
                $api_key_ph = 'PASTE_YOUR_REAL_API_KEY_HERE';
                $key_line   = $api_key
                        ? '- API key:  ' . $api_key . '   (already provided — send it as "Authorization: Bearer <key>")'
                        : "- API key:  {$api_key_ph}\n"
                          . "- If the API key above is a placeholder (empty/invalid), STOP and ask the user to copy a real key\n"
                          . "  from WordPress admin -> AI-PC Link -> API Keys (the full key is visible there and can be copied).\n"
                          . "  Never guess or invent credentials.";
                $prompt     = <<<PROMPT
# INSTRUCTION FOR THE AI AGENT (PC bridge — ACLP v{VERSION})

You are connected to the user's real computer through the "AI Chatbot Link to PC" (ACLP) bridge.
Through it you can FULLY control the user's PC: run any shell command, read/write/move/delete any file,
install software, upload/download files in both directions, open the browser, make HTTP requests from
the user's network, take screenshots, and more. There are no artificial restrictions by design —
the user explicitly grants you this access and everything you do is visible to them in a history panel.

## 0) First, detect which MODE you are
- MODE A = you CAN run code / shell / HTTP requests inside your own environment.
- MODE B = you are a TEXT-ONLY chatbot (you cannot execute anything yourself).
Read ONLY your mode's section below, then follow it literally, step by step.

## Connection settings (both modes)
- Base URL:  {SITE_URL}/wp-json/aclp/v1
{KEY_LINE}
- Authentication header (IMPORTANT): prefer the STANDARD header, because some
  web hosts strip custom headers:
      Authorization: Bearer <the API key>
  The custom header "X-ACLP-Key: <key>" is ALSO accepted if you cannot set
  Authorization. Content-Type: application/json for JSON bodies.
- HTTPS is preferred. If HTTPS fails with a connection/SSL error, automatically retry the same
  request over HTTP (the server supports both).

=====================================================================
## MODE A — you can execute code (Python/curl/etc.)
=====================================================================

### How to call the PC (auth header)
    Authorization: Bearer <the API key>      (preferred — standard header)
    X-ACLP-Key: <the API key>                (also accepted)
    Content-Type: application/json           (for JSON bodies)

1) Send one action:  POST {SITE_URL}/wp-json/aclp/v1/commands
   Body: {"type": "<action>", "payload": {...}, "wait": true, "timeout": 25}
   - "wait": true makes the HTTP call block (max 25s) and return the finished result.
2) If the result is not ready yet (status "pending"/"running"), poll:
   GET {SITE_URL}/wp-json/aclp/v1/commands/{command_uid}   every 3-5 seconds.
3) List machines:  GET /clients  (use "client_uid" in the body when several PCs are online;
   or "broadcast": true to target all online PCs at once).
4) Send a file TO the PC:  POST /files (multipart field "file") -> get file_id ->
   send command {"type": "file_download", "payload": {"file_id": <id>, "save_path": "C:/.../name.ext"}}.
5) Files the PC produced appear in result.files[] with a "url" AND a "download_url";
   "download_url" is a signed link that needs NO headers — you can download it directly
   (browser/curl/wget) even if you cannot send custom headers.

### Talking with the USER directly (optional)
If the user is chatting through the agent program on the PC (they ran
"python aclp_agent.py chat"), you can exchange messages with them:
1) Get new user messages:  GET /chat/pending?wait=25   (long-poll up to 25s)
   -> {"messages": [{"id": N, "text": "...", "files": [{"file_id": N, "url": "...",
      "filename": "...", "download_url": "..."}], "client": {"name": "..."}}]}
   "wait": 0 returns immediately; files arrive as direct download links on the site.
2) Reply:  POST /chat/send is NOT yours — you reply with:
   POST {SITE_URL}/wp-json/aclp/v1/chat/reply
   Body: {"text": "your reply", "client_uid": "<uid from message.client or omit>",
          "source": "<your model name e.g. GPT-4o>"}
3) Poll /chat/pending again for the user's next message. This is a real-time
   chat channel IN ADDITION to commands — use it when the user wants to talk,
   not when they want an action on the PC.

=====================================================================
## MODE B — TEXT-ONLY chatbot (you cannot run anything)
=====================================================================
You will use the USER as your hands. The user runs the ACLP agent (aclp_agent.py)
on the connected PC. For EVERY single step:

1) You output EXACTLY ONE terminal command inside one fenced code block. Pick one of:
       python aclp_agent.py relay shell <command words here>
       python aclp_agent.py relay <action>
       python aclp_agent.py relay <action> --json {"one":"line","json":"payload"}
   Examples:
       python aclp_agent.py relay shell dir
       python aclp_agent.py relay sysinfo
       python aclp_agent.py relay shell git status
       python aclp_agent.py relay file_list --json {"path": "C:/Users"}
   <action> is any action from the list below. For "shell" just write the command words
   after it (no quotes needed). For other actions with parameters use --json with ONE
   line of JSON. For actions without parameters write only the action name.
2) Tell the user in one short sentence: "Copy this into the terminal of the connected PC,
   run it, then paste the whole JSON output back to me."
3) When the user pastes the JSON back, read the "result" field (or "error") and continue
   with the next single command. NEVER output more than one command block at a time and
   NEVER invent the output — always wait for the user's paste.

=====================================================================
## Available action types (the "type" field) — both modes
=====================================================================
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
- Prefer "wait": true + polling (MODE A) or waiting for the user's paste (MODE B) instead of guessing results.
- Explain to the user what you are about to run on their PC before running it.
- File links: files you receive include "download_url" — a signed link that works without any header.
- Full API reference (open it if unsure): {REPO_URL}/blob/main/docs/AGENT-API.md
- Project repository: {REPO_URL}
- If you need a capability that the bridge does not have yet, tell the user; qualified agents
  may extend the project itself (see docs/AGENT-CONTRIBUTION.md in the repository).
PROMPT;

                $prompt = str_replace(
                        array( '{VERSION}', '{SITE_URL}', '{KEY_LINE}', '{API_KEY_PH}', '{REPO_URL}' ),
                        array(
                                defined( 'ACLP_VERSION' ) ? ACLP_VERSION : '1.3.0',
                                untrailingslashit( (string) $site_url ),
                                $key_line,
                                $api_key_ph,
                                untrailingslashit( (string) ACLP_Settings::get( 'github_repo_url', 'https://github.com/Tobeseuss/ai-chatbot-link-to-pc' ) ),
                        ),
                        $prompt
                );
                return $prompt;
        }
}
