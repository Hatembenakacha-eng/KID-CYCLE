# KidCycle - Configuration & Database Setup Guide

## Issue Fixed
✅ Session authentication not persisting despite being logged in

## What Was Wrong
1. Missing API endpoint definition in JavaScript
2. Broken async/await fetch calls with syntax errors
3. Missing session persistence to localStorage
4. CORS headers not properly configured
5. No database schema

## Setup Instructions

### Step 1: Create the Database
1. Open phpMyAdmin (usually at http://localhost/phpmyadmin)
2. Go to "SQL" tab or click on "New" database
3. Copy the entire contents of `php/database.sql`
4. Paste it into the SQL editor
5. Click "Go" to execute

**Alternatively, via terminal:**
```bash
mysql -u root -p < "c:\xampp\htdocs\KID-CYCLE-master\php\database.sql"
```

### Step 2: Verify Installation
After setup, test the login at: `http://localhost/KID-CYCLE-master/Connexion.html`

**Test credentials:**
- Email: `admin@kidcycle.com`
- Password: `admin123`

OR

- Email: `marie@example.com`
- Password: `user123`

### Files Modified
1. ✅ **kidcycle.js** - Added API_AUTH constant, fixed fetch calls
2. ✅ **Connexion.html** - Added kidcycle.js script include
3. ✅ **formulaire.html** - Added kidcycle.js script include
4. ✅ **config.php** - Fixed CORS headers for credentials
5. ✅ **database.sql** - Created complete schema (NEW FILE)

### Key Features Implemented
- ✅ Server-side session management via PHP
- ✅ localStorage fallback for frontend session state
- ✅ Proper CORS headers with credentials
- ✅ Secure password hashing with bcrypt
- ✅ Email validation and unique constraints
- ✅ Session persistence across pages

## Testing Checklist

- [ ] Database created successfully
- [ ] Can login with test credentials
- [ ] Session persists after page refresh
- [ ] "Mon compte" link shows user info on profile page
- [ ] Can navigate to protected pages (panier, favoris, etc.)
- [ ] Logout works and returns to home page
- [ ] New registration works and auto-logs in

## Troubleshooting

**"Cannot access account" message still appears:**
1. Clear browser localStorage: Press F12, go to Application/Storage, delete kc_session
2. Clear browser cookies
3. Check database connection: verify DB credentials in config.php match your setup
4. Check MySQL is running (XAMPP Control Panel)

**Login button doesn't work:**
1. Open browser console (F12)
2. Check for errors
3. Verify kidcycle.js loaded successfully
4. Ensure form has correct IDs (login-form, email, pwd, remember)

**"Erreur réseau" error:**
1. Verify PHP files are accessible at http://localhost/KID-CYCLE-master/api/auth.php
2. Check XAMPP Apache is running
3. Verify database connection in config.php

## Database Details

Tables created:
- **utilisateurs** - User accounts
- **produits** - Products/items for sale
- **commandes** - User orders
- **commande_items** - Items in each order
- **favoris** - User favorites

## Security Notes
- Passwords are hashed with bcrypt
- Sessions use secure cookies with SameSite=Lax
- Email addresses are unique and validated
- SQL injection is prevented with prepared statements
- CORS is properly configured for credentials
