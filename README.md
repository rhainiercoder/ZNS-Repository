# Happy Teeth

## Requirements
- XAMPP (Apache + MariaDB/MySQL)
- PHP 8+
- phpMyAdmin

## Setup (Local)
1. Copy config file:
   - Duplicate `config.example.php` and rename to `config.php`
   - Edit values inside `config.php` if needed

2. Create the database:
   - Database name: `happy_teeth_db`

3. Import the SQL:
   - In phpMyAdmin → select `happy_teeth_db` → Import
   - Import: `happy_teeth_db.sql`

4. Run the project:
   - Start Apache + MySQL in XAMPP
   - Open: `http://localhost/happy-teeth/`

## Notes
- `config.php` is intentionally not committed (contains credentials).
- If you add a one-time admin setup page, delete it after use.
