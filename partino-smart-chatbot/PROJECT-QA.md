# PROJECT-QA — Partino Smart Parts Chatbot v1.0.0

گزارش QA نهایی. تاریخ: 2026-09-04 — محیط تست: WordPress 6.7.1 + PHP 8.3 (WASM sandbox) + SQLite integration، به‌علاوه `php -l` روی PHP 8.5 و اجرای Unit Tests.

---

## 1. Architecture

- **Pattern:** Modular OOP، Namespace `Partino\Chatbot`، Autoloader اختصاصی، Singleton `Plugin` به‌عنوان Orchestrator.
- **Conversation:** State Machine واقعی سمت سرور (`Engine` + `States`) با ۱۳ State؛ هر Transition سمت PHP اعتبارسنجی می‌شود. Context به‌صورت JSON در جدول conversations ذخیره می‌شود.
- **AI:** لایه NLU خالص. ورودی → OpenRouter → خروجی JSON با Schema سخت‌گیرانه → `AI::validate_output()` (لیست‌سفید Intent، Sanitize فیلدها، Clamp کردن confidence). AI هرگز مستقیم به DB دسترسی ندارد. خطای AI ⇒ Fallback قاعده‌محور (`Engine::rule_based_intent` + `Normalizer` + Fuzzy Matcher های Vehicle/Part).
- **Frontend:** Vanilla JS (IIFE، بدون Build/Dependency/jQuery)، CSS کاملاً زیر `#partino-chatbot`، تزریق Config با `wp_localize_script`، ارتباط از طریق REST + Nonce.
- **Data:** ۷ جدول Custom با ایندکس؛ بدون استفاده از postmeta.

## 2. Implemented Features

✔ چت‌بات RTL فارسی با UI مطابق Screenshot (هدر سفید X/لوگو/عنوان، کارت پیام با Badge «ماشینت کنارته»، Quick Actions دو ستونه، نوار اکشن پایینی خرید سریع/درخواست جدید/بررسی وضعیت/منوی سه‌نقطه، ورودی + میکروفون + دکمه ارسال فلش، Bottom-Sheet موبایل با safe-area)
✔ Auto-open با تأخیر تنظیم‌پذیر + Launcher شناور پس از بستن + به‌خاطرسپاری بستن
✔ جریان کامل: قطعه → خودرو → تیپ → سال → تأیید → اصلاح (خودرو/سال/قطعه) → موبایل → ثبت
✔ Smart Input: اعداد فارسی/عربی، «دویست و شش»، Finglish، aliases، سال دو رقمی (88→1388)، میلادی
✔ Conversation Persistence (localStorage UUID + Resume سمت سرور + Session Timeout)
✔ Anonymous Conversation → تبدیل به Inquiry بعد از شماره
✔ Voice Input با Web Speech API (fa-IR، فقط سمت مرورگر)
✔ پنل مدیریت ۸ صفحه‌ای + تنظیمات ۹ تب + Live Preview + Import/Export/Reset
✔ CSV Export با BOM (سازگار Excel) + عملیات گروهی + جستجو/فیلتر/صفحه‌بندی
✔ Email Notification با Template متغیردار + هوک SMS
✔ Logger با Redaction خودکار (sk-or-*, شماره موبایل)
✔ Cron پاک‌سازی روزانه بر اساس Retention
✔ i18n کامل با `partino-smart-chatbot`؛ Accessibility (aria-label ها، focus management، ESC، prefers-reduced-motion)

## 3. Database Tables

`partino_inquiries`, `partino_conversations`, `partino_messages`, `partino_vehicles`, `partino_parts`, `partino_quick_actions`, `partino_logs` — همه با `{$wpdb->prefix}`، ساخته‌شده با `dbDelta()`، دارای ایندکس روی ستون‌های پرکاربرد (status/phone/created_at/conversation_id/parent_id/…) و Versioning (`PARTINO_CHATBOT_DB_VERSION`).

## 4. REST Endpoints

`GET /config`، `POST /conversation/start|message|select|phone` (عمومی: Nonce+RateLimit+Validation)، `POST /admin/test-ai` (capability اختصاصی). قالب پاسخ استاندارد `{success, data|message}`.

