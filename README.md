# Grand Library Management System

A self-contained staff and public catalog application for a small library. It is built with PHP and MySQLi and runs on a standard WAMP/Apache stack.

## Features

- Public catalog with search by title, author, or ISBN; category and availability filters.
- Shared staff login for `Admin` and `Librarian` accounts.
- Dashboard with live counts for titles, physical copies, available copies, members, active loans, and overdue loans.
- Book, author, category, physical copy, and member management.
- Dedicated author CRUD with search, linked-title counts, author profiles, and protected deletion.
- Membership status management with borrowing history.
- Book issue and return workflows with active member and copy availability checks.
- Loan register with member/title/ISBN/barcode search and active, overdue, and returned filters.
- Read-only reports for inventory, members, and circulation, with search, status and date filters plus print/save-as-PDF support.
- Admin-only staff account management.
- Responsive staff interface with CSRF protection, POST-only logout, and escaped output.

## Requirements

- Apache with PHP 8.3 or later, including the `mysqli` extension.
- MySQL 8.0 or later, or a compatible MySQL/MariaDB version.
- WAMP, XAMPP, or an equivalent Apache/PHP/MySQL environment.

## Installation

1. Place the project in the web root, for example:

   ```text
   C:\wamp64\www\LibraryManagement
   ```

2. Start Apache and MySQL.

3. Import the supplied database export:

   ```text
   C:\wamp64\www\LibraryManagement\database\library_management.sql
   ```

   From a command prompt in the project directory:

   ```text
   mysql -u root -p < database\library_management.sql
   ```

   The export creates the `library_management` database, all seven tables, constraints, indexes, and the current legitimate records. It includes staff password hashes but no plaintext passwords.

4. Configure the database connection using environment variables or a local override file.

   Supported environment variables:

   ```text
   DB_HOST
   DB_PORT
   DB_USER
   DB_PASS
   DB_NAME
   ```

   Alternatively, copy `config/local.example.php` to `config/local.php` and enter your local MySQL credentials. `config/local.php` is ignored by Git and must never be committed.

   If no environment variables or local override are provided, the application defaults to `localhost`, port `3306`, user `root`, an empty password, and database `library_management`.

5. Open the application:

   ```text
   http://localhost/LibraryManagement/
   ```

6. Sign in with an existing staff account. The exported records include the current Admin and Librarian accounts.

7. If you are installing with an empty `users` table instead, open `admin.php` and create the first Administrator account. This public setup page closes automatically after the first staff account exists.

No plaintext credentials are included in tracked source files or the SQL export.

## Roles and Permissions

| Role | Access |
| --- | --- |
| Admin | Dashboard, catalog, books, authors, categories, copies, members, circulation, reports, and staff management |
| Librarian | Dashboard, catalog, books, authors, categories, copies, members, circulation, and reports |
| Public | Landing page and catalog only |

Unauthenticated requests to staff pages are redirected to the login page. Librarians receive HTTP 403 for staff-management routes.

## Author Management

- Authors can be searched, created, viewed, edited, and deleted from the Authors section.
- Each author profile shows linked titles, physical copy totals, and available copies.
- Author names are limited to 150 characters and checked for duplicates.
- Authors linked to one or more books cannot be deleted until the books are reassigned or removed.
- All author screens require an authenticated staff session and state-changing actions require POST plus a valid CSRF token.

## Reports

Reports are read-only and available to both Admin and Librarian accounts.

- **Overview:** title, copy, availability, loan, overdue, member, author, and category totals.
- **Collection by Category:** title and copy totals with current availability.
- **Most Borrowed Titles:** the five titles with the most loan records.
- **Inventory Report:** search by title, ISBN, author, or category; filter by available or unavailable copies.
- **Member Report:** search by name, email, or phone; filter by status and membership-date range.
- **Circulation Report:** search by member, title, ISBN, or barcode; filter by loan status and issue-date range.
- Overdue means `returned_at IS NULL` and `due_date` is earlier than today.
- Availability is calculated from active loan records, not stored separately.
- **Print / Save PDF** uses a print-specific layout that hides navigation and filter controls.

## Circulation Rules

