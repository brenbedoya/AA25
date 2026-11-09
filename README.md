# 🎃 Noche de Disfraces — Desafío Halloween

Aplicación web con **API REST en PHP + MySQL** para ver disfraces de Halloween y votar por ellos.
Listo para ejecutarse en **XAMPP**.

## Instalación en XAMPP

1. Copia la carpeta `halloween` dentro de `C:\xampp\htdocs\` (en Linux/Mac: `/opt/lampp/htdocs/`).
2. Abre el **panel de XAMPP** y arranca **Apache** y **MySQL**.
3. Entra a <http://localhost/phpmyadmin>, ve a la pestaña **Importar**, elige `sql/halloween.sql` y pulsa **Importar**.
   (Crea la base `halloween`, las tablas `disfraces`, `usuarios` y `votos`, y datos de ejemplo.)
4. Abre <http://localhost/halloween/>.

Si tu MySQL tiene usuario/contraseña distintos de `root` / vacío, edita `api/config.php`.

### Usuario administrador

| Usuario | Contraseña  |
|---------|-------------|
| `admin` | `Admin123!` |

Entra con él en `login.html` y aparecerá el enlace **Administración**. Cambia la clave antes de publicar el proyecto:

```bash
php -r "echo password_hash('MiNuevaClave', PASSWORD_DEFAULT);"
```
y pega el resultado en la columna `clave` del usuario `admin` (phpMyAdmin → tabla `usuarios`).

## Páginas

| Archivo          | Qué hace                                                                 |
|------------------|--------------------------------------------------------------------------|
| `index.html`     | Lista de disfraces (nombre, descripción, foto, votos) + botón **Votar**  |
| `registro.html`  | Registro de usuarios (inicia sesión automáticamente al terminar)         |
| `login.html`     | Inicio de sesión                                                         |
| `admin.html`     | CRUD de disfraces con subida de foto (solo administrador)                |

## API REST (`/api`)

Todas las respuestas son JSON. Las operaciones que modifican datos exigen la cabecera `X-CSRF-Token`
(el token se obtiene con `GET sesion.php`).

| Método | Endpoint                      | Acceso   | Descripción                                   |
|--------|-------------------------------|----------|-----------------------------------------------|
| GET    | `disfraces.php`               | público  | Lista de disfraces (ordenados por votos)      |
| GET    | `disfraces.php/{id}`          | público  | Detalle de un disfraz                         |
| POST   | `disfraces.php`               | admin    | Crear (multipart: `nombre`, `descripcion`, `foto`) |
| PUT    | `disfraces.php/{id}`          | admin    | Actualizar (JSON, o POST multipart con `_method=PUT`) |
| DELETE | `disfraces.php/{id}`          | admin    | Eliminar (borrado lógico: `eliminado = 1`)    |
| POST   | `usuarios.php`                | público  | Registrar usuario `{nombre, clave}`           |
| GET    | `sesion.php`                  | público  | Estado de la sesión + token CSRF              |
| POST   | `sesion.php`                  | público  | Iniciar sesión `{nombre, clave}`              |
| DELETE | `sesion.php`                  | sesión   | Cerrar sesión                                 |
| GET    | `votos.php`                   | sesión   | Ids de los disfraces votados por el usuario   |
| POST   | `votos.php`                   | sesión   | Votar `{id_disfraz}`                          |
| DELETE | `votos.php?id_disfraz={id}`   | sesión   | Retirar el voto                               |
| GET    | `imagen.php?id={id}`          | público  | Foto del disfraz                              |

Códigos usados: `200`, `201`, `400`, `401` (sin sesión), `403` (sin permiso / CSRF), `404`, `409` (duplicado), `413`/`415` (imagen), `422` (validación), `429` (demasiados intentos).

## Seguridad y buenas prácticas aplicadas

- **Contraseñas** con `password_hash()` / `password_verify()` (bcrypt); nunca en texto plano.
- **Consultas preparadas (PDO)** en todo el código: sin inyección SQL.
- **Sesiones** con cookie `HttpOnly` + `SameSite=Lax`, `session_regenerate_id()` al iniciar sesión y cierre de sesión que destruye la cookie.
- **CSRF**: token por sesión exigido en todas las peticiones POST/PUT/DELETE.
- **Autorización en el servidor**: el panel de admin solo se oculta en la interfaz; la API vuelve a comprobar el rol en cada operación (`403` si no es admin). El nombre `admin` está reservado y no se puede registrar.
- **Votos duplicados**: clave `UNIQUE (id_usuario, id_disfraz)` en la tabla `votos` + transacción que actualiza el contador `disfraces.votos`.
- **XSS**: el frontend inserta datos con `textContent` / `createElement`, nunca con `innerHTML`.
- **Subida de imágenes**: se valida el contenido real (`getimagesize`), tipos JPG/PNG/GIF/WEBP, máx. 2 MB, nombre aleatorio; `uploads/` bloquea el acceso directo (`.htaccess`) y las fotos se sirven por `api/imagen.php`.
- **Fuerza bruta**: 5 intentos fallidos de login bloquean 30 s; mensaje de error genérico (no revela si el usuario existe).
- Los errores internos se registran en el log de PHP y no se muestran detalles al usuario.

## Decisiones de diseño

- **Esquema**: se respetaron las tres tablas del enunciado y se añadieron `PRIMARY KEY` / `AUTO_INCREMENT`, claves únicas y claves foráneas (el enunciado no las incluía).
- **Administrador**: la tabla `usuarios` no tiene columna de rol, por lo que el admin es el usuario cuyo nombre coincide con `ADMIN_USER` (`api/config.php`).
- **Foto**: el archivo se guarda en `uploads/` (columna `foto`, nombre de ≤ 20 caracteres) y `foto_blob` guarda una miniatura JPEG, porque el tipo `BLOB` admite como máximo 64 KB. Si el archivo faltara, la API sirve la miniatura.
- **Votos**: cada usuario puede votar **una vez por disfraz** (puede apoyar a varios) y puede retirar su voto. Si prefieres un solo voto por usuario en total, bastaría cambiar la clave única a `(id_usuario)`.
- **Borrado**: lógico (`eliminado = 1`), usando la columna prevista en el enunciado.
- Las fuentes de Google (Creepster/Nunito) se cargan por internet; sin conexión se usan fuentes del sistema.

## Estructura

```
halloween/
├── index.html · login.html · registro.html · admin.html
├── css/estilo.css
├── js/            api.js (cliente REST) · index.js · login.js · registro.js · admin.js
├── api/           config.php · db.php · bootstrap.php · imagenes.php
│                  disfraces.php · votos.php · sesion.php · usuarios.php · imagen.php
├── sql/halloween.sql
└── uploads/       (fotos subidas; acceso directo bloqueado)
```
