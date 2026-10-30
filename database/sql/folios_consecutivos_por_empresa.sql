-- ============================================================================
--  Folios de pólizas consecutivos POR EMPRESA
-- ----------------------------------------------------------------------------
--  Renumera el folio de TODAS las pólizas ya creadas para que cada empresa
--  lleve su propia secuencia 0001, 0002, 0003, ... (en orden de creación / id).
--
--  Ejecutar UNA sola vez en producción (respalda la tabla antes).
--  Requiere MySQL 8+ (usa funciones de ventana).
-- ============================================================================

START TRANSACTION;

-- 1) Quitar el UNIQUE global sobre `folio` (si existe) para poder repetir
--    "0001" entre empresas distintas.
SET @tiene_unique := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'polizas'
      AND INDEX_NAME = 'polizas_folio_unique'
);
SET @sql := IF(@tiene_unique > 0,
    'ALTER TABLE polizas DROP INDEX polizas_folio_unique',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2) Renumerar folios por empresa, en orden de id (creación).
UPDATE polizas p
JOIN (
    SELECT id,
           LPAD(ROW_NUMBER() OVER (PARTITION BY id_empresa ORDER BY id), 4, '0') AS nuevo_folio
    FROM polizas
) x ON x.id = p.id
SET p.folio = x.nuevo_folio;

-- 3) Crear el UNIQUE compuesto (id_empresa, folio) si aún no existe.
SET @tiene_compuesto := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'polizas'
      AND INDEX_NAME = 'polizas_empresa_folio_unique'
);
SET @sql := IF(@tiene_compuesto = 0,
    'ALTER TABLE polizas ADD UNIQUE KEY polizas_empresa_folio_unique (id_empresa, folio)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

COMMIT;

-- Verificación:
-- SELECT id_empresa, COUNT(*) total, MIN(folio) primero, MAX(folio) ultimo
-- FROM polizas GROUP BY id_empresa;
