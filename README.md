# Online Printing and Packaging Services System

Multi-vendor web application built with **Core PHP (PDO)**, **MySQL**, **HTML/CSS/JavaScript** and **Bootstrap 5**.
Customers order custom printing and packaging from several vendors in one request, upload designs,
get automatic cost calculation (items + delivery distance), pay (Cash on Delivery by default) and track delivery on a map.

## Setup (XAMPP) - 4 steps
1. Extract the ZIP and copy the folder `online-printing-system` to `C:\xampp\htdocs\`.
   **Check:** the file `index.php` must be directly at `C:\xampp\htdocs\online-printing-system\index.php`
   (NOT in `...\online-printing-system\online-printing-system\`). If you had an older copy, delete it first or you will keep seeing the old project.
2. Open the XAMPP Control Panel and **Start** Apache and MySQL.
3. Open `http://localhost/online-printing-system/install.php` and click **Install** (or **Repair** if you already have the old database).
   *(Alternative: phpMyAdmin -> Import -> `database/printing_system.sql`.)*
4. Open `http://localhost/online-printing-system/`

## If something shows an error
- Open `http://localhost/online-printing-system/check.php`: it tests PHP, extensions, folders, MySQL and every table, and tells you exactly what to fix.
- "Your database is out of date" -> open `install.php` and click **Repair the database** (keeps your data).
- Errors are written to `logs/php-error.log`. To see PHP errors on screen while developing, set `APP_DEBUG` to `true` in `config/app.php`.
- `install.php` and `check.php` only work from the same computer (localhost). You can delete `install.php` after setup.

## How to place a multi-vendor order (one checkout, several vendors)
1. Log in as `customer@printpack.com`, open **Services**.
2. Click **+ Add to cart** on a service of vendor A (for example *Vinyl Banner*), then on a service of vendor B (for example *Corrugated Shipping Box*).
3. Open the **Cart**: it shows *"Multi-vendor order: 2 vendors"* with each vendor's items, then **Proceed to Checkout**.
4. Pick the delivery location on the map: delivery distance and charge are calculated **per vendor**; confirm the order.
5. The order has one number but one part per vendor. Each vendor sees only its own part, assigns its own rider, updates its own status and confirms its own payment. Sample data already contains two multi-vendor orders.

## Newest additions (see `docs/CHANGES_1_TO_6.md`)
Contact Administrator for suspended accounts + admin Support Requests, shared footer with Help/FAQ/Contact pages, vendor name on service cards,
public vendor profile, vendors choose their services from a list at registration, Back-to-Top button.
**Upgrading from the previous version?** Open `install.php?mode=repair` (keeps your data) or run `database/migrations/2026_changes_1_to_6.sql` in phpMyAdmin.
Demo: `suspended@printpack.com` / `password123` is a suspended vendor (login -> Contact Administrator).

## Test accounts (password for all: `password123`)
| Role | Email |
|---|---|
| Admin (use `/admin/login.php`) | admin@printpack.com |
| Vendor | vendor@printpack.com, vendor2@printpack.com, vendor3@printpack.com |
| Customer | customer@printpack.com, customer2@printpack.com |

## Requirements coverage
| Requirement | Where |
|---|---|
| Registration / login, roles (customer, vendor, admin); vendors must be approved by the admin before they can log in | `register.php`, `login.php`, `admin/login.php`, `includes/auth.php` |
| Service browsing & search (category, vendor, price, availability) | `customer/services.php` |
| Vendor service management (type, size, material, quantity, price, options) | `vendor/services.php`, `add-service.php`, `edit-service.php` |
| Multi-vendor order in a single request | `customer/cart.php`, `customer/place-order.php` |
| Automatic cost calculation (service, quantity, customisation, distance) | `includes/functions.php` (`cart_lines`, `delivery_charge`), live in checkout |
| Order tracking with maps (estimated vehicle position, not real GPS) | `customer/tracking.php` + `api/tracking.php` + `assets/js/map.js` |
| Notifications (confirmed, processing, dispatched, delivered) | `notifications.php`, `notify()` in `includes/functions.php` |
| Admin dashboard & control panel | `admin/*.php` |
| Reporting & analytics | `admin/reports.php` |
| Feedback & rating | `customer/feedback.php`, `vendor/feedback.php`, `admin/feedback.php` |
| Payment (COD default, online optional = demo) | checkout + `customer/payment.php` |
| UC-20 Manage Deliveries (vendor assigns rider/courier) | `vendor/order-details.php`, `save_delivery_details()` |
| UC-21 Receive Payments (vendor confirms payment received) | `vendor/payments.php`, `confirm_vendor_payment()` |
| File upload & design submission | `customer/upload-design.php`, `download.php` |
| Global search | `search.php` (search box in the navbar) |
| Responsive, cross-platform design | Bootstrap 5 layout, `assets/css/style.css` |

## Order status flow (used in UI, SQL and PHP)
`Pending -> Processing -> Dispatched -> Delivered` (or `Cancelled`). A vendor must assign a delivery rider before an order can be Dispatched.
Cash on Delivery stays `Pending` until delivery; the vendor then confirms the payment received (UC-21), and when all vendors of the order
have confirmed, the order payment becomes `Paid`.

## Documentation
`docs/DOCUMENTATION_SYNC_GUIDE.md` has the final terminology, replacement wording for test cases, architecture, sequence diagrams,
module diagram and the database design generated from the real SQL.

## Cost calculation
- Unit price = service price + selected customisation options
- Items total = unit price x quantity
- Delivery charge (per vendor) = base fee + rate per km x distance (vendor shop -> customer, Haversine formula)
- Default base fee Rs. 100 and Rs. 15/km; the admin can change them in **Admin -> More -> Settings**.
- Prices are always recalculated on the server, never trusted from the browser.

## Maps
Works out of the box with **OpenStreetMap** (no key needed). To use **Google Maps** instead, paste your API key in
**Admin -> More -> Settings -> Map provider** (or in `config/app.php`, `GOOGLE_MAPS_API_KEY`). Customers mark their delivery location on the map at checkout;
vendors set their shop location in their profile. The tracking vehicle position is an *estimate* based on
dispatch time and distance (there is no GPS device), and refreshes every 15 seconds.

## Security measures
Prepared statements everywhere (SQL injection), output escaping (XSS), CSRF tokens on every form,
`password_hash` / `password_verify`, session regeneration on login, role checks and ownership checks on every page,
upload validation (extension + real MIME type + size, random file names, files served only through `download.php`),
vendors need admin approval before login, suspended accounts cannot log in.

## Notes
- Online card/wallet payment is a **simulated demo**: no real transaction happens and no card data is stored.
- Notifications are in-app (no email/SMS).
- The logo `assets/img/logo.svg` is a placeholder: overwrite it with your own logo file.
- Admin accounts are stored in `users` and linked 1:1 by the `admins` table (Admin_ID + User_ID).
- Folder structure: `config/` settings, `includes/` shared code, `customer/ vendor/ admin/` portals, `api/` JSON feed,
  `assets/` CSS/JS/libraries, `database/` SQL, `uploads/` design files.
