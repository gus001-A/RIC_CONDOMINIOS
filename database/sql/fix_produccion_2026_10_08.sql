-- ============================================================================
--  RIC_2026 · Cuentas fondeadoras por usuario · 2026-10-08
--  Ejecutar en phpMyAdmin sobre la base de datos correspondiente.
--  Haz respaldo antes de correr esto.
-- ============================================================================
--
-- El SUPERUSUARIO asigna a cada usuario qué cuentas fondeadoras puede ver
-- (igual que la asignación de empresas, pero filtrado por empresa).
-- Regla: un usuario SIN fondeadoras asignadas en una empresa NO puede generar
-- ningún tipo de póliza en ella; sólo usa las que tenga asignadas.

CREATE TABLE IF NOT EXISTS `cuentas_fondeadoras_usuarios` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_usuario` BIGINT UNSIGNED NOT NULL,
    `id_cuenta` BIGINT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `cfu_usuario_cuenta_unique` (`id_usuario`, `id_cuenta`),
    KEY `cfu_cuenta_index` (`id_cuenta`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- OPCIONAL: para que NADIE se quede bloqueado el día que se suba esto, asigna a
-- todos los usuarios activos TODAS las fondeadoras de las empresas que ya tienen.
-- Después el superusuario va quitando lo que no corresponda desde
-- Cuentas > "Asignar Cuentas Fondeadoras". Quita los "-- " para ejecutarlo.
-- ----------------------------------------------------------------------------
-- INSERT IGNORE INTO `cuentas_fondeadoras_usuarios` (`id_usuario`, `id_cuenta`, `created_at`, `updated_at`)
-- SELECT eu.`id_usuario`, c.`id_cuenta`, NOW(), NOW()
-- FROM `empresas_usuarios` eu
-- JOIN `usuarios` u ON u.`id_usuario` = eu.`id_usuario` AND u.`activo` = 1
-- JOIN `cuentas` c ON c.`id_empresa` = eu.`id_empresa` AND c.`fondeo_c` = 1 AND c.`en_uso` = 1;
