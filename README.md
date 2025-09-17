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

