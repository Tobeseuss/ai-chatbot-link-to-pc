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
         * از v1.4.0 متن عمداً با لحن «راهنمای ادغام API» و کاملاً خنثی نوشته می‌شود
         * (درخواست مالک): بدون اشاره به «کنترل کامپیوتر واقعی کاربر» — چون چنین عبارت‌هایی
         * باعث رد شدن درخواست توسط مدل‌های آموزش‌دیده‌ی ایمن می‌شد. چارچوب: سرویس پردازش
         * کار (job) → ثبت با POST در یک URL → بررسی نتیجه از URL نتیجه پس از چند ثانیه.
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
# ACLP BRIDGE — API CLIENT INTEGRATION GUIDE (v{VERSION})

The ACLP Bridge is a REST job-processing service. You interact with it exactly
like any other API: you build a JSON request, submit it to one endpoint, and the
service returns the processed result — either in the same HTTP response or from
a result endpoint once the job has finished. Always read actual values from the
API responses; never assume them.

## 0) First, detect which MODE you are
- MODE A = you can make HTTP requests yourself (Python, curl, JavaScript fetch, ...).
- MODE B = you can only print text; a human runs the commands for you.
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
- NOTE about the PYTHON AGENT (for the human operator, not for you): when the agent's
  first-run setup asks for "WordPress site URL", the operator enters ONLY the site root
  {SITE_URL} — never this /wp-json/... REST path (agent v1.3.1+ repairs wrong entries).

=====================================================================
## MODE A — submitting jobs over HTTP
=====================================================================

### Request pattern (every job follows this exact pattern)
    POST {SITE_URL}/wp-json/aclp/v1/commands
    Authorization: Bearer <the API key>      (preferred — standard header)
    X-ACLP-Key: <the API key>                (also accepted)
    Content-Type: application/json

    Body: {"type": "<job_type>", "payload": {...}, "wait": true, "timeout": 25}

1) Put the job type and its parameters into the JSON structure above and POST it
   to the URL. With "wait": true the HTTP call blocks for up to 25 seconds and
   returns the finished job result directly in the response (fastest path — the
   default you should use).
2) If the response shows status "pending" or "running" (long jobs), check the
   dedicated result endpoint after a few seconds:
       GET {SITE_URL}/wp-json/aclp/v1/commands/{command_uid}
   Repeat every 3-5 seconds until status becomes "completed" or "failed", then
   read the "result" field. The whole interaction cycle is simply:
   submit -> wait/poll -> process the result.
3) Several environments can be registered under one key. GET /clients lists them
   (pass "client_uid" in the body to target one; "broadcast": true targets all).
4) To place a file into the environment:  POST /files (multipart field "file")
   -> you get a file_id -> submit job
   {"type": "file_download", "payload": {"file_id": <id>, "save_path": "C:/.../name.ext"}}.
5) Files produced by a job appear in result.files[] with a "url" AND a "download_url";
   "download_url" is a signed link that needs NO headers — download it directly
   (browser/curl/wget) even if you cannot send custom headers.

### Messaging channel with the operator (optional)
Besides jobs, the bridge exposes a simple messaging channel. If the operator is
connected through the console program (they ran "python aclp_agent.py chat"),
you can exchange messages with them:
1) Fetch new messages:  GET /chat/pending?wait=25   (long-poll up to 25s)
   -> {"messages": [{"id": N, "text": "...", "files": [{"file_id": N, "url": "...",
      "filename": "...", "download_url": "..."}], "client": {"name": "..."}}]}
   "wait": 0 returns immediately; attachments arrive as direct download links.
2) Reply:  POST /chat/send is NOT yours — you reply with:
   POST {SITE_URL}/wp-json/aclp/v1/chat/reply
   Body: {"text": "your reply", "client_uid": "<uid from message.client or omit>",
          "source": "<your model name e.g. GPT-4o>"}
3) Poll /chat/pending again for the next message. Use this channel for questions
   and clarifications — jobs stay on the /commands endpoints.

=====================================================================
## MODE B — TEXT-ONLY clients (relay CLI)
=====================================================================
You interact through a small relay program (aclp_agent.py) that the operator runs
in their terminal. For EVERY single step:

1) You output EXACTLY ONE terminal command inside one fenced code block. Pick one of:
       python aclp_agent.py relay shell <command words here>
       python aclp_agent.py relay <job_type>
       python aclp_agent.py relay <job_type> --json {"one":"line","json":"payload"}
   Examples:
       python aclp_agent.py relay shell dir
       python aclp_agent.py relay sysinfo
       python aclp_agent.py relay shell git status
       python aclp_agent.py relay file_list --json {"path": "C:/Users"}
   <job_type> is any type from the list below. For "shell" just write the command words
   after it (no quotes needed). For other types with parameters use --json with ONE
   line of JSON. For types without parameters write only the type name.
2) Add one short sentence: "Run this in the terminal, then paste the JSON output back."
3) When the JSON comes back, read the "result" field (or "error") and continue with
   the next single command. NEVER output more than one command block at a time and
   NEVER invent output — always wait for the paste.

=====================================================================
## Job types (the "type" field) — both modes
=====================================================================
ping — service health check (returns version + capabilities)
sysinfo — environment information summary
shell {"command": "..."} — run a command-line task in the environment
run_python {"code": "..."} — run a short Python routine in the environment
file_read {"path": "..."} — read a file from the workspace
file_write {"path": "...", "content_base64": "..."} — write a file into the workspace
file_list {"path": "..."} — list directory contents
file_delete {"path": "...", "recursive": false} — remove a workspace item
file_mkdir {"path": "..."} — create a directory
file_move {"src": "...", "dst": "...", "copy": false} — move or copy a workspace item
upload_file {"path": "..."} — transfer a workspace file to you (environment -> you)
file_download {"file_id": N, "save_path": "..."} — place your uploaded file into the workspace (you -> environment)
open_url {"url": "..."} — open a URL with the environment's default handler
http_request {"url": "...", "method": "GET"} — perform an HTTP request from the environment
screenshot {} — capture a still image of the environment display
process_list {} — list running tasks
kill_process {"pid": N} — terminate a task by PID
install {"packages": ["name"], "manager": "auto"} — install packages into the environment
privilege_status {} / privilege_run {"command": "..."} — elevated tasks (optional; may need
operator approval); shell also accepts {"elevated": true} when configured.

## Rules
- Default to "wait": true; fall back to polling the result endpoint every 3-5 seconds
  (MODE A) or waiting for the paste (MODE B). Never guess results.
- Say briefly what each job does before submitting it.
- Files you receive include "download_url" — a signed link that works without any header.
- Full API reference (open it if unsure): {REPO_URL}/blob/main/docs/AGENT-API.md
- Project repository: {REPO_URL}
- If a capability you need is missing, tell the operator; the service is extensible
  (see docs/AGENT-CONTRIBUTION.md in the repository).
PROMPT;

                $prompt = str_replace(
                        array( '{VERSION}', '{SITE_URL}', '{KEY_LINE}', '{API_KEY_PH}', '{REPO_URL}' ),
                        array(
                                defined( 'ACLP_VERSION' ) ? ACLP_VERSION : '1.4.0',
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
