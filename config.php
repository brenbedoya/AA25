<?php
// Configuración de la aplicación. Ajusta estos valores si tu XAMPP es distinto.

const DB_HOST    = 'localhost';
const DB_NAME    = 'halloween';
const DB_USER    = 'root';   // usuario por defecto de XAMPP
const DB_PASS    = '';       // XAMPP no trae contraseña para root
const DB_CHARSET = 'utf8mb4';

// Nombre del usuario que tiene permisos de administración (CRUD de disfraces).
// Ese nombre queda reservado: nadie puede registrarse con él desde la web.
const ADMIN_USER = 'admin';

// Tamaño máximo de imagen subida (bytes).
const MAX_UPLOAD_BYTES = 2 * 1024 * 1024; // 2 MB
