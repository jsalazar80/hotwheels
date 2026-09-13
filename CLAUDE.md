# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project overview

TechSupport is a PHP 8 + MySQL web app for registering and tracking technical support tickets ("soportes"), built with plain procedural PHP (no framework, no build step) plus PDO, Bootstrap 5, and vanilla JS. The app root/entry point is `index.php`, but nearly all real code — including `composer.json`/`vendor/` — lives under `sistema/`, which is the actual webroot. The only Composer dependencies are `tecnickcom/tcpdf` (see [PDF export](#pdf-export-soporte_pdfphp)), `phpmailer/phpmailer` (see [Emailing](#emailing-includesemailphp)), and `phpoffice/phpspreadsheet` (see [Excel import](#excel-import-importar_soportesphp)); everything else is dependency-free.

## Running the app

There is no build, lint, or test command — this is a plain PHP app meant to be dropped onto an Apache/PHP server (XAMPP-style) with `sistema/` as the webroot people actually browse to (`sistema/login.php`, `sistema/index.php`, etc.). The top-level `index.php` just redirects to `sistema/login.php`.

1. Run `composer install` inside `sistema/` (installs `sistema/vendor/tecnickcom/tcpdf`, `sistema/vendor/phpmailer/phpmailer`, and `sistema/vendor/phpoffice/phpspreadsheet`, used by `soporte_pdf.php`/`includes/email.php`/`importar_soportes.php` respectively).
2. Import `sistema/database.sql` into MySQL. The dump has its own `CREATE DATABASE IF NOT EXISTS db_techsupport` + `USE db_techsupport;` baked in, so it always targets a database literally named `db_techsupport` regardless of what you pass on the command line — `DB_NAME` in `sistema/config/db.php` must match that name (it does by default).
3. Edit `sistema/config/db.php` for local DB credentials.
4. Serve the folder with Apache/PHP (e.g. XAMPP) and open `sistema/install.php` once to create the Master admin user, then delete it.
5. Log in at `sistema/login.php`.

**Windows/PowerShell import gotcha:** never pipe the dump in through PowerShell's text pipeline (`Get-Content database.sql | mysql ...`) — Windows PowerShell 5.1 re-encodes piped text to the console's codepage before it reaches `mysql.exe`'s stdin, silently mangling every accented character (`Julián` → `JuliÃ¡n`) even though the file itself and `DB_CHARSET` are correct UTF-8/`utf8mb4`. Import via `cmd /c "mysql -u root --default-character-set=utf8mb4 < database.sql"` (or the mysql client directly from `cmd.exe`/a real shell) instead, which redirects the raw file bytes untouched.

Useful ad-hoc checks (no test suite exists):
```
php -l sistema/some_file.php     # syntax check a single file
php -S localhost:8000 -t sistema # quick local server if not using XAMPP/Apache
```

## Architecture

### Request pattern (every screen follows this shape)
Each top-level PHP file under `sistema/` (`clientes.php`, `soportes.php`, `cnf_bancos.php`, etc.) is a self-contained MVC-in-one-file script:
1. `require_once __DIR__ . '/includes/auth.php'` (starts session, connects PDO via `config/db.php`) and `includes/functions.php`.
2. `requerirPermiso(ID)` — guards the whole page with the numeric `tbl_menu_admin.id` for *that specific screen* (hardcoded per file, e.g. `requerirPermiso(2)` in `soportes.php`, `requerirPermiso(16)` in `cnf_bancos.php`). There are no `MENU_*` constants — ids are passed literally.
3. Inline handling of POST (`$_POST['accion'] === 'guardar'`) and GET actions (e.g. `?toggle=ID`) before any HTML is emitted, using `redirigirConMensaje()` (PRG pattern: redirect with `?msg_tipo=&msg=` query params, rendered by `mostrarAlertas()`).
4. Query data, then `include __DIR__ . '/includes/header.php'` (opens `<html>`, sidebar, topbar), page HTML, `include __DIR__ . '/includes/footer.php'` (closes tags).
5. Uses `$tituloPagina` (page title / topbar heading) and `botonVolverMenu()` (shows a "back" button when arrived via `?padre=ID` from `menu_opciones.php`).

`sistema/includes/functions.php` holds all shared helpers: permission checks (`tienePermiso`, `requerirPermiso`, `esAdmin`/`requerirAdmin` — admin is defined as having permission on menu id 4 "Usuarios"), formatting (`formatoFecha`, `limpiar` for XSS-safe output), audit logging (`registrarAuditoria` → `tbl_general_auditory`, `registrarEventoAcceso` → `tbl_login`), ticket numbering (`generarNumeroTicket`/`incrementarNumeroTicket` against `tbl_configuracion`), and business recalculation (`recalcularTiempoSoporte`, `recalcularSaldoCliente`).

### Permissions & menu model
- `tbl_menu_admin` rows are menu options; each has an `id`, `orden`, `icono`, `url`, and `is_submenu` (0 for top-level, or the parent's id for a child).
- `tbl_profiles.permisos` is a CSV of `tbl_menu_admin.id`s; on login these are exploded into `$_SESSION['tsp_permisos']` (see `login.php`).
- A menu option with children never links directly to its `url` — the sidebar routes it to `menu_opciones.php?padre=ID`, a generic grid screen that lists only the child options the current profile is allowed to see.
- Every protected screen re-validates its own permission id server-side via `requerirPermiso()`; the sidebar only controls visibility, not access.

### Session keys are prefixed `tsp_`
Every `$_SESSION` key in this app is written as `$_SESSION['tsp_<name>']` (`tsp_usuario_id`, `tsp_nombre`, `tsp_id_tbl_profiles`, `tsp_perfil_nombre`, `tsp_permisos` — set in `login.php`, read across `auth.php`/`functions.php`/`header.php`/`sidebar.php`/every screen). This is a deliberate project convention (not a PHP requirement) meant to namespace the app's session data; keep using the `tsp_` prefix for any new session key. `sistema/vendor/phpmailer/` ships its own unrelated `$_SESSION` usage in an unused OAuth helper script — that third-party code is not part of this convention and isn't invoked by the app.

### `tbl_soportes.state` is an exception
Everywhere else in the schema `state` is a plain 0/1 active/inactive flag. In `tbl_soportes`, `state` is instead a foreign key into `tbl_soporte_estado` representing the ticket's real status (`ESTADO_SOPORTE_ANULADO=0`, `REPORTADO=1`, `EN_PROCESO=2`, `SOLUCIONADO=3`, `FINALIZADO=4`, constants defined in `functions.php`). Only `state = FINALIZADO` tickets count toward a client's balance in `recalcularSaldoCliente()`. Saving a ticket with `state = FINALIZADO` is rejected (client-side and server-side, in `soporte_detalle.php`) unless both `fecha_hora_solved_str` and `fecha_hora_solved_end` are already set.

### `tbl_soportes.fecha` is `datetime`, not `date`
It stores a full timestamp (not just a calendar day) so seeded/demo tickets can carry a realistic time-of-day. Anywhere it's compared against a plain `Y-m-d` value, wrap it in `DATE(...)` (`index.php`'s "registrados hoy" stat and `soportes.php`'s date-range list filter both do this) — comparing the raw column to a bare date string silently excludes same-day rows with a non-midnight time. Anywhere it's bound to an `<input type="date">` value, truncate it first (`date('Y-m-d', strtotime($soporte['fecha']))` in `soporte_detalle.php`) since a full datetime string is not a valid `type="date"` value and would blank the field.

### Random test data (`generar_prueba.php`)
"Generar Datos de Prueba" (permission id 21, under the Supervisor menu) inserts 100 realistic-but-random `tbl_soportes` rows in one click — random client/categoría/tipo/prioridad/técnico/estado/fecha (using each client's `valor_por_hora_ref` when set), closed tickets get a coherent `fecha_hora_solved_str`/`_end` pair and go through the real `recalcularTiempoSoporte()`/`recalcularSaldoCliente()`, and ticket numbering continues from `tbl_configuracion.siguiente_numero` (updated once at the end, not per row — see the next paragraph for why). Every inserted row is audited exactly like a real user action. It's meant purely for demos/testing, not a fixture for automated tests (there are none).

**`generarNumeroTicket()`/`incrementarNumeroTicket()` don't compose in a loop:** `obtenerConfiguracion()` caches `tbl_configuracion` in a function-local `static` on first read and never refreshes it, so calling `generarNumeroTicket()` repeatedly in the same PHP process (e.g. a bulk-insert loop) returns the *same* ticket number every iteration even after `incrementarNumeroTicket()` writes a new value to the DB — fine for the one-call-per-HTTP-request pattern every other screen uses, but not for batch generation. `generar_prueba.php` works around it by reading `siguiente_numero` once, incrementing a local counter per row, and writing it back to `tbl_configuracion` a single time after the loop.

### Excel import (`importar_soportes.php`)
Bulk-loads `tbl_soportes` rows from an `.xlsx` file (permission id 22, "Importar Soportes" under Configuración) using `phpoffice/phpspreadsheet`. The file's header row is matched by **name**, not position (`IMPORTAR_SOPORTES_COLUMNAS` lists the 19 required headers), so column order in the upload doesn't matter as long as the header text matches. `CLIENTE`/`CATEGORIA`/`TIPO`/`PRIORIDAD`/`TECNICO`/`ESTADO` are resolved to ids by case-insensitive exact match against each catalog's `nombre`/`nombre_comercial` (`ESTADO` against `tbl_soporte_estado.nombre`, e.g. `REPORTADO`/`EN PROCESO`/`SOLUCIONADO`/`FINALIZADO`/`ANULADO`; built once into in-memory maps before the row loop, not queried per row). `ANALISIS`/`OBSERVACION`/`RECOMENDACION` are the only mapped columns that may be left empty per row — every other mapped column, including `ESTADO`, is required. Because `ESTADO_SOPORTE_ANULADO = 0` is a legitimate id, the `ESTADO` resolution lookup must check `=== null` (not falsy) to tell "not found" apart from a resolved id of `0`, unlike the other name-lookups in this file which use a plain falsy check since none of their ids are legitimately `0`. `FECHA REPORTADO`+`HORA REPORTADO` (and the `DESDE`/`HASTA` pairs) are combined into single datetime strings via `importarSoportesCombinarFechaHora()`, which accepts both real Excel date/time cells (numeric serials, converted with `PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject()`) and plain text (tries `d/m/Y` before falling back to `strtotime()`, since PHP's generic parser assumes `m/d/Y` and would silently swap day/month for DD/MM/YYYY text). `asunto` isn't one of the mapped columns (the user's column spec has no ASUNTO field) — it's auto-derived from `DESCRIPCION`, truncated to 200 chars.

Two modes, both sharing the same row-validation/resolution code path:
- **Previsualizar** (`id_import_type = 1`): `TRUNCATE TABLE tbl_soportes_preview` (a schema copy of `tbl_soportes` plus `import_id`, deliberately with **no FKs** since it's disposable staging cleared on every run) then inserts only the rows that pass validation there; doesn't touch `tbl_configuracion.siguiente_numero` or call `registrarAuditoria()` for the staged rows (not real data).
- **Importar** (`id_import_type = 2`): inserts into the real `tbl_soportes`, audits each row, advances `siguiente_numero` once at the end (same static-cache workaround as `generar_prueba.php` above), and calls `recalcularSaldoCliente()` per distinct client touched.

Every run (either mode) gets one `tbl_imports` row (`fecha_hora_str` at the start, `fecha_hora_end` once the loop finishes, `table_name` = whichever table it wrote to) — this is the only durable record of *that* run; per-row error messages are shown once in the response and not persisted anywhere. Rows that fail validation (empty required field per the mapped list, an unresolvable name, or `HASTA < DESDE`) are skipped and reported with their Excel row number; they don't abort the rest of the file. `state` comes from the row's resolved `ESTADO` id — unlike `soporte_detalle.php`, the import does **not** re-enforce the "`FINALIZADO` requires both attention dates" rule as a separate check, since `FECHA/HORA DESDE` and `FECHA/HORA HASTA` are already mandatory for every row regardless of `ESTADO`, so that invariant holds automatically.

The downloadable template (`?accion=plantilla`) is generated by the same file, not a static asset — regenerate it (or update `IMPORTAR_SOPORTES_COLUMNAS`) together if the column mapping ever changes.

### PDF export (`soporte_pdf.php`)
Generates the ticket PDF (logo header + watermark) using `TCPDF` (classic, self-contained `tecnickcom/tcpdf` ^6.11, installed via Composer into `sistema/vendor/`, alongside `sistema/composer.json`/`composer.lock`). Since `sistema/vendor/` sits inside the webroot, it carries its own `.htaccess` (`Require all denied`), matching `config/`, `files/`, and `lib/`.

The actual PDF-building logic (the `SoportePDF` class plus `construirPdfSoporte($pdo, $id)`) lives in `includes/soporte_pdf_builder.php`, shared by two callers: `soporte_pdf.php` (renders it inline in the browser via `Output(..., 'I')`) and `soporte_email.php` (gets the raw bytes via `Output(..., 'S')` to attach to an email). `construirPdfSoporte()` enforces `state = FINALIZADO` and writes the `SEL` audit entry itself, so both callers get that validation/logging for free — don't duplicate either check in a caller.

The folder `sistema/lib/tcpdf/` is a **leftover, unused vendored copy** of TCPDF 7.0.4 — that version is no longer a self-contained library but a thin compatibility facade delegating to the separate Composer package `tecnickcom/tc-lib-pdf` (class `Com\Tecnick\Pdf\Tcpdf`), which was never installed. Requiring it directly throws `Class "Com\Tecnick\Pdf\Tcpdf" not found` at runtime. Do not re-wire `soporte_pdf.php` back to `sistema/lib/tcpdf/tcpdf.php`; it can be deleted whenever someone confirms nothing else references it.

**Watermark cursor gotcha:** the `SoportePDF::marcaDeAgua()` no-logo branch draws the rotated company-name watermark via `Text()` inside a `StartTransform()`/`Rotate()`/`StopTransform()` block. TCPDF's `Text()` internally calls `Cell()`, which moves the page cursor (X/Y) to wherever it just wrote — `StopTransform()` only undoes the rotation matrix, not the cursor. `marcaDeAgua()` therefore saves `GetX()`/`GetY()` before drawing and restores them with `SetXY()` at the end; without that restore, all ticket content rendered after the watermark call starts mid-page, squeezed against the right margin. Keep that save/restore if this method is touched again.

### File uploads
Ticket attachments (`soportes.php`, `soporte_detalle.php`) are restricted to `png/jpg/jpeg/mp4/pdf`, saved under `sistema/files/soportes/` as `soporte_{id}_{time}_{i}.{ext}`, and recorded in `tbl_soportes_archivos`. `sistema/files/`, `sistema/config/`, and `sistema/lib/` each carry a `.htaccess` blocking direct web access/execution.

### Emailing (`includes/email.php`)
`enviarCorreo($pdo, $datos)` sends mail via `PHPMailer` (Composer, SMTP mode) and is the only way the app sends email. It always reads its connection settings from the single active row in `tbl_email_config_sender` (`ml_host`, `ml_username`, `ml_password`, `id_tbl_email_config_port`/`_auth`/`_scrt` — FKs to the `tbl_email_config_port`/`tbl_email_config_auth`/`tbl_email_config_scrt` lookup tables, whose ids are the literal values `'25'/'465'/'587'`, `'true'/'false'`, `'ssl'/'tls'`), editable at `cnf_email.php` (permission id 20, "Configurar Correo" under Configuración). `$datos['cc']`/`['bcc']` fall back to the config row's own `cc`/`bcc` when not passed explicitly.

Every call — success or failure — writes one row to `tbl_email_send` (`state = 1` and `comment = 'OK'` on success; `state = 0` and `comment` = the PHPMailer/SMTP error detail on failure) and a matching `tbl_general_auditory` `INS` entry; this logging happens inside `enviarCorreo()` itself, so callers don't need to log anything separately. `tbl_email_send.table_id` is the id of the source record the email was generated from (passed as `$datos['tableId']`) — combined with `type_email` (`1` = ticket PDF) it's how the app answers "how many times has this record been emailed": `SELECT COUNT(*) FROM tbl_email_send WHERE type_email = ? AND table_id = ? AND state = 1`. `soporte_email.php` is the current caller: it builds the ticket PDF via `construirPdfSoporte()` (see PDF export above, so it's also gated to `state = FINALIZADO`), emails it as an attachment to the client's registered address with `tipo = 1`/`tableId = ` the ticket's id, and `soportes.php`'s Solicitudes list uses that same count (via a correlated subquery aliased `correos_enviados`) to turn the envelope button green with a send count once at least one send succeeded — the button only renders at all when the ticket is `FINALIZADO` and the client has an email.

### Auditing
`registrarAuditoria($pdo, $accion, $tabla, $id_registro, $query = '', $observaciones = '')` is called after essentially every INSERT/UPDATE/SELECT-as-report across the app and writes to `tbl_general_auditory`; it deliberately swallows its own exceptions so a logging failure never blocks the real operation. `$accion` is one of `INS`/`UPD`/`DEL`/`SEL` (`SEL` is used for reports, e.g. `soporte_pdf.php`'s ticket PDF, with `$observaciones` holding the report name). `user_ing` is always `$_SESSION['tsp_usuario_id']`.
- `$query` must be the **fully interpolated** SQL actually executed (real values, not `?` placeholders) — build it with `interpolarSql($pdo, $sql, $params)`, called with the *same* `$sql`/`$params` just passed to `$stmt->execute()`. It quotes strings via `$pdo->quote()`, leaves numbers/bools unquoted, and renders `null` as `NULL`; never use it to build SQL to execute, only for the audit trail. Leave `$query` as `''` (stored as `NULL`) when the action has no single representative SQL statement (e.g. the drag-reorder handlers that loop one `UPDATE ... WHERE id = ?` per row) — put a description in `$observaciones` instead.
- `$observaciones` is a short human-readable description of the task performed (e.g. `'Recálculo de totales tras editar pago'`, written by `recalcularSaldoCliente()`/`recalcularTiempoSoporte()` whenever they run, regardless of which screen triggered them).

Login/logout events go to `tbl_login` via `registrarEventoAcceso()` instead (unrelated to `tbl_general_auditory`).

### Frontend
No JS build step — Bootstrap 5 and Bootstrap Icons are loaded from CDN in `includes/header.php`/`login.php`, plus local `assets/css/style.css` and `assets/js/app.js`. Recurring UI patterns implemented in plain JS: drag-and-drop reordering of catalog rows (categorías, tipos, prioridades — updates `orden` sequentially per group), a drag-and-drop file dropzone (`inicializarDropzone()`), and a searchable icon picker (`inicializarSelectorIcono()` + `assets/js/bootstrap-icons-list.js`).
