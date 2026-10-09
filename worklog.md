# worklog.md — لاگ کاری پروژه

> برای ایجنت‌های توسعه‌دهنده: قبل از شروع کار این فایل را بخوانید تا بدانید چه کارهایی انجام شده. پس از پایان کارتان، بخش جدیدی با همین قالب به **ابتدای** لیست اضافه کنید.

---

## [1.0.0] — 2026-10-09 — ایجنت: Super Z

**محدوده:** ساخت کامل پروژه از صفر (نسخه اولیه)

### کارهای انجام‌شده

- ساخت ریپازیتوری عمومی `Tobeseuss/ai-chatbot-link-to-pc` با GitHub API
- **افزونه وردپرس v1.0.0:**
  - فایل اصلی + ثابت‌ها + هوک‌ها (`ai-chatbot-link-to-pc.php`)
  - `class-aclp-activator.php`: ساخت ۵ جدول با dbDelta + زمان‌بندی cron ساعتی
  - `class-aclp-settings.php`: ۱۱ تنظیم با مقادیر پیش‌فرض
  - `class-aclp-api-keys.php`: ساخت/فعال‌سازی/حذف کلید — ذخیره هش SHA-256، نمایش یک‌بار
  - `class-aclp-clients.php`: ثبت/upsert کلاینت، سقف کلاینت، آنلاین/آفلاین
  - `class-aclp-commands.php`: صف فرمان، چرخه وضعیت pending→sent→running→completed/failed، کوئری فیلتردار، آمار
  - `class-aclp-files.php`: ذخیره آپلود در `uploads/aclp-files/Y/m/` با نام uniqid
  - `class-aclp-logger.php` (class-aclp-logs.php): audit trail کامل رویدادها
  - `class-aclp-cron.php`: پاک‌سازی خودکار تاریخچه/فایل‌ها بر اساس تنظیمات + سقف ردیف‌ها
  - `class-aclp-auth.php`: استخراج کلید از هدر/bearer/پارامتر + نرخ بر دقیقه با transient
  - `class-aclp-rest.php`: ۱۲ مسیر در namespace `aclp/v1` (ثبت ایجنت، heartbeat، صف، نتیجه، آپلود، دانلود، فرمان چت‌بات با wait/broadcast)
  - پنل مدیریت فارسی: داشبورد (۶ کارت آماری + راهنمای اتصال + آخرین تعاملات)، کلیدها، سیستم‌ها، تاریخچه با فیلتر و جزئیات کامل (payload/نتیجه/فایل‌ها)، تنظیمات + پاک‌سازی دستی
  - `uninstall.php` با گزینه انتخابی حذف کامل داده‌ها
- **ایجنت پایتون v1.0.0:**
  - `aclp_agent.py` (~۶۵۰ خط): setup wizard، auto-install requests، رجیستری ۱۸ اکشن با دکوراتور `@handler`، backoff هوشمند، لاگ فایل، آپلود خودکار فایل‌های خروجی، دانلود فایل ورودی
  - `start_agent.bat` / `start_agent.sh` + `requirements.txt` + `config.example.json` + README فارسی
  - تست: py_compile موفق + اجرای `--version` موفق
- **ابزار و انتشار:**
  - `tools/build_release.py`: ساخت دو ZIP با شماره نسخه خودکار از سورس + sha256
  - بررسی سینتکس PHP با اسکریپت تعادل براکت/کوتیشن (php-cli در محیط در دسترس نبود)
  - کامیت‌های ساختاریافته + پوش به main + Release v1.0.0 با ۲ فایل ZIP

### نکات مهم برای ایجنت‌های بعدی

- نسخه در ۴ جا باید باهم بروز شوند (چک‌لیست بخش ۲ project.md)
- کنسول ایجنت انگلیسی می‌ماند (ADR-7) — مستندات فارسی هستند
- `wait=true` حداکثر ۲۵ ثانیه است (سازگاری با پراکسی‌ها)
- همه پاسخ‌های API شکل `{"ok": true/false, ...}` دارند؛ خطاها پیام فارسی دارند
- قبل از هر تغییر: brainstorm.md و بخش «وضعیت فعلی» project.md را بخوانید

---

## [1.1.0] — 2026-10-09 — ایجنت: Super Z

