# AI Camera POS System

A fast, lightweight, and modern **AI-Powered Scan Point-of-Sale (POS) System** designed for retail shops and grocery marts. Built with **pure native PHP 8+**, **MySQL**, **HTML5/CSS3/Vanilla JavaScript**, **Bootstrap 5**, and the **Google Gemini Vision API**.

No third-party PHP frameworks (Laravel, Symfony, etc.) are used.

---

## 🌟 Key Features

1. **AI Vision Product Recognition**:
   - Cashier uses a **laptop webcam** or **smartphone mobile camera** to snap a photo of multiple products at once (e.g. 2 cans of soda, 1 loaf of bread).
   - The photo is analyzed by **Google Gemini Vision AI** to count and identify the products.
   - **Strict MySQL Price Protection**: AI *only* identifies the items. All official product names, SKUs, retail prices, and stock counts are retrieved **exclusively from your MySQL database**. AI cannot hallucinate new items or tamper with prices!
   - Unrecognized items are flagged as **`Product not found`**, allowing the cashier to manually search and add them.

2. **Gemini Model Flexibility**:
   - Choose between top vision models directly from Admin Settings:
   - `gemini-3.6-flash` *(Recommended for fast multimodal vision)*
   - Built-in interactive **"Test AI Connection"** button with live latency indicator.

