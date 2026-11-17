# Library Management System — Master Website Development Specification
**Version 1.0 | PHP + MySQL + Node.js + Express**

## Purpose
Build a working database-driven Library Management System for a school/college library. The main application uses PHP, HTML, CSS, vanilla JavaScript and MySQL on WAMPServer. A separate Node.js + Express API connects to the same database. This specification is for website implementation only—not the academic report or presentation.

## 1. Scope
### Included
- Staff login for Admin and Librarian using one shared login page.
- Dashboard with live database metrics.
- Book catalog with search and filters.
- Books, authors, categories, members and physical-copy management.
- Issue/return workflow, loan history and overdue reports.
- Secure PHP/MySQL implementation.
- Independent Express JSON API for available books.

### Excluded
- Member login or self-service portal.
- Payments, barcode scanner hardware, mobile app, email notifications.
- Academic report, presentation, APA references or contribution table.

## 2. Critical instructions for OpenCode
1. Inspect the existing project and actual MySQL schema before editing.
2. Do not rebuild from scratch or overwrite working files.
3. Preserve existing landing page, styling, database connection and working routes.
4. Existing known files: `assets/mera.js`, `assets/tera.css`, `config/db.php`, `includes/header.php`, `includes/sidebar.php`, `admin.php`, `catalog.php`, `index.php`, `login.php`, `logout.php`.
5. Treat the live `library_management` schema as authoritative. Confirm exact columns, types, constraints and relationships. This document describes the intended model; it is not a verified schema dump.
6. Do not drop, truncate, recreate or reset tables. Ask before migrations or destructive operations.
7. Reuse `config/db.php`; inspect whether it provides PDO or MySQLi and stay consistent.
8. First task: audit only—no code changes. Report file tree, current page status, DB connection method, actual schema, mismatches and safe implementation order.
9. Implement in small phases. After each phase, list changed files and tests run.
10. No hard-coded dashboard numbers or fake success states.

## 3. Architecture
| Layer | Technology | Role |
|---|---|---|
| Main website | PHP, HTML, CSS, vanilla JS | Pages, sessions, CRUD, validation |
| Database | MySQL/MariaDB via WAMP | Persistent data |
| PHP DB access | Existing connection style; prepared statements | Secure queries |
| Independent API | Node.js + Express | JSON endpoint |
| API DB access | mysql2/promise connection pool | Read same database |

PHP is the primary website. Express is an independent API task, not a replacement frontend or backend.

## 4. Intended database model (7 tables)
Inspect the real schema before using any column names.

| Table | Purpose | Planned fields |
|---|---|---|
| `users` | Staff accounts | `id`, `name`, `email` unique, password hash, `role` (`admin`/`librarian`), `created_at` |
| `members` | Library members | `id`, `name`, `email`, `phone`, `address`, `membership_date`, `status` |
| `authors` | Authors | `id`, `name` |
| `categories` | Categories | `id`, `name`, `description` |
| `books` | Book title metadata | `id`, `title`, `isbn` unique, `author_id`, `category_id`, `publisher`, `publication_year` |
| `book_copies` | Physical copies | `id`, `book_id`, `barcode` unique; confirm whether `status` exists |
| `loans` | Issue/return history | `id`, `member_id`, `copy_id`, `issued_by`, `issue_date`, `due_date`, nullable `returned_at` |

### Relationships
- authors 1-to-many books.
- categories 1-to-many books.
- books 1-to-many book_copies.
- members 1-to-many loans.
- book_copies 1-to-many loans over time, but at most one active loan per copy.
- users 1-to-many loans via `issued_by`.

### Business rules
- Active loan: `returned_at IS NULL`.
- A copy is available when it has no active loan, unless the actual schema has a maintained copy status; avoid conflicting sources of truth.
- Only active members can borrow.
- A copy cannot have two active loans.
- Return updates `returned_at`; never delete loan history.
- Use transactions for issue/return.
- Do not delete records referenced by loan history; block deletion or use an existing archive/inactive mechanism.
- Enforce unique ISBN/barcode/email where the schema supports those fields.

## 5. Roles and login
Admin and Librarian use the same `login.php`. After login, check `users.role` on the server.
- Both: dashboard, catalog, books, members, issue/return, loans and reports.
- Admin only: staff account/role management and admin settings.
- Librarian: day-to-day library operations.
- Members are records in `members`; **no member login** in this version.

Use `password_hash()` and `password_verify()`. Regenerate session ID after login. Protect every private page and POST action with server-side session and role checks. Use CSRF tokens. Logout clears and destroys the session. Never store plaintext passwords.

## 6. Screen-by-screen requirements

### 6.1 Public landing — `index.php`
- Preserve existing design.
- Header with brand, Home and Staff Login.
- Hero section with system intro and Staff Login CTA.
- Feature sections for catalog, member records, issue/return and reports.
- Complete the currently unfinished footer.
- Do not show private member or loan information publicly.

