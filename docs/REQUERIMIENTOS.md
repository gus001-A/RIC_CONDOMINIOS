# RIC Condominios — Requerimientos

Sistema web + app móvil para administración de fraccionamientos y/o condominios, construido sobre la base contable de RIC (ingresos, egresos, pólizas y estado de resultados).

Documento original: [RIC CONDOMINIOS.docx](RIC%20CONDOMINIOS.docx)

## Entornos

- **Web / Backoffice:** administradores, contadores, mantenimiento y seguridad.
- **App móvil:** condóminos, visitas y personal de campo (iOS / Android).

## Roles

Los mismos de RIC, más:

- Condómino (propietario / inquilino)
- Técnico / Mantenimiento
- Seguridad / Vigilancia

## Módulos

### 1. Usuarios, propiedades y geolocalización
- Datos completos de contacto del condómino (teléfonos, correos, contactos de emergencia).
- Propiedad: número de casa/lote, manzana, calle, identificador interno, dirección completa.
- Fotos de fachada/residentes y coordenadas GPS de la casa.

### 2. Contabilidad y transparencia (base RIC)
- El condómino consulta pólizas y estado de resultados de su condominio.
- Anotaciones en ventana emergente por póliza, con usuario, fecha, hora e identificador de casa.
- Alerta visual en pólizas que tienen anotaciones (visible para todos los que las consultan).
- Listado de deudores / morosos de cuotas y estados de cuenta.
- Mensajería interna bidireccional condómino ↔ administración.

### 3. Mantenimiento e incidencias en zonas comunes
- Reporte de desperfectos o sugerencias con fotos y descripción.
- Folio único y canalización al menú de administrador / mantenimiento.
- Estatus: Pendiente → En proceso → Atendida → Supervisada / Cancelada.
- En cada paso: comentarios, fotos y gastos; visible para los condóminos.

### 4. Seguridad y control de accesos
- Registro de entradas por vigilancia: placas, comentarios, identificación, casa destino.
- Solicitud de autorización a la casa (notificación push, aprobar / negar) y aviso de visitas.
- Botón de alertas vecinales: intrusos, agresiones, incendio, emergencia médica; con fotos/videos.
- Difusión a todos los usuarios; reporte con nombre o anónimo, siempre con trazabilidad para el administrador.

### 5. Servicios particulares y asesoría inmobiliaria
- Solicitud de mantenimiento privado para una casa (la administración asesora o consigue proveedores).
- Asesoría de renta / venta de la propiedad.
- Privacidad: solo visible entre la casa y el administrador.
- Formatos editables de contratos/convenios que concatenan datos del condominio, la casa y el usuario.

### 6. Stock / inventario
- Existencias en tiempo real de insumos y materiales, con base en compras reportadas y consumos.

## Stack

Se parte del stack actual de RIC (Laravel + Inertia/Vue + MySQL). La sugerencia del documento (Node/Django, PostgreSQL, Flutter/React Native) queda como referencia para la app móvil.
