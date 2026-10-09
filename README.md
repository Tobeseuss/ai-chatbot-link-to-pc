# AI Chatbot Link to PC

> پل ارتباطی بین چت‌بات‌های هوش مصنوعی و سیستم‌عامل کاربر (ویندوز / لینوکس) — افزونه وردپرس + ایجنت پایتون

[![Version](https://img.shields.io/badge/version-2.0.0-blue.svg)](https://github.com/Tobeseuss/ai-chatbot-link-to-pc/releases)
[![License](https://img.shields.io/badge/license-GPL--2.0-green.svg)](LICENSE)
[![Platform](https://img.shields.io/badge/platform-Windows%20%7C%20Linux-lightgrey.svg)]()

---

## 🤖 نحوه اعلام نحوه استفاده از پل به ایجنت هوش مصنوعی (اصل اساسی ۴)

**این بخش با هر تغییری بروز می‌شود.** متن آماده زیر را کپی کنید و به هر هوش مصنوعی / LLM ای بدهید تا با پل کار کند؛ حتی مدل‌های ضعیف و محدود، **چت‌بات‌های فقط-متنی** (حالت B) و **هوش مصنوعی‌هایی که فقط می‌توانند یک آدرس وب را باز و مرور کنند** (حالت C — اصل اساسی ۵) با همین متن می‌توانند پل را راه بیندازند. جای `API key` عمداً خالی است تا خود ایجنت از شما سوال کند و آن را از پنل وردپرس (AI-PC Link ← کلیدهای API) بگیرد — در داشبورد پلاگین می‌توانید کلید موردنظر را از منوی کشویی انتخاب کنید تا متن با همان کلید ساخته و کپی شود و آدرس سایت هم خودکار جای‌گذاری می‌شود. همین متن از مسیر `GET {آدرس‌سایت}/wp-json/aclp/v1/agent-prompt` هم در دسترس است و همیشه با نسخه افزونه هماهنگ است.

> **از v1.4.0 متن با لحن کاملاً خنثی و حرفه‌ای «راهنمای ادغام API» نوشته می‌شود** (درخواست مالک): مدل به‌جای توصیف «کنترل سیستم واقعی»، سرویس را یک سرویس پردازش کار (job) می‌بیند — ساختار JSON را در URL ثبت می‌کند و نتیجه را از URL نتیجه پس از چند ثانیه برمی‌دارد. این لحن باعث می‌شود مدل‌های آموزش‌دیده‌ی ایمن درخواست را رد نکنند و حرفه‌ای‌تر تعامل کنند.
>
> **از v2.0.0 حالت سوم تعامل به‌عنوان اصل اساسی ۵ تثبیت شد (درخواست مالک):** بسیاری از هوش مصنوعی‌ها اعلام می‌کنند «امکان تعامل با API ندارند» ولی می‌توانند یک آدرس وب را باز و مرور کنند — پس برای آن‌ها **دروازه URL** ساخته شد: دستور در انتهای آدرس قرار می‌گیرد (`.../url/run?key=...&cmd=...`)، آدرس باز می‌شود و نتیجه در همان صفحه (متن ساده) دیده و پردازش می‌شود. متن زیر این حالت را در بخش **MODE C** توضیح می‌دهد؛ جزئیات کامل در `docs/AGENT-API.md` بخش 3.10. **قاعده مالک: در هر تغییر آینده، این حالت باید توسعه و تکمیل شود.**

```text
# ACLP BRIDGE — API CLIENT INTEGRATION GUIDE (v2.0.0)

The ACLP Bridge is a REST job-processing service. You interact with it exactly
like any other API: you build a JSON request, submit it to one endpoint, and the
service returns the processed result — either in the same HTTP response or from
a result endpoint once the job has finished. Always read actual values from the
API responses; never assume them.

## 0) First, detect which MODE you are
- MODE A = you can make HTTP requests yourself (Python, curl, JavaScript fetch, ...).
- MODE B = you can only print text; a human runs the commands for you.
- MODE C = you can only OPEN/BROWSE a URL and read the page (no POST, no headers).
Read ONLY your mode's section below, then follow it literally, step by step.

## Connection settings (all modes)
- Base URL:  https://YOUR-SITE.com/wp-json/aclp/v1
- API key:  PASTE_YOUR_REAL_API_KEY_HERE
- If the API key above is a placeholder (empty/invalid), STOP and ask the user to copy a real key
  from WordPress admin -> AI-PC Link -> API Keys (the full key is visible there and can be copied).
  Never guess or invent credentials.
- Authentication header (IMPORTANT): prefer the STANDARD header, because some
  web hosts strip custom headers:
      Authorization: Bearer <the API key>
  The custom header "X-ACLP-Key: <key>" is ALSO accepted if you cannot set
  Authorization. Content-Type: application/json for JSON bodies.
- HTTPS is preferred. If HTTPS fails with a connection/SSL error, automatically retry the same
  request over HTTP (the server supports both).
- NOTE about the PYTHON AGENT (for the human operator, not for you): when the agent's
  first-run setup asks for "WordPress site URL", the operator enters ONLY the site root
  https://YOUR-SITE.com — never this /wp-json/... REST path (agent v1.3.1+ repairs wrong entries).

=====================================================================
## MODE A — submitting jobs over HTTP
=====================================================================

### Request pattern (every job follows this exact pattern)
    POST https://YOUR-SITE.com/wp-json/aclp/v1/commands
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
       GET https://YOUR-SITE.com/wp-json/aclp/v1/commands/{command_uid}
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
   POST https://YOUR-SITE.com/wp-json/aclp/v1/chat/reply
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
## MODE C — URL-ONLY clients (open a URL, read the page)
=====================================================================
If you cannot run code, send POST requests or set custom headers — but you CAN
open a web address and read its content — use the URL Gateway. The whole cycle
is one page view: the command sits at the end of the URL, and the opened page
shows the job report.

1) Submit a job and (normally) get the result in the same page view:
       https://YOUR-SITE.com/wp-json/aclp/v1/url/run?key=<the API key>&cmd=<command>&wait=15
   - "cmd" = one shell command, URL-encoded (spaces become %20). Example:
       .../url/run?key=aclp_live_xxx&cmd=echo%20hello&wait=15
   - Non-shell job: drop "cmd" and pass "type" instead, e.g.
       .../url/run?key=aclp_live_xxx&type=sysinfo&wait=15
   - "wait" = seconds the page keeps collecting the result (0-25, default 15).
2) Read the plain-text report on the page: STATUS, RESULT, FILES. If STATUS is
   still "pending" or "running", wait the seconds shown on the page, then open
   the RESULT URL printed there (the /url/result link with your ticket). Repeat
   until STATUS is "completed" or "failed". NEVER invent output — read the page.
3) If the page says select_node, several nodes share this key: re-open the same
   URL adding &client=<client_uid> of one node from the list.
4) URL limits: keep "cmd" under ~1200 characters and URL-encode it fully
   (& becomes %26, spaces %20, quotes %22). For advanced jobs use
   &payload= with ONE line of URL-encoded JSON, e.g. for file_list:
   &type=file_list&payload=%7B%22path%22%3A%22C%3A%2F%22%7D
5) Files produced by the job appear in the FILES section as signed download
   links that open in any browser — no headers needed. Add &format=json to
   receive standard JSON instead of the plain-text page.

=====================================================================
## Job types (the "type" field) — all modes
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
  (MODE A), waiting for the paste (MODE B) or re-opening the result URL (MODE C).
  Never guess results.
- Say briefly what each job does before submitting it.
- Files you receive include "download_url" — a signed link that works without any header.
- Full API reference (open it if unsure): https://github.com/Tobeseuss/ai-chatbot-link-to-pc/blob/main/docs/AGENT-API.md
- Project repository: https://github.com/Tobeseuss/ai-chatbot-link-to-pc
- If a capability you need is missing, tell the operator; the service is extensible
  (see docs/AGENT-CONTRIBUTION.md in the repository).
```

> ایجنت‌های توسعه‌دهنده (که می‌خواهند خودشان پروژه را بروزرسانی و ریلیز منتشر کنند): `docs/AGENT-CONTRIBUTION.md` + `GET /github-integration`.

---

## ⚠️ هشدار امنیتی — قبل از شروع بخوانید

این ابزار به هوش مصنوعی **کنترل کامل و بدون محدودیت** روی سیستمی که ایجنت روی آن اجرا شود می‌دهد: اجرای هر دستور، دیدن/ویرایش/حذف هر فایل، نصب برنامه، دانلود/آپلود فایل و اجرای مرورگر. این دقیقاً هدف طراحی آن است، اما یعنی:

- **کلید API را مثل رمز عبور بانکی نگه دارید.** هرکس کلید را داشته باشد، کنترل کامل سیستم شما را دارد.
- ایجنت را **فقط روی سیستم خودتان** و با آگاهی کامل اجرا کنید.
- ترجیحاً روی سایت وردپرس **HTTPS** فعال باشد؛ اما اگر HTTPS سایت شما مشکل اتصال داشت، همه عملکردهای پلاگین و ایجنت با **HTTP** هم کار می‌کنند (ایجنت v1.1+ به‌صورت خودکار به HTTP سوییچ می‌کند). بدانید که کلید در مسیر HTTP قابل شنود است؛ این انتخاب را آگاهانه انجام دهید.
- کلیدهای استفاده‌نشده را از پنل مدیریت **غیرفعال یا حذف** کنید و کلید را دوره‌ای عوض کنید.
- **دسترسی مدیر/روت اختیاری است:** از نسخه 1.1 ایجنت می‌تواند در صورت نیاز و با پیکربندی اختیاری شما (sudo/su در لینوکس یا UAC در ویندوز) دستورات سطح بالا را هم اجرا کند؛ بدون این پیکربندی هم هیچ عملکردی محدود نمی‌شود.
- این نرم‌افزار «کاملاً شفاف» طراحی شده: ایجنت در کنسول قابل مشاهده است، همه چیز لاگ می‌شود و در تاریخچه پنل مدیریت دیده می‌شود. هیچ ویژگی مخفی‌کاری ندارد.

---

## 🎯 این پروژه چیست؟

یک افزونه وردپرسی روی سایت شما نصب می‌شود و یک REST API امن (با احراز هویت کلید API) فراهم می‌کند. برنامه «ایجنت» (پایتون) روی سیستم شما (ویندوز یا لینوکس) اجرا می‌شود و به همان API وصل می‌ماند. هر چت‌بات هوش مصنوعی که بتواند درخواست HTTP بزند (OpenAI function calling، Claude tool use، LangChain، n8n و...) می‌تواند از طریق پلاگین روی سیستم شما فرمان اجرا کند:

- ✅ اجرای هر دستور شل / پاورشل / بش
- ✅ مشاهده، ایجاد، ویرایش، حذف و انتقال فایل‌ها و پوشه‌ها
- ✅ نصب برنامه (winget / choco / apt / dnf / pacman / pip)
- ✅ دانلود و آپلود فایل بین چت‌بات و سیستم (دوطرفه)
- ✅ اجرای مرورگر و باز کردن URL + درخواست HTTP از IP سیستم شما
- ✅ اسکرین‌شات، اطلاعات سیستم، مدیریت پردازه‌ها
- ✅ اجرای کد پایتون موقت

### چرا این معماری؟

چت‌بات‌ها نمی‌توانند مستقیم به سیستم شما وصل شوند (پشت NAT/فایروال هستید). این پروژه با الگوی **صف فرمان و polling** این مشکل را حل می‌کند: ایجنت خودش به سرور وصل می‌ماند و فرمان‌ها را برمی‌دارد؛ چت‌بات فقط با سرور صحبت می‌کند. نتیجه: بدون نیاز به پورت باز، IP ثابت یا VPN.

```
چت‌بات هوش مصنوعی ──HTTP/HTTPS──▶ سایت وردپرس (پلاگین) ◀──polling── ایجنت روی PC شما
        ▲                              │                            │
        └──────── نتیجه + فایل‌ها ◀─────┘◀──────── نتیجه اجرا ────────┘
```

---

## ✨ قابلیت‌های کلیدی

| قابلیت | توضیح |
|--------|-------|
| 🔑 **کلیدهای API نامحدود و همیشه قابل مشاهده** | هر کلید مستقل با نام، یادداشت، سقف سیستم و فعال/غیرفعال کردن. مقدار کامل کلید همیشه در پنل نمایش داده می‌شود و دکمه کپی دارد (درخواست مالک) |
| 💻 **چند سیستم همزمان با یک کلید** | هر نصب ایجنت یک UID یکتا دارد؛ چند سیستم می‌توانند همزمان از یک کلید استفاده کنند. چت‌بات می‌تواند سیستم مقصد را انتخاب کند یا `broadcast` به همه سیستم‌های آنلاین بزند |
| 📜 **تاریخچه کامل تعاملات** | فرمان ارسالی، payload کامل، نتیجه دریافتی، خطاها، فایل‌های منتقل‌شده، مدت اجرا و زمان‌بندی — همه قابل مشاهده و فیلترشدنی در پنل |
| 🗂 **مدیریت نگهداری از پنل** | مدت نگهداری تاریخچه، مدت نگهداری فایل‌ها، سقف ردیف‌ها، حداکثر حجم فایل، محدودیت نرخ درخواست — همه از تنظیمات |
| 📁 **انتقال فایل دوطرفه** | چت‌بات فایل بفرستد روی PC ذخیره شود؛ ایجنت فایل از PC به سرور آپلود کند تا چت‌بات دانلود کند |
| 🖥 **پنل مدیریت فارسی** | داشبورد آماری، مدیریت کلیدها، سیستم‌های آنلاین/آفلاین، تاریخچه با جزئیات کامل، تنظیمات |
| 🌐 **پشتیبانی HTTP + HTTPS** | اگر HTTPS سایت مشکل داشت، ایجنت به‌صورت خودکار روی HTTP ادامه می‌دهد؛ متن آماده معرفی پل به ایجنت در داشبورد و `GET /agent-prompt` |
| 🔐 **ارتقای سطح دسترسی اختیاری** | اکشن‌های `privilege_status` / `privilege_run` و پرچم `elevated` — sudo/su در لینوکس، UAC در ویندوز؛ کاملاً اختیاری |
| 🗨 **گفتگوی مستقیم کاربر با هوش مصنوعی** | کاربر با اجرای `python aclp_agent.py chat` مستقیماً با هوش مصنوعی(های) متصل به کلید خود گفتگو می‌کند؛ پیام و فایل (به‌صورت لینک مستقیم از روی سایت — حتی اگر هوش مصنوعی آپلود فایل را پشتیبانی نکند) رد‌وبدل می‌شود و همه‌چیز در پنل «گفتگوها» ثبت می‌شود |
| 🐍 **ایجنت بدون هیچ وابستگی** | ایجنت فقط با پایتون خام (3.8+) اجرا می‌شود — هیچ pip install و هیچ دانلودی لازم نیست؛ همه‌چیز داخل پوشه ایجنت است |
| 🪟 **کنسول تمام-انگلیسی و مقاوم** | همه پیام‌های ایجنت انگلیسی است (مشکل نمایش فارسی در cmd ویندوز یک‌بار برای همیشه حذف شد) و هر خطای مهلک همراه توقف پنجره + لاگ کامل در `aclp_agent.log` نمایش داده می‌شود — پنجره دیگر بی‌صدا بسته نمی‌شود |
| 💬 **پشتیبانی از چت‌بات‌های فقط-متنی** | دستور `python aclp_agent.py relay <action>` — چت‌باتی که نمی‌تواند کد اجرا کند فقط یک دستور چاپ می‌کند؛ کاربر آن را در ترمینال اجرا و خروجی JSON را به چت‌بات برمی‌گرداند. تاریخچه کامل هم روی سرور ثبت می‌شود |
| 🌐 **دروازه URL — اصل اساسی ۵ (از v2.0.0)** | برای هوش مصنوعی‌هایی که «امکان تعامل با API ندارند» ولی می‌توانند یک آدرس را باز کنند: `GET /url/run?key=...&cmd=...` — دستور در انتهای URL، نتیجه در همان صفحه (متن ساده؛ با `&format=json` هم JSON)؛ `GET /url/result` هم نتیجه را با ticket پس از چند ثانیه نشان می‌دهد |
| 🌍 **کنسول تمام-انگلیسی (از v1.3.0)** | پیام‌های ایجنت فقط انگلیسی است تا مشکل نمایش متن‌های فارسی/RTL در cmd قدیمی ویندوز برای همیشه حذف شود؛ خروجی دستورات به همان شکل (UTF-8) منتقل می‌شود |
| 🤖 **توسعه‌پذیری توسط خود ایجنت‌ها** | ایجنت‌های متصل می‌توانند با PAT قابل‌تنظیم در پنل، تغییرات را کامیت و نسخه جدید منتشر کنند (`docs/AGENT-CONTRIBUTION.md`) |
| 🔄 **آپدیت آسان** | نسخه‌بندی معنایی (semver) + فایل‌های ZIP آماده در بخش Releases به‌همراه ایجنت به‌روزشده |

---

## 🚀 شروع سریع (۳ گام)

### گام ۱ — نصب افزونه در وردپرس

1. از بخش [Releases](https://github.com/Tobeseuss/ai-chatbot-link-to-pc/releases) فایل `ai-chatbot-link-to-pc-v2.0.0.zip` را دانلود کنید.
2. در وردپرس: **افزونه‌ها ← افزودن ← بارگذاری افزونه** و فایل ZIP را نصب و فعال کنید.
3. منوی جدید **«AI-PC Link»** در پیشخوان ظاهر می‌شود.

### گام ۲ — ساخت کلید API و نصب ایجنت

1. **AI-PC Link ← کلیدهای API ← ساخت کلید جدید** — کلید ساخته می‌شود و از همان‌جا و در هر زمان آینده قابل مشاهده و کپی است (دکمه «کپی» کنار کلید).
2. فایل `aclp-agent-v2.0.0.zip` را از Releases دانلود و روی سیستم خود (ویندوز/لینوکس) استخراج کنید.
3. اجرا کنید:
   - **ویندوز:** دوبار کلیک روی `start_agent.bat`
   - **لینوکس:** `chmod +x start_agent.sh && ./start_agent.sh`
4. آدرس سایت و کلید API را وارد کنید — **فقط ریشه سایت** را وارد کنید (مثل `https://example.com`)، نه آدرس کامل REST `/wp-json/...` (ایجنت خودش مسیر REST را اضافه می‌کند و از v1.3.1 اگر آدرس کامل چسبانده باشد خودش اصلاحش می‌کند). اگر HTTPS مشکل داشت، آدرس را با `http://` وارد کنید یا اجازه دهید ایجنت خودش سوییچ کند. پیام `Registered as client #1` یعنی سیستم شما در پنل «سیستم‌های متصل» آنلاین است. در همین مرحله می‌توانید نام هوش مصنوعی/چت‌بات کنترل‌کننده را هم وارد کنید تا در پنل نمایش داده شود (`ai_model`).
5. *(اختیاری)* در همان setup می‌توانید مشخصات sudo/runas را بدهید تا دستورات نیازمند دسترسی مدیر هم قابل اجرا شوند — رد کردن این مرحله هیچ محدودیتی ایجاد نمی‌کند.

### گام ۳ — اتصال چت‌بات

چت‌بات شما (هر فریم‌ورکی که HTTP call می‌تواند) با یک درخواست فرمان می‌فرستد:

```bash
curl -X POST "https://example.com/wp-json/aclp/v1/commands" \
  -H "X-ACLP-Key: aclp_live_xxxxxxxx..." \
  -H "Content-Type: application/json" \
  -d '{
    "type": "shell",
    "payload": { "command": "echo Hello from my PC" },
    "wait": true,
    "timeout": 20
  }'
```

پاسخ (چون `wait=true` بود، تا رسیدن نتیجه صبر می‌کند):

```json
{
  "command_uid": "8f0c...",
  "type": "shell",
  "status": "completed",
  "result": { "exit_code": 0, "stdout": "Hello from my PC\n", "stderr": "" }
}
```

مستندات کامل API برای ایجنت‌ها: [`docs/AGENT-API.md`](docs/AGENT-API.md)

### چت‌بات فقط-متنی دارید؟ (حالت B)
اگر چت‌بات شما نمی‌تواند کد/HTTP اجرا کند، فقط متن تولید می‌کند — مشکلی نیست. در داشبورد پلاگین کلید API را از منوی کشویی انتخاب کنید، متن آماده (حالت B) را به چت‌بات بدهید؛ چت‌بات در هر مرحله یک دستور مثل زیر چاپ می‌کند، شما آن را در ترمینال سیستم اجرا می‌کنید و خروجی JSON را به چت‌بات می‌دهید:

```bash
python aclp_agent.py relay shell dir
python aclp_agent.py relay sysinfo
python aclp_agent.py relay file_list --json {"path": "C:/Users"}
```

هر اجرای relay در تاریخچه پلاگین هم ثبت می‌شود و نتیجه به‌صورت یک بلوک JSON چاپ می‌شود.

### هوش مصنوعی فقط می‌تواند آدرس باز کند؟ (حالت C — اصل اساسی ۵، از v2.0.0)
بعضی مدل‌ها اعلام می‌کنند «امکان تعامل با API نداریم» ولی می‌توانند یک آدرس وب را باز و مرور کنند. برای این‌ها **دروازه URL** ساخته شده: کافی است آدرس زیر (با کلید و دستور خودتان) را به آن‌ها بدهید یا در اختیارشان بگذارید — دستور در انتهای آدرس است و نتیجه در همان صفحه به‌صورت متن ساده ظاهر می‌شود:

```text
https://example.com/wp-json/aclp/v1/url/run?key=aclp_live_xxxxxxxx&cmd=echo%20hello&wait=15
https://example.com/wp-json/aclp/v1/url/run?key=aclp_live_xxxxxxxx&type=sysinfo&wait=15
https://example.com/wp-json/aclp/v1/url/result?key=aclp_live_xxxxxxxx&ticket=JOB_UID
```

متن آماده اصل ۴ (بخش MODE C) به هوش مصنوعی می‌آموزد چطور خودش این آدرس‌ها را بسازد؛ مستندات کامل در [`docs/AGENT-API.md`](docs/AGENT-API.md) بخش 3.10.

---

## 📦 دانلود و نصب

| فایل | کاربرد | محل |
|------|--------|-----|
| `ai-chatbot-link-to-pc-v2.0.0.zip` | افزونه وردپرس | [Releases](https://github.com/Tobeseuss/ai-chatbot-link-to-pc/releases) |
| `aclp-agent-v2.0.0.zip` | ایجنت سیستم (پایتون — بدون هیچ وابستگی) | [Releases](https://github.com/Tobeseuss/ai-chatbot-link-to-pc/releases) |

پیش‌نیازها: وردپرس 5.8+ با PHP 7.4+ · پایتون 3.8+ روی سیستم کاربر

> ایجنت از نسخه 1.3.0 فقط با کتابخانه استاندارد پایتون کار می‌کند — هیچ pip install و هیچ دانلودی لازم نیست؛ پوشه ایجنت کامل و خودکفاست. برای قابلیت اختیاری اسکرین‌شات: `pip install pyautogui pillow`

---

## 📚 مستندات

| سند | مخاطب | محتوا |
|-----|-------|-------|
| [`docs/USER-GUIDE.fa.md`](docs/USER-GUIDE.fa.md) | کاربر انسانی | نصب گام‌به‌گام، کار با پنل، عیب‌یابی کامل (فارسی) |
| [`docs/AGENT-API.md`](docs/AGENT-API.md) | چت‌بات / ایجنت هوش مصنوعی | مرجع کامل REST API + نمونه کد + اسکیمای ابزار (انگلیسی — چون ایجنت‌های AI انگلیسی را دقیق‌تر پارس می‌کنند) |
| [`docs/AGENT-CONTRIBUTION.md`](docs/AGENT-CONTRIBUTION.md) | ایجنت‌های توسعه‌دهنده | پروتکل خودانتشارسازی: چک کامیت‌ها، کامیت/پوش با PAT، انتشار ریلیز |
| [`docs/DEVELOPER.md`](docs/DEVELOPER.md) | توسعه‌دهنده | معماری، جداول دیتابیس، افزودن اکشن جدید، فرآیند انتشار |
| [`project.md`](project.md) | ایجنت‌های توسعه‌دهنده آینده | اصول بنیادین پروژه، قرارداد نسخه‌بندی، چک‌لیست الزامی هر تغییر |
| [`brainstorm.md`](brainstorm.md) | همه | تاریخچه کامل گفتگوهای کاربر و ایجنت هوش مصنوعی |
| [`worklog.md`](worklog.md) | ایجنت‌های توسعه‌دهنده | لاگ کاری نسخه به نسخه |
| [`CHANGELOG.md`](CHANGELOG.md) | همه | تغییرات هر نسخه |
| [`SECURITY.md`](SECURITY.md) | همه | ملاحظات امنیتی و توصیه‌ها |
| [`.ai/README.md`](.ai/README.md) | ایجنت‌های توسعه‌دهنده | نقشه محل‌های ذخیره ماندگار PAT مالک و روش بازیابی/بروزرسانی |

---

## ⚙️ تنظیمات مهم پنل (نگهداری تاریخچه و فایل‌ها)

مسیر: **AI-PC Link ← تنظیمات**

- **مدت نگهداری تاریخچه (روز):** فرمان‌ها و لاگ‌های قدیمی‌تر از این مهلت خودکار حذف می‌شوند (۰ = همیشه)
- **مدت نگهداری فایل‌ها (روز):** فایل‌های منتقل‌شده پس از این مهلت از سرور پاک می‌شوند (۰ = همیشه)
- **حداکثر ردیف‌های لاگ / فرمان:** سقف جلوگیری از بزرگ‌شدن دیتابیس
- **حداکثر حجم هر فایل آپلودی (مگابایت):** پیش‌فرض ۲۵۶
- **حداکثر درخواست در دقیقه (هر کلید):** محافظت در برابر سوءاستفاده
- **فاصله Polling ایجنت:** سرعت پاسخ‌دهی در برابر مصرف منابع

پاک‌سازی دستی هم در همان صفحه موجود است (حذف کل تاریخچه / فقط فایل‌ها / رکوردهای قدیمی‌تر از N روز).

---

## 🔧 عیب‌یابی سریع

| مشکل | راه‌حل |
|------|--------|
| ایجنت می‌گوید کلید نامعتبر است | کلید را دوباره از پنل کپی کنید (بدون فاصله اضافه)؛ مطمئن شوید کلید غیرفعال نشده |
| سیستم در پنل آنلاین نمی‌شود | اگر HTTPS مشکل دارد، آدرس را با `http://` وارد کنید — ایجنت v1.1+ خودش هم هنگام خطای SSL به HTTP سوییچ می‌کند؛ اگر سرور Self-signed است، گواهی را به سیستم اعتماد کنید |
| فرمان در صف می‌ماند | ایجنت در حال اجراست؟ کنسول ایجنت را ببینید؛ بخش «سیستم‌های متصل» باید آنلاین باشد |
| آدرس `/url/run` خطای 404 می‌دهد | افزونه سایت هنوز نسخه **2.0.0+** نیست — ZIP جدید را نصب کنید (`/ping` باید `"url_gate": true` برگرداند) |
| خطای حجم فایل | مقدار `post_max_size` و `upload_max_filesize` PHP سرور را افزایش دهید |
| آپلود بزرگ شکست می‌خورد | در تنظیمات پلاگین سقف حجم را کم کنید یا محدودیت PHP را بالا ببرید |
| دستورات مدیر اجرا نمی‌شوند | اکشن `privilege_status` را بزنید؛ در لینوکس `elevation_password` را در config.json بگذارید، در ویندوز روی پیام UAC «Yes» بزنید یا ایجنت را با Run as administrator اجرا کنید |
| متن‌های فارسی کنسول ایجنت به‌هم‌ریخته است | از v1.2 ایجنت خودکار کنسول را UTF-8 می‌کند و در cmd قدیمی ویندوز متن را reshape می‌کند. اگر باز هم مشکل داشت: فونت ترمینال را روی یک فونت یونیکد (مثل Cascadia Mono یا Consolas) بگذارید یا از Windows Terminal استفاده کنید |

راهنمای کامل عیب‌یابی در [`docs/USER-GUIDE.fa.md`](docs/USER-GUIDE.fa.md).

---

## 🗺 نقشه راه

- [x] **v1.0.0** — هسته پلاگین، REST API، پنل فارسی، ایجنت کراس‌پلتفرم، تاریخچه کامل
- [x] **v1.1.0** — fallback خودکار HTTP، ارتقای سطح دسترسی اختیاری، یکپارچگی گیت‌هاب (PAT از پنل)، متن آماده معرفی پل به ایجنت (`/agent-prompt` + داشبورد + README)
- [x] **v1.2.0** — چت‌بات‌های فقط-متنی (حالت relay ایجنت + حالت B در متن اصل ۴)، نمایش دائمی کلیدها و PAT با دکمه کپی، صفحه «جزئیات و اتصال‌ها» هر کلید (سیستم‌های متصل، مشخصات ایجنت، هوش مصنوعی کنترل‌کننده، وضعیت استفاده دوطرفه)، انتخاب کلید + جای‌گذاری خودکار آدرس سایت در متن داشبورد، `ai_model` در ثبت‌نام ایجنت، نمایش صحیح فارسی در کنسول
- [x] **v1.3.0** — گفتگوی مستقیم کاربر با هوش مصنوعی از طریق ایجنت (`chat` + چهار مسیر REST جدید + پنل «گفتگوها»)، احراز هویت Bearer-first (رفع قطع ارتباط روی هاست‌هایی که هدر سفارشی را حذف می‌کنند)، **رفع باگ بحرانی ذخیره‌سازی client_uid**، لینک دانلود امضاشده فایل‌ها (`download_url` بدون نیاز به هدر)، ایجنت تمام-انگلیسی + بدون وابستگی (فقط کتابخانه استاندارد) + مقاوم در برابر خطا (پنجره باز می‌ماند + لاگ کامل)
- [x] **v1.3.1** — رفع باگ 404 ثبت‌نام ایجنت: وقتی کاربر به‌جای ریشه سایت، آدرس کامل REST (`…/wp-json/aclp/v1`) را وارد می‌کرد مسیر دوبله می‌شد (تست زنده دوم مالک)؛ ایجنت حالا هر ورودی را نرمال می‌کند و `config.json` خرابِ ذخیره‌شده را در هر استارت خودترمیم می‌کند — بدون اجرای دوباره setup + متن اصل ۴ و راهنماها صریحاً «فقط ریشه سایت» را می‌گویند
- [x] **v1.4.0** — بازطراحی حرفه‌ای متن اصل ۴ با لحن خنثی «راهنمای ادغام API» (درخواست مالک): حذف عبارات هشداردهنده مثل «کنترل کامل کامپیوتر واقعی» که باعث رد شدن درخواست توسط مدل‌های ایمن‌آموزش‌دیده می‌شد؛ چارچوب جدید: سرویس پردازش کار (job) → ثبت JSON در URL → بررسی نتیجه از URL نتیجه پس از چند ثانیه + خنثی‌سازی AGENT-API.md (نام ابزار نمونه از `pc_control` به `aclp_submit_job`)
- [x] **v2.0.0 (Major به درخواست مالک) — دروازه URL / اصل اساسی ۵:** حالت سوم تعامل برای هوش‌هایی که فقط می‌توانند آدرس باز کنند: `GET /url/run` (دستور در انتهای URL، انتظار تا ۲۵ ثانیه، گزارش متن ساده در همان صفحه) + `GET /url/result` (نتیجه با ticket) + صفحه راهنمای خودکار + فهرست گره‌ها در حالت چندسیستمه + فایل‌های خروجی با لینک امضاشده + `&format=json`؛ متن اصل ۴ بخش MODE C گرفت؛ `/ping` فیلد `url_gate` برگرداند؛ قاعده مالک: در هر تغییر آینده این حالت توسعه و تکمیل می‌شود
- [ ] **v2.1** — WebSocket برای دریافت لحظه‌ای فرمان (بدون polling) + اعلان به چت‌بات با webhook + توسعه بیشتر دروازه URL (broadcast و صف چندفرمانی از طریق URL)
- [ ] **v2.2** — رمزنگاری سرتاسری payload، پشتیبانی macOS، حالت تأیید دستوری اختیاری
- [ ] **v2.3** — اجرای زمان‌بندی‌شده فرمان‌ها، گروه‌بندی سیستم‌ها، نقش‌های کاربری

پیشنهادهای شما هم خوشآمدید — [Issue بسازید](https://github.com/Tobeseuss/ai-chatbot-link-to-pc/issues).

---

## 🤝 توسعه این پروژه با هوش مصنوعی

این پروژه با پنج اصل بنیادین مدیریت می‌شود (شرح کامل در [`project.md`](project.md)):

1. **پس از هر تغییر:** مستندات، worklog و project.md باید بروز شوند تا ایجنت‌های آینده بتوانند ادامه دهند + فایل `brainstorm.md` تاریخچه گفتگوها را ثبت می‌کند.
2. **پس از هر تغییر:** README و مستندات آموزش کاربر و ایجنت‌ها بروز می‌شوند.
3. **پس از هر تغییر:** کد کامیت و پوش می‌شود و نسخه جدید پلاگین + ایجنت (با رعایت semver) به‌صورت ZIP در Releases منتشر می‌گردد. قبل از پوش، کامیت‌های ریموت بررسی و به کاربر گزارش می‌شود؛ ایجنت‌های متصل می‌توانند خودشان این چرخه را با PAT تنظیمات پلاگین انجام دهند.
4. **پس از هر تغییر:** `docs/AGENT-API.md` بروز می‌شود و بخش «نحوه اعلام نحوه استفاده از پل به ایجنت هوش مصنوعی» در ابتدای همین README و در داشبورد پلاگین هماهنگ بروزرسانی می‌شود.
5. **پس از هر تغییر (اصل اساسی ۵):** **دروازه URL** (حالت تعامل فقط با باز کردن آدرس) باید توسعه و تکمیل شود — هر قابلیت جدید باید از طریق URL هم قابل استفاده باشد (یا معادل URL-friendly داشته باشد)، مستندات باید نمونه URL داشته باشند و متن اصل ۴ بخش MODE C را به‌روز نگه دارد.

## 📄 مجوز

GPL-2.0-or-later — متن کامل در [LICENSE](LICENSE).
