# Stock-Inventory-Management
# Inventory Management System

## Overview
The **Inventory Management System** is a web-based application designed to help administrators efficiently manage products, stock transactions, and generate reports. The system includes authentication for administrators and essential inventory management features.

## Features
- **Admin Authentication**
- **Product Information Management**
  - Add, Delete, Update Products
  - Product Name, Description, Unit, Unit Price
- **Stock Management**
  - Purchase and Sale Transactions
  - Stock Status Tracking
- **Reports Generation**
  - Daily and Monthly Transaction Reports

## Installation Guide
### Step 1: Download and Extract Files
1. Download the zip file containing the project.
2. Extract the zip file.
3. Copy the `Inventory-Management-System` folder.

### Step 2: Place the Project in the Root Directory
- **For XAMPP:** Paste the folder in `C:\xampp\htdocs\`
- **For WAMP:** Paste the folder in `C:\wamp\www\`
- **For LAMP:** Paste the folder in `/var/www/html/`

### Step 3: Start Local Server
- Open **XAMPP/WAMP/LAMP** and start the following services:
  - Apache
  - MySQL

### Step 4: Setup the Database
1. Open your browser and go to **[http://localhost/phpmyadmin](http://localhost/phpmyadmin)**.
2. Click on **Databases** and create a new database named `ims480`.
3. Click on the newly created database and go to the **Import** tab.
4. Select the `ims480.sql` file located inside the `SQL file` folder from the extracted project.
5. Click **Go** to import the database.

### Step 5: Run the Project
1. Open your browser and navigate to:
   ```
   http://localhost/Inventory-Management-System
   ```
2. The login page should appear.

## Admin Credentials
- **Username:** `admin`
- **Password:** `admin`

## Screenshots
- Admin Login Page
- Stock Management
- Product Management
- Sales and Purchase Reports

## Troubleshooting
1. **Database connection error?**
   - Check the `config.php` file and ensure the database name is correct (`ims480`).
2. **Apache/MySQL not running?**
   - Restart XAMPP/WAMP/LAMP and check for port conflicts.
3. **Page not found?**
   - Verify the project folder is in the correct directory and the server is running.

## License
This project is free to use and modify for educational and personal purposes.

