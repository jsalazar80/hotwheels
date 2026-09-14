<?php
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
require __DIR__ . '/sistema/config/db.php';

function rutaFotoCarro($idCarro, $archivo) {
    return 'sistema/files/carros/folder_' . $idCarro . '/' . $archivo;
}
function rutaMiniaturaCarro($idCarro, $archivo) {
    $rel = 'sistema/files/carros/folder_' . $idCarro . '/thumbnail/s_' . $archivo;
    return file_exists(__DIR__ . '/' . $rel) ? $rel : null;
}
function rutaLogoMarca($idMarca) {
    $rel = 'sistema/files/marcas/folder_' . $idMarca . '/fot_' . $idMarca . '.jpg';
    return file_exists(__DIR__ . '/' . $rel) ? $rel : null;
}
function rutaMiniaturaLogoMarca($idMarca) {
    $rel = 'sistema/files/marcas/folder_' . $idMarca . '/thumbnail/s_fot_' . $idMarca . '.jpg';
    return file_exists(__DIR__ . '/' . $rel) ? $rel : null;
}
function esc($v) { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }

function renderizarTarjetaAuto($c, $nombreMarca) {
    $rutaFoto = $c['portada'] ? rutaFotoCarro($c['id'], $c['portada']) : null;
    $rutaMini = $c['portada'] ? (rutaMiniaturaCarro($c['id'], $c['portada']) ?: $rutaFoto) : null;
    $textoBusqueda = mb_strtolower($c['modelo'] . ' ' . $nombreMarca . ' ' . ($c['serie_nombre'] ?? '') . ' ' . ($c['tipo_nombre'] ?? '') . ' ' . ($c['color_nombre'] ?? ''));
    ?>
    <div class="auto-card" data-buscar="<?= esc($textoBusqueda) ?>">
        <div class="auto-foto"
             <?php if ($rutaFoto): ?>onclick="abrirLightbox('<?= esc($rutaFoto) ?>','<?= esc($c['modelo']) ?>')"<?php endif; ?>>
            <?php if ($rutaMini): ?>
                <img src="<?= esc($rutaMini) ?>" alt="<?= esc($c['modelo']) ?>" loading="lazy">
            <?php else: ?>
                <i class="bi bi-car-front"></i>
            <?php endif; ?>
        </div>
        <div class="auto-info">
            <div class="modelo"><?= esc($c['modelo']) ?></div>
            <div class="auto-chips">
                <?php if ($c['serie_nombre']): ?><span><i class="bi bi-collection"></i> <?= esc($c['serie_nombre']) ?></span><?php endif; ?>
                <?php if ($c['color_nombre']): ?><span><span class="swatch" style="background-color:<?= esc($c['color_hex'] ?: '#fff') ?>;"></span> <?= esc($c['color_nombre']) ?></span><?php endif; ?>
                <?php if ($c['escala_nombre']): ?><span><?= esc($c['escala_nombre']) ?></span><?php endif; ?>
            </div>
        </div>
    </div>
    <?php
}

$marcas = $pdo->query("SELECT id, nombre FROM tbl_hotwheels_marcas WHERE state = 1 ORDER BY nombre ASC")->fetchAll();

