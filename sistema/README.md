# 🛠️ TechSupport - Sistema de Registro y Control de Soporte Técnico

Segunda entrega: catálogos de soporte, menú con submenús y "pantalla de
opciones" genérica, clientes rediseñados (persona natural/jurídica), tickets
ampliados (análisis, solución, tiempos, costos, archivos adjuntos) y generación
de PDF con logo y marca de agua.

## 🆕 Cambios de esta entrega

- **Menú siempre oculto**: el sidebar ahora es "off-canvas" en todos los
  dispositivos (no solo en móvil); se abre/cierra únicamente con el botón de
  hamburguesa en la barra superior.
- **Selector de íconos**: los campos que requieren un ícono (por ejemplo, en
  **Menús**) ahora usan un combo con buscador que muestra el ícono real junto
  a su nombre (`assets/js/bootstrap-icons-list.js` + `inicializarSelectorIcono()`
  en `assets/js/app.js`).
- **Archivos adjuntos con arrastrar y soltar**: la sección se renombró de
  "Adjuntar Fotos/Videos" a **"Archivos adjuntos"**, con una zona drag & drop
  reutilizable (`inicializarDropzone()`) que acepta múltiples archivos con
  extensión **png, jpg, mp4 o pdf** (antes también permitía gif/webp/video en
  otros formatos; ahora se restringe exactamente a lo solicitado). Los PDF se
  muestran con un ícono propio en la galería del ticket.
- **Sin constantes `MENU_*`**: se eliminaron todas. Cada pantalla ahora llama
  `requerirPermiso(ID)` pasando directamente el id numérico del registro
  correspondiente en `tbl_menu_admin` (por ejemplo, `requerirPermiso(4)` en
  `usuarios.php`). `esAdmin()`/`requerirAdmin()` usan el id 4 (Usuarios)
  directamente y ya no dependen de `requerirPermiso()`.
- **Permisos de perfil agrupados**: en **Perfiles**, la lista de "Permisos de
  menú" ahora muestra cada opción de primer nivel (`is_submenu = 0`)
  inmediatamente seguida de sus opciones hijas, en vez de mostrar todos los
  padres primero y todos los hijos después.

## 🆕 Cambios de esta entrega

- **Clientes**: al elegir "Natural", el campo RUC/Cédula exige exactamente 10
  dígitos y la etiqueta "Nombre comercial" cambia a **"Nombre"**; al elegir
  "Jurídica", exige 13 dígitos y la etiqueta vuelve a "Nombre comercial". El
  correo (cuando se ingresa) sigue validándose en formato y duplicados.
- **Categorías de Soporte**: la lista ahora se reordena **arrastrando las
  filas** (drag & drop); el campo "Orden" se ocultó del formulario porque ya
  no se edita manualmente — las categorías nuevas se agregan al final
  automáticamente.
- **Nueva opción de menú "Supervisor"** (`url = #`, sin pantalla propia por
  ahora, is_submenu=0).
- **Bancos, Cuentas Bancarias, Tipos de Pago y Pagos**: nuevas tablas
  `tbl_bancos`, `tbl_bancos_cuentas`, `tbl_tipos_pago` y `tbl_pagos`, con sus
  pantallas `cnf_bancos.php`, `cnf_cuentas_bancarias.php` y **`pagos.php`**
  (menú de primer nivel). Al registrar un pago por Transferencia o Cheque se
  muestran los campos Banco, Cuenta Bancaria y N° de referencia (validados en
  servidor); para Efectivo/Crédito se guardan automáticamente en 1/blanco.
  > **Nota:** `tbl_pagos` incluye el campo `id_tbl_clientes` (no estaba en el
  > detalle de columnas solicitado), agregado porque es indispensable para
  > poder calcular el saldo por cliente en `tbl_saldos`.
- **Control de saldos** (`tbl_saldos`): `total_soportes` (suma de
  `tbl_soportes.monto_total` con `state = 4`/FINALIZADO) y `total_pagos`
  (suma de `tbl_pagos.monto` con `state = 1`), por cliente. Se recalculan
  automáticamente con `recalcularSaldoCliente()` cada vez que se guarda un
  pago o se actualiza el estado de un ticket.
- **Perfiles**: se reafirmó el agrupamiento padre → hijos con sangría en la
  lista de permisos (ya incluye automáticamente las nuevas opciones de menú).

## 🆕 Cambios de esta entrega

- **Reordenamiento por arrastre** ahora también en **Tipos de Soporte** y
  **Prioridades de Soporte** (mismo patrón que Categorías: campo "Orden"
  oculto, filas arrastrables).