3. **Dual User Roles**:
   - **Admin**:
     - Modern statistics dashboard (today's sales, stock units, low-stock alerts, recent transactions).
     - Full Product Catalog Management (CRUD, image upload, SKU, price, stock).
     - Category Management.
     - User Management (Cashiers and Admins, password resets, active/inactive toggles).
     - Sales Reports with date range filters and receipt re-printing.
     - System Settings & Gemini API key configuration.
   - **Cashier**:
     - Fast touch-friendly POS interface.
     - AI camera viewfinder with front/rear camera flip and native mobile camera file fallback.
     - Cart management (item count, price, stepper quantity +/-, remove, discount).
     - Multi-payment checkout (Cash with fast preset buttons, Credit/Debit Card, QR Payment).
     - Real-time change calculator with deficit prevention.
     - Formatted thermal receipt popup and `@media print` optimized thermal receipt printing.
     - Personal sales history viewer.

4. **100% Mobile & Tablet Friendly**:
   - Responsive layout designed with large touch buttons for Camera, Add to Cart, Checkout, and Print Receipt.
   - Supports live HTML5 webcam stream as well as native smartphone camera photo capture.

5. **Security & Data Integrity**:
   - PHP sessions with secure cookies.
   - CSRF protection on POST requests.
   - Secure password hashing using `password_hash()` (BCrypt).
   - PDO prepared statements everywhere to eliminate SQL injection.
   - Atomic database transactions during checkout with row-level stock verification.

---

## 📁 Project Structure

```
c:\xampp\htdocs\ai_pos_system\
│
├── config/
│   ├── database.php          # PDO connection with port auto-fallback (3307 & 3306)
│   ├── config.php            # Session, CSRF, settings, and formatters
│   └── auth.php              # Role authentication (Admin vs Cashier) & audit logger
│
├── database/
│   ├── schema.sql            # Complete database schema with foreign keys & seed data
│   ├── init_db.php           # Web/CLI database installer script
│   └── create_svgs.php       # Product vector icon generator
│
├── api/
│   ├── ai_detect.php         # Gemini Vision integration & MySQL catalog matcher
│   ├── test_ai.php           # Test Gemini API connection endpoint
│   ├── pos_checkout.php      # Transactional order placement & stock deduction
│   └── get_products.php      # Live product search & category filtering for POS
│
├── admin/
│   ├── index.php             # Admin Dashboard (KPIs, low-stock warnings, recent orders)
│   ├── products.php          # Product catalog management (table, search, filters)
│   ├── product_form.php      # Add / Edit product modal & image uploader
│   ├── categories.php        # Category management (CRUD)
│   ├── users.php             # User management (Admins & Cashiers)
│   ├── sales.php             # Sales history, filter by date, view & reprint receipt
│   └── settings.php          # System settings & Gemini API configuration
│
├── pos/
│   ├── index.php             # Cashier POS Interface (AI Camera, Cart, Quick Grid)
│   └── sales.php             # Cashier Sales History & Receipt Reprinting
│
├── includes/
│   ├── header.php            # Navbar, mobile responsive navigation
│   ├── footer.php            # Global scripts, system status
│   └── alerts.php            # Flash message renderer
│
├── assets/
│   ├── css/
│   │   ├── style.css         # Modern POS layout & responsive styles
│   │   └── receipt.css       # Thermal receipt print styles (@media print)
│   ├── js/
│   │   ├── pos.js            # POS state (cart, qty, change calculator, checkout)
│   │   ├── camera.js         # Webcam stream & mobile photo capture handler
│   │   └── main.js           # Shared utilities (toasts, formatters)
│   └── uploads/
│       └── products/         # Uploaded and demo product images
│
├── tests/
│   ├── test_system.php       # 20-point automated system verification test
│   └── test_web_login.php    # HTTP authentication test
│
├── login.php                 # Authentication page with 1-click demo logins
├── logout.php                # Session destruction and redirection
├── receipt_view.php          # Standalone printable thermal receipt
└── README.md                 # Complete documentation
```

---

## 🚀 Setup Instructions

### Prerequisites
- **PHP 8.0+** with `pdo_mysql` and `curl` extensions enabled.
- **MySQL 5.7+ / 8.0+** or MariaDB 10.4+.
- Web server (**Apache** via XAMPP or PHP built-in server).

### 1. Database Initialization
The database configuration is in `.env` (copy `.env.example` if needed). By default, it connects to:
- **Host**: `127.0.0.1`
- **Port**: `3307` *(with auto-fallback to `3306`)*
- **User**: `root`
- **Password**: empty *(set `DB_PASS` in `.env` if your MySQL user has a password)*

For Gemini outside the Admin Settings page, set `GEMINI_API_KEY` in `.env`. The database value saved from Admin Settings takes precedence when present. Never commit `.env`.

To initialize the database, tables, and sample catalog:
**Option A (Command Line):**
```bash
cd c:\xampp\htdocs\ai_pos_system
php database/init_db.php
```

**Option B (Web Browser):**
Open your browser and navigate to:
```
http://localhost/ai_pos_system/database/init_db.php
```

The script will automatically create the `ai_pos_system` database, all 7 tables, and seed the initial inventory and demo accounts.

### 2. Access the Application
Open:
```
http://localhost/ai_pos_system/
```
*(Or if using PHP built-in server: `php -S localhost:8000`, open `http://localhost:8000/`)*

---

## 🔑 Default Login Credentials

You can log in manually or click the **"Quick Demo Login"** buttons on the login page:

| Role | Username | Password | Access Level |
| :--- | :--- | :--- | :--- |
| **Administrator** | `admin` | `admin123` | Full Access: Dashboard, Products, Users, Sales, AI Settings, POS |
| **Cashier** | `cashier` | `cashier123` | Cashier Access: POS, AI Camera Scanner, Checkout, Personal Sales |

---

## 🤖 Google Gemini API Configuration

1. Log in as **Admin** (`admin` / `admin123`).
2. Go to **AI & Settings** in the top navigation bar (`/admin/settings.php`).
3. Under **Google Gemini AI Camera Engine**:
   - **Select AI Model**: Choose `gemini-3.6-flash` (recommended).
   - **Gemini API Key**: Paste your Google Gemini API key.
     - *How to get a free API key:*
       1. Visit [Google AI Studio](https://aistudio.google.com/app/apikey).
       2. Sign in with your Google account.
       3. Click **"Create API Key"** and copy the key string (`AIzaSy...`).
4. Click the **"Test Connection"** button. The system will ping Gemini and show:
   ```
   ✓ Connection Successful!
   Model: gemini-3.6-flash | Latency: 480 ms
   AI response: "AI POS Engine Ready."
   ```
5. Click **"Save All Settings"**.

---

## 🛒 How to Test the AI Camera POS System

### Step 1: Open the POS
1. Log in as **Cashier** (`cashier` / `cashier123`) or switch to POS from Admin.
2. You will see the POS screen with the product catalog on the left and the shopping cart on the right.

### Step 2: Open Camera Scanner
1. Click the large purple **"AI Camera Scan"** button at the top-left.
2. The scanner modal will open:
   - On desktop/laptop: Your webcam stream will display in the viewfinder box.
   - On mobile phone: You can use the live stream or tap **"Upload / Phone"** to directly snap a photo with your phone's native camera.

### Step 3: Take a Product Photo
Point your camera at products that exist in your store catalog. For example:
- 2 cans of Coca-Cola and 1 packet of White Bread.
- Click **"Take Photo"**.

### Step 4: AI Identification & Catalog Verification
1. Gemini Vision analyzes the photo, counts each visible item, and identifies the products.
2. The system compares the detected items against the **MySQL database**:
   - **Matched Items**: Displayed with a green border, official MySQL price, SKU, and detected quantity.
   - **Unmatched Items**: Displayed with a red warning: **`Product not found`** (e.g. if the item is not in the store's database). Cashier can click *"Search Catalog"* to manually add an alternative.
3. The cashier can adjust the quantity with `+` / `-` buttons before adding to the cart.
4. Click **"Add Detected Items to Cart"**.

### Step 5: Checkout & Payment
1. The items will appear in the cart on the right with the real-time subtotal, discount option, and grand total.
2. Click the large green **"Checkout"** button.
3. Select the payment method:
   - **Cash**:
     - Enter cash received or tap the quick cash buttons (`Exact`, `+10`, `+20`, `RM50`, `RM100`).
     - The change amount will calculate instantly in real-time.
   - **QR Payment**:
     - Shows dynamic DuitNow / QR payment box for contactless e-wallets.
   - **Card**:
     - Records payment via credit or debit card terminal.
4. Click **"Complete & Print Receipt"**.

### Step 6: Print Thermal Receipt
1. The official thermal receipt modal appears, containing:
   - Shop name, address, phone number
   - Unique invoice number (e.g., `INV-20260903-ABCD`)
   - Date and time
   - Cashier name
   - Line items, quantities, unit prices, line totals
   - Subtotal, discount, grand total
   - Amount paid & change returned
   - Thank you message
2. Click **"Print Receipt"** to trigger thermal printer output (optimized with `@media print`).
3. Click **"New Sale"** to clear the cart for the next customer.

---

## 🧪 Automated Testing

A 20-point verification test script is included. To run it:
```bash
php tests/test_system.php
```

**Output:**
```text
=== AI CAMERA POS SYSTEM AUTOMATED VERIFICATION ===

[PASS] All required tables exist (users, categories, products, orders, order_items, settings, audit_logs)
[PASS] Admin user exists in database
[PASS] Admin user role is 'admin'
[PASS] Admin password 'admin123' verifies against hash
[PASS] Cashier user exists in database
[PASS] Cashier user role is 'cashier'
[PASS] Cashier password 'cashier123' verifies against hash
[PASS] Settings 'shop_name' is configured: AI SMART MART
[PASS] Settings 'currency_symbol' is configured: RM
[PASS] Settings 'gemini_model' is configured: gemini-3.6-flash
[PASS] Catalog has at least 10 active products (found: 14)
[PASS] Sample product 'Coca-Cola Can 320ml' (BEV-001) exists
[PASS] Product image exists on disk: assets/uploads/products/coca_cola.svg

--- Testing Transactional Checkout ---
[PASS] Checkout created order #1 with invoice 'TEST-INV-XXXX'
[PASS] Coca-Cola stock decremented correctly
[PASS] Gardenia Bread stock decremented correctly
[PASS] Order change calculated correctly

--- Testing AI Vision Matching & Price Protection ---
[PASS] AI detected 2 matched items from MySQL catalog
[PASS] AI matched item price for Coca-Cola comes strictly from MySQL (RM2.80)
[PASS] Unmatched product is flagged as 'Product not found'

========================================
RESULTS: 20 PASSED, 0 FAILED
========================================
```

---

## 🛡️ Security Best Practices Included

- **No Plaintext Passwords**: Encrypted using PHP `password_hash()` (BCrypt).
- **SQL Injection Prevention**: 100% PDO prepared statements.
- **Cross-Site Request Forgery (CSRF)**: Dynamic CSRF tokens on all POST requests.
- **Cross-Site Scripting (XSS)**: Escaped outputs using `htmlspecialchars()`.
- **Role-Based Access Control**: Strict `require_admin()` and `require_login()` checks.
- **Race Condition Prevention**: Stock deduction executed inside atomic PDO transactions with `FOR UPDATE` locks.

---

## 📄 License
This project is open-source and free for commercial or educational use.