$todosCarros = $pdo->query("SELECT c.id, c.modelo, c.cantidad, c.id_tbl_hotwheels_marcas,
        s.nombre serie_nombre, t.nombre tipo_nombre, co.nombre color_nombre, co.hexcol color_hex, e.nombre escala_nombre,
        (SELECT a.archivo FROM tbl_hotwheels_carros_archivos a WHERE a.id_tbl_hotwheels_carros = c.id AND a.state = 1 ORDER BY a.id ASC LIMIT 1) portada
    FROM tbl_hotwheels_carros c
    LEFT JOIN tbl_hotwheels_series s ON s.id = c.id_tbl_hotwheels_series
    LEFT JOIN tbl_hotwheels_tipos t ON t.id = c.id_tbl_hotwheels_tipos
    LEFT JOIN tbl_hotwheels_colores co ON co.id = c.id_tbl_hotwheels_colores
    LEFT JOIN tbl_hotwheels_escalas e ON e.id = c.id_tbl_hotwheels_escalas
    WHERE c.state = 1
    ORDER BY c.modelo ASC")->fetchAll();

$carrosPorMarca = [];
foreach ($todosCarros as $c) {
    $carrosPorMarca[$c['id_tbl_hotwheels_marcas']][] = $c;
}

$marcas = array_values(array_filter($marcas, fn($m) => !empty($carrosPorMarca[$m['id']])));
usort($marcas, fn($a, $b) => strcmp($a['nombre'], $b['nombre']));

// Autos sin marca asignada: se muestran igual, en una sección aparte, para que el
// catálogo sea completo (el total de autos debe cuadrar con las tarjetas mostradas).
$carrosSinMarca = $carrosPorMarca[''] ?? [];

$totalMarcas = count($marcas);
$totalAutos = count($todosCarros);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Catálogo de la Colección - Hotwheels</title>
<link rel="icon" href="sistema/favicon.ico" sizes="any">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Inter:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<style>
:root {
    --carbon-oscuro: #14161a;
    --carbon-medio: #262a33;
    --rojo-principal: #c8102e;
    --rojo-oscuro: #93101f;
    --ambar: #d9a441;
    --ambar-claro: #f4e9d5;
    --crema: #f6f5f2;
    --crema-calida: #f2ece1;
    --texto: #1e2025;
}
* { box-sizing: border-box; }
html { scroll-behavior: smooth; }
body {
    margin: 0;
    background: var(--crema);
    color: var(--texto);
    font-family: 'Inter', 'Segoe UI', Roboto, Arial, sans-serif;
}
img { max-width: 100%; }

/* ---------- Hero ---------- */
.hero {
    background: radial-gradient(circle at 20% 20%, var(--carbon-medio), var(--carbon-oscuro) 65%);
    color: #fff;
    padding: 56px 20px 40px;
    text-align: center;
    position: relative;
    overflow: hidden;
}
.hero::after {
    content: "";
    position: absolute; inset: 0;
    background: repeating-linear-gradient(135deg, rgba(217,164,65,0.05) 0 2px, transparent 2px 26px);
    pointer-events: none;
}
.hero-logo { width: 84px; height: 84px; border-radius: 50%; box-shadow: 0 0 0 3px var(--ambar-claro); margin-bottom: 14px; }
.hero-login {
    position: absolute;
    top: 18px; right: 20px;
    display: inline-flex; align-items: center; gap: 6px;
    color: #fff;
    text-decoration: none;
    font-size: 0.8rem; font-weight: 600;
    padding: 7px 14px;
    border: 1px solid rgba(217,164,65,0.5);
    border-radius: 999px;
    transition: background .15s, border-color .15s;
    z-index: 1;
}
.hero-login:hover { background: rgba(217,164,65,0.15); border-color: var(--ambar); color: #fff; }
.hero h1 {
    font-family: 'Playfair Display', Georgia, serif;
    font-size: clamp(1.9rem, 4vw, 2.8rem);
    font-weight: 700;
    margin: 0 0 6px;
    letter-spacing: 0.5px;
}
.hero p.tagline { color: var(--ambar); text-transform: uppercase; letter-spacing: 3px; font-size: 0.78rem; margin: 0 0 22px; }
.hero-stats { display: flex; justify-content: center; gap: 34px; flex-wrap: wrap; margin-top: 6px; }
.hero-stats div { text-align: center; }
.hero-stats .num { font-size: 1.7rem; font-weight: 700; color: #fff; line-height: 1; }
.hero-stats .lbl { font-size: 0.72rem; text-transform: uppercase; letter-spacing: 1.5px; color: rgba(255,255,255,0.55); margin-top: 4px; }

/* ---------- Barra pegajosa: buscador + navegador de marcas ---------- */
.barra-sticky {
    position: sticky; top: 0; z-index: 40;
    background: #fff;
    border-bottom: 1px solid #e7e2d8;
    box-shadow: 0 2px 10px rgba(20,22,26,0.06);
}
.buscador-wrap { max-width: 1180px; margin: 0 auto; padding: 14px 20px 10px; }
.buscador { position: relative; }
.buscador i { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #a39c8c; }
.buscador input {
    width: 100%;
    padding: 11px 14px 11px 40px;
    border: 1px solid #ded6c4;
    border-radius: 999px;
    font-size: 0.95rem;
    outline: none;
    transition: border-color .15s, box-shadow .15s;
}
.buscador input:focus { border-color: var(--ambar); box-shadow: 0 0 0 3px var(--ambar-claro); }
.nav-marcas {
    max-width: 1180px; margin: 0 auto;
    display: flex; gap: 14px; overflow-x: auto;
    padding: 4px 20px 14px;
    scrollbar-width: thin;
}
.nav-marcas a {
    flex: 0 0 auto;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    width: 72px;
    text-decoration: none;
    color: var(--carbon-oscuro);
    text-align: center;
}
.nav-marcas .chip-logo {
    width: 48px; height: 48px;
    border-radius: 50%;
    background: var(--crema-calida);
    border: 1px solid transparent;
    display: flex; align-items: center; justify-content: center;
    overflow: hidden;
    transition: border-color .15s, transform .15s;
}
.nav-marcas .chip-logo img { width: 100%; height: 100%; object-fit: contain; }
.nav-marcas .chip-logo i { font-size: 1.2rem; color: #c9c2b2; }
.nav-marcas a:hover .chip-logo { border-color: var(--ambar); transform: translateY(-2px); }
.nav-marcas .chip-nombre {
    font-size: 0.68rem;
    font-weight: 600;
    line-height: 1.2;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 100%;
}
.nav-marcas .chip-nombre small { display: block; font-weight: 400; opacity: .6; }

/* ---------- Contenido ---------- */
.contenedor { max-width: 1180px; margin: 0 auto; padding: 34px 20px 60px; }

.marca-section { margin-bottom: 46px; scroll-margin-top: 128px; }
.marca-header { display: flex; align-items: center; gap: 14px; margin-bottom: 18px; padding-bottom: 12px; border-bottom: 2px solid var(--ambar-claro); }
.marca-logo {
    width: 52px; height: 52px; border-radius: 50%;
    background: #fff; border: 1px solid #e7e2d8;
    display: flex; align-items: center; justify-content: center;
    overflow: hidden; flex: 0 0 auto;
}
.marca-logo img { width: 100%; height: 100%; object-fit: contain; }
.marca-logo i { font-size: 1.4rem; color: #c9c2b2; }
.marca-header h2 { font-family: 'Playfair Display', Georgia, serif; font-size: 1.4rem; margin: 0; }
.marca-header .badge-cant {
    margin-left: auto;
    background: var(--rojo-principal); color: #fff;
    font-size: 0.72rem; font-weight: 700;
    padding: 4px 11px; border-radius: 999px;
    flex: 0 0 auto;
}

.grid-autos { display: grid; grid-template-columns: repeat(auto-fill, minmax(158px, 1fr)); gap: 16px; align-items: start; }
.auto-card {
    background: #fff;
    border: 1px solid #e7e2d8;
    border-radius: 12px;
    overflow: hidden;
    transition: transform .18s ease, box-shadow .18s ease;
}
.auto-card:hover { transform: translateY(-4px); box-shadow: 0 10px 24px rgba(20,22,26,0.12); }
.auto-foto {
    min-height: 90px;
    background: var(--crema-calida);
    display: flex; align-items: center; justify-content: center;
    cursor: pointer;
    overflow: hidden;
}
.auto-foto img { display: block; width: 100%; height: auto; }
.auto-foto i { font-size: 2rem; color: #c9c2b2; }
.auto-info { padding: 10px 12px 13px; }
.auto-info .modelo { font-weight: 700; font-size: 0.86rem; line-height: 1.25; margin-bottom: 6px; min-height: 2.2em; }
.auto-chips { display: flex; flex-wrap: wrap; gap: 4px; font-size: 0.68rem; }
.auto-chips span { background: var(--crema-calida); color: #6b6455; padding: 2px 7px; border-radius: 999px; display: inline-flex; align-items: center; gap: 3px; }
.auto-chips .swatch { width: 9px; height: 9px; border-radius: 50%; display: inline-block; border: 1px solid rgba(0,0,0,0.15); }

.sin-resultados { display: none; text-align: center; padding: 60px 20px; color: #857f72; }
.sin-resultados i { font-size: 2.4rem; margin-bottom: 10px; display: block; }

footer { text-align: center; padding: 26px 20px 34px; color: #a39c8c; font-size: 0.78rem; }
footer a { color: #a39c8c; }

/* ---------- Lightbox ---------- */
.lightbox { position: fixed; inset: 0; background: rgba(15,16,19,0.92); z-index: 200; display: none; align-items: center; justify-content: center; padding: 30px; }
.lightbox.show { display: flex; }
.lightbox img { max-width: 100%; max-height: 82vh; border-radius: 10px; box-shadow: 0 20px 60px rgba(0,0,0,0.5); }
.lightbox .cerrar { position: absolute; top: 18px; right: 22px; color: #fff; font-size: 1.8rem; background: none; border: none; cursor: pointer; opacity: .85; }
.lightbox .titulo { position: absolute; top: 20px; left: 24px; color: #fff; font-family: 'Playfair Display', Georgia, serif; font-size: 1.1rem; }

/* ---------- Botón flotante "volver arriba" ---------- */
.btn-arriba {
    position: fixed;
    right: 20px; bottom: 24px;
    width: 46px; height: 46px;
    border-radius: 50%;
    background: var(--carbon-oscuro);
    color: #fff;
    border: 1px solid var(--ambar);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.2rem;
    text-decoration: none;
    box-shadow: 0 6px 18px rgba(20,22,26,0.3);
    opacity: 0; visibility: hidden;
    transform: translateY(10px);
    transition: opacity .2s, transform .2s, visibility .2s, background .15s;
    z-index: 60;
}
.btn-arriba.show { opacity: 1; visibility: visible; transform: translateY(0); }
.btn-arriba:hover { background: var(--rojo-principal); border-color: var(--rojo-principal); color: #fff; }

@media (max-width: 480px) {
    .hero { padding: 42px 16px 30px; }
    .hero-login { position: static; display: inline-flex; margin-bottom: 14px; }
    .hero-stats { gap: 22px; }
    .grid-autos { grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 10px; }
}
</style>
</head>
<body>

<div class="hero">
    <a href="sistema/login.php" class="hero-login"><i class="bi bi-box-arrow-in-right"></i> Login</a>
    <img src="sistema/assets/img/logo.svg" alt="Hotwheels" class="hero-logo">
    <h1>Catálogo de la Colección</h1>
    <p class="tagline">Autos de colección · organizados por marca</p>
    <div class="hero-stats">
        <div><div class="num"><?= (int)$totalAutos ?></div><div class="lbl">Autos</div></div>
        <div><div class="num"><?= (int)$totalMarcas ?></div><div class="lbl">Marcas</div></div>
    </div>
</div>

<div class="barra-sticky">
    <div class="buscador-wrap">
        <div class="buscador">
            <i class="bi bi-search"></i>
            <input type="text" id="inputBuscar" placeholder="Buscar por modelo, serie, tipo o marca...">
        </div>
    </div>
    <nav class="nav-marcas">
        <?php foreach ($marcas as $m): ?>
            <?php $rutaLogoNav = rutaMiniaturaLogoMarca($m['id']) ?: rutaLogoMarca($m['id']); ?>
            <a href="#marca-<?= (int)$m['id'] ?>">
                <span class="chip-logo">
                    <?php if ($rutaLogoNav): ?>
                        <img src="<?= esc($rutaLogoNav) ?>" alt="<?= esc($m['nombre']) ?>" loading="lazy">
                    <?php else: ?>
                        <i class="bi bi-award"></i>
                    <?php endif; ?>
                </span>
                <span class="chip-nombre"><?= esc($m['nombre']) ?><small>(<?= count($carrosPorMarca[$m['id']]) ?>)</small></span>
            </a>
        <?php endforeach; ?>
        <?php if ($carrosSinMarca): ?>
            <a href="#marca-otras">
                <span class="chip-logo"><i class="bi bi-question-lg"></i></span>
                <span class="chip-nombre">Sin marca<small>(<?= count($carrosSinMarca) ?>)</small></span>
            </a>
        <?php endif; ?>
    </nav>
</div>

<div class="contenedor" id="contenedorCatalogo">
    <?php foreach ($marcas as $m): ?>
        <?php $rutaLogoM = rutaMiniaturaLogoMarca($m['id']) ?: rutaLogoMarca($m['id']); ?>
        <section class="marca-section" id="marca-<?= (int)$m['id'] ?>" data-marca-nombre="<?= esc(mb_strtolower($m['nombre'])) ?>">
            <div class="marca-header">
                <div class="marca-logo">
                    <?php if ($rutaLogoM): ?>
                        <img src="<?= esc($rutaLogoM) ?>" alt="<?= esc($m['nombre']) ?>" loading="lazy">
                    <?php else: ?>
                        <i class="bi bi-award"></i>
                    <?php endif; ?>
                </div>
                <h2><?= esc($m['nombre']) ?></h2>
                <span class="badge-cant"><?= count($carrosPorMarca[$m['id']]) ?> auto<?= count($carrosPorMarca[$m['id']]) === 1 ? '' : 's' ?></span>
            </div>
            <div class="grid-autos">
                <?php foreach ($carrosPorMarca[$m['id']] as $c): ?>
                    <?php renderizarTarjetaAuto($c, $m['nombre']); ?>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endforeach; ?>

    <?php if ($carrosSinMarca): ?>
        <section class="marca-section" id="marca-otras" data-marca-nombre="sin marca otras">
            <div class="marca-header">
                <div class="marca-logo"><i class="bi bi-question-lg"></i></div>
                <h2>Sin marca asignada</h2>
                <span class="badge-cant"><?= count($carrosSinMarca) ?> auto<?= count($carrosSinMarca) === 1 ? '' : 's' ?></span>
            </div>
            <div class="grid-autos">
                <?php foreach ($carrosSinMarca as $c): ?>
                    <?php renderizarTarjetaAuto($c, ''); ?>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <div class="sin-resultados" id="sinResultados">
        <i class="bi bi-emoji-frown"></i>
        No se encontraron autos que coincidan con la búsqueda.
    </div>
</div>

<footer>
    Catálogo generado a partir de la colección · <a href="sistema/login.php"><i class="bi bi-gear"></i> Acceso administrador</a>
</footer>

<div class="lightbox" id="lightbox" onclick="cerrarLightbox(event)">
    <button class="cerrar" onclick="cerrarLightbox(event)"><i class="bi bi-x-lg"></i></button>
    <div class="titulo" id="lightboxTitulo"></div>
    <img id="lightboxImg" src="" alt="">
</div>

<a href="#" class="btn-arriba" id="btnArriba" aria-label="Volver arriba" onclick="event.preventDefault(); window.scrollTo({top:0, behavior:'smooth'});">
    <i class="bi bi-arrow-up"></i>
</a>

<script>
function abrirLightbox(src, titulo) {
    document.getElementById('lightboxImg').src = src;
    document.getElementById('lightboxTitulo').textContent = titulo;
    document.getElementById('lightbox').classList.add('show');
}
function cerrarLightbox(e) {
    if (e.target.id === 'lightbox' || e.target.closest('.cerrar')) {
        document.getElementById('lightbox').classList.remove('show');
        document.getElementById('lightboxImg').src = '';
    }
}
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        document.getElementById('lightbox').classList.remove('show');
        document.getElementById('lightboxImg').src = '';
    }
});

(function () {
    const input = document.getElementById('inputBuscar');
    const secciones = Array.from(document.querySelectorAll('.marca-section'));
    const sinResultados = document.getElementById('sinResultados');
    let temporizador;

    input.addEventListener('input', function () {
        clearTimeout(temporizador);
        temporizador = setTimeout(filtrar, 120);
    });

    function filtrar() {
        const termino = input.value.trim().toLowerCase();
        let algunaVisible = false;

        secciones.forEach(function (seccion) {
            const tarjetas = Array.from(seccion.querySelectorAll('.auto-card'));
            let visiblesEnSeccion = 0;

            tarjetas.forEach(function (tarjeta) {
                const coincide = termino === '' || tarjeta.dataset.buscar.includes(termino) || seccion.dataset.marcaNombre.includes(termino);
                tarjeta.style.display = coincide ? '' : 'none';
                if (coincide) visiblesEnSeccion++;
            });

            const mostrarSeccion = visiblesEnSeccion > 0;
            seccion.style.display = mostrarSeccion ? '' : 'none';
            if (mostrarSeccion) algunaVisible = true;
        });

        sinResultados.style.display = algunaVisible ? 'none' : 'block';
    }
})();

(function () {
    const boton = document.getElementById('btnArriba');
    window.addEventListener('scroll', function () {
        boton.classList.toggle('show', window.scrollY > 400);
    });
})();
</script>

</body>
</html>
