# project.md — سند پروژه AI Chatbot Link to PC

> **این سند برای ایجنت‌های هوش مصنوعی توسعه‌دهنده در آینده نوشته شده است.** قبل از هر تغییری، این فایل را کامل بخوانید. آخرین بروزرسانی: 2026-10-09 (نسخه 1.0.0)

---

## 1. نمای کلی

**نام:** AI Chatbot Link to PC
**مخزن:** https://github.com/Tobeseuss/ai-chatbot-link-to-pc
**هدف:** ایجاد پل ارتباطی کامل و بدون محدودیت بین چت‌بات‌های هوش مصنوعی و سیستم‌عامل کاربر (ویندوز/لینوکس)؛ چت‌بات بتواند از طریق افزونه وردپرس، هر کاری روی سیستم کاربر انجام دهد: اجرای دستور، کار با فایل، نصب برنامه، دانلود/آپلود، مرور وب و هر اقدام دیگری.

**اجزا:**

| جزء | زبان | محل | نقش |
|-----|------|-----|-----|
| افزونه وردپرس | PHP | `ai-chatbot-link-to-pc/` | سرور مرکزی: REST API، کلیدها، صف فرمان، فایل‌ها، تاریخچه، پنل مدیریت |
| ایجنت | Python 3.9+ | `agent/` | روی سیستم کاربر اجرا می‌شود، فرمان‌ها را برمی‌دارد و اجرا می‌کند |
| مستندات | Markdown | `docs/` + فایل‌های ریشه | راهنمای کاربر، مرجع API ایجنت‌ها، سند توسعه |

## 2. اصول بنیادین (غیرقابل نقض — خواسته مالک پروژه)

### اصل اساسی شماره ۱ — بروزرسانی مستندات داخلی
بعد از هر تغییری، تمام مستندات، worklog ها و project.md ها جهت توسعه‌های آتی توسط هوش مصنوعی باید بروزرسانی و آپدیت شوند. فایل `brainstorm.md` نیز باید همیشه داشته باشیم و در آن **تاریخچه تمام گفتگوهای بین کاربر و ایجنت هوش مصنوعی** مستند شود.

### اصل اساسی شماره ۲ — بروزرسانی مستندات کاربر
بعد از هر تغییری، فایل README و مستندات و فایل‌های md آموزش کار با افزونه (برای کاربر انسانی و برای ایجنت‌های هوش مصنوعی) باید بروزرسانی و آپدیت شوند.

### اصل اساسی شماره ۳ — انتشار نسخه‌دار
بعد از هر تغییری، تغییرات باید در ریپو کامیت و پوش شوند و نسخه جدید پلاگین به همراه نسخه جدید برنامه ایجنت (که کاربر اجرا می‌کند) با رعایت اصول ورژن‌بندی، به صورت فایل ZIP در بخش Releases در دسترس قرار گیرد.

**چک‌لیست اجباری هر تغییر** (به ترتیب):

1. کد را تغییر بده
2. شماره نسخه را در این ۴ جا بروز کن: هدر `ai-chatbot-link-to-pc.php` (Version)، ثابت `ACLP_VERSION`، متغیر `__VERSION__` در `agent/aclp_agent.py`، سند CHANGELOG.md
3. `project.md` را بروز کن (بخش وضعیت و تصمیمات)
4. `worklog.md` را با یک بخش جدید بروز کن
5. `brainstorm.md` را با خلاصه گفتگو بروز کن
6. `README.md` و مستندات `docs/` را هماهنگ با تغییرات بروز کن
7. `CHANGELOG.md` را بروز کن
8. با `python3 tools/build_release.py` بسته‌های ZIP بساز
9. کامیت (پیام استاندارد: `feat|fix|docs|refactor|chore: خلاصه`)، پوش به main
10. در GitHub با تگ `vX.Y.Z` ریلیز بساز و دو فایل ZIP را ضمیمه کن

## 3. معماری فنی

### 3.1 جریان کار (Command Queue Pattern)

```
[چت‌بات] POST /commands {type, payload, client_uid?, wait?}
      → پلاگین: ردیف در جدول aclp_commands (status=pending)
[ایجنت] GET /agent/commands/pending (هر poll_interval ثانیه)
      → پلاگین: status=pending → sent + فایل‌های to_pc ضمیمه
[ایجنت] POST /agent/commands/{uid}/status {running}
[ایجنت] اجرای اکشن → آپلود فایل‌های خروجی (POST /agent/files)
[ایجنت] POST /agent/commands/{uid}/result {status, result, files}
      → پلاگین: status=completed/failed + ثبت نتیجه
[چت‌بات] GET /commands/{uid} → نتیجه + لینک فایل‌ها
      (یا در همان POST /commands با wait=true منتظر نتیجه می‌ماند — long-poll حداکثر 25 ثانیه)
```

### 3.2 جداول دیتابیس (پیشوند wp_ پیش‌فرض)

| جدول | نقش | نکات مهم |
|------|-----|----------|
| `aclp_api_keys` | کلیدها | فقط `key_hash` (SHA-256) ذخیره می‌شود؛ متن کلید هرگز |
| `aclp_clients` | سیستم‌های متصل | `client_uid` یکتا؛ `last_seen_at` مبنای آنلاین/آفلاین |
| `aclp_commands` | فرمان‌ها + نتایج | قلب تاریخچه؛ payload/result به‌صورت JSON |
| `aclp_files` | متادیتای فایل‌ها | فایل فیزیکی در `wp-content/uploads/aclp-files/Y/m/` |
| `aclp_logs` | لاگ رویدادها | audit trail کامل (auth_failed، key_created، ...) |

### 3.3 احراز هویت

