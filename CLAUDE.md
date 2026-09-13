# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project overview

This project is now branded **Hotwheels** (originally "TechSupport", a PHP 8 + MySQL ticketing app). It has been stripped down to a bare skeleton — login, users, profiles/permissions, an administrable menu, and audit logging — as the base for a **new car-collection (Hot Wheels) inventory system** whose data model hasn't been designed yet. Built with plain procedural PHP (no framework, no build step) plus PDO, Bootstrap 5, and vanilla JS. The app root/entry point is `index.php`, but nearly all real code — including `composer.json`/`vendor/` — lives under `sistema/`, which is the actual webroot. The only Composer dependencies are `tecnickcom/tcpdf`, `phpmailer/phpmailer`, and `phpoffice/phpspreadsheet` (see [Reference-only screens](#reference-only-screens-tickets-domain) below); everything else is dependency-free.

## Running the app

There is no build, lint, or test command — this is a plain PHP app meant to be dropped onto an Apache/PHP server (XAMPP-style) with `sistema/` as the webroot people actually browse to (`sistema/login.php`, `sistema/index.php`, etc.). The top-level `index.php` just redirects to `sistema/login.php`.

1. Run `composer install` inside `sistema/`.
2. Import `sistema/migrations/database.sql` into MySQL. The dump has its own `CREATE DATABASE IF NOT EXISTS db_hotwheels` + `USE db_hotwheels;` baked in, so it always targets a database literally named `db_hotwheels` regardless of what you pass on the command line — `DB_NAME` in `sistema/config/db.php` must match that name (it does by default for local hosts).
3. Edit `sistema/config/db.php` for local DB credentials.
4. Serve the folder with Apache/PHP (e.g. XAMPP) and open `sistema/install.php` once to create the Master admin user, then delete it.
5. Log in at `sistema/login.php`.

**Windows/PowerShell import gotcha:** never pipe the dump in through PowerShell's text pipeline (`Get-Content database.sql | mysql ...`) — Windows PowerShell 5.1 re-encodes piped text to the console's codepage before it reaches `mysql.exe`'s stdin, silently mangling every accented character even though the file itself and `DB_CHARSET` are correct UTF-8/`utf8mb4`. Import via `cmd /c "mysql -u root --default-character-set=utf8mb4 < migrations/database.sql"` (or the mysql client directly from `cmd.exe`/a real shell) instead, which redirects the raw file bytes untouched. For the same reason, never test-import that file into a *different* database name via `mysql <other_db> < migrations/database.sql` — the file's own `USE db_hotwheels;` statement overrides whatever database you pass on the command line, so it always writes to `db_hotwheels` regardless.

`sistema/migrations/` holds `database.sql` (the full schema dump, regenerate it with `mysqldump` after any schema change), the stale `reset.sql` (truncates tables from the old ticket domain — not updated for the current schema), and dated `database_backup_*.sql` snapshots taken before risky schema changes (gitignored, local only).

Useful ad-hoc checks (no test suite exists):
```
php -l sistema/some_file.php     # syntax check a single file
php -S localhost:8000 -t sistema # quick local server if not using XAMPP/Apache
```

## Architecture

### Request pattern (every screen follows this shape)
Each top-level PHP file under `sistema/` (`usuarios.php`, `perfiles.php`, `cnf_empresa.php`, etc.) is a self-contained MVC-in-one-file script:
1. `require_once __DIR__ . '/includes/auth.php'` (starts session, connects PDO via `config/db.php`) and `includes/functions.php`.
2. `requerirPermiso(ID)` — guards the whole page with the numeric `tbl_menu_admin.id` for *that specific screen* (hardcoded per file, e.g. `requerirPermiso(4)` in `usuarios.php`). There are no `MENU_*` constants — ids are passed literally.
3. Inline handling of POST (`$_POST['accion'] === 'guardar'`) and GET actions (e.g. `?toggle=ID`) before any HTML is emitted, using `redirigirConMensaje()` (PRG pattern: redirect with `?msg_tipo=&msg=` query params, rendered by `mostrarAlertas()`).
4. Query data, then `include __DIR__ . '/includes/header.php'` (opens `<html>`, sidebar, topbar), page HTML, `include __DIR__ . '/includes/footer.php'` (closes tags).
5. Uses `$tituloPagina` (page title / topbar heading) and `botonVolverMenu()` (shows a "back" button when arrived via `?padre=ID` from `menu_opciones.php`).

`sistema/includes/functions.php` holds shared helpers: permission checks (`tienePermiso`, `requerirPermiso`), formatting (`formatoFecha`, `limpiar` for XSS-safe output), and audit logging (`registrarAuditoria` → `tbl_general_auditory`, `registrarEventoAcceso` → `tbl_login`). `sistema/includes/auth.php` has session/login guards (`requerirLogin`) plus `esAdmin`/`requerirAdmin` — admin is defined as having permission on menu id 4 "Usuarios".