توجه: `/conversation/confirm` و `/conversation/submit` طرح اولیه، در قالب `select (option=confirm)` و `phone` پیاده شدند تا سطح حمله کمتر و State Machine یکپارچه بماند.

## 5. Security Checks (انجام‌شده و تأییدشده در تست)

| بررسی | نتیجه |
|---|---|
| SQL Injection — تمام Queryها با `$wpdb->prepare` | ✅ Pass (بازبینی کد + تست ورودی خصمانه) |
| XSS — Escape همه خروجی‌ها + `textContent` در JS (بدون innerHTML برای داده کاربر) | ✅ Pass |
| CSRF — Nonce برای REST (`wp_rest`) و ادمین (`check_admin_referer`) | ✅ Pass (403 بدون Nonce تأیید شد) |
| Privilege — `manage_partino_chatbot` + `current_user_can` روی همه اکشن‌ها | ✅ Pass (403 برای کاربر ناشناس) |
| Rate Limit — 20/دقیقه + Block موقت | ✅ Pass (429 مشاهده شد) |
| API Key Exposure — بررسی HTML/JS/REST خروجی | ✅ Pass (هیچ نشتی؛ Mask در پنل/Export؛ Redact در Log) |
| Phone Privacy — Mask در لیست/لاگ، کامل فقط در جزئیات | ✅ Pass |
| Input Validation — UUID، option pattern `^[a-z0-9_:\-]+$`، طول پیام، شماره ایرانی | ✅ Pass |
| Direct Access — `ABSPATH` guard در همه فایل‌ها + index.php سکوت | ✅ Pass |
| CORS — هیچ Header سفارشی باز نشده | ✅ Pass |

## 6. Tests Performed & Results

**Static:** `php -l` روی تمام ۳۵+ فایل PHP (PHP 8.5-wasm) → ۰ خطا. `node --check` روی هر دو JS → ۰ خطا.

**Unit (38 assertions, all pass):** Normalizer (digits/text/number-words/year/score ×13)، Phone Normalization (×9: 0912/+98/98/0098/912/فارسی/فاصله/نامعتبر)، AI Output Validation (×8: valid/fences/prose/bad-intent/broken/clamp/XSS)، Settings Sanitization (×8: رنگ نامعتبر/Cap/کلید Mask شده/Export). فایل‌های PHPUnit رسمی در `tests/` + `phpunit.xml.dist` موجود است (Mock کامل، بدون نیاز به API واقعی).

**Integration روی WordPress 6.7.1 واقعی:**

| # | سناریو | نتیجه |
|---|---|---|
| 1 | Activation | ✅ «Plugin activated»، بدون Fatal/Warning |
| 2 | Deactivation → Reactivation | ✅ بدون خطا، داده‌ها حفظ شد |
| 3 | DB Creation + Seed | ✅ ۷ جدول + برند/مدل/تیپ/قطعه/اکشن |
| 4 | Frontend render + assets + config | ✅ mount + CSS/JS با filemtime + بدون نشت |
| 5 | Flow کامل: لنت ترمز → 206 → تیپ 2 → 1388 → تأیید → 12345 (رد) → +98912… (قبول) | ✅ State ها دقیقاً طبق سناریوی بخش ۷۰ |
| 6 | اصلاح سال + سال با اعداد فارسی ۱۳۹۰ | ✅ |
| 7 | ورودی‌های «پژو 206»/«Peugeot 206»/«دویست و شش»/«سمند lx»/«تندر 90» | ✅ همه Normalize شدند |
| 8 | ورودی ناشناخته | ✅ پیام clarification + گزینه برندها |
| 9 | قطعه+خودرو در یک پیام | ✅ مستقیم به Trim |
| 10 | Resume بعد از Refresh | ✅ history=3، state حفظ شد |
| 11 | UUID جعلی / option خصمانه | ✅ 404 / 400 |
| 12 | Rate Limit | ✅ 429 + Block |
| 13 | AI با کلید خراب | ✅ Fallback بدون Crash + Log خطا |
| 14 | Admin: هر ۸ صفحه | ✅ HTTP 200، ۰ Fatal، ۰ Warning |
| 15 | Inquiry در ادمین + Mask + جزئیات + تاریخچه ۱۹ پیام | ✅ |
| 16 | تغییر Status → «تماس گرفته شد» | ✅ |
| 17 | CSV Export | ✅ text/csv + BOM + ردیف صحیح |
| 18 | ذخیره تنظیمات + انعکاس فوری در فرانت | ✅ |
| 19 | Reset Settings | ✅ |
| 20 | حذف Inquiry (+conversation/messages) | ✅ Empty State نمایش داده شد |
| 21 | Test-AI endpoint (بدون کلید → پیام مناسب؛ بدون لاگین → 403) | ✅ |

