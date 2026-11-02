-- Wonderlab Beauty Studio - sistema de citas
-- Importar una sola vez en phpMyAdmin (pestaña "Importar") o con: mysql -u root < schema.sql
CREATE DATABASE IF NOT EXISTS wonderlab_citas CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE wonderlab_citas;

CREATE TABLE IF NOT EXISTS usuarios (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    nombre        VARCHAR(100) NOT NULL,
    email         VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    creado_en     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Para limitar intentos de inicio de sesión por IP
CREATE TABLE IF NOT EXISTS intentos_login (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    ip        VARCHAR(45) NOT NULL,
    creado_en DATETIME NOT NULL,
    INDEX (ip, creado_en)
) ENGINE=InnoDB;

-- Textos, imágenes, colores y reglas de reserva editables desde el panel
CREATE TABLE IF NOT EXISTS configuracion (
    clave VARCHAR(60) PRIMARY KEY,
    valor TEXT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS servicios (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    nombre       VARCHAR(120) NOT NULL,
    categoria    VARCHAR(80) NULL,
    descripcion  TEXT NULL,
    duracion_min INT NOT NULL DEFAULT 60,
    precio       DECIMAL(10,2) NOT NULL DEFAULT 0,
    imagen       VARCHAR(255) NULL,
    activo       TINYINT(1) NOT NULL DEFAULT 1,
    orden        INT NOT NULL DEFAULT 0
) ENGINE=InnoDB;

-- Rangos de atención por día (puede haber varios por día, ej. 9-13 y 15-19)
CREATE TABLE IF NOT EXISTS horarios (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    dia_semana  TINYINT NOT NULL,   -- 0 = domingo ... 6 = sábado
    hora_inicio TIME NOT NULL,
    hora_fin    TIME NOT NULL
) ENGINE=InnoDB;

-- Días u horas sin atención (vacaciones, feriados, etc.)
CREATE TABLE IF NOT EXISTS bloqueos (
    id     INT AUTO_INCREMENT PRIMARY KEY,
    inicio DATETIME NOT NULL,
    fin    DATETIME NOT NULL,
    motivo VARCHAR(200) NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS citas (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    servicio_id      INT NULL,
    servicio_nombre  VARCHAR(120) NOT NULL,   -- copia por si el servicio se edita o elimina
    precio           DECIMAL(10,2) NOT NULL DEFAULT 0,
    inicio           DATETIME NOT NULL,
    fin              DATETIME NOT NULL,
    cliente_nombre   VARCHAR(120) NOT NULL,
    cliente_telefono VARCHAR(30) NOT NULL,
    cliente_email    VARCHAR(150) NULL,
    notas            TEXT NULL,               -- escritas por el cliente
    notas_internas   TEXT NULL,               -- solo visibles en el panel
    estado           ENUM('pendiente','confirmada','completada','cancelada','no_asistio') NOT NULL DEFAULT 'pendiente',
    origen           ENUM('web','admin') NOT NULL DEFAULT 'web',
    token            CHAR(64) NOT NULL UNIQUE, -- para que el cliente cancele su cita
    ip               VARCHAR(45) NULL,
    google_event_id  VARCHAR(255) NULL,
    creado_en        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (inicio),
    INDEX (estado),
    FOREIGN KEY (servicio_id) REFERENCES servicios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS galeria (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    imagen    VARCHAR(255) NOT NULL,
    titulo    VARCHAR(150) NULL,
    orden     INT NOT NULL DEFAULT 0,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS google_tokens (
    id            INT PRIMARY KEY,
    access_token  TEXT NOT NULL,
    refresh_token TEXT NULL,
    expires_at    INT NOT NULL
) ENGINE=InnoDB;

-- ---------- Datos iniciales (todo se puede cambiar desde el panel) ----------

INSERT IGNORE INTO configuracion (clave, valor) VALUES
('nombre_sitio', 'Wonderlab Beauty Studio'),
('eslogan', 'Belleza que se nota, cuidado que se siente'),
('logo', ''),
('color_primario', '#7e5256'),
('color_secundario', '#3e2427'),
('moneda', '$'),
('hero_titulo', 'Wonderlab Beauty Studio'),
('hero_subtitulo', 'Uñas, pestañas y cejas con el detalle que mereces. Reserva tu cita en línea en menos de un minuto.'),
('hero_boton', 'Reservar mi cita'),
('hero_imagen', ''),
('nosotros_titulo', 'Sobre nosotras'),
('nosotros_texto', 'En Wonderlab creemos que cada visita es un momento para ti. Trabajamos con productos de calidad, higiene rigurosa y mucho cariño en cada detalle.\n\nEdita este texto desde el panel de administración > Sitio web > Nosotros.'),
('nosotros_imagen', ''),
('video_titulo', 'Conoce nuestro estudio'),
('video_youtube', ''),
('video_archivo', ''),
('telefono', ''),
('whatsapp', ''),
('codigo_pais', '57'),
('email', ''),
('direccion', ''),
('mapa_url', ''),
('instagram', 'https://www.instagram.com/wonderlab_beauty_studio/'),
('facebook', ''),
('tiktok', ''),
('politicas', 'Te pedimos llegar 5 minutos antes. Si no puedes asistir, cancela tu cita con al menos 12 horas de anticipación.'),
('reserva_mensaje', '¡Gracias por reservar! Te esperamos.'),
('pie_texto', 'Todos los derechos reservados.'),
('intervalo_min', '30'),
('anticipacion_horas', '2'),
('dias_max', '60'),
('capacidad', '1'),
('estado_inicial', 'pendiente'),
('cancelacion_horas', '12'),
('google_bloquear', '1'),
('google_invitar', '0');

INSERT IGNORE INTO servicios (id, nombre, categoria, descripcion, duracion_min, precio, orden) VALUES
(1, 'Manicure semipermanente', 'Uñas', 'Limpieza, forma y esmaltado semipermanente de larga duración.', 60, 0, 1),
(2, 'Pedicure spa', 'Uñas', 'Exfoliación, hidratación y esmaltado.', 60, 0, 2),
(3, 'Uñas acrílicas', 'Uñas', 'Aplicación de uñas acrílicas con diseño a elección.', 120, 0, 3),
(4, 'Lifting de pestañas', 'Pestañas', 'Curvatura natural y duradera para tus pestañas.', 60, 0, 4),
(5, 'Diseño de cejas', 'Cejas', 'Perfilado y diseño según la forma de tu rostro.', 30, 0, 5);

-- Lunes a sábado de 9:00 a 19:00
INSERT IGNORE INTO horarios (id, dia_semana, hora_inicio, hora_fin) VALUES
(1, 1, '09:00', '19:00'),
(2, 2, '09:00', '19:00'),
(3, 3, '09:00', '19:00'),
(4, 4, '09:00', '19:00'),
(5, 5, '09:00', '19:00'),
(6, 6, '09:00', '19:00');