### 6.2 Shared staff login — `login.php`
- Email and password fields; show/hide password; submit.
- Server-side validation and prepared query by email.
- Verify password hash.
- On success regenerate session ID and redirect to existing dashboard route (`admin.php` if that is the established route).
- Generic invalid-credentials message.
- No member login.

### 6.3 Dashboard — `admin.php` or existing dashboard route
Live SQL cards:
- Total book titles.
- Total physical copies.
- Total members.
- Currently issued copies.
- Available copies.
- Overdue loans.
Also show recent loans and quick actions (Add Book, Add Member, Issue Book, Catalog). Handle empty tables gracefully.

### 6.4 Catalog — `catalog.php`
Show title, ISBN, author, category, publisher, year, total copies and available copies. Search title/ISBN/author. Filter category and availability. Add pagination if needed. Actions: details, edit, delete/archive subject to history restrictions. Data must come from SQL.

### 6.5 Books
**Add:** title, ISBN, author select, category select, publisher, publication year. Validate required fields, lengths, uniqueness, year range and referenced IDs.
**Edit:** load by validated ID, prefill fields, update safely; unique ISBN excludes current record.
**Details:** show book metadata and each physical copy with barcode/availability.
**Delete:** block if related copies/loans would be orphaned; use safe archive only if schema supports it.

### 6.6 Book copies
- List copies by book with barcode and computed availability.
- Add copy with unique barcode.
- Edit barcode only if valid and unique.
- Never delete a copy with loan history.
- Availability comes from active loans unless actual schema has a consistent status mechanism.

### 6.7 Authors
- List, create, edit and search authors.
- Show linked book count.
- Do not delete an author linked to books unless books are reassigned or safe deletion is supported.

### 6.8 Categories
- List, create, edit and search categories.
- Show linked book count.
- Protect categories referenced by books.

### 6.9 Members
- List name, email, phone, membership date, status and active-loan count.
- Search name/email/phone; filter active/inactive.
- Add/edit member fields matching actual schema.
- Validate email and other inputs.
- Do not delete members with loan history; deactivate instead if supported.

### 6.10 Issue book
- Select active member, book title and available physical copy/barcode.
- Issue date and due date.
- On POST: transaction; verify active member; verify copy has no active loan; insert loan with member/copy/staff/date fields; commit.
- Reject already-issued copies and inactive members.
- Prevent duplicate concurrent issue using transaction/locking where supported.

### 6.11 Return book
- List active loans with member, title, barcode, issue and due dates.
- Search member/title/barcode; filter overdue/current.
- Confirm return.
- Update `returned_at` in a transaction; do not delete loan.
- Returned copy becomes available.

### 6.12 Loans / transactions
- Show active and historical loans.
- Columns: member, book, barcode, staff user, issue date, due date, return date, status.
- Filters: active, returned, overdue, member and date range.
- Use joins based on actual foreign keys.

### 6.13 Reports
- Overdue: active loan and due date before today.
- Currently issued: active loans.
- Available copies: total copies minus active loans, or equivalent correct query.
- Most borrowed titles.
- Member borrowing history.
- Date-range circulation report.
- Reports are read-only.

### 6.14 Shared includes
- `header.php`: brand, staff name, logout.
- `sidebar.php`: Dashboard, Catalog, Books, Authors, Categories, Members, Copies, Issue/Return, Loans, Reports.
- Hide admin-only links for Librarian, but enforce permissions server-side.
- Add `footer.php` if missing.
- Avoid duplicate navigation and repeated asset includes.

## 7. Suggested target structure
Extend the existing project; do not recreate it. Preserve `admin.php`, `catalog.php`, `mera.js` and `tera.css` if they are already referenced.

```text
LibraryManagement/
├── index.php
├── login.php
├── logout.php
├── admin.php
├── catalog.php
├── config/db.php
├── includes/
│   ├── header.php
│   ├── sidebar.php
│   ├── footer.php
│   ├── auth.php
│   ├── csrf.php
│   └── functions.php
├── books/ (add.php, edit.php, view.php, delete.php)
├── authors/ (index.php)
├── categories/ (index.php)
├── members/ (index.php, add.php, edit.php, delete.php)
├── copies/ (index.php, add.php)
├── loans/ (index.php, issue.php, return.php)
├── reports/ (index.php)
├── assets/
│   ├── tera.css
│   ├── mera.js
│   └── images/
└── api-express/
    ├── package.json
    ├── .env.example
    ├── .gitignore
    └── src/
        ├── server.js
        ├── db.js
        └── routes/books.js
```