- هدر `X-ACLP-Key` (یا `Authorization: Bearer` یا پارامتر `api_key`)
- هدر `X-ACLP-Client-UID` برای مسیرهای ایجنت
- محدودیت نرخ با transient در هر دقیقه برای هر کلید
- هر دو طرف (چت‌بات و ایجنت) از همان کلید استفاده می‌کنند؛ مالکیت با key_id کنترل می‌شود

### 3.4 رجیستری اکشن‌های ایجنت

در `agent/aclp_agent.py` دکوراتور `@handler("نام")` هر اکشن را ثبت می‌کند. اکشن‌های فعلی: `ping, sysinfo, shell, run_python, process_list, kill_process, file_read, file_write, file_list, file_delete, file_mkdir, file_move, upload_file, file_download, open_url, http_request, screenshot, install`

هر هندلر امضای `fn(agent, payload) -> (result_dict, [produced_file_paths])` دارد. فایل‌های خروجی خودکار آپلود و به نتیجه پیوست می‌شوند.

**افزودن اکشن جدید:** فقط یک تابع با دکوراتور اضافه کنید + مستندات + semver مینور. جزئیات در `docs/DEVELOPER.md`.

### 3.5 تنظیمات (option `aclp_settings`)

`poll_interval, online_timeout, command_timeout, history_retention_days, file_retention_days, max_log_entries, max_commands_rows, max_file_size_mb, rate_limit_per_min, max_pending_per_client, delete_data_on_uninstall`

نگهداری خودکار در cron ساعتی `aclp_hourly_maintenance` (کلاس `ACLP_Cron`).

## 4. وضعیت فعلی

**نسخه جاری: 1.0.0 (انتشار اولیه — 2026-10-09)**

- [x] هسته پلاگین: جداول، تنظیمات، cron نگهداری، uninstall
- [x] REST API کامل (۱۲ مسیر) با احراز هویت کلید + نرخ
- [x] پنل مدیریت فارسی: داشبورد، کلیدها، سیستم‌ها، تاریخچه با جزئیات کامل، تنظیمات
- [x] ایجنت پایتون: ۱۸ اکشن، setup wizard، auto-install وابستگی، backoff، لاگ
- [x] مستندات کامل (فارسی + مرجع API انگلیسی برای ایجنت‌ها)
- [x] اسکریپت build و انتشار (tools/build_release.py)

## 5. تصمیمات معماری ثبت‌شده (ADR)

| # | تصمیم | دلیل |
|---|-------|------|
| 1 | Polling به‌جای WebSocket در v1 | سادگی، سازگاری با همه هاست‌های اشتراکی وردپرس؛ WebSocket در v1.1 |
| 2 | ذخیره هش کلید (نه متن) | حتی با لو رفتن دیتابیس، کلید قابل استفاده نیست |
| 3 | فایل‌ها خارج از پوشه افزونه (uploads) | سازگاری با بروزرسانی افزونه بدون از دست رفتن داده |
| 4 | نام‌گذاری فایل ذخیره‌شده با uniqid | جلوگیری از تداخل و path traversal |
| 5 | UI فارسی بدون textdomain | مخاطب فعلی کاربر فارسی‌زبان است؛ i18n در صورت نیاز آینده |
| 6 | `wait=true` با sleep-loop حداکثر 25 ثانیه | سازگار با timeout پیش‌فرض پراکسی‌ها؛ ساده و بدون وابستگی |
| 7 | کنسول ایجنت انگلیسی | جلوگیری از خرابی کاراکتر فارسی در ترمینال‌های قدیمی ویندوز (cp1252/cp437)؛ مستندات فارسی در docs/ |

## 6. قرارداد نسخه‌بندی (Semver)

فرمت: `MAJOR.MINOR.PATCH` — تگ گیت: `vX.Y.Z`

- **PATCH** (1.0.1): رفع باگ بدون تغییر API — نسخه هر دو بسته یکجا زیاد می‌شود
- **MINOR** (1.1.0): قابلیت جدید سازگار (اکشن جدید ایجنت، مسیر API جدید، قابلیت پنل)
- **MAJOR** (2.0.0): تغییر شکسته (حذف مسیر API، تغییر شکل پاسخ موجود، تغییر جداول)

هر ریلیز حتماً شامل **دو** فایل ZIP است: `ai-chatbot-link-to-pc-vX.Y.Z.zip` (افزونه) و `aclp-agent-vX.Y.Z.zip` (ایجنت) — حتی اگر فقط یکی تغییر کرده باشد (ایجنت و افزونه باید همیشه هم‌نسخه باشند تا سازگاری تضمین شود).

## 7. ساختار پوشه‌ها

```
ai-chatbot-link-to-pc/
├── ai-chatbot-link-to-pc/          ← افزونه وردپرس (این پوشه ZIP می‌شود)
│   ├── ai-chatbot-link-to-pc.php   ← فایل اصلی
│   ├── uninstall.php
│   ├── readme.txt
│   ├── includes/                   ← هسته (۱۱ کلاس)
│   └── admin/                      ← پنل مدیریت + css/js
├── agent/                          ← ایجنت پایتون (این پوشه ZIP می‌شود)
│   ├── aclp_agent.py
│   ├── requirements.txt
│   ├── config.example.json
│   ├── start_agent.bat / .sh
│   └── README.fa.md
├── docs/                           ← USER-GUIDE.fa.md, AGENT-API.md, DEVELOPER.md
├── tools/build_release.py          ← اسکریپت ساخت ZIP
├── README.md, project.md, brainstorm.md, worklog.md,
├── CHANGELOG.md, SECURITY.md, LICENSE, .gitignore
```
