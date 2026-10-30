# Catálogo de Códigos Postales (autocompletado de dirección)

El autocompletado de **CP → Estado / Municipio / Colonias** en los formularios de
Personas y Empresas usa la tabla `codigos_postales` (~145,900 filas, SEPOMEX).

Si al escribir el código postal aparece:

> "No se pudo consultar el código postal en este momento"
> o "El catálogo de códigos postales no está instalado en este servidor"

…es porque esa tabla **no existe o está vacía** en el servidor (los `seeders` no
corren en un deploy por FTP). El resto del formulario sigue funcionando: puedes
capturar estado, municipio y colonia a mano.

## Cómo instalar la tabla en producción (una sola vez)

### Opción A — phpMyAdmin (recomendada en hosting compartido)
1. Entra a phpMyAdmin y selecciona la base de datos del sistema.
2. Pestaña **Importar**.
3. Sube **`codigos_postales.sql.gz`** (1.7 MB, phpMyAdmin lo descomprime solo).
   Si tu phpMyAdmin no acepta `.gz`, sube `codigos_postales.sql` (12 MB) — puede
   que necesites subir el límite `upload_max_filesize` / `post_max_size`.
4. Ejecutar. El script hace `DROP TABLE IF EXISTS` + `CREATE TABLE` + los INSERT,
   así que se puede volver a correr sin problema.

### Opción B — línea de comandos (si tienes acceso SSH)
```bash
gunzip < database/sql/codigos_postales.sql.gz | mysql -u USUARIO -p BASE_DE_DATOS
# o, si prefieres el seeder (necesita database/data/sepomex.csv desplegado):
php artisan db:seed --class=CodigosPostalesSeeder
```

## Regenerar el dump desde local
```bash
mysqldump -uroot --no-tablespaces --skip-comments ric codigos_postales > database/sql/codigos_postales.sql
gzip -9 -k -f database/sql/codigos_postales.sql
```