## 8. PHP implementation standards
- Reuse the existing connection and its PDO/MySQLi style.
- Prepared statements for every dynamic query.
- Validate all input server-side; verify foreign-key IDs exist.
- Escape output with `htmlspecialchars($value, ENT_QUOTES, 'UTF-8')`.
- Use POST for state changes; never mutate data through GET.
- CSRF token for create/update/delete/issue/return.
- Post/Redirect/Get after successful submissions.
- Transactions for issue/return.
- Controlled user-facing errors; no raw SQL errors or stack traces.
- Responsive semantic HTML, labels, accessible controls and clear empty states.

## 9. Express API
Create a separate service under `api-express/`. Required endpoint:

`GET /api/books/available`

Return available book titles and current available-copy counts, computed from actual `book_copies` and active `loans` schema.

Example:
```json
{
  "success": true,
  "data": [
    {
      "book_id": 12,
      "title": "Introduction to Programming",
      "available_copies": 3
    }
  ]
}
```

Requirements:
- Express + `mysql2/promise` (or compatible maintained driver).
- `.env` variables: `DB_HOST`, `DB_PORT`, `DB_USER`, `DB_PASSWORD`, `DB_NAME`, `PORT`.
- Commit `.env.example`, never real `.env`.
- Connection pool and parameterized SQL.
- `GET /api/health`.
- Correct HTTP status codes and generic JSON errors.
- Do not expose password hashes, member contact details or private loan data.
- API is read-only for this task.

PHP uses existing `config/db.php`; Express uses its own `.env`. Both connect to the same `library_management` database.

## 10. Security checklist
| Risk | Control |
|---|---|
| SQL injection | Prepared statements |
| XSS | `htmlspecialchars()` output encoding |
| Password theft | `password_hash()` / `password_verify()` |
| Unauthorized access | Session guard + server-side role checks |
| CSRF | CSRF tokens on state-changing forms |
| Session fixation | Regenerate ID after login |
| Duplicate issue | Transaction + active-loan check/locking |
| History damage | Restrict deletes that break foreign keys/history |
| Error leakage | Generic UI errors; protected server logs |
| Credential leakage | `.env` excluded from version control |

## 11. UI/UX
- Preserve current landing page visual identity.
- Consistent staff header/sidebar and active nav state.
- Responsive desktop/tablet/mobile layouts.
- Readable tables, clear actions and empty states.
- Labeled forms with validation feedback.
- Confirmation for destructive actions and circulation actions.
- All visible statistics must be database-backed.

## 12. Development phases
A. Audit existing files and real database schema; no edits yet.  
B. Foundation: shared includes, auth/CSRF helpers, footer and navigation.  
C. Admin/Librarian login, sessions, role guards and logout.  
D. Dashboard and searchable/filterable catalog.  
E. Authors, categories, books, members and copies CRUD.  
F. Issue/return, active loans, history and overdue logic.  
G. Reports.  
H. Express API.  
I. Full verification and regression testing.

At the end of each phase report changed files, completed features, DB changes, tests run, results, known issues and next step.

## 13. Acceptance tests
- WAMP runs and PHP connects to existing `library_management`.
- Existing landing page and current routes still work; footer complete.
- Admin and Librarian can use shared login; invalid credentials rejected.
- Unauthenticated users cannot access staff pages.
- Librarian cannot access admin-only functions.
- Dashboard figures match SQL data.
- Catalog search and filters work.
- CRUD persists correctly.
- Authors/categories/members/copies are safe with foreign-key history.
- A copy cannot be issued twice while active.
- Return updates loan history and makes copy available.
- Reports match database records.
- Prepared statements, escaping, CSRF and role checks are verified.
- Express health and available-books endpoints return valid JSON.
- No existing data or working code is lost.

## 14. Important schema verification
Before implementation confirm:
- Does `book_copies` have a `status` column?
- Is `loans.returned_at` DATE or DATETIME?
- Are role values exactly `admin` and `librarian`?
- What are the exact foreign-key column names?
- Does `config/db.php` expose PDO or MySQLi?
- Are there existing constraints/indexes?

Do not force the live schema to match this document without approval.

## 15. First OpenCode prompt (audit only)
```text
Read LibraryManagement_Master_Development_Plan.docx or .md completely.

You are working in an existing Library Management System. Do not rebuild it.
First inspect all existing files and the actual MySQL library_management schema.
Preserve the existing landing page, login design, CSS/JS assets, database
connection and working routes. Do not drop, truncate, recreate or reset tables.

Do not edit any code yet. Return an audit report containing:
1. Existing file tree and each file's purpose.
2. Current landing/login/dashboard/catalog status.
3. How config/db.php connects to MySQL (PDO or MySQLi).
4. Actual seven tables, columns, types, primary keys, foreign keys and constraints.
5. Differences between the live schema and this specification.
6. Missing/broken routes or files.
7. A safe phased implementation plan.

Ask for approval before any schema migration or destructive change.
After audit approval, implement in small phases and test each phase.
```

## Final rule
The actual database schema and existing working code are authoritative. This plan is the implementation target, not permission to overwrite working project files.
