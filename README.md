# CommerceGo

CommerceGo is a PHP and MySQL web-based e-commerce and operations system built for Essen Pharmacy. It supports customer shopping, staff product/inventory workflows, admin management, POS sales, Stripe checkout, product imports, stock control, expiry handling, and support chat.

## Main Functionalities

### Authentication and Roles
- User registration, login, logout, forgot password, and reset password.
- Role-based access for `admin`, `staff`, and `customer`.
- Separate portal layouts and navigation for each role.
- Profile management with avatar/profile updates.

### Customer Portal
- Browse approved pharmacy products by search and category.
- Product cards with low-stock and sold-out display states.
- Add to cart with stock validation.
- Cart quantity update, remove item, clear cart, and order summary.
- Sold-out cart items remain visible but are greyed out and excluded from checkout totals.
- Checkout through Stripe Checkout.
- Order success flow that creates orders, deducts stock, and clears the cart.
- Order history page.
- Support chat and store location page.

### Staff Portal
- Staff dashboard with product count, pending compliance count, low-stock count, and expired-product count.
- Product table with expiry highlighting, red low-stock quantities, and filters for category, low stock, and expired products.
- Add product submissions for admin approval.
- Product import through `.xlsx` or `.csv`.
- Staff product management and profile page.
- Access to POS workflow.

### Admin Portal
- Admin dashboard with revenue, orders, customers, pending approvals, and alert cards.
- Alert counts for pending approvals, low-stock products, and expired products.
- Product management: add, edit, delete, search, filter, import, pagination, low-stock filter, and expired-product filter.
- Staff management.
- Customer management.
- Product approval/rejection workflow for staff submissions.
- Order listing and order detail pages.
- Analytics/reporting page.
- Support chat management.

### Inventory, Expiry, and Checkout Rules
- `stockQuantity` is the main stock source used across product pages, cart, checkout, POS, admin, and staff screens.
- Low stock is shown in the admin/staff/customer inventory views.
- Expired active products are automatically marked inactive by `includes/product-expiry.php`.
- Customer-facing product/cart/checkout flows block expired, inactive, unapproved, sold-out, or over-stock checkout items.

### Product Import
- Admin and staff can upload `.xlsx` or `.csv` files.
- Import fields include product name, barcode, description, category, price, stock, image path, expiry date, status, compliance, and product type.
- Product images can be imported from paths/URLs or auto-generated depending on importer logic.

### POS
- Staff POS pages support product lookup, cart, checkout, and receipts.
- POS product eligibility checks include active status, approval, stock, and expiry.

## Technology Used

- **Backend:** PHP
- **Database:** MySQL / MariaDB
- **Database access:** MySQLi
- **Frontend:** HTML, CSS, vanilla JavaScript
- **Payment:** Stripe PHP SDK
- **Dependency management:** Composer
- **Spreadsheet import:** PHP-based XLSX/CSV import helper
- **Local server target:** XAMPP on Windows

## Project Structure

```text
FYP-CommerceGo/
|-- admin/              Admin dashboard, products, approvals, staff, customers, orders, analytics
|-- customer/           Customer product browsing, cart, checkout, orders, profile, support chat
|-- staff/              Staff dashboard, product submissions, product management, profile
|-- pos/                Staff POS cart, checkout, receipt, product lookup
|-- includes/           Shared config, product import, expiry automation, barcode, support helpers
|-- css/                Shared login/register styles
|-- scripts/            Local helper scripts for demo import files
|-- vendor/             Composer dependencies
|-- commercego.sql      Main database schema and seed export
|-- db.php              Shared database connection
|-- login.php           Login entry point
|-- register.php        Customer registration
`-- STRIPE_SETUP.md     Stripe testing notes
```

## Local Setup

### 1. Requirements
- XAMPP with Apache and MySQL/MariaDB.
- PHP supported by your XAMPP installation.
- Composer.
- A web browser.

### 2. Place the Project
Put the project folder here:

```text
C:\xampp\htdocs\FYP-CommerceGo
```

The local app URL is:

```text
http://localhost/FYP-CommerceGo
```

### 3. Install PHP Dependencies
From the project folder:

```bash
composer install
```

This installs the Stripe PHP SDK into `vendor/`.

### 4. Create and Import the Database
1. Start Apache and MySQL in XAMPP.
2. Open phpMyAdmin.
3. Create a database named:

```text
commercego
```

4. Import:

```text
commercego.sql
```

### 5. Configure Environment Values
The app defaults to local XAMPP values:

```text
DB host: localhost
DB user: root
DB password: empty
DB name: commercego
App URL: http://localhost/FYP-CommerceGo
```

Optional `.env` file in the project root:

```env
COMMERCEGO_DB_HOST=localhost
COMMERCEGO_DB_USER=root
COMMERCEGO_DB_PASS=
COMMERCEGO_DB_NAME=commercego
COMMERCEGO_APP_URL=http://localhost/FYP-CommerceGo
STRIPE_SECRET_KEY=your_stripe_test_secret_key
```

Do not commit real secret keys.

### 6. Configure Stripe for Checkout
For Stripe test checkout:
1. Add a Stripe test secret key through `.env` or local config.
2. Use Stripe test mode.
3. Test card:

```text
4242 4242 4242 4242
```

Use any future expiry date, any CVC, and any cardholder name.

More details are in `STRIPE_SETUP.md`.

### 7. Run the Application
Open:

```text
http://localhost/FYP-CommerceGo
```

Login/register through the web UI. Existing seed users are included in `commercego.sql`; additional customer accounts can be created using `register.php`.

## Useful Pages

- Login: `http://localhost/FYP-CommerceGo/login.php`
- Register: `http://localhost/FYP-CommerceGo/register.php`
- Admin dashboard: `http://localhost/FYP-CommerceGo/admin/dashboard.php`
- Staff dashboard: `http://localhost/FYP-CommerceGo/staff/dashboard.php`
- Customer dashboard: `http://localhost/FYP-CommerceGo/customer/dashboard.php`
- POS dashboard: `http://localhost/FYP-CommerceGo/pos/dashboard.php`

## Notes for Development

- Run PHP syntax checks after editing PHP files:

```bash
php -l path/to/file.php
```

- Keep `vendor/` generated by Composer.
- Keep credentials in `.env` or ignored local config files.
- Import/demo spreadsheets are for product import testing.
- The project is designed as a PHP/MySQL full-stack app, not a separated frontend/backend application.
