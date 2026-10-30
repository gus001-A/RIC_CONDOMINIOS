-- ============================================================================
--  RIC_2026 · Correcciones de esquema en producción · 2026-08-28
--  Ejecutar en phpMyAdmin sobre la base de datos correspondiente
--  (u659313512_riccuernabd, u659313512_ricaxobd, etc.)
--  Haz respaldo antes de correr esto.
-- ============================================================================

-- ----------------------------------------------------------------------------
-- 1) Error al cerrar la póliza:
--    Unknown column 'motivo_cierre' in 'SET'
--    -> Faltan las columnas de cierre en la tabla `polizas`.
-- ----------------------------------------------------------------------------
ALTER TABLE `polizas`
    ADD COLUMN `fecha_cierre`      TIMESTAMP     NULL DEFAULT NULL AFTER `fecha_autorizacion`,
    ADD COLUMN `id_usuario_cierre` BIGINT UNSIGNED NULL DEFAULT NULL AFTER `fecha_cierre`,
    ADD COLUMN `motivo_cierre`     VARCHAR(500)  NULL DEFAULT NULL AFTER `id_usuario_cierre`;

-- FK opcional (recomendada). Si da error porque ya existe, ignórala.
ALTER TABLE `polizas`
    ADD CONSTRAINT `fk_polizas_id_usuario_cierre`
    FOREIGN KEY (`id_usuario_cierre`) REFERENCES `usuarios` (`id_usuario`)
    ON UPDATE CASCADE ON DELETE SET NULL;


-- ----------------------------------------------------------------------------
-- 2) Error al subir archivo:
--    a foreign key constraint fails (... REFERENCES `users` (`id`))
--    -> La FK de poliza_archivos apunta a `users`(id) pero el login usa
--       `usuarios`(id_usuario). Hay que reapuntarla.
-- ----------------------------------------------------------------------------

-- 2.1 Quitar la FK equivocada (el nombre reportado en el error):
ALTER TABLE `poliza_archivos` DROP FOREIGN KEY `fk_poliza_archivos_id_usuario_subio`;
--     Si tu instalación usó otro nombre, búscalo con:
--     SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
--     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'poliza_archivos'
--       AND COLUMN_NAME = 'id_usuario_subio' AND REFERENCED_TABLE_NAME IS NOT NULL;

-- 2.2 Permitir NULL en la columna (para ON DELETE SET NULL):
ALTER TABLE `poliza_archivos` MODIFY `id_usuario_subio` BIGINT UNSIGNED NULL;

-- 2.3 Limpiar valores que no existan en `usuarios` (evita el error 1452 al crear la FK):
UPDATE `poliza_archivos` pa
LEFT JOIN `usuarios` u ON u.`id_usuario` = pa.`id_usuario_subio`
SET pa.`id_usuario_subio` = NULL
WHERE pa.`id_usuario_subio` IS NOT NULL AND u.`id_usuario` IS NULL;

-- 2.4 Crear la FK correcta hacia `usuarios`(id_usuario):
ALTER TABLE `poliza_archivos`
    ADD CONSTRAINT `fk_poliza_archivos_id_usuario_subio`
    FOREIGN KEY (`id_usuario_subio`) REFERENCES `usuarios` (`id_usuario`)
    ON UPDATE CASCADE ON DELETE SET NULL;


-- ----------------------------------------------------------------------------
-- 3) (OPCIONAL) Recalcular saldos de las cuentas fondeadoras para quitar
--    el dinero que las pólizas diferidas restaron indebidamente.
--    Deja los saldos = suma de movimientos SOLO de pólizas NO diferidas.
--    Revisa el resultado del SELECT antes de aplicar el UPDATE.
-- ----------------------------------------------------------------------------
-- SELECT c.id_cuenta, c.nombre_cuenta, c.saldo_inicial AS saldo_actual,
--        COALESCE((
--            SELECT SUM(m.monto)
--            FROM movimientos_poliza m
--            JOIN polizas p ON p.id = m.id_poliza
--            WHERE (m.id_cuenta = c.id_cuenta OR m.id_caja_fondo = c.id_cuenta)
--              AND (p.es_por_pagar = 0 OR p.es_por_pagar IS NULL)
--        ), 0) AS saldo_recalculado
-- FROM cuentas c;

-- UPDATE cuentas c
-- SET c.saldo_inicial = COALESCE((
--         SELECT SUM(m.monto)
--         FROM movimientos_poliza m
--         JOIN polizas p ON p.id = m.id_poliza
--         WHERE (m.id_cuenta = c.id_cuenta OR m.id_caja_fondo = c.id_cuenta)
--           AND (p.es_por_pagar = 0 OR p.es_por_pagar IS NULL)
--     ), 0);


-- ----------------------------------------------------------------------------
-- 4) Error al crear/editar personas:
--    SQLSTATE[23000]: Column 'Materno' cannot be null
--    -> `personas.Materno` es NOT NULL, pero el apellido materno es OPCIONAL
--       en el formulario, en la validación y en el modelo.
-- ----------------------------------------------------------------------------
ALTER TABLE `personas` MODIFY `Materno` VARCHAR(255) NULL DEFAULT NULL;
UPDATE `personas` SET `Materno` = NULL WHERE `Materno` = '';

-- (Opcional, si tu instalación aún no tiene la columna `empleado` en personas)
-- ALTER TABLE `personas` ADD COLUMN `empleado` TINYINT(1) NOT NULL DEFAULT 0 AFTER `activo`;