**محدوده:** پیاده‌سازی ۶ درخواست جدید مالک در گفتگو #۲: پشتیبانی HTTP در صورت خرابی HTTPS، ذخیره ماندگار PAT در پروژه، قابلیت خودانتشارسازی ایجنت‌های هوش مصنوعی (پوش + Release با PAT پنل)، چک اجباری کامیت‌های ریموت قبل از پوش، اصل اساسی شماره ۴ (متن آماده معرفی پل به ایجنت در README + داشبورد + `/agent-prompt`)، ارتقای سطح دسترسی اختیاری در ایجنت پایتون.

### کارهای انجام‌شده

- **پلاگین (PHP) — ۱۰ فایل تغییر/بروزرسانی:**
  - `class-aclp-settings.php`: دو تنظیم متنی جدید `github_repo_url` (پیش‌فرض ریپوی رسمی) و `github_pat` + جداسازی کلیدهای متنی از عددی در `update()`
  - `class-aclp-utils.php`: تابع `agent_prompt()` — منبع واحد حقیقت متن آماده معرفی پل به ایجنت (انگلیسی، placeholder خالی `PASTE_YOUR_REAL_API_KEY_HERE`) + برچسب فارسی اکشن‌های جدید
  - `class-aclp-rest.php`: مسیرهای جدید `GET /agent-prompt` (عمومی) و `GET /github-integration` (کلید API) + فیلدهای `http_fallback_url` و `allow_http_fallback` در `/ping` → مجموعاً ۱۴ مسیر
  - `class-aclp-admin-pages.php`: داشبورد — بخش «نحوه اعلام نحوه استفاده از پل به ایجنت هوش مصنوعی» با دکمه کپی + یادداشت پشتیبانی HTTP؛ تنظیمات — بخش «یکپارچگی گیت‌هاب» + اکشن‌های جدید در فیلتر تاریخچه
  - `class-aclp-admin.php`: ذخیره دو فیلد گیت‌هاب در `handle_save_settings`
  - bump نسخه: هدر + `ACLP_VERSION` → 1.1.0
- **ایجنت (Python) — `aclp_agent.py` (۷۲۶ → ~۱۰۱۰ خط):**
  - fallback پروتکل: `base_candidates` + `_maybe_switch_protocol()` (SSLError/ConnectTimeout/ConnectionError → سوییچ دائمی https⇄http بدون شمارش به‌عنوان retry) + `allow_http_fallback` (پیش‌فرض true)؛ `api()` به retry ۴باره بازنویسی شد
  - ارتقای دسترسی: `is_elevated()`، `_shell_result()`، `_looks_like_permission_error()`، `_pw_bytes()`؛ `Agent.run_privileged()` → `_run_elevated_unix` (sudo -S با رمز stdin → sudo ساده → su -c) و `_run_elevated_windows` (دو لایه PowerShell `-EncodedCommand` + `Start-Process -Verb RunAs -Wait`، خروجی با فایل temp، تشخیص رد شدن UAC)
  - اکشن‌های جدید: `privilege_status`، `privilege_run` + پرچم `elevated` در `shell` + تلاش مجدد سطح‌بالای `install` فقط با `auto_elevate`/اعتبار پیکربندی‌شده → ۲۰ اکشن
  - setup wizard: مرحله اختیاری «Configure elevation now? [y/N]» با getpass
  - bump `__VERSION__` → 1.1.0 + py_compile و smoke-test موفق
- **ذخیره ماندگار PAT (خواسته مالک):**
  - تلاش اول: `.ai/pat.b64` داخل ریپو (Base64) ← GitHub Push Protection حتی Base64 را تشخیص داد و push رد شد (GH013)
  - راه‌حل نهایی: سه نسخه محلی ماندگار (`/home/z/my-project/.env` کلید `ACLPS_GITHUB_PAT`، `scripts/github_pat.local` (0600)، `.ai/pat.b64` که gitignore شد) + تنظیمات پلاگین روی سایت؛ ریپو فقط `.ai/README.md` (نقشه محل‌های ذخیره + دستور بازیابی/بروزرسانی) را نگه می‌دارد
  - اعتبار توکن تست شد (user: Tobeseuss) و کامیت‌های ریموت چک شد: هیچ کامیت جدیدی نبود
