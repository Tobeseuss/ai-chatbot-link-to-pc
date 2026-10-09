# AI Chatbot Link to PC

> پل ارتباطی بین چت‌بات‌های هوش مصنوعی و سیستم‌عامل کاربر (ویندوز / لینوکس) — افزونه وردپرس + ایجنت پایتون

[![Version](https://img.shields.io/badge/version-1.2.0-blue.svg)](https://github.com/Tobeseuss/ai-chatbot-link-to-pc/releases)
[![License](https://img.shields.io/badge/license-GPL--2.0-green.svg)](LICENSE)
[![Platform](https://img.shields.io/badge/platform-Windows%20%7C%20Linux-lightgrey.svg)]()

---

## 🤖 نحوه اعلام نحوه استفاده از پل به ایجنت هوش مصنوعی (اصل اساسی ۴)

**این بخش با هر تغییری بروز می‌شود.** متن آماده زیر را کپی کنید و به هر هوش مصنوعی / LLM ای که می‌خواهید کنترل سیستم شما را داشته باشد بدهید؛ حتی مدل‌های ضعیف و محدود و **چت‌بات‌های فقط-متنی** (که نمی‌توانند کد اجرا کنند) هم با همین متن می‌توانند پل را راه بیندازند (حالت B). جای `API key` عمداً خالی است تا خود ایجنت از شما سوال کند و آن را از پنل وردپرس (AI-PC Link ← کلیدهای API) بگیرد — در داشبورد پلاگین می‌توانید کلید موردنظر را از منوی کشویی انتخاب کنید تا متن با همان کلید ساخته و کپی شود و آدرس سایت هم خودکار جای‌گذاری می‌شود. همین متن از مسیر `GET {آدرس‌سایت}/wp-json/aclp/v1/agent-prompt` هم در دسترس است و همیشه با نسخه افزونه هماهنگ است.

```text
# INSTRUCTION FOR THE AI AGENT (PC bridge — ACLP v1.3.1)

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
- NOTE about the PYTHON AGENT (for the user, not for you): when the agent's first-run setup asks
  for "WordPress site URL", the user must enter ONLY the site root https://YOUR-SITE.com — NEVER this
  /wp-json/... REST path. Agent v1.3.1+ also repairs a wrong entry automatically on start.

=====================================================================
## MODE A — you can execute code (Python/curl/etc.)
=====================================================================

### How to call the PC (auth header)
    Authorization: Bearer <the API key>      (preferred — standard header)
    X-ACLP-Key: <the API key>                (also accepted)
    Content-Type: application/json           (for JSON bodies)

1) Send one action:  POST https://YOUR-SITE.com/wp-json/aclp/v1/commands
   Body: {"type": "<action>", "payload": {...}, "wait": true, "timeout": 25}
   - "wait": true makes the HTTP call block (max 25s) and return the finished result.
2) If the result is not ready yet (status "pending"/"running"), poll:
   GET https://YOUR-SITE.com/wp-json/aclp/v1/commands/{command_uid}   every 3-5 seconds.
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
   POST https://YOUR-SITE.com/wp-json/aclp/v1/chat/reply
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
- Full API reference (open it if unsure): https://github.com/Tobeseuss/ai-chatbot-link-to-pc/blob/main/docs/AGENT-API.md
- Project repository: https://github.com/Tobeseuss/ai-chatbot-link-to-pc
- If you need a capability that the bridge does not have yet, tell the user; qualified agents
  may extend the project itself (see docs/AGENT-CONTRIBUTION.md in the repository).
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
| 🌍 **کنسول تمام-انگلیسی (از v1.3.0)** | پیام‌های ایجنت فقط انگلیسی است تا مشکل نمایش متن‌های فارسی/RTL در cmd قدیمی ویندوز برای همیشه حذف شود؛ خروجی دستورات به همان شکل (UTF-8) منتقل می‌شود |
| 🤖 **توسعه‌پذیری توسط خود ایجنت‌ها** | ایجنت‌های متصل می‌توانند با PAT قابل‌تنظیم در پنل، تغییرات را کامیت و نسخه جدید منتشر کنند (`docs/AGENT-CONTRIBUTION.md`) |
| 🔄 **آپدیت آسان** | نسخه‌بندی معنایی (semver) + فایل‌های ZIP آماده در بخش Releases به‌همراه ایجنت به‌روزشده |

---

## 🚀 شروع سریع (۳ گام)

### گام ۱ — نصب افزونه در وردپرس

1. از بخش [Releases](https://github.com/Tobeseuss/ai-chatbot-link-to-pc/releases) فایل `ai-chatbot-link-to-pc-v1.3.1.zip` را دانلود کنید.
2. در وردپرس: **افزونه‌ها ← افزودن ← بارگذاری افزونه** و فایل ZIP را نصب و فعال کنید.
3. منوی جدید **«AI-PC Link»** در پیشخوان ظاهر می‌شود.

### گام ۲ — ساخت کلید API و نصب ایجنت

1. **AI-PC Link ← کلیدهای API ← ساخت کلید جدید** — کلید ساخته می‌شود و از همان‌جا و در هر زمان آینده قابل مشاهده و کپی است (دکمه «کپی» کنار کلید).
2. فایل `aclp-agent-v1.3.1.zip` را از Releases دانلود و روی سیستم خود (ویندوز/لینوکس) استخراج کنید.
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

---

## 📦 دانلود و نصب

| فایل | کاربرد | محل |
|------|--------|-----|
| `ai-chatbot-link-to-pc-v1.3.1.zip` | افزونه وردپرس | [Releases](https://github.com/Tobeseuss/ai-chatbot-link-to-pc/releases) |
| `aclp-agent-v1.3.1.zip` | ایجنت سیستم (پایتون — بدون هیچ وابستگی) | [Releases](https://github.com/Tobeseuss/ai-chatbot-link-to-pc/releases) |

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
- [ ] **v1.4** — WebSocket برای دریافت لحظه‌ای فرمان (بدون polling) + اعلان به چت‌بات با webhook
- [ ] **v1.5** — رمزنگاری سرتاسری payload، پشتیبانی macOS، حالت تأیید دستوری اختیاری
- [ ] **v1.6** — اجرای زمان‌بندی‌شده فرمان‌ها، گروه‌بندی سیستم‌ها، نقش‌های کاربری

پیشنهادهای شما هم خوشآمدید — [Issue بسازید](https://github.com/Tobeseuss/ai-chatbot-link-to-pc/issues).

---

## 🤝 توسعه این پروژه با هوش مصنوعی

این پروژه با چهار اصل بنیادین مدیریت می‌شود (شرح کامل در [`project.md`](project.md)):

1. **پس از هر تغییر:** مستندات، worklog و project.md باید بروز شوند تا ایجنت‌های آینده بتوانند ادامه دهند + فایل `brainstorm.md` تاریخچه گفتگوها را ثبت می‌کند.
2. **پس از هر تغییر:** README و مستندات آموزش کاربر و ایجنت‌ها بروز می‌شوند.
3. **پس از هر تغییر:** کد کامیت و پوش می‌شود و نسخه جدید پلاگین + ایجنت (با رعایت semver) به‌صورت ZIP در Releases منتشر می‌گردد. قبل از پوش، کامیت‌های ریموت بررسی و به کاربر گزارش می‌شود؛ ایجنت‌های متصل می‌توانند خودشان این چرخه را با PAT تنظیمات پلاگین انجام دهند.
4. **پس از هر تغییر:** `docs/AGENT-API.md` بروز می‌شود و بخش «نحوه اعلام نحوه استفاده از پل به ایجنت هوش مصنوعی» در ابتدای همین README و در داشبورد پلاگین هماهنگ بروزرسانی می‌شود.

## 📄 مجوز

GPL-2.0-or-later — متن کامل در [LICENSE](LICENSE).
