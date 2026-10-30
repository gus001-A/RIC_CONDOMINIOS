-- ============================================================================
--  RIC_2026 · Correcciones de esquema en producción · 2026-09-22
--  Ejecutar en phpMyAdmin sobre la base de datos correspondiente.
--  Haz respaldo antes de correr esto.
-- ============================================================================

-- ----------------------------------------------------------------------------
-- Columnas del flujo de revisión/autorización/rechazo/liquidación de pólizas
-- que el código ya usa (MovimientoController: revisarPoliza, autorizarPoliza,
-- rechazarPoliza, storeNomina, storeAbono, etc.) pero que pueden faltar en
-- algunas instalaciones. Sin ellas, esas acciones fallan con:
--   SQLSTATE[42S22]: Column not found: 1054 Unknown column 'id_usuario_revisor'
-- (o el nombre de la columna que falte) — típicamente sin dejar rastro visible
-- para el usuario si la acción se dispara por axios (ver también el fix de
-- storeNomina en el código, que ahora si expone estos errores).
--
-- Cada ALTER está protegido con una comprobación previa vía información del
-- esquema, para poder correr el script más de una vez sin error si alguna
-- columna ya existe. Si tu MySQL/MariaDB no soporta bien el patrón de abajo,
-- comenta manualmente las líneas cuyas columnas ya existan.
-- ----------------------------------------------------------------------------

-- Requiere MySQL 8.0.29+ / MariaDB 10.3+ para `ADD COLUMN IF NOT EXISTS`.
-- Si tu versión es más vieja, quita el "IF NOT EXISTS" y corre solo los
-- ALTER de las columnas que de verdad falten.
ALTER TABLE `polizas`
    ADD COLUMN IF NOT EXISTS `id_usuario_revisor` BIGINT UNSIGNED NULL DEFAULT NULL AFTER `id_usuario_autorizador`,
    ADD COLUMN IF NOT EXISTS `fecha_revision` TIMESTAMP NULL DEFAULT NULL AFTER `fecha_autorizacion`,
    ADD COLUMN IF NOT EXISTS `comentario_revision` VARCHAR(500) NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `comentario_autorizacion` VARCHAR(500) NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `estatus_anterior` VARCHAR(30) NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `motivo_rechazo` VARCHAR(500) NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `fecha_liquidacion` TIMESTAMP NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `fecha_abono` TIMESTAMP NULL DEFAULT NULL;

-- Si `estatus` en tu base es un ENUM restringido (en vez de VARCHAR), amplíalo
-- para que acepte todos los valores que el sistema usa:
-- ALTER TABLE `polizas` MODIFY `estatus`
--     ENUM('PENDIENTE','CAPTURADO','REVISADO','AUTORIZADO','ABONADO','LIQUIDADO','CERRADO')
--     NOT NULL DEFAULT 'CAPTURADO';
