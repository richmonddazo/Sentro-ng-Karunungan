# Sentro ng Karunungan — Step 1 Foundation

This starter converts the project from browser-only/localStorage behavior to PHP + MySQL.

## Install
1. Import `database/sentro_ng_karunungan.sql` in phpMyAdmin.
2. Copy the package contents into `C:\xampp\htdocs\Sentro-ng-Karunungan`.
3. Keep `config/database.php` at XAMPP defaults unless your MySQL credentials differ.
4. Start Apache and MySQL.
5. Open `http://localhost/Sentro-ng-Karunungan/register.php`.
6. Register a normal account and test login/logout.

## Make your first admin
After registering the account you want to use as admin, run in phpMyAdmin:

```sql
UPDATE users
SET role = 'admin'
WHERE email = 'YOUR_ADMIN_EMAIL_HERE';
```

Log out and sign back in. The account will be redirected to the admin dashboard.

## Included data model
- users
- categories
- books
- reservations
- notifications
- audit_logs

## Next milestone
Book management + cover uploads + public/user browse/search + reservation request flow.