- Only members with status `Active` may borrow.
- Only physical copies without an active loan may be issued.
- The default loan period is 14 days.
- The issue date cannot be in the future.
- The due date cannot be earlier than the issue date.
- A copy cannot have more than one active loan at a time.
- Issuing and returning are transactional and use row locking to prevent duplicate active loans.
- `issued_by` is recorded from the logged-in staff account.
- Returning a loan sets `returned_at` to the current date. Loan history is preserved; loans are never deleted by the application.
- A loan is overdue when `returned_at` is `NULL` and `due_date` is earlier than today.
- Issue and return actions require POST requests and a valid CSRF token.

## Project Structure

```text
LibraryManagement/
|-- admin.php                  First Administrator setup
|-- catalog.php                Public catalog
|-- dashboard.php              Staff dashboard
|-- login.php                  Shared staff login
|-- logout.php                 POST-only session logout
|-- index.php                  Public landing page
|-- assets/
|   |-- tera.css               Application and print styles
|   `-- mera.js                Client-side UI behavior
|-- authors/                   Author CRUD and author profiles
|-- books/                     Book title management
|-- categories/                Category management
|-- config/
|   |-- db.php                 Environment-aware MySQLi connection
|   |-- local.example.php      Local credential override template
|   `-- local.php              Local credentials (ignored by Git)
|-- copies/                    Physical copy management
|-- database/
|   `-- library_management.sql Schema, constraints, and legitimate data export
|-- includes/
|   |-- auth.php               Session and role guards
|   |-- csrf.php               CSRF token helpers
|   |-- footer.php
|   |-- functions.php          Shared helpers
|   |-- header.php
|   `-- sidebar.php
|-- loans/
|   |-- index.php              Loan register, search, filters, returns
|   |-- issue.php              Issue a book
|   `-- return.php             Process a return
|-- members/                   Member management and borrowing history
|-- reports/
|   `-- index.php              Inventory, member and circulation reports
`-- staff/                     Administrator-only staff management
```

## Database Overview

The supplied export creates the seven-table schema:

- `users`: staff accounts, roles, statuses, and hashed passwords.
- `authors`: book authors.
- `categories`: book categories.
- `books`: titles, ISBNs, author/category links, publishers, and publication years.
- `book_copies`: physical copy records identified by barcode.
- `members`: library member records and membership status.
- `loans`: loan history with member, copy, issuing staff member, issue date, due date, and optional return date.

The schema includes primary keys, unique ISBN/barcode/email constraints, foreign keys, indexes, and loan date check constraints. The application reads and writes this schema but does not run automatic migrations.

## Security Notes

- Database queries use prepared statements and bound parameters.
- Database credentials can come from environment variables or `config/local.php`; no real password is stored in tracked source files.
- `config/local.php` and `.env` files are ignored by Git.
- Staff pages require an authenticated session with a valid `Admin` or `Librarian` role.
- Staff-management pages require the `Admin` role.
- State-changing requests validate CSRF tokens.
- Logout is POST-only with CSRF validation. A GET request to `logout.php` does not destroy the session.
- Output is escaped before being rendered in HTML.
- Session cookies are HTTP-only and use `SameSite=Lax`.
- Database and application error details are logged rather than shown to users.

## Manual Smoke Test

1. Open the public catalog and test search, category, and availability filters.
2. Sign in as an Administrator.
3. Create an author, a member, a book linked to that author, and at least one physical copy.
4. Confirm the author profile lists the linked title and copy availability.
5. Confirm an author linked to a book cannot be deleted, then delete an unused author.
6. Issue an available copy to an active member.
7. Confirm the copy is no longer available for issue while the loan is active.
8. Find the loan in the register and process the return.
9. Open Reports and test inventory, member, and circulation filters.
10. Use Print / Save PDF and confirm navigation and filter controls are hidden.
11. Confirm the dashboard counts and report totals match the database.
12. Sign in as a Librarian and confirm circulation/report access works and Staff Management returns HTTP 403.
13. Use the Log out button. Confirm a GET request to `logout.php` does not end an active session.

## Troubleshooting

- If the application reports that it is temporarily unavailable, check MySQL is running and the credentials in the environment or `config/local.php` are correct.
- If login fails, confirm the staff account status is `Active`.
- If `admin.php` is closed, a staff account already exists; use Staff Management or the existing Administrator account.
- If a copy cannot be issued, confirm the member is `Active` and the copy has no active loan.
- If an author cannot be deleted, reassign or remove the linked books first.
- If the SQL import fails, confirm you are using a MySQL 8.0+ client and that no conflicting `library_management` database is selected.
