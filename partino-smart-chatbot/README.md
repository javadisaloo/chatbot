# Partino Smart Parts Chatbot

چت‌بات هوشمند استعلام قطعات خودرو برای وردپرس — با جریان مکالمه State-Based، پشتیبانی اختیاری از هوش مصنوعی (OpenRouter)، پنل مدیریت کامل و امنیت استاندارد وردپرس.

- **نسخه:** 1.0.0
- **حداقل وردپرس:** 6.0
- **حداقل PHP:** 8.1 (تست‌شده تا 8.4)
- **Text Domain:** `partino-smart-chatbot`

---

## فهرست

1. [نصب](#نصب)
2. [پیکربندی](#پیکربندی)
3. [راه‌اندازی OpenRouter](#راهاندازی-openrouter)
4. [نحوه استفاده](#نحوه-استفاده)
5. [راهنمای پنل مدیریت](#راهنمای-پنل-مدیریت)
6. [دیتابیس](#دیتابیس)
7. [REST API](#rest-api)
8. [هوک‌ها و فیلترها](#هوکها-و-فیلترها)
9. [امنیت](#امنیت)
10. [عیب‌یابی](#عیبیابی)
11. [توسعه](#توسعه)

---

## نصب

1. پوشه `partino-smart-chatbot` را در `wp-content/plugins/` قرار دهید (یا ZIP آن را از «افزونه‌ها ← افزودن» بارگذاری کنید).
2. افزونه را فعال کنید. در فعال‌سازی:
   - ۷ جدول اختصاصی با `dbDelta()` ساخته می‌شود.
   - داده‌های اولیه (برندها/مدل‌ها/تیپ‌های پرکاربرد ایرانی، ۱۰ قطعه پرمصرف و ۲ اکشن سریع) seed می‌شود.
   - Capability اختصاصی `manage_partino_chatbot` به نقش Administrator داده می‌شود.
   - کرون پاک‌سازی روزانه (`partino_chatbot_daily_cleanup`) زمان‌بندی می‌شود.
3. به صفحه اول سایت بروید؛ چت‌بات پس از تأخیر تنظیم‌شده (پیش‌فرض ۳ ثانیه) باز می‌شود.

## پیکربندی

از منوی **Partino Chatbot ← تنظیمات**:

| تب | تنظیمات |
|---|---|
| عمومی | فعال/غیرفعال، Auto Open، تأخیر (0 تا 10000ms)، نمایش موبایل/دسکتاپ، موقعیت، RTL |
| ظاهر | ۶ رنگ، ۶ اندازه، لوگو (کتابخانه رسانه)، عنوان، زیرعنوان، Badge، پیام خوش‌آمد، Placeholder + **پیش‌نمایش زنده** |
| چت‌بات | Typing Indicator، اسکرول خودکار، انیمیشن، ورودی صوتی، اکشن‌های سریع، Timestamp |
| هوش مصنوعی | کلید API (Mask شده)، مدل، Temperature، Max Tokens، Timeout، Retry، System Prompt، **تست اتصال** |
| گفتگو | حداکثر طول، مهلت نشست، مدت نگهداری، ریست بعد از ثبت، امکان اصلاح |
| سرنخ | اجباری بودن شماره، اعتبارسنجی ایرانی، رفتار تکراری، پیام موفقیت |
| حریم خصوصی | Minimal / Standard / Extended |
| اعلان‌ها | ایمیل ادمین با متغیرهای `{phone} {part} {vehicle} {year} {id} {admin_url}` |
| پیشرفته | Rate Limit، مدت مسدودسازی، حذف داده در Uninstall، Debug Log، Import/Export/Reset |

## راه‌اندازی OpenRouter

1. از [openrouter.ai](https://openrouter.ai) کلید API بگیرید (`sk-or-...`).
2. در تب «هوش مصنوعی» کلید را وارد و ذخیره کنید، مدل دلخواه (مثل `openai/gpt-4o-mini`) را تنظیم کنید.
3. با دکمه **تست اتصال** صحت کلید را بررسی کنید.
4. «فعال‌سازی AI» را روشن کنید.

نکات:
- کلید **فقط در سرور** نگهداری می‌شود؛ در پنل Mask شده نمایش می‌یابد و هرگز به فرانت‌اند یا Export نمی‌رود.
- AI فقط نقش NLU دارد (تشخیص Intent و استخراج قطعه/خودرو/سال). تمام Business Logic و اعتبارسنجی سمت PHP است.
- در صورت خطا/Timeout/کلید نامعتبر، جریان قاعده‌محور داخلی **به‌صورت خودکار** جایگزین می‌شود و چت‌بات هرگز از کار نمی‌افتد.

## نحوه استفاده

جریان کاربر:

```
خوش‌آمد + Quick Actions
  → انتخاب/تایپ قطعه (لنت ترمز خودروی من)
  → برند و مدل خودرو (206 / پژو 206 / Peugeot 206 / دویست و شش)
  → تیپ (تیپ 2، از دکمه‌ها یا تایپ)
  → سال (1388 / ۱۳۹۰ / 2015 / 88)
  → تأیید (با دکمه‌های اصلاح خودرو/سال/قطعه)
  → شماره موبایل (09xxxxxxxxx یا +98، اعتبارسنجی و Normalize)
  → ثبت استعلام ✅
```

- گفتگو با Refresh صفحه از بین نمی‌رود (UUID در localStorage، داده‌ها در سرور).
- ورودی صوتی از Web Speech API مرورگر استفاده می‌کند (`fa-IR`)؛ هیچ صدایی به سرور ارسال نمی‌شود.

## راهنمای پنل مدیریت

- **داشبورد:** آمار کل/جدید/امروز/هفته/ماه، نرخ تبدیل، شمارنده AI + آخرین استعلام‌ها.
- **استعلام‌ها:** جستجو (شماره/قطعه/خودرو)، فیلتر وضعیت، صفحه‌بندی، عملیات گروهی، خروجی CSV، صفحه جزئیات با تاریخچه کامل گفتگو. شماره در لیست Mask شده (`0912***4567`) و در جزئیات کامل است.
- **گفتگوها:** لیست تمام گفتگوها با State و تعداد پیام.
- **خودروها:** مدیریت سه‌سطحی برند ← مدل ← تیپ با aliases برای تشخیص هوشمند.
- **قطعات:** نام/نامک/دسته/آیکون/aliases/ترتیب/فعال.
- **اکشن‌های سریع:** عنوان، قطعه مرتبط، ترتیب، فعال/غیرفعال.
- **لاگ‌ها:** سطوح DEBUG/INFO/WARNING/ERROR با حساسیت‌زدایی خودکار (کلید API و شماره تلفن هرگز Log نمی‌شوند) + پاک‌سازی.

## دیتابیس

جداول (همه با `{$wpdb->prefix}` و ایندکس‌های مناسب):

| جدول | نقش | ایندکس‌های کلیدی |
|---|---|---|
| `partino_inquiries` | استعلام‌ها | uuid(U), status, customer_phone, conversation_id, part_id, vehicle_model, created_at |
| `partino_conversations` | گفتگوها + State + Context | uuid(U), status, updated_at |
| `partino_messages` | پیام‌ها (user/assistant/system) | conversation_id, created_at |
| `partino_vehicles` | برند/مدل/تیپ سلسله‌مراتبی | parent_id, type, is_active |
| `partino_parts` | قطعات | slug(U), is_active |
| `partino_quick_actions` | اکشن‌های سریع | is_active |
| `partino_logs` | لاگ‌ها | level, channel, created_at |

نسخه‌بندی: `PARTINO_CHATBOT_DB_VERSION` + گزینه `partino_chatbot_db_version`؛ ارتقای Schema با `dbDelta()` در `plugins_loaded` انجام می‌شود.

## REST API

Namespace: `partino-chatbot/v1`

| Endpoint | Method | دسترسی | توضیح |
|---|---|---|---|
| `/config` | GET | Nonce | پیکربندی عمومی ویجت (بدون هیچ Secret) |
| `/conversation/start` | POST | Nonce + RateLimit | شروع یا ادامه گفتگو (`conversation` اختیاری برای Resume) |
| `/conversation/message` | POST | Nonce + RateLimit | پیام متنی آزاد |
| `/conversation/select` | POST | Nonce + RateLimit | انتخاب گزینه (`qa:*`, `part:*`, `brand:*`, `model:*`, `trim:*`, `confirm`, `edit_*`, `restart`) |
| `/conversation/phone` | POST | Nonce + RateLimit | ثبت شماره و ایجاد Inquiry |
| `/admin/test-ai` | POST | Nonce + `manage_partino_chatbot` | تست اتصال OpenRouter |

قالب پاسخ:

```json
{ "success": true,  "data": { "state": "...", "messages": [], "options": [], "ui": {"input_mode": "text"} } }
{ "success": false, "code": "partino_rate_limited", "message": "..." }   // با HTTP 429/403/400/404
```

## هوک‌ها و فیلترها

```php
// Actions
do_action( 'partino_before_inquiry_created', array $data );
do_action( 'partino_after_inquiry_created', int $id, array $data );
do_action( 'partino_inquiry_notification', int $id, array $data ); // اتصال SMS/CRM
do_action( 'partino_conversation_message', int $conversation_id, string $role, string $content );

// Filters
apply_filters( 'partino_chatbot_config', array $config );          // پیکربندی فرانت
apply_filters( 'partino_chatbot_should_load', bool $load );        // کنترل نمایش ویجت
apply_filters( 'partino_chatbot_template', string $template );     // Override قالب mount
apply_filters( 'partino_inquiry_statuses', array $statuses );      // وضعیت‌های سفارشی
apply_filters( 'partino_before_ai_request', array $messages, string $state );
apply_filters( 'partino_after_ai_response', ?array $parsed, string $text );
```

نمونه اتصال SMS:

```php
add_action( 'partino_inquiry_notification', function ( $id, $data ) {
    my_sms_provider_send( $data['customer_phone'], "استعلام {$data['part_name']} ثبت شد." );
}, 10, 2 );
```

## امنیت

- تمام ورودی‌ها: `sanitize_text_field` / `sanitize_textarea_field` / `absint` / `sanitize_key` / `sanitize_hex_color`
- تمام خروجی‌ها: `esc_html` / `esc_attr` / `esc_url` / `esc_textarea`
- تمام SQLها: `$wpdb->prepare()` — هیچ مقدار کاربر بدون Placeholder وارد Query نمی‌شود
- REST عمومی: Nonce `wp_rest` + Rate Limit (پیش‌فرض ۲۰/دقیقه/IP، پاسخ **429**) + مسدودسازی موقت متخلف
- REST/Admin: `current_user_can( 'manage_partino_chatbot' )` + `check_admin_referer`
- کلید API: فقط سرور، Mask در پنل، Mask/حذف در Export، Redact در Logs
- شماره تلفن: Mask در لیست‌ها و لاگ‌ها، کامل فقط در صفحه جزئیات با Permission
- IP: فقط هش HMAC-SHA256 با salt وردپرس (در حالت Minimal اصلاً ذخیره نمی‌شود)
- همه فایل‌ها: `defined( 'ABSPATH' ) || exit;` + فایل‌های `index.php` سکوت

## عیب‌یابی

| مشکل | راه‌حل |
|---|---|
| چت‌بات نمایش داده نمی‌شود | تنظیمات ← عمومی ← «فعال بودن» و «نمایش موبایل/دسکتاپ» را بررسی کنید؛ Cache صفحه را خالی کنید |
| «نشست شما منقضی شده» | صفحه را Refresh کنید (Nonce منقضی شده — طبیعی بعد از ~۲۴ ساعت) |
| 429 دریافت می‌کنید | Rate Limit؛ از تنظیمات ← پیشرفته سقف را افزایش دهید |
| تست AI خطای 401 | کلید OpenRouter نامعتبر است؛ دوباره وارد و ذخیره کنید |
| AI جواب نمی‌دهد اما چت کار می‌کند | رفتار درست است — Fallback قاعده‌محور فعال شده؛ لاگ‌ها را ببینید |
| ایمیل نمی‌رسد | ارسال ایمیل سرور (wp_mail/SMTP) را با افزونه SMTP بررسی کنید |

## توسعه

```bash
# Syntax check
find . -name '*.php' -not -path './vendor/*' -print0 | xargs -0 -n1 php -l

# Unit tests (بدون نیاز به وردپرس — Mock کامل OpenRouter)
composer install
composer test
```

ساختار:

```
partino-smart-chatbot/
├── partino-smart-chatbot.php   # Bootstrap + Header + Constants
├── uninstall.php               # حذف مشروط داده‌ها
├── includes/                   # هسته (Namespace: Partino\Chatbot)
│   ├── class-plugin.php        # Orchestrator (Singleton)
│   ├── class-database.php      # Schema + dbDelta + Seed + Versioning
│   ├── class-engine.php        # State Machine مکالمه
│   ├── class-ai.php            # OpenRouter Client + Output Validation
│   ├── class-rest-api.php      # REST Routes + Permissions
│   ├── class-security.php      # RateLimit + IP Hash + Phone Validation
│   ├── class-normalizer.php    # نرمال‌سازی فارسی/اعداد/سال
│   └── ...
├── admin/                      # پنل مدیریت + assets
├── public/                     # ویجت فرانت (Vanilla JS + CSS Namespaced)
├── templates/chatbot.php       # Mount point (قابل Override با فیلتر)
└── tests/                      # PHPUnit (unit، بدون وابستگی به API واقعی)
```

مسیر توسعه آینده بدون Rewrite: Seller Matching، Quotation، SMS/WhatsApp (از `partino_inquiry_notification`)، WooCommerce، CRM — همگی از طریق هوک‌های موجود قابل اتصال‌اند.

## لایسنس

GPL-2.0-or-later
