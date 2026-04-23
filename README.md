# CSS Event & Productivity Dashboard
**Built by Four** - McMaster Computer Science Society

---

## File Structure

```
css_dashboard/
├── login.php               - Sign-in page (Increment 1 · Vincent)
├── logout.php              - Session destroy + redirect
├── events.php              - Browse events + RSVP (Increment 2 · Micai + Sumer)
├── event_form.php          - Create / Edit event form (Increment 2 · Micai)
├── event_delete.php        - Delete event handler (Increment 2 · Micai)
├── rsvp.php                - AJAX RSVP toggle endpoint (Increment 2 · Sumer)
├── dashboard.php           - Exec dashboard: stats, chart, productivity (Increment 2+3 · Saharsh + Angad)
├── log_contribution.php    - Save team stat contribution (Increment 3 · Angad)
│
├── includes/
│   ├── db.php              - PDO database connection
│   ├── auth.php            - Session helpers, requireLogin(), requireExec()
│   ├── header.php          - Shared HTML header + navbar partial
│   └── footer.php          - Shared HTML footer + toast JS
│
├── setup.sql               - Creates all DB tables + seeds demo data (run once)
├── my_style.css            - Shared site styles (Built by Five design system)
├── app_style.css           - Dashboard app styles (login, events, dashboard, forms)
│
├── index.html              - Home page (static)
├── about.html              - About Us / Team page (static)
├── project.html            - Projects index (static)
├── showcase.html           - Showcase page (static)
├── web-design-critique.html - Web Design Critique (static)
├── client-report.html      - Client Report (static)
├── dev-plan.html           - Development Plan (static)
│
└── imgs/                   - All images (logos, portraits, wireframes)
```

---

## Setup Instructions

### 1. Requirements
- PHP 8.0+
- MySQL 5.7+ or MariaDB 10.3+
- A local server: XAMPP, MAMP, Laragon, or WAMP

### 2. Create the Database

Open phpMyAdmin (or your MySQL client) and run:

```sql
CREATE DATABASE css_dashboard CHARACTER SET utf8mb4;
```

Then import the setup file:
```
mysql -u root -p css_dashboard < setup.sql
```
Or paste the contents of `setup.sql` into phpMyAdmin's SQL tab.

### 3. Configure Database Credentials

Open `includes/db.php` and update:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'css_dashboard');
define('DB_USER', 'root');       // ← your MySQL username
define('DB_PASS', '');           // ← your MySQL password
```

### 4. Fix Password Hashes (Important)

The demo passwords in `setup.sql` use a placeholder hash.
Run this in your terminal to generate real bcrypt hashes:

```bash
php -r "echo password_hash('exec123',   PASSWORD_DEFAULT) . PHP_EOL;"
php -r "echo password_hash('member123', PASSWORD_DEFAULT) . PHP_EOL;"
```

Then update the Users rows in your database with the output hashes.

Alternatively, add this temporary helper page, visit it once, then delete it:

```php
<?php
require 'includes/db.php';
$pdo = getDB();
$pdo->prepare("UPDATE Users SET password=? WHERE email='exec@mcmaster.ca'")
    ->execute([password_hash('exec123', PASSWORD_DEFAULT)]);
$pdo->prepare("UPDATE Users SET password=? WHERE email='member@mcmaster.ca'")
    ->execute([password_hash('member123', PASSWORD_DEFAULT)]);
echo 'Done';
```

### 5. Place the Project

Copy the entire `css_dashboard/` folder into your server's web root:
- **XAMPP**: `C:/xampp/htdocs/css_dashboard/`
- **MAMP**: `/Applications/MAMP/htdocs/css_dashboard/`
- **Laragon**: `C:/laragon/www/css_dashboard/`

### 6. Open in Browser

```
http://localhost/css_dashboard/login.php
```

---

## Demo Accounts

| Role      | Email                  | Password    |
|-----------|------------------------|-------------|
| Executive | exec@mcmaster.ca       | exec123     |
| Member    | member@mcmaster.ca     | member123   |

**Executive** sees: Events (with Edit/Delete), Dashboard (stats + bar chart + all 5 productivity teams + log forms), New Event form.

**Member** sees: Events page with RSVP toggle only.

---

## What Each File Does

| File | Increment | Who | What |
|------|-----------|-----|------|
| `login.php` | 1 | Vincent | Form → PDO query → session start → role redirect |
| `logout.php` | 1 | Vincent | session_destroy → redirect to login |
| `events.php` | 2 | Micai + Sumer | SELECT all events + RSVP counts; category JS filter; RSVP AJAX; exec edit/delete buttons |
| `event_form.php` | 2 | Micai | GET id → pre-fill for edit; POST → INSERT or UPDATE Events |
| `event_delete.php` | 2 | Micai | POST event_id → DELETE Events (RSVPs cascade) → flash redirect |
| `rsvp.php` | 2 | Sumer | AJAX POST → INSERT or DELETE RSVPs → return JSON {rsvpd, count} |
| `dashboard.php` | 2+3 | Saharsh + Angad | Stat counters (COUNT, AVG); monthly bar chart (GROUP BY MONTH); 5-team productivity grid with sub-teams + log forms |
| `log_contribution.php` | 3 | Angad | POST → INSERT Team_Stats → redirect dashboard |

---

## Exec Productivity Teams

The dashboard tracks contributions across 5 teams:

| Main Team | Sub-teams |
|-----------|-----------|
| **Communications** | Design, Social Media |
| **Student Support** | Academic, Mentorship |
| **Events Team** | *(none - flat)* |
| **Outreach** | *(none - flat)* |
| **Web & Tech** | *(none - flat)* |

Teams with sub-teams show a percentage breakdown bar per sub-team.
Teams without sub-teams show a single activity bar relative to the most active team.
All teams show the 3 most recent contributions logged.