## 7. Browser Compatibility

کد فرانت فقط از APIهای پایدار استفاده می‌کند: `fetch`, `classList`, CSS Custom Properties, Flexbox, `matchMedia`, `localStorage` (با try/catch برای Private Mode) — پشتیبانی: Chrome/Edge/Firefox/Safari مدرن + iOS Safari و Android Chrome. `100dvh` با fallback `max-height` و `env(safe-area-inset-*)` برای iPhone. Web Speech API فقط در مرورگرهای پشتیبان فعال است (در بقیه دکمه Disable + title توضیح). تست دستی گرافیکی در مرورگر واقعی در این محیط CI ممکن نبود؛ ساختار DOM/CSS روی خروجی HTML سرور Verify شد.

## 8. Known Limitations

1. **اجرای PHPUnit رسمی در این Sandbox ممکن نبود** (PHP CLI بومی و Composer در دسترس نبود) — همان Assertionها ۱:۱ از طریق PHP-WASM اجرا و پاس شدند؛ فایل‌های PHPUnit آماده اجرا با `composer test` روی محیط شما هستند.
2. **تست اتصال واقعی OpenRouter** به‌دلیل فیلترشدن دامنه در Sandbox انجام نشد؛ مسیر خطا (SSL failure) تست شد و Fallback و Logging صحیح عمل کرد. Test Connection را روی سرور خودتان اجرا کنید.
3. فونت Vazirmatn به‌صورت `local()` رفرنس شده تا فایل فونت باندل نشود (کوچک ماندن افزونه)؛ اگر فونت روی سیستم/قالب موجود نباشد به فونت سیستم Fallback می‌شود. در صورت تمایل فایل woff2 را در `public/assets/` اضافه و `@font-face` را کامل کنید.
4. «بررسی وضعیت» در نوار اکشن فعلاً پیام راهنما نمایش می‌دهد (پیگیری واقعی نیاز به احراز هویت مشتری دارد — مسیر توسعه آینده).
5. Rate Limit مبتنی بر Transient است؛ روی سایت‌های چند سروره بدون Object Cache مشترک دقت آن کاهش می‌یابد.

## 9. Future Improvements

Seller Matching و Quotation (هوک‌ها آماده)، SMS/WhatsApp از طریق `partino_inquiry_notification`، داشبورد گزارش نموداری، WooCommerce Product Link، WP-CLI commands، REST endpointهای ادمین برای SPA، Multi-language با WPML/Polylang.

## 10. Final Acceptance Checklist

- [x] Plugin Activate می‌شود — بدون Activation Error
- [x] No PHP Fatal / Syntax Error (php -l همه فایل‌ها)
- [x] No JS Syntax Error (node --check)
- [x] Database ساخته و Seed می‌شود
- [x] Chatbot در Frontend نمایش داده می‌شود
- [x] Auto Open / Close / Reopen (launcher)
- [x] RTL / Mobile (bottom-sheet) / Desktop (floating)
- [x] Quick Actions / Part / Vehicle / Trim / Year Selection
- [x] Confirmation + Edit Vehicle / Year / Part
- [x] Phone Validation + Normalization (+98 → 09)
- [x] Inquiry ثبت و Conversation ذخیره می‌شود
- [x] Admin: نمایش، تغییر Status، Search، Filter، Pagination، CSV، Bulk، Delete
- [x] Settings + Live Preview + Import/Export/Reset
- [x] OpenRouter Test Connection endpoint + AI Fallback
- [x] Rate Limit (429) + Nonce (403) + Capability (403)
- [x] Prepared SQL / XSS Prevention / API Key فقط سرور / Log Redaction
- [x] Uninstall مشروط (پیش‌فرض حفظ داده)
- [x] README.md + PROJECT-QA.md

**PROJECT STATUS: READY** ✅
