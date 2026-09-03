=== Partino Smart Parts Chatbot ===
Contributors: partino
Tags: chatbot, automotive, parts, inquiry, ai, persian, rtl
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

چت‌بات هوشمند استعلام قطعات خودرو با جریان مکالمه مرحله‌به‌مرحله، پشتیبانی از هوش مصنوعی OpenRouter و پنل مدیریت کامل.

== Description ==

Partino Smart Parts Chatbot یک چت‌بات فارسی و راست‌به‌چپ برای سایت‌های فروش قطعات خودرو است:

* جریان مکالمه State-Based: قطعه ← خودرو ← تیپ ← سال ← تأیید ← موبایل
* درک زبان طبیعی با OpenRouter (اختیاری) + جریان قاعده‌محور بدون AI
* پنل مدیریت کامل: استعلام‌ها، گفتگوها، خودروها، قطعات، اکشن‌های سریع، تنظیمات و لاگ‌ها
* خروجی CSV، جستجو، فیلتر و صفحه‌بندی
* امنیت استاندارد وردپرس: Nonce، Capability اختصاصی، Prepared Queries، Rate Limit
* حریم خصوصی: هش IP، Mask شماره موبایل، حالت‌های Minimal/Standard/Extended
* بدون وابستگی به قالب یا jQuery؛ CSS/JS کاملاً Namespace شده

== Installation ==

1. پوشه partino-smart-chatbot را در wp-content/plugins قرار دهید.
2. افزونه را از پیشخوان فعال کنید.
3. از منوی «Partino Chatbot ← تنظیمات» ظاهر، پیام‌ها و AI را پیکربندی کنید.

== Frequently Asked Questions ==

= آیا بدون کلید OpenRouter کار می‌کند؟ =
بله. جریان قاعده‌محور داخلی همه مراحل (قطعه، خودرو، تیپ، سال، تأیید، موبایل) را بدون AI پوشش می‌دهد.

= آیا کلید API در فرانت‌اند دیده می‌شود؟ =
خیر. کلید فقط در سرور نگهداری می‌شود و در پنل نیز Mask شده نمایش می‌یابد.

== Changelog ==

= 1.0.0 =
* انتشار اولیه.
