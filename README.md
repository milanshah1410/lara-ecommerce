## Installation Guide
```bash
# Get the code!
 git clone https://github.com/milan1410/archer.git && cd archer

# Install PHP dependencies
 composer install 

# Install JS dependencies
 npm install or npm i

#Set Environment Variable
 Rename file ..env.example to .env

# Database structure 
 php artisan migrate

# Database dummy data
 php artisan db:seed

# Run Project
 npm run dev
 php artisan serve

#Admin OR Manager URL 
 http://127.0.0.1:8000/admin

 Login Credentials
 1. U: admin@mstech.com 
    P: admin

# stub 
php artisan stub:publish

# Admin Module Gerneration Example:post 
php artisan make:filament-crud Post name:string body:text published:boolean
# with sample 20 data
php artisan make:filament-crud Post name:string body:text published:boolean --faker

#For Relationships
php artisan make:filament-crud Comment body:text post_id:belongsTo --faker

# For Examples : Country → State → City CRUD scaffolding with proper relationships.
php artisan make:filament-crud Country name:string code:string --faker
php artisan make:filament-crud State country_id:belongsTo name:string --faker
php artisan make:filament-crud City country_id:belongsTo state_id:belongsTo name:string --faker


# Filament
composer require filament/filament:"^3.2"
php artisan make:filament-panel admin
php artisan make:filament-user

Create a Filament Resource (CRUD)
For example, if you want a CRUD for Post model:
php artisan make:filament-resource Post

Create Filament Pages & Widgets
Page (custom logic):
php artisan make:filament-page DashboardStats

Widget (for dashboard cards):
php artisan make:filament-widget RevenueChart

php artisan vendor:publish --tag=filament-config

Filament Assets Not Published
Sometimes assets don’t get published properly. Run:
php artisan filament:upgrade


##setup ecomenrce
Products (variants/images/attributes)

Categories (nested / parent-child)

Customers (accounts, addresses)

Orders + Order Items (status, payments, shipments)

Inventory / Stock movements

Coupons & Discounts

Shipping Methods & Rates

Payment Methods (records / logs)

Reviews / Q&A

Site Settings / Tax & Currency / Admin Dashboard widgets
-----
1) Foundation (create core records first)
# Categories (self parent for nested categories)
php artisan make:filament-crud Category name:string slug:string parent_id:belongsTo description:text is_active:boolean

# Customers (users for storefront)
php artisan make:filament-crud Customer first_name:string last_name:string email:string phone:string is_active:boolean

# Products (base product; link to categories as many-to-many)
php artisan make:filament-crud Product name:string slug:string sku:string description:text price:decimal(10,2) sale_price:decimal(10,2) stock_quantity:integer is_active:boolean categories:belongsToMany

2) Product-related / catalog pieces
# Product images (images stored with product_id)
php artisan make:filament-crud ProductImage product_id:belongsTo file_path:string alt_text:string position:integer is_primary:boolean

# Product variants (size/color etc.)
php artisan make:filament-crud ProductVariant product_id:belongsTo sku:string price:decimal(10,2) stock_quantity:integer attributes:json

# Attributes (optional: product attribute definitions)
php artisan make:filament-crud Attribute name:string slug:string

# Attribute values (link attribute -> value -> variant)
php artisan make:filament-crud AttributeValue attribute_id:belongsTo value:string product_variant_id:belongsTo

3) Customer addresses & accounts
# Addresses linked to customers
php artisan make:filament-crud Address customer_id:belongsTo line1:string line2:string city:string state:string country:string postal_code:string is_default:boolean

# Wishlist (optional)
php artisan make:filament-crud Wishlist customer_id:belongsTo name:string
php artisan make:filament-crud WishlistItem wishlist_id:belongsTo product_id:belongsTo

4) Orders & payments
# Orders (basic order info; addresses stored as JSON optionally)
php artisan make:filament-crud Order customer_id:belongsTo status:string total_amount:decimal(10,2) payment_status:string shipping_address:json billing_address:json placed_at:datetime shipped_at:datetime notes:text

# Order items (line items)
php artisan make:filament-crud OrderItem order_id:belongsTo product_id:belongsTo product_variant_id:belongsTo quantity:integer unit_price:decimal(10,2) total_price:decimal(10,2)

# Payments (records / logs for payments)
php artisan make:filament-crud Payment order_id:belongsTo payment_method_id:belongsTo amount:decimal(10,2) currency:string transaction_id:string status:string paid_at:datetime meta:json

# Payment methods (for admin-managed payment modes)
php artisan make:filament-crud PaymentMethod name:string provider:string config:json is_active:boolean

5) Shipping, shipments & returns
# Shipping methods (flat rate / carrier)
php artisan make:filament-crud ShippingMethod name:string description:text price:decimal(10,2) is_active:boolean rules:json

# Shipments (tracking)
php artisan make:filament-crud Shipment order_id:belongsTo tracking_number:string carrier:string shipped_at:datetime delivered_at:datetime status:string

# Return / RMA requests
php artisan make:filament-crud ReturnRequest order_id:belongsTo customer_id:belongsTo status:string reason:text requested_at:datetime processed_at:datetime

6) Promotions, taxes & inventory movements
# Coupons
php artisan make:filament-crud Coupon code:string type:string amount:decimal(10,2) percentage:integer starts_at:datetime expires_at:datetime usage_limit:integer used_count:integer is_active:boolean

# Tax rates
php artisan make:filament-crud TaxRate name:string rate:decimal(5,2) country:string state:string is_active:boolean

# Stock / inventory movement log
php artisan make:filament-crud StockMovement product_id:belongsTo product_variant_id:belongsTo quantity:integer movement_type:string reason:text performed_by_id:belongsTo performed_at:datetime meta:json

7) Reviews, Q&A, & admin helpers
# Product reviews
php artisan make:filament-crud Review customer_id:belongsTo product_id:belongsTo rating:integer comment:text status:string

# Tags (optional)
php artisan make:filament-crud Tag name:string slug:string

# Settings / key-value for admin panel
php artisan make:filament-crud Setting key:string value:json group:string description:text