### Permissions & menu model
- `tbl_menu_admin` rows are menu options; each has an `id`, `orden`, `icono`, `url`, and `is_submenu` (0 for top-level, or the parent's id for a child).
- `tbl_profiles.permisos` is a CSV of `tbl_menu_admin.id`s; on login these are exploded into `$_SESSION['tsp_permisos']` (see `login.php`).
- A menu option with children never links directly to its `url` — the sidebar routes it to `menu_opciones.php?padre=ID`, a generic grid screen that lists only the child options the current profile is allowed to see.
- Every protected screen re-validates its own permission id server-side via `requerirPermiso()`; the sidebar only controls visibility, not access.
- Current menu tree: `Dashboard` (id 1), `Configuración` (9, parent) → `Datos de la Empresa` (10), `Menús` (6), `Perfiles` (5), `Usuarios` (4, ⚠️ don't renumber — `esAdmin()` hardcodes this id), `Configurar Correo` (20); `Supervisor` (15, parent) → `Accesos al Sistema` (8), `Auditoría` (7).

### Session keys are prefixed `tsp_`
Every `$_SESSION` key in this app is written as `$_SESSION['tsp_<name>']` (`tsp_usuario_id`, `tsp_nombre`, `tsp_id_tbl_profiles`, `tsp_perfil_nombre`, `tsp_permisos` — set in `login.php`, read across `auth.php`/`functions.php`/`header.php`/`sidebar.php`/every screen). This is a deliberate project convention (not a PHP requirement) meant to namespace the app's session data; keep using the `tsp_` prefix for any new session key.

### Auditing
`registrarAuditoria($pdo, $accion, $tabla, $id_registro, $query = '', $observaciones = '')` is called after essentially every INSERT/UPDATE across the app and writes to `tbl_general_auditory`; it deliberately swallows its own exceptions so a logging failure never blocks the real operation. `$accion` is one of `INS`/`UPD`/`DEL`/`SEL` (`SEL` is reserved for reports, with `$observaciones` holding the report name).
- `$query` must be the **fully interpolated** SQL actually executed (real values, not `?` placeholders) — build it with `interpolarSql($pdo, $sql, $params)`, called with the *same* `$sql`/`$params` just passed to `$stmt->execute()`. It quotes strings via `$pdo->quote()`, leaves numbers/bools unquoted, and renders `null` as `NULL`; never use it to build SQL to execute, only for the audit trail. Leave `$query` as `''` (stored as `NULL`) when the action has no single representative SQL statement — put a description in `$observaciones` instead.
- `$observaciones` is a short human-readable description of the task performed.

Login/logout events go to `tbl_login` via `registrarEventoAcceso()` instead (unrelated to `tbl_general_auditory`).

### Emailing (`includes/email.php`)
`enviarCorreo($pdo, $datos)` sends mail via `PHPMailer` (Composer, SMTP mode) and is the only way the app sends email. It always reads its connection settings from the single active row in `tbl_email_config_sender` (`ml_host`, `ml_username`, `ml_password`, `id_tbl_email_config_port`/`_auth`/`_scrt` — FKs to the `tbl_email_config_port`/`tbl_email_config_auth`/`tbl_email_config_scrt` lookup tables), editable at `cnf_email.php` (permission id 20, "Configurar Correo"). `$datos['cc']`/`['bcc']` fall back to the config row's own `cc`/`bcc` when not passed explicitly.

Every call — success or failure — writes one row to `tbl_email_send` (`state = 1`/`comment = 'OK'` on success; `state = 0`/`comment` = the PHPMailer/SMTP error on failure) and a matching `tbl_general_auditory` `INS` entry; this logging happens inside `enviarCorreo()` itself. There is currently no active caller of `enviarCorreo()` — the only one that existed (`soporte_email.php`) is a reference-only screen, see below.

### Reference-only screens (tickets domain)
Four files were deliberately **left in place but not wired into the menu** when the ticket/client/payment domain was removed, as copy-from templates for whenever the car-inventory data model is designed: `soporte_email.php`, `importar_soportes.php`, `soporte_pdf.php`, and `includes/soporte_pdf_builder.php`. They still reference tables that no longer exist (`tbl_soportes`, `tbl_clientes`, `tbl_saldos`, the `tbl_soporte_*` catalogs) and **will error if invoked** — that's expected. A few `functions.php` helpers survive only because these files still call them: the `ESTADO_SOPORTE_*` constants, `generarNumeroTicket`/`incrementarNumeroTicket` (and the matching `prefijo_ticket`/`siguiente_numero` columns on `tbl_configuracion`, still editable from `cnf_empresa.php`), `resolverRutaArchivoSoporte`, and `recalcularSaldoCliente`. Don't delete these without also deleting (or rewriting) the four files above.

They demonstrate three patterns worth reusing once the car model exists: emailing a generated PDF as an attachment with per-record send-count tracking (`soporte_email.php` + `soporte_pdf_builder.php`, via `tbl_email_send`), building a PDF with TCPDF including a rotated watermark (`includes/soporte_pdf_builder.php`'s `marcaDeAgua()` — note its `GetX()`/`GetY()` save-and-restore around `Text()`, needed because TCPDF's `Text()` moves the page cursor even inside a `StartTransform()` rotation block), and bulk-importing rows from a named-header `.xlsx` file with phpspreadsheet, validating/resolving each row before insert (`importar_soportes.php`).

### Frontend
No JS build step — Bootstrap 5 and Bootstrap Icons are loaded from CDN in `includes/header.php`/`login.php`, plus local `assets/css/style.css` and `assets/js/app.js`. The searchable icon picker (`inicializarSelectorIcono()` in `assets/js/bootstrap-icons-list.js`) is still active, used by `menus.php` to pick each menu option's icon. `assets/js/app.js` also still contains drag-and-drop catalog reordering and a file dropzone (`inicializarDropzone()`) left over from the removed ticket screens — currently unused by any page, safe to reuse or delete when building the new module.