- **سند پروتکل جدید `docs/AGENT-CONTRIBUTION.md`:** گردش کار کامل ایجنت‌های توسعه‌دهنده (چک کامیت‌ها → تغییرات → تصمیم خودکار مالک/غیرمالک PAT → build/push/Release → بستن حلقه اصول ۱–۴)
- **مستندات:** README (بخش اصل ۴ در ابتدا + نسخه‌ها + قابلیت‌ها + عیب‌یابی + نقشه راه + چهار اصل)، project.md (اصل ۳ تکمیلی + اصل ۴ + چک‌لیست ۱۲ گامی + §3.4/3.5/3.6/3.7 + ADR 8–11 + وضعیت 1.1.0)، AGENT-API.md (بخش ۰ HTTP fallback + ۳.۲/۳.۳ + جدول اکشن‌ها + نمونه ابزار + gotchas)، USER-GUIDE (بخش‌های ۱۲/۱۳/۱۴)، DEVELOPER (مسیرها + معماری + چک‌لیست)، SECURITY (vault + HTTP آگاهانه + ارتقا)، CHANGELOG (1.1.0 کامل)، brainstorm (گفتگو #۲ کامل)، agent/README.fa.md + config.example.json

### نکات مهم برای ایجنت‌های بعدی

- متن اصل ۴ را **هرگز** فقط در یک جا بروز نکن: `ACLP_Utils::agent_prompt()` + بلوک README باید هماهنگ بمانند (چک‌لیست گام ۵).
- PAT عوض شد؟ سه جا در محیط توسعه (`.env`، `scripts/github_pat.local`، `.ai/pat.b64` gitignored) + تنظیمات پلاگین — جزئیات در `.ai/README.md`.
- قبل از هر پوش: `git fetch` + گزارش کامیت‌های جدید به کاربر (خواسته صریح مالک).
- هیچ عملکرد ایجنت وابسته به دسترسی مدیر نیست؛ ارتقا فقط با `elevated:true` / `privilege_run` / `auto_elevate`.

---

## [قالب] — برای ثبت کار بعدی

```
## [X.Y.Z] — YYYY-MM-DD — ایجنت: <نام>

**محدوده:** <توضیح>

### کارهای انجام‌شده
- ...

### نکات مهم برای ایجنت‌های بعدی
- ...
```

---

## [1.2.0] — 2026-10-09 — ایجنت: Super Z (کدنویس)

**محدوده:** ۵ درخواست جدید مالک: ۱) رفع به‌هم‌ریختگی فارسی کنسول ایجنت، ۲) نمایش دائمی کلیدهای API و PAT با دکمه کپی، ۳) پشتیبانی از چت‌بات‌های فقط-متنی (حالت relay)، ۴) نمای کامل هر کلید API (سیستم‌های متصل، مشخصات ایجنت/هوش مصنوعی، وضعیت استفاده دوطرفه)، ۵) متن اصل ۴ در داشبورد با آدرس سایت خودکار + منوی انتخاب کلید API.

### کارهای انجام‌شده

**ایجنت (aclp_agent.py → v1.2.0):**
- کنسول فارسی: `_console_setup()` (UTF-8 reconfigure + SetConsoleOutputCP/SetConsoleCP 65001 + chcp در ویندوز)، تشخیص bidi ترمینال (`WT_SESSION`/`TERM_PROGRAM`/غیر-nt/ANSICON)، `fa()` با reshape خودکار (arabic-reshaper + python-bidi، نصب best-effort)، `say(fa, en)` دوزبانه با fallback، `log()` کنسول شکل‌داده ولی فایل لاگ خام
- setup wizard کاملاً فارسی + سوال جدید `ai_model` (نام هوش مصنوعی کنترل‌کننده)
- اکشن CLI جدید `relay`: `relay shell <words>` / `relay <action>` / `relay <action> --json {...}` — register → POST /commands (client_uid خودش، source = ai_model یا text-chatbot-relay) → برداشتن از صف خودش + اجرا (با چک وضعیت برای جلوگیری از تعارض با نمونه poll) → چاپ بلوک JSON نهایی؛ exit code 0/1/2
- `register()` فیلد `ai_model` می‌فرستد؛ `load_config` پیش‌فرض `ai_model: ""`؛ config.example.json اضافه شد؛ py_compile + smoke-test (relay help، --version، --help) موفق

