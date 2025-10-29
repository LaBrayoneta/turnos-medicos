<?php
// db.php - Configuración mejorada con manejo de errores
const DB_HOST = '127.0.0.1';
const DB_NAME = 'turnos_medicos';
const DB_USER = 'root';
const DB_PASS = '';

function db(): PDO {
  static $pdo = null;
  if ($pdo === null) {
    try {
      $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
      $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
      ]);
    } catch (PDOException $e) {
      // Log del error para depuración
      error_log("Error de conexión a BD: " . $e->getMessage());
      
      // En producción, mostrar mensaje genérico
      die("Error al conectar con la base de datos. Por favor, verifica tu configuración en db.php");
    }
  }
  return $pdo;
}