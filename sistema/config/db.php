<?php
/**
 * Configuración de conexión a la base de datos
 * Ajuste estos valores según su servidor
 */

if($_SERVER['HTTP_HOST']=="hotwheels.jjsc.me")
{
    define('DB_HOST', '66.225.201.101');
    define('DB_NAME', 'giuhoeah_hotwhe');
    define('DB_USER', 'giuhoeah_hotwhe');
    define('DB_PASS', '');
    define('DB_CHARSET', 'utf8mb4');
}
elseif($_SERVER['HTTP_HOST']=="localhost" || $_SERVER['HTTP_HOST']=="192.168.1.229" || $_SERVER['HTTP_HOST']=="172.16.1.21")
{
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'db_hotwheels');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_CHARSET', 'utf8mb4');    
}  
    
date_default_timezone_set('America/Guayaquil');

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $opciones = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $opciones);
} catch (PDOException $e) {
    die('Error de conexión a la base de datos: ' . $e->getMessage());
}