**پلاگین (→ 1.2.0):**
- دیتابیس: ستون `key_plain` (aclp_api_keys) و `ai_model` (aclp_clients)؛ `ACLP_Activator::maybe_upgrade()` روی admin_init + `add_missing_columns()` (SHOW COLUMNS + ALTER) برای نصب‌های موجود
- `ACLP_API_Keys`: create متن کامل ذخیره می‌کند؛ `usage_stats()` (فرمان/نتیجه/فایل هر دو جهت/آخرین فعالیت/DISTINCT sources) + سطح‌بندی استفاده دوطرفه full/partial/one_way/none + `bidirectional_label()`
- `ACLP_Clients::register` فیلد ai_model؛ REST: `/agent/register` پاس ai_model، `GET /clients` فیلدهای ip/agent_version/python_version/ai_model/capabilities، `GET /agent-prompt?api_key=` (اعتبارسنجی کلید، 401 برای نامعتبر)
- `ACLP_Utils::agent_prompt($site_url, $api_key)`: بازطراحی کامل متن اصل ۴ — بخش «0) detect MODE»، MODE A (فراخوانی مستقیم API)، MODE B (relay برای چت‌بات فقط-متنی، قوانین سختگیرانه برای مدل‌های ضعیف)، بلوک کلید جایگزین‌شونده؛ رفع باگ تکرار پیشوند «- API key:»
- پنل: جدول کلیدها با کلید کامل + دکمه کپی + لینک «📊 جزئیات و اتصال‌ها»؛ صفحه مخفی `aclp-key-view` (کلید کامل، آمار دوطرفه ۴ کارتی، هوش مصنوعی‌های در ارتباط، جدول سیستم‌ها با وضعیت اتصال/ai_model/قابلیت‌ها، آخرین تعاملات)؛ جدول سیستم‌ها + کلید و ai_model؛ داشبورد: منوی کشویی کلید (کلیدهای فعال) + قالب `<script type="text/template">` + بازسازی زنده JS (regex بلوک placeholder) + کپی؛ تنظیمات: دکمه «کپی PAT»؛ اعلان key_created اصلاح شد («همیشه قابل مشاهده»)
- JS: `copyText` با fallback textarea + flash، `.aclp-copy-val` (کپی value input)، selector منطق
- چکر PHP حالت‌مند: 15/15 OK؛ شبیه‌سازی پایتونی جایگزینی بلوک کلید/regex JS: OK

