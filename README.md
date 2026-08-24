# WooCommerce Orders Date Range Filter

Add custom date filters, preset date ranges, and cached summary statistics above the WooCommerce admin orders table.

## Features

- Custom **From / To** date filters
- Preset ranges:
  - Last 7 days
  - Last 14 days
  - Last 1–6 months
  - Last 1 year
- HPOS support
- Legacy `shop_order` list support
- Summary statistics for the selected range
- Total order count
- Unique customer count
- Total purchased item quantity
- Unique parent-product count
- Total order value
- Average order value
- Order-status distribution
- Top five products by quantity
- Five-minute statistics cache
- WordPress timezone-aware date handling
- No settings page or custom database table

## Requirements

- WordPress 6.0+
- PHP 7.4+
- WooCommerce

## Installation

1. Upload `WooCommerce-Orders-Date-Range-Filter` to `/wp-content/plugins/`.
2. Activate **WooCommerce Orders Date Range Filter**.
3. Open **WooCommerce → Orders**.
4. Select a preset range or enter custom dates.
5. Click **Apply**.

## HPOS / Legacy Support

For the HPOS order list, the plugin uses:

- `woocommerce_order_list_table_extra_tablenav`
- `woocommerce_order_list_table_prepare_items_query_args`

For the legacy `shop_order` list, it uses:

- `manage_posts_extra_tablenav`
- `pre_get_posts`

The plugin declares HPOS compatibility.

## Date Handling

All server-side date calculations use the WordPress timezone.

Preset month calculations are clamped to valid calendar dates to avoid end-of-month overflow.

If only one custom date is supplied, that date is used as both the start and end date.

If the From date is after the To date, the values are automatically swapped.

## Statistics

The selected date range shows:

- order count
- unique customers
- purchased item quantity
- unique products
- total order value
- average order value
- status counts
- top five products

`Total order value` means the sum of all matching order totals across the registered WooCommerce order statuses. It should not automatically be interpreted as recognized revenue, because cancelled, failed, refunded, or custom statuses may be included.

## Unique Customers

Registered customers are keyed by customer ID.

Guest customers are deduplicated using a hash of billing email when available, then billing phone. Orders without either value are counted independently.

Raw email addresses and phone numbers are not stored in the statistics cache.

## Performance

Statistics use paginated `wc_get_orders()` calls with 200 orders per batch and cache the computed result for five minutes.

The query returns `WC_Order` objects directly, avoiding an extra `wc_get_order()` lookup for each returned order ID.

Very large stores should benchmark the statistics box with production-sized order histories.

## Data Storage

No custom tables or persistent settings are created.

Statistics are temporarily stored in WordPress transients with a five-minute expiration.

## Security

- Order-list filtering and statistics require `manage_woocommerce`.
- Request dates are sanitized and strictly validated.
- Output is escaped according to context.
- No raw customer email or phone values are persisted in cached statistics.

## License

GPL-3.0

## Author

Amirreza Shayesteh Far  
https://github.com/amirrezashf

---

# فیلتر بازه تاریخ سفارش‌های ووکامرس

این افزونه فیلتر تاریخ سفارشی و بازه‌های آماده را به بالای جدول سفارش‌های پنل مدیریت WooCommerce اضافه می‌کند و پس از انتخاب بازه، آمار خلاصه همان دوره را نمایش می‌دهد.

## قابلیت‌ها

- تاریخ «از / تا»
- ۷ و ۱۴ روز اخیر
- ۱ تا ۶ ماه اخیر
- یک سال اخیر
- پشتیبانی از HPOS
- پشتیبانی از Order Storage قدیمی
- تعداد سفارش
- مشتری یکتا
- تعداد محصولات خریداری‌شده
- تنوع محصول
- مجموع مبلغ سفارش‌ها
- میانگین مبلغ سفارش
- وضعیت سفارش‌ها
- ۵ محصول پرفروش
- Cache پنج‌دقیقه‌ای
- بدون صفحه تنظیمات و Custom Table

## تاریخ‌ها

محاسبات Server-side بر اساس Timezone تنظیم‌شده WordPress انجام می‌شوند.

برای بازه‌های ماهانه، تاریخ‌ها به روز معتبر ماه مقصد Clamp می‌شوند تا مشکلات روزهای ۲۹، ۳۰ و ۳۱ ماه ایجاد نشود.

اگر فقط یکی از تاریخ‌های From یا To وارد شود، همان روز به‌عنوان کل بازه در نظر گرفته می‌شود.

## نکته درباره مبلغ کل سفارش‌ها

این عدد جمع Total تمام Orderهای موجود در Statusهای ثبت‌شده WooCommerce در بازه است و الزاماً «درآمد قطعی» نیست؛ زیرا ممکن است سفارش‌های لغوشده، ناموفق، Refund شده یا Statusهای سفارشی نیز در آن وجود داشته باشند.

## Performance

آمار به‌صورت صفحه‌بندی‌شده و در Batchهای ۲۰۰تایی محاسبه و به مدت پنج دقیقه Cache می‌شود.

## مجوز

GPL-3.0

## نویسنده

Amirreza Shayesteh Far  
https://github.com/amirrezashf
