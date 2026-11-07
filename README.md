<<<<<<< HEAD
Database Management | Week: PHP Login / Logout + GitHub
Based on last week's Resume CRUD Lab
This code extends the previous CRUD project, retaining skills.php, skill_create.php, skill_edit.php, skill_delete.php and index.php.

Setup (local XAMPP / Angsila)
Backup the existing MySQL database in phpMyAdmin first.
Keep previous tables profile, education, skill, training. Do not re-import the previous setup.sql: it contains DROP TABLE commands.
In phpMyAdmin, select your existing database and run database/this_week_migration.sql (creates users only).
Configure config/db.php using your assigned MySQL credentials and database name (use environment variables on shared servers).
Upload files to a dedicated folder on web server (for Angsila, follow instructor's permitted deployment path).
Open register.php, sign up with a unique email and password >= 10 characters.
Open login.php then test skills.php, skill_create.php, skill_edit.php, skill_delete.php, index.php, logout.php.
All new accounts get their own profile; earlier demo skill records remain in the original profile and do not appear for new users.
Lab tasks
TODO 1: SELECT id, profile_id, password_hash ... WHERE email = ? with prepared statement.
TODO 2: bind email, execute, read matched user.
TODO 3: check password_verify (never compare passwords directly).
TODO 4: regenerate session ID; store user_id/profile_id/email on successful authentication.
TODO 5: redirect to skills.php.
TODO 6: logout.php securely destroys the session (POST + CSRF token).
Test cases
Unauthenticated access to skills.php, index.php or create/edit/delete redirects to login.
Existing account correct password logs in; incorrect password fails.
Logout prevents access to private pages, even with browser Back/refresh.
Two different registered users cannot view/update/delete each other's skills.
Create → Edit → Delete after login works correctly.
SQL injection string in email must not bypass login.
Repository does not contain a real DB password or .env file.
Git/GitHub evidence
git init → git add . → git commit -m "Add PHP session login" → git branch -M main → git remote add origin <URL> → git push -u origin main. For an existing repository, simply commit and push updates. Commit at least twice with meaningful messages. Avoid uploading secrets. Include screenshots (register, successful login, failed login, CRUD, logout) in your report, not database passwords.

Security note
Teaching lab only. HTTPS is recommended on shared Angsila hosting. Authenticator uses password hashing, prepared statements, session rotation, owner-scoped CRUD, and POST+CSRF logout; production apps also require throttling, session timeouts, centralized permissions, HTTPS enforcement and monitoring.

Deliverables
A. GitHub repository (source excluding secrets) B. Running Angsila URL or instructor-approved local demonstration C. Screenshot evidence D. Short explanation of Session and login/logout flow.

Instructor / student status
STUDENT STARTER: login/logout intentionally incomplete. Complete TODO 1–6.
Resume CRUD Lab: INSERT / UPDATE / DELETE
Continue from the previous PHP/MySQL Resume Lab. Student target: manage the skill table.

Setup
Use a separate practice database. Import database/setup.sql with phpMyAdmin. Caution: this file DROPS existing resume_lab tables and resets their data. Do not import into a database with important data.
Start Apache + MySQL, place this folder inside your local web root, and visit http://localhost/<folder>/skills.php.
Configure config/db.php for your environment. Defaults localhost/root/empty password are for local labs only. For Angsila, obtain real database settings from the administrator and keep credentials out of Git.
Read the files: skills.php, skill_create.php, skill_edit.php, skill_delete.php.
Student activities
Task 1 (INSERT): skill_create.php TODO 1, save a new skill, confirm the row appears.
Task 2 (SELECT + UPDATE): skill_edit.php TODO 2a/2b, change level and verify the new value.
Task 3 (DELETE): skill_delete.php TODO 3, delete one practice record and verify it disappears.
Task 4: explain why UPDATE and DELETE require WHERE. Demonstrate parameter binding.
Task 5: Git workflow: create feature branch, commit SQL completion, push to GitHub (NO secrets), submit repository URL and screenshots.
SQL mapping hints
INSERT: INSERT INTO ... (...) VALUES (?, ?, ?); bind order: profile_id, skill_name, skill_level.
SELECT: filter using id = ? AND profile_id = ?.
UPDATE: SET skill_name = ?, skill_level = ? with both WHERE conditions.
DELETE: do not omit WHERE id = ? AND profile_id = ?.
Security / scope
The CRUD pages use prepared statements, validation, output escaping, POST for changes, and CSRF tokens. They target the first demo profile only. This is an unauthenticated classroom example, not suitable for public deployment on Angsila without sign-in, authorization and production hardening. Prefer local-only access for exercises.

Grading (100 points)
INSERT 25, SELECT+UPDATE 30, DELETE 25, screenshots/testing 10, Git commits 10.

Starter: fill all TODO SQL queries before submitting.
=======
# Find-my-meow-meow
เว็บไซต์ประกาศหาแมวและหาบ้านให้แมว
>>>>>>> aa19d28dfe5e425365dc16c65600443702af79aa