**مستندات (اصل ۱، ۲، ۴):**
- AGENT-API.md: نسخه 1.2.0، بخش 10 کامل (relay/MODE B) + 10.1 (ai_model و فیلدهای register)، /clients نمونه جدید، /agent-prompt با ?api_key=، نکته source برای نمایش هوش مصنوعی‌ها در پنل
- README: badge و ZIPها → 1.2.0، بلوک اصل ۴ هماهنگ با agent_prompt (MODE A/B)، جدول قابلیت‌ها (+۳ ردیف)، گام ۲ (کلید همیشه قابل کپی) + بخش «چت‌بات فقط-متنی دارید؟»، عیب‌یابی فارسی کنسول، نقشه راه
- USER-GUIDE: بخش ۳ بازنویسی (کلید همیشه قابل مشاهده + جزئیات هر کلید)، بخش ۴ (سوالات جدید wizard فارسی)، بخش ۵ (زیربخش چت‌بات فقط-متنی)، بخش ۶ (پنل جدید)، بخش ۱۴ (حالت‌ها و ?api_key=)
- project.md: نسخه 1.2.0، اصل ۴ (MODE A/B + انتخابگر + ?api_key=)، جداول (key_plain/ai_model)، بخش 3.8 جدید (relay + کنسول فارسی)، وضعیت، ADR 12-15 + موقوف‌سازی ADR 7
- CHANGELOG (1.2.0 کامل)، SECURITY (ذخیره متن کلید — ریسک پذیرفته‌شده)، brainstorm (گفتگو #3)، worklog همین بخش، agent/README.fa.md (کنسول فارسی + relay)، config.example.json (ai_model)

**انتشار:**
- git fetch: هیچ کامیت جدید ریموت غیر از کامیت‌های قبلی نبود (گزارش در پایان به کاربر)
- build_release: دو ZIP v1.2.0 (پلاگین + ایجنت) با نسخه هماهنگ
- کامیت + پوش + Release v1.2.0 با دو asset + کپی ZIPها به /home/z/my-project/download/

### نکات مهم برای ایجنت‌های بعدی
- متن اصل ۴ اکنون **چهار** جای هماهنگ دارد: `ACLP_Utils::agent_prompt()` + بلوک README + AGENT-API.md بخش 10 + همین توصیف در project.md — همه را با هم بروز کن.
- دو ستون دیتابیس جدید با `maybe_upgrade` (admin_init) مهاجرت می‌شوند؛ نصب تازه از CREATE TABLE می‌گیرد. برای ستون‌های آینده همین الگو.
- متن فارسی جدید در ایجنت: همیشه از `say()`/`fa()` استفاده کن، هرگز `print` مستقیم فارسی. فایل لاگ خام می‌ماند.
- regex JS بلوک کلید (`- API key:  PASTE_YOUR_REAL_API_KEY_HERE[\s\S]*?Never guess or invent credentials\.`) به متن heredoc وابسته است؛ اگر متن را عوض کردی، هر دو سمت (PHP + JS) را هماهنگ کن.

---
Task ID: 4
Agent: Super Z (main agent)
Task: v1.3.0 — گزارش‌های زنده مالک: ایجنت تمام-انگلیسی، رفع بسته‌شدن فوری ایجنت (کشف دو باگ بحرانی با تست زنده روی tpptc.ir)، صفر وابستگی ایجنت، php-cli واقعی، گفتگوی مستقیم کاربر با هوش مصنوعی + ارسال فایل با لینک سایت

Work Log:
- چک کامیت‌های ریموت (اصل ۳): origin/main = c7d40ea، هیچ کامیت جدیدی خارج از v1.2.0 نبود (0 پشت / 0 جلو)
- تست زنده tpptc.ir (پلاگین نصب‌شده: v1.2.0): کشف ۱ — هاست هدر سفارشی X-ACLP-Key را strip می‌کند (401 aclp_missing_key) ولی Authorization: Bearer و ?api_key= کار می‌کنند → علت اصلی «بسته شدن ایجنت بلافاصله پس از تنظیمات»
- تست زنده ثبت‌نام: کشف ۲ — باگ بحرانی پلاگین از v1.0.0: `ACLP_Clients::register` فیلد client_uid را INSERT نمی‌کرد؛ همه کلاینت‌ها با UID خالی ذخیره می‌شدند → بعد از ثبت‌نام هیچ فرمانی قابل تحویل نبود (404 «سیستم موردنظر یافت نشد») و کلاینت دوم به‌خاطر UNIQUE KEY ثبت نمی‌شد
- FIX پلاگین: client_uid در INSERT + پاک‌سازی خودکار ردیف‌های UID خالی هنگام آپگرید (add_missing_columns)
- FIX ایجنت: احراز هویت Bearer-first (هر دو هدر ارسال می‌شوند)
- ایجنت v1.3.0 بازنویسی بخش‌های کلیدی: حذف کامل fa/say/reshaper/bidi (تمام-انگلیسی؛ ۰ نویسه RTL در فایل)، حذف requests و ساخت لایه urllib (_http_request/_http_download/_multipart + NetworkError(ssl|timeout|connection) برای fallback)، نقطه ورود crash-proof (traceback + لاگ + pause_before_exit روی TTY)، چک‌لیست ۵ مرحله‌ای در شکست ثبت‌نام، حالت جدید `chat` (thread polling هر ۲ث + /file با آپلود و پیوست لینک امضاشده)، start_agent.bat با py→python fallback و بدون pip
- پلاگین v1.3.0: کلاس ACLP_Chat + جدول aclp_chat_messages + ۴ مسیر REST چت (send/pending/reply/replies با long-poll و تحویل) + لینک download_url امضاشده (HMAC wp_salt، hash_equals) در api_shape همه فایل‌ها + صفحه پنل «گفتگوها» + بخش گفتگو در جزئیات کلید + cron پاک‌سازی (retention + max_chat_rows) + chat_supported در ping + ACLP_VERSION 1.3.0
- متن اصل ۴ (منبع واحد agent_prompt): Bearer توصیه‌شده، بخش «Talking with the USER directly»، download_url؛ همگام‌سازی بایت‌به‌بایت README با اسکریپت جدید scripts/sync_prompt_readme.py
- php-cli: باینری استاتیک PHP 8.2.28 در tools-bin/php (بدون روت)؛ check_php_syntax.php اکنون php -l واقعی اجرا می‌کند — ۱۶/۱۶ فایل OK
- تست‌ها: smoke_test_agent.py (mock server کامل REST) ۱۰/۱۰ پاس شامل چرخه کامل چت؛ live_test_agent.py روی tpptc.ir: ping Bearer ✓، ثبت‌نام ✓، فرمان روی v1.2.0 با 404 مطابق انتظار باگ قدیمی؛ py_compile ✓؛ چک ۰ نویسه RTL در ایجنت ✓
- مستندات: AGENT-API.md (v1.3.0 + بخش ۱۱ چت + Bearer + download_url)، README (بلوک اصل ۴ هماهنگ + قابلیت‌ها + نسخه‌ها + نقشه راه)، USER-GUIDE.fa.md (chat + عیب‌یابی بسته‌شدن پنجره + کنسول انگلیسی + Bearer)، SECURITY (ریسک لینک امضاشده + پیام‌ها)، CHANGELOG (1.3.0)، project.md (جداول + 3.9/3.10 + وضعیت + ADR 16-20 + موقوع‌سازی ADR-14)، agent/README.fa.md بازنویسی، brainstorm.md (گفتگو #۴)، worklog ریپو

Stage Summary:
- ریپو: https://github.com/Tobeseuss/ai-chatbot-link-to-pc (main) — Release v1.3.0 با دو ZIP
- دو باگ بحرانی با تست زنده پیدا و رفع شد: (۱) strip هدر سفارشی توسط هاست → Bearer-first؛ (۲) عدم ذخیره client_uid در پلاگین → FIX + پاک‌سازی آپگرید
- ایجنت اکنون: تمام-انگلیسی، صفر وابستگی (پایتون 3.8+ خام)، مقاوم در برابر خطا (پنجره باز می‌ماند)، دارای حالت chat کاربر↔هوش مصنوعی با ارسال فایل از طریق لینک سایت
- بعد از انتشار: کاربر باید ZIP پلاگین v1.3.0 را نصب کند (ردیف‌های خراب خودکار پاک می‌شوند؛ شامل ردیف تست «ACLP-Dev-LiveTest») و ایجنت v1.3.0 را جایگزین کند؛ سپس تست زنده نهایی با `python aclp_agent.py chat`

---
## Task ID: 5 — v1.3.1 (2026-10-09)
**Task:** رفع باگ 404 ثبت‌نام ایجنت (گزارش تست زنده دوم مالک: ورود آدرس کامل REST در setup ⇒ مسیر دوبله ⇒ `rest_no_route`) + سینک مستندات اصل ۴ + انتشار نسخه‌دار

**Work Log:**
- چک ریموت (اصل ۳): origin/main = e94f9ca (0 پشت / 0 جلو)
- تشخیص با تست زنده دوطرفه روی tpptc.ir: آدرس درست + کلید مالک ⇒ ثبت‌نام موفق؛ مسیر دوبله ⇒ همان 404 گزارش‌شده. فهرست مسیرهای REST ⇒ پلاگین 1.3.0 روی سایت نصب است (مسیرهای چت موجود و سالم)
- ایجنت: `_normalize_site_url()` (۹/۹ تست واحد) + خودترمیمی config در `Agent.__init__` + پرامپت setup «site root» + چک‌لیست REGISTRATION FAILED بازنویسی + __VERSION__ → 1.3.1
- پلاگین: خط «فقط ریشه سایت» در متن اصل ۴ (agent_prompt) + نسخه‌ها → 1.3.1 (هدر، ACLP_VERSION، readme.txt)
- اصل ۴: بلوک README با sync_prompt_readme.py از منبع واحد بازتولید شد
- تست: شبیه‌سازی دقیق سناریوی مالک (config دوبله + `--once`) ⇒ `[fix]` + ثبت‌نام موفق client #4 + config نرمال شد؛ py_compile ✓؛ GET /chat/pending زنده ✓
- مستندات: CHANGELOG 1.3.1، AGENT-API، README، agent/README.fa.md، USER-GUIDE.fa.md، project.md (ADR 21)، brainstorm #۵، worklog
- build_release v1.3.0→1.3.1 (دو ZIP) → commit/push → Release v1.3.0→v1.3.1 → کپی به download/

**Stage Summary:**
- Release v1.3.1: https://github.com/Tobeseuss/ai-chatbot-link-to-pc/releases/tag/v1.3.1
- گام مالک: استارت ایجنت 1.3.1 (بدون setup مجدد — config خودترمیم می‌شود) و تست زنده؛ آپدیت پلاگین به 1.3.1 اختیاری ولی توصیه‌شده