- **Menús con pestañas**: la pantalla `menus.php` ahora muestra una pestaña
  "Menús Principales" y una pestaña por cada menú que tenga submenús; el
  reordenamiento por arrastre en cada pestaña actualiza `orden` de forma
  secuencial (1..n) **dentro de ese grupo** (`is_submenu` del padre
  correspondiente).
- **Clientes**: el correo ahora es **obligatorio** (antes era opcional).
- **Soportes**: "Tiempo total" y "Monto total" se calculan **en vivo** con
  JavaScript al cambiar Inicio/Fin de atención o Valor por hora, sin
  necesidad de guardar.
- **`tbl_pagos`**: se agregó el campo **`no_cuenta`** (después de
  `id_tbl_bancos`).
  > **Nota:** se pidió agregarlo a `tbl_tipos_pago`, pero esa tabla no tiene
  > (ni tendría sentido que tuviera) un campo `id_tbl_bancos`; por el uso
  > descrito en el formulario de Pagos, se agregó a `tbl_pagos`.
- **Formulario de Pagos reetiquetado**: "Banco origen" (`id_tbl_bancos`),
  "Cuenta origen" (`no_cuenta`, texto libre), "N° de referencia" (`ref_no`),
  "Cuenta destino" (`id_tbl_bancos_cuentas`) — en ese orden.
- **Nueva pantalla `saldos.php`** ("Saldos", menú de primer nivel): lista el
  saldo de todos los clientes (total facturado por soportes finalizados menos
  total pagado), con buscador y acceso directo a sus pagos.
- **Dashboard ampliado**: saldo total (facturado, pagado, pendiente), gráfico
  de líneas de cantidad de soportes por mes, gráfico de líneas de monto de
  pagos por mes (ambos con selector de año), y gráficos de pie de soportes
  por cliente, categoría, tipo de atención, prioridad y estado.

## 🚀 Instalación

1. Suba todos los archivos a su servidor.
2. Importe `database.sql`:
   ```
   mysql -u root -p < database.sql
   ```
3. Edite `config/db.php` con los datos de su servidor (`DB_NAME` = `db_techsupport`).
4. Abra `install.php`, configure el usuario Master (usuario sugerido: **admin**).
5. **Elimine `install.php`** del servidor por seguridad.
6. Ingrese desde `login.php` y revise **Configuración → Datos de la Empresa**
   para subir el logo (se usa en los PDF de soporte).
7. Verifique que la carpeta `files/soportes/` tenga permisos de escritura para
   el servidor web (necesaria para subir fotos/videos de los tickets).

## 🗄️ Tablas nuevas o modificadas en esta entrega

| Tabla                          | Descripción                                                          |
|---------------------------------|------------------------------------------------------------------|
| `tbl_soporte_categoria`         | Categorías de soporte (Asistencia técnica, Programación, etc.)     |
| `tbl_soporte_tipo`              | Tipos de atención (Presencial, Remoto, Whatsapp, combinaciones...) |
| `tbl_soporte_prioridad`         | Antes `tbl_prioridades_soporte` (mismo contenido, solo renombrada)  |
| `tbl_soporte_estado`            | Catálogo de estados; **su id se guarda directamente en `tbl_soportes.state`** (0 Anulado, 1 Reportado, 2 En Proceso, 3 Solucionado, 4 Finalizado) |
| `tbl_tipo_persona`              | Natural / Jurídica                                                  |
| `tbl_clientes`                  | Rediseñada: `id_tbl_tipo_persona`, `rucci`, `nombre_comercial`, `razon_social`, `telefono`, `celular`, `correo`, `direccion` |
| `tbl_soportes`                  | Ampliada con solicitante, análisis, solución, observación, recomendación, tiempos de atención, valor/hora, monto total, categoría, tipo, y `state` como FK al estado |
| `tbl_soportes_archivos`         | Fotos y videos adjuntos de cada ticket (guardados en `files/soportes/`) |

### ⚠️ Cambio importante: `tbl_soportes.state`
A diferencia del resto del sistema (donde `state` es 0/1 activo-inactivo), en
`tbl_soportes` el campo `state` **es una llave foránea** a `tbl_soporte_estado`
y representa el estado real del ticket (Reportado, En Proceso, Solucionado,
Finalizado o Anulado=0). Esto es una excepción intencional solicitada para
esta tabla.

## 🧾 RUC/Cédula y tipo de persona

En el formulario de clientes:
- **Natural** (id 1): se oculta "Razón social", la etiqueta del campo cambia a
  **"Cédula"**.
- **Jurídica** (id 2): se muestra "Razón social" (obligatoria), la etiqueta
  cambia a **"RUC"**.
- El campo `rucci` se valida (cliente y servidor) para aceptar **exactamente
  10 o 13 dígitos numéricos**, y no se permite duplicados.

