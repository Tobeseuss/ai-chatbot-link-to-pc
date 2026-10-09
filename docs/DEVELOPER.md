# DEVELOPER.md — سند توسعه‌دهنده

> برای هر ایجنت هوش مصنوعی یا انسانی که روی این پروژه کد می‌زند. قبل از شروع: `project.md` (اصول + چک‌لیست) و `worklog.md` (تاریخچه) را بخوانید.

---

## ۱. معماری کد

### افزونه (PHP)

```
ai-chatbot-link-to-pc.php      ← بوت‌استرپ: ثابت‌ها، require ها، هوک‌های global
includes/
  class-aclp-settings.php      ← option aclp_settings + defaults
  class-aclp-utils.php         ← زمان UTC، uid، برچسب‌های فارسی، مسیر storage
  class-aclp-activator.php     ← dbDelta جداول + cron
  class-aclp-api-keys.php      ← CRUD کلید (هش SHA-256)
  class-aclp-clients.php       ← CRUD کلاینت + register/upsert
  class-aclp-commands.php      ← صف + چرخه وضعیت + query + stats
  class-aclp-files.php         ← ذخیره فایل + api_shape + stats
  class-aclp-logs.php          ← ACLP_Logger::add + query
  class-aclp-cron.php          ← نگهداری ساعتی
  class-aclp-auth.php          ← استخراج/اعتبارسنجی کلید + نرخ + کلاینت
  class-aclp-rest.php          ← ثبت ۱۲ مسیر + هندلرها + body() + command_shape()
admin/
  class-aclp-admin.php         ← منو + admin_post هندلرها + ACLP_Admin_Notices
  class-aclp-admin-pages.php   ← رندر ۵ صفحه
  css/aclp-admin.css, js/aclp-admin.js
uninstall.php                  ← پاک‌سازی اختیاری
```

الگوها:
- همه زمان‌ها **UTC** با `ACLP_Utils::now()`
- همه کوئری‌ها با `$wpdb->prepare`
- هر پاسخ REST شکل `{"ok":..., ...}` و خطاها `WP_Error` با `array('status'=>HTTP)`
- جداول با پیشوند وردپرس: `$wpdb->prefix . 'aclp_*'`

### ایجنت (Python)

یک فایل `aclp_agent.py` با بخش‌های مشخص: bootstrap requests → config → handlers registry → Agent class → main. 

**افزودن اکشن جدید:**

```python
@handler("my_action")
def h_my_action(agent, payload):
    # payload: دیکشنری فرستاده‌شده از چت‌بات
    # برای اجرای دستور: _run_shell(cmd, timeout)
    # برای خطای تمیز: raise ACLPError("پیام")
    return {"any": "json-serializable"}, []   # (نتیجه, فایل‌های خروجی)
```

- فایل‌های خروجی (مسیرهای لیست دوم) خودکار آپلود و به نتیجه پیوست می‌شوند و بعد حذف می‌شوند
- بعد از افزودن اکشن: به `ACLP_Utils::type_label` برچسب فارسی اضافه کن، docs/AGENT-API.md را بروز کن، نسخه مینور زیاد کن
- کلاس `Agent` مسئول: هدرها (کلید + UID)، api() با retry سه‌باره، register (با رزرو خودکار UID در تعارض)، دانلود/آپلود فایل، حلقه اصلی با backoff

## ۲. جریان داده فرمان (ref)

```
POST /commands → insert(status=pending)
GET  /agent/commands/pending → select(status=pending) → update(sent) + پیوست فایل‌های to_pc
POST /agent/commands/{uid}/status {running} → update(running, started_at)
POST /agent/files → insert(aclp_files, from_pc)
POST /agent/commands/{uid}/result → update(completed|failed, result) + attach file_ids
GET  /commands/{uid} → select + join client + files
```

`wait=true` در `ACLP_REST::route_create_command` با حلقه `usleep(500ms)` تا ۲۵ ثانیه پیاده شده.

## ۳. ریسک‌های شناخته‌شده و مسیر بهبود

| موضوع | وضعیت فعلی | بهبود پیشنهادی |
|-------|-----------|----------------|
| انتقال | polling (پیش‌فرض ۵s) | WebSocket یا SSE در v1.1 |
| رمزنگاری محتوا | HTTPS transport فقط | E2E با کلید عمومی در payload |
| payload بزرگ | محدود به post_max_size | chunked upload |
| لاگ‌های خام شل | در نتیجه ذخیره می‌شوند | streaming خروجی زنده |
| یوزر sudo لینوکس | `sudo` بدون tty ممکن است خطا بدهد | مستندسازی / NOPASSWD توسط خود کاربر |

## ۴. تست دستی سریع (بدون سیستم واقعی)

```bash
# ۱. کلید بسازید در پنل، بعد:
KEY="aclp_live_..."
BASE="https://local.test/wp-json/aclp/v1"
curl -H "X-ACLP-Key: $KEY" "$BASE/ping"
# ۲. ایجنت را در --once اجرا کنید تا یک چرخه برود
python aclp_agent.py --once
# ۳. فرمان آزمایشی:
curl -X POST "$BASE/commands" -H "X-ACLP-Key: $KEY" -H "Content-Type: application/json" \
  -d '{"type":"ping","wait":true,"timeout":10}'
```

## ۵. ساخت و انتشار (اصل اساسی ۳)

```bash
python3 tools/build_release.py          # دو ZIP در dist/ + sha256
git add -A && git commit -m "feat: ..." && git push origin main
# سپس Release با تگ vX.Y.Z + پیوست دو ZIP (دستی یا با API — نمونه زیر)

GH=api.github.com; REPO="Tobeseuss/ai-chatbot-link-to-pc"; TOKEN=xxx
RELEASE_ID=$(curl -sS -X POST "https://$GH/repos/$REPO/releases" \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"tag_name":"v1.1.0","name":"v1.1.0","body":"..."}' | python3 -c "import sys,json;print(json.load(sys.stdin)['id'])")
curl -sS -X POST -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/zip" \
  --data-binary @dist/ai-chatbot-link-to-pc-v1.1.0.zip \
  "https://uploads.github.com/repos/$REPO/releases/$RELEASE_ID/assets?name=ai-chatbot-link-to-pc-v1.1.0.zip"
```

`tools/build_release.py` نسخه را از هدر PHP می‌خواند و پوشه‌های موقت می‌سازد؛ خروجی همیشه در `dist/` (gitignored).

## ۶. چک‌لیست نهایی هر PR/تغییر (خلاصه — کامل در project.md)

- [ ] نسخه در ۴ جا بروز شد؟
- [ ] docs/ و README هماهنگ شدند؟
- [ ] brainstorm.md + worklog.md + project.md آپدیت شدند؟
- [ ] build گرفتی؟ ریلیز زدی؟ پوش کردی؟
