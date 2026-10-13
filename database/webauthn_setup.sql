-- Login sin contraseña vía WebAuthn (huella/Face ID/PIN del dispositivo, al estilo passkeys).
-- La contraseña nunca se elimina: esto es un método alternativo, no un reemplazo.
-- Ejecutar una sola vez desde phpMyAdmin.

CREATE TABLE IF NOT EXISTS `configuracion_seguridad` (
  `id`                  TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `webauthn_habilitado` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `configuracion_seguridad` (`id`, `webauthn_habilitado`) VALUES (1, 0)
  ON DUPLICATE KEY UPDATE id = id;

-- 1 usuario -> N credenciales (a diferencia de usuarios.remember_token, que es una sola por usuario).
CREATE TABLE IF NOT EXISTS `webauthn_credentials` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario_id`    INT UNSIGNED NOT NULL,
  `credential_id` VARCHAR(512) NOT NULL COMMENT 'ID de la credencial (base64), único por autenticador',
  `public_key`    TEXT NOT NULL COMMENT 'Clave pública PEM devuelta por processCreate()',
  `sign_count`    INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Contador anti-clonado del autenticador',
  `device_name`   VARCHAR(100) DEFAULT NULL COMMENT 'Etiqueta elegida por el usuario, ej: "Mi celular"',
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_used_at`  DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_webauthn_credential_id` (`credential_id`),
  KEY `idx_webauthn_usuario` (`usuario_id`),
  CONSTRAINT `fk_webauthn_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
