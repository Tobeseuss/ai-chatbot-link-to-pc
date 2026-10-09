# .ai/ — حافظه ماندگار PAT مالک (Credentials Vault Map)

> **هدف این پوشه:** طبق درخواست مالک پروژه، توکن PAT گیت‌هاب باید همیشه قابل پیدا کردن باشد تا با ریست شدن محیط توسعه از بین نرود و هر ایجنت جدید بتواند بدون پرسیدن دوباره آن را بازیابی کند.
>
> ⚠️ **نکته مهم:** خودِ توکن به این ریپو commit **نمی‌شود** — GitHub Push Protection توکن را حتی به‌صورت Base64 هم تشخیص می‌دهد و push را رد می‌کند (تجربه‌شده در نسخه 1.1.0). به‌جای آن، توکن در **محیط توسعه ماندگار** (که با ریست پاک نمی‌شود) و در **تنظیمات پلاگین روی سایت** نگهداری می‌شود و این فایل نقشه آن‌هاست.

## 🔑 محل‌های ذخیره PAT (مالک: Tobeseuss)

| # | محل | مسیر | فرمت | ماندگاری |
|---|-----|------|------|----------|
| ۱ | محیط توسعه | `/home/z/my-project/.env` → کلید `ACLPS_GITHUB_PAT` | متن خام | ماندگار در برابر ریست محیط |
| ۲ | محیط توسعه | `/home/z/my-project/scripts/github_pat.local` | متن خام (chmod 600) | ماندگار در برابر ریست محیط |
| ۳ | محیط توسعه | `/.ai/pat.b64` همین پوشه (gitignored) | Base64 | ماندگار در برابر ریست محیط |
| ۴ | سایت کاربر | WP Admin → AI-PC Link → تنظیمات → «یکپارچگی گیت‌هاب» | متن خام | روی سرور کاربر |
| ۵ | API پلاگین | `GET {site}/wp-json/aclp/v1/github-integration` با هدر کلید API | JSON | روی سرور کاربر |

**بازیابی سریع (در محیط توسعه):**

```bash
PAT=$(grep '^ACLPS_GITHUB_PAT=' /home/z/my-project/.env | cut -d= -f2)
echo "PAT loaded (${#PAT} chars)"
# تست اعتبار:
curl -s -H "Authorization: Bearer $PAT" https://api.github.com/user | grep login
# یا دیکد کردن نسخه Base64:
PAT=$(base64 -d /home/z/my-project/ai-chatbot-link-to-pc/.ai/pat.b64)
```

**اگر همه محلی‌ها از دست رفت بودند** (محیط کاملاً نو و ریپو تازه clone شده):
1. از کاربر بخواهید PAT را در تنظیمات پلاگین وارد کرده باشد → با یک کلید API معتبر از `GET /github-integration` بخوانید، یا
2. از کاربر مستقیم بپرسید (هرگز توکن نسازید/حدس نزنید).

## ⚠️ قواعد

1. این توکن **فقط** برای: clone/push ریپو، ساخت Release، و مدیریت Issues استفاده می‌شود.
2. اگر ایجنتی توکن را تغییر داد/regenerate کرد، **بلافاصله** هر سه محل محیط توسعه را بروز کند:
   ```bash
   NEW="TOKEN_NEW"
   # ۱) .env
   sed -i "s|^ACLPS_GITHUB_PAT=.*|ACLPS_GITHUB_PAT=$NEW|" /home/z/my-project/.env
   # ۲) local file
   printf 'ACLP project GitHub PAT (owner: Tobeseuss)\nPAT=%s\n' "$NEW" > /home/z/my-project/scripts/github_pat.local
   # ۳) Base64 vault (gitignored)
   printf '%s' "$NEW" | base64 -w0 > /home/z/my-project/ai-chatbot-link-to-pc/.ai/pat.b64
   # ۴) یادآوری: کاربر باید در تنظیمات پلاگین هم بروز کند
   ```
3. توکن را در کد، لاگ، پیام‌های قابل مشاهده کاربر، یا **هر فایلی که به ریپو می‌رود** قرار ندهید (Push Protection گیت‌هاب push را رد می‌کند و اگر رد نکرد، Secret Scanning توکن را ابطال می‌کند).
4. اگر گیت‌هاب توکن را رد کرد (401)، به کاربر اطلاع دهید تا توکن جدید بسازد و طبق بند ۲ بروزش کنید.
5. راه جایگزین اگر مالک اصرار به ذخیره داخل ریپو دارد: از URL «unblock secret» که هنگام push ردشده در خروجی گیت می‌آید، در مرورگر خودش استفاده کند و اجازه دهد — اما توصیه نمی‌شود.