## 🧭 Menú con submenús y "pantalla de opciones"

Cualquier opción de `tbl_menu_admin` que tenga una o más opciones hijas
(`is_submenu` = su id) deja de enlazar directamente a su `url`: el sidebar la
redirige a `menu_opciones.php?padre=ID`, una pantalla genérica que muestra en
una cuadrícula de botones (ícono + nombre) todas las opciones hijas activas
que el perfil del usuario tiene permitidas. Al elegir una opción hija, se
navega a su `url` real.

En esta entrega, **Configuración** (id 9) es el primer ejemplo: agrupa Datos
de la Empresa, Categorías de Soporte, Tipos de Soporte, Prioridades de Soporte
y Tipo de Persona.

**Botón "Volver atrás":** toda pantalla alcanzada desde `menu_opciones.php`
recibe el parámetro `?padre=ID` en su URL; la función `botonVolverMenu()` (ya
incluida al inicio de cada formulario del sistema) detecta ese parámetro y
muestra un botón para regresar a la pantalla de opciones correspondiente. Si
se accede a la página de otra forma (por ejemplo, un enlace directo desde
Soportes), el botón simplemente no aparece.

## 🎫 Ticket de soporte ampliado

Cada solicitud ahora incluye:
- **Solicitante** (persona específica que reporta, además del cliente)
- **Descripción, Análisis, Solución, Observación, Recomendación** (todos texto largo)
- **Categoría** y **Tipo de atención** (catálogos configurables)
- **Tiempo y costo**: inicio/fin de atención, valor por hora y monto total
  (`total_minutos` y `monto_total` se recalculan automáticamente con
  `recalcularTiempoSoporte()` cada vez que se guarda el ticket)
- **Archivos adjuntos**: fotos y videos, subidos y almacenados en
  `files/soportes/`, visibles y eliminables desde el detalle del ticket
- **Bitácora de seguimiento**: cada cambio de estado o comentario queda
  registrado con usuario y fecha/hora

## 📄 PDF del ticket (con logo y marca de agua)

Desde el detalle de cualquier ticket (`soporte_pdf.php?id=N`) se genera un PDF
con TCPDF que incluye:
- Encabezado con el logo de la empresa (configurado en **Datos de la Empresa**)
- **Marca de agua**: el propio logo, centrado y semitransparente, de fondo en
  toda la página (si no hay logo configurado, se usa el nombre de la empresa
  rotado como marca de agua de texto)
- Toda la información del ticket: cliente, solicitante, categoría, tipo,
  prioridad, técnico, descripción/análisis/solución/observación/recomendación,
  y el resumen de tiempo y costo

## 🔐 Perfiles y permisos

Sin cambios en el esquema de perfiles respecto a la entrega anterior; se
agregaron los ids 9 a 14 (Configuración y sus 5 submenús) al perfil **Master**.
Administrador y Técnico mantienen sus permisos previos (no ven Configuración).

## 📁 Estructura del proyecto (archivos nuevos de esta entrega)

```
techsupport/
├── files/soportes/                Fotos y videos adjuntos de los tickets
├── lib/tcpdf/                     Librería TCPDF para generar los PDF
├── menu_opciones.php              Pantalla genérica de submenús
├── cnf_empresa.php                Datos de empresa y logo
├── cnf_soporte_categoria.php      CRUD de categorías de soporte
├── cnf_soporte_tipo.php           CRUD de tipos de atención
├── cnf_soporte_prioridad.php      CRUD de prioridades (antes sin pantalla propia)
├── cnf_tipo_persona.php           CRUD de tipo de persona
├── soporte_pdf.php                Generación de PDF del ticket (logo + marca de agua)
├── clientes.php                   Reescrito: tipo de persona, RUC/cédula, razón social
├── soportes.php                   Reescrito: nuevos campos + subida de archivos
└── soporte_detalle.php            Reescrito: análisis/solución/tiempos/archivos/bitácora
```

## 🔐 Seguridad

- Contraseñas con `password_hash()` (bcrypt).
- Sentencias preparadas (PDO) en todas las consultas.
- Carpetas `config/`, `lib/` y `files/` protegidas contra ejecución/acceso
  directo vía `.htaccess`.
- Solo se aceptan extensiones de imagen/video permitidas al subir archivos.
- Cada página valida el permiso en el servidor, no solo en el menú visual.

## 🧭 Próximos pasos sugeridos

Reportes de soportes (por técnico, cliente, categoría, tiempos de resolución),
notificación por correo al cliente cuando cambia el estado de su ticket,
encuestas de satisfacción, SLA por prioridad, y exportación a Excel.

---
Desarrollado para **TechSupport** — Registro y Control de Soporte Técnico 🛠️
