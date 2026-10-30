<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UsuarioController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmpresaController;
use App\Http\Controllers\CuentaController;
use App\Http\Controllers\CuentaFondeadoraController;
use App\Http\Controllers\PersonaController;
use App\Http\Controllers\MovimientoController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\ReporteCondominioController;
use App\Http\Controllers\DireccionController;
use App\Http\Controllers\CasaController;
use App\Http\Controllers\TransparenciaController;
use App\Http\Controllers\CuotaController;
use App\Http\Controllers\MensajeController;
use App\Http\Controllers\IncidenciaController;
use App\Http\Controllers\VisitaController;
use App\Http\Controllers\AlertaController;
use App\Http\Controllers\ServicioController;
use App\Http\Controllers\DocumentoController;
use App\Http\Controllers\InventarioController;
use App\Http\Controllers\PulsoController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Redirigir a login
Route::get('/', function () {
    return redirect()->route('login');
})->name('home');

// Dashboard
Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth'])
    ->name('dashboard');

// Inicio: tablero de RIC Condominios, distinto para cada rol
Route::get('/inicio', [\App\Http\Controllers\InicioController::class, 'index'])
    ->middleware(['auth'])
    ->name('inicio');

// ✅ TODAS LAS RUTAS DE MOVIMIENTOS JUNTAS Y EN ORDEN
Route::middleware(['auth'])->group(function () {
    // ============================================
    // 📍 DIRECCIONES (catálogo estados/municipios + búsqueda por CP)
    // ============================================
    Route::get('/direccion/estados', [DireccionController::class, 'estados'])->name('direccion.estados');
    Route::get('/direccion/municipios', [DireccionController::class, 'municipios'])->name('direccion.municipios');
    Route::get('/direccion/cp/{cp}', [DireccionController::class, 'codigoPostal'])->name('direccion.cp');

    // ============================================
    // 📝 RUTAS DE NÓMINA (MUST GO BEFORE RESOURCE)
    // ============================================
    Route::get('/movimientos/nomina/create', [MovimientoController::class, 'createNomina'])->middleware('modulo:nomina')->name('movimientos.nomina.create');
    Route::post('/movimientos/nomina/store', [MovimientoController::class, 'storeNomina'])->middleware('modulo:nomina')->name('movimientos.nomina.store');
    Route::get('/movimientos/nomina/empleados', [MovimientoController::class, 'getEmpleadosNomina'])->middleware('modulo:nomina')->name('movimientos.nomina.empleados');
    Route::get('/movimientos/nomina/calcular-fecha', [MovimientoController::class, 'calcularFechaPago'])->middleware('modulo:nomina')->name('movimientos.nomina.calcular-fecha');

    // ============================================
    // 📄 RUTAS PERSONALIZADAS DE MOVIMIENTOS
    // ============================================
    Route::get('/movimientos/{id}/abono', [MovimientoController::class, 'showAbono'])->name('movimientos.abono');
    Route::post('/movimientos/abono', [MovimientoController::class, 'storeAbono'])->name('movimientos.abono.store');
    Route::delete('/movimientos/abono/{id}', [MovimientoController::class, 'destroyAbono'])->name('movimientos.abono.destroy');
    Route::post('movimientos/liquidar-ingreso', [MovimientoController::class, 'liquidarIngreso'])->name('movimientos.liquidar.ingreso');
    Route::get('/movimientos/documento-fiscal/{id}/{tipo}', [MovimientoController::class, 'getDocumentoFiscal'])->name('movimientos.documento.fiscal');
    Route::get('movimientos/buscar/personas', [MovimientoController::class, 'buscarPersonas'])->name('movimientos.buscar.personas');
    Route::get('movimientos/buscar/cuentas', [MovimientoController::class, 'buscarCuentas'])->name('movimientos.buscar.cuentas');
    Route::get('movimientos/saldo/cuenta', [MovimientoController::class, 'saldoCuenta'])->name('movimientos.saldo.cuenta');
    Route::post('movimientos/calcular-desglose', [MovimientoController::class, 'calcularDesglose'])->name('movimientos.calcular-desglose');
    Route::get('movimientos/marcadores', [MovimientoController::class, 'indexMarcadores'])->name('movimientos.marcadores.index');
    Route::post('movimientos/marcadores/store', [MovimientoController::class, 'storeMarcador'])->name('movimientos.marcadores.store');
    Route::put('movimientos/marcadores/{id}', [MovimientoController::class, 'updateMarcador'])->name('movimientos.marcadores.update');
    Route::delete('movimientos/marcadores/{id}', [MovimientoController::class, 'destroyMarcador'])->name('movimientos.marcadores.destroy');
    Route::post('movimientos/traspaso/store', [MovimientoController::class, 'storeTraspaso'])->name('movimientos.traspaso.store');
    Route::get('movimientos/export/excel', [MovimientoController::class, 'exportExcel'])->name('movimientos.export.excel');
    Route::get('movimientos/export/pdf', [MovimientoController::class, 'exportPdf'])->name('movimientos.export.pdf');
    Route::get('/movimientos/fiscal-doble-iva/create', [MovimientoController::class, 'createFiscalDobleIva'])->name('movimientos.fiscal.doble.iva.create');
    Route::post('/movimientos/fiscal-doble-iva/store', [MovimientoController::class, 'storeFiscalDobleIva'])->name('movimientos.fiscal.doble.iva.store');
    
    // 🔹 Movimientos - Obtener última póliza de una persona
    Route::get('/movimientos/ultima-poliza', [MovimientoController::class, 'obtenerUltimaPoliza'])->name('movimientos.ultima.poliza');
    
    // 🔹 Movimientos - Exportar PDF de póliza
    Route::get('/movimientos/export-pdf/{id}', [MovimientoController::class, 'exportPdfPoliza'])->name('movimientos.export.pdf.poliza');

    // ============================================
    // ✅ ACCIONES DE PÓLIZA (FLUJO DE APROBACIÓN)
    // ============================================
    Route::post('/movimientos/{id}/revisar', [MovimientoController::class, 'revisarPoliza'])->name('movimientos.revisar');
    Route::post('/movimientos/{id}/autorizar', [MovimientoController::class, 'autorizarPoliza'])->name('movimientos.autorizar');
    Route::post('/movimientos/{id}/rechazar', [MovimientoController::class, 'rechazarPoliza'])->name('movimientos.rechazar');
    Route::post('/movimientos/{id}/cerrar', [MovimientoController::class, 'cerrarPoliza'])->name('movimientos.cerrar');
    Route::post('/movimientos/{id}/reabrir', [MovimientoController::class, 'reabrirPoliza'])->name('movimientos.reabrir');
    Route::post('/movimientos/{id}/revertir-revision', [MovimientoController::class, 'revertirRevision'])->name('movimientos.revertir.revision');

    // ============================================
    // 📊 INFORMACIÓN ADICIONAL DE PÓLIZA (API)
    // ============================================
    Route::get('/movimientos/{id}/detalle', [MovimientoController::class, 'getDetallePoliza'])->name('movimientos.detalle');
    Route::get('/movimientos/{id}/estadisticas', [MovimientoController::class, 'getEstadisticasPoliza'])->name('movimientos.estadisticas');
    Route::get('/movimientos/{id}/historial', [MovimientoController::class, 'getHistorialPoliza'])->name('movimientos.historial');
    Route::get('/movimientos/{id}/imprimir', [MovimientoController::class, 'imprimirPoliza'])->name('movimientos.imprimir');
    Route::post('/movimientos/{id}/archivos', [MovimientoController::class, 'subirArchivo'])->name('movimientos.archivos.subir');
    Route::get('/movimientos/cuentas-activas', [MovimientoController::class, 'obtenerCuentasActivas'])->name('movimientos.cuentas-activas');
    Route::get('/movimientos/archivos/{id}', [MovimientoController::class, 'verArchivo'])->name('movimientos.archivos.ver');
    Route::delete('/movimientos/archivos/{id}', [MovimientoController::class, 'eliminarArchivo'])->name('movimientos.archivos.eliminar');
    Route::post('/movimientos/archivos/{id}/reemplazar', [MovimientoController::class, 'reemplazarArchivo'])->name('movimientos.archivos.reemplazar');
    
    // ============================================
    // 🟢 RESOURCE - DEBE IR AL FINAL
    // ============================================
    Route::resource('movimientos', MovimientoController::class);
});

// ✅ PERSONAS - PRIMERO LAS RUTAS PERSONALIZADAS, LUEGO EL RESOURCE (oculto en RIC Condominios)
Route::middleware(['auth', 'modulo:personas'])->group(function () {
    Route::post('/personas/{id}/toggle-active', [PersonaController::class, 'toggleActive'])->name('personas.toggle-active');
    Route::post('/personas/generar-rfc', [PersonaController::class, 'generarRFC'])->name('personas.generar-rfc');
    Route::get('/personas/{id}/documentos', [PersonaController::class, 'getDocumentos'])->name('personas.documentos');
    Route::post('/personas/{id}/subir-documento', [PersonaController::class, 'subirDocumento'])->name('personas.subir-documento');
    Route::get('/personas/documentos/{id}/descargar', [PersonaController::class, 'descargarDocumento'])->name('personas.descargar-documento');
    Route::delete('/personas/documentos/{id}/eliminar', [PersonaController::class, 'eliminarDocumento'])->name('personas.eliminar-documento');
    Route::patch('/personas/documentos/{id}/toggle-finalizado', [PersonaController::class, 'toggleFinalizado'])->name('personas.toggle-finalizado');
    Route::resource('personas', PersonaController::class);
});

// ✅ CUENTAS FONDEADORAS POR USUARIO (sólo SUPERUSUARIO; oculto en RIC Condominios)
Route::middleware(['auth', 'modulo:fondeadoras_usuario'])->group(function () {
    Route::get('/cuentas-fondeadoras', [CuentaFondeadoraController::class, 'index'])->name('cuentas-fondeadoras.index');
    Route::put('/cuentas-fondeadoras/usuario/{id}', [CuentaFondeadoraController::class, 'actualizarUsuario'])->name('cuentas-fondeadoras.usuario');
    Route::put('/cuentas-fondeadoras/cuenta/{id}', [CuentaFondeadoraController::class, 'actualizarCuenta'])->name('cuentas-fondeadoras.cuenta');
});

// ✅ CUENTAS - PRIMERO LAS RUTAS PERSONALIZADAS, LUEGO EL RESOURCE
Route::middleware(['auth'])->group(function () {
    Route::get('/cuentas/get-cuentas-madre', [CuentaController::class, 'getCuentasMadre'])->name('cuentas.get-cuentas-madre');
    Route::get('/cuentas/datatable', [CuentaController::class, 'getCuentas'])->name('cuentas.datatable');
    Route::post('/cuentas/{cuenta}/cambiar-estado', [CuentaController::class, 'cambiarEstado'])->name('cuentas.cambiar-estado');
    Route::get('/cuentas/inactivas', [CuentaController::class, 'inactivas'])->name('cuentas.inactivas');
    Route::post('/cuentas/{id}/restaurar', [CuentaController::class, 'restaurar'])->name('cuentas.restaurar');
    Route::get('/cuentas/get-cuentas-resultados', [CuentaController::class, 'getCuentasResultados'])->name('cuentas.get-cuentas-resultados');
    Route::get('/cuentas/verificar-codigo', [CuentaController::class, 'verificarCodigo'])->name('cuentas.verificar-codigo');
    Route::resource('cuentas', CuentaController::class);
});

// ✅ EMPRESAS - PRIMERO LAS RUTAS PERSONALIZADAS, LUEGO EL RESOURCE
Route::middleware(['auth'])->group(function () {
    Route::post('/empresas/{id}/toggle-active', [EmpresaController::class, 'toggleActive'])->name('empresas.toggle-active');
    Route::post('/empresas/{empresa}/cambiar-estado', [EmpresaController::class, 'cambiarEstado'])->name('empresas.cambiar-estado');
    Route::get('/empresas/select', [EmpresaController::class, 'getEmpresasSelect'])->name('empresas.select');
    Route::resource('empresas', EmpresaController::class);
});

// ✅ USUARIOS
Route::middleware(['auth'])->group(function () {
    Route::resource('usuarios', UsuarioController::class);
    Route::post('/usuarios/{id}/restore', [UsuarioController::class, 'restore'])->name('usuarios.restore');
    Route::post('/usuarios/{id}/reset-password', [UsuarioController::class, 'resetPassword'])->name('usuarios.reset-password');
    Route::post('/usuarios/{id}/toggle-activo', [UsuarioController::class, 'toggleActivo'])->name('usuarios.toggle-activo');
});

// ✅ CASAS Y CONDÓMINOS (permisos por rol y condominio se validan en el controlador)
Route::middleware(['auth'])->group(function () {
    Route::get('/mi-casa', [CasaController::class, 'miCasa'])->name('casas.mi');
    Route::post('/casas/{casa}/toggle-activo', [CasaController::class, 'toggleActivo'])->name('casas.toggle-activo');
    Route::post('/casas/{casa}/ubicacion', [CasaController::class, 'actualizarUbicacion'])->name('casas.ubicacion');
    Route::post('/casas/{casa}/fotos', [CasaController::class, 'subirFoto'])->name('casas.fotos.store');
    Route::delete('/casas/fotos/{foto}', [CasaController::class, 'eliminarFoto'])->name('casas.fotos.destroy');
    Route::post('/casas/{casa}/condominos', [CasaController::class, 'storeCondomino'])->name('condominos.store');
    // POST (no PUT) porque puede traer la foto del condómino como multipart.
    Route::post('/condominos/{condomino}', [CasaController::class, 'updateCondomino'])->name('condominos.update');
    Route::post('/condominos/{condomino}/toggle-activo', [CasaController::class, 'toggleCondomino'])->name('condominos.toggle-activo');
    Route::resource('casas', CasaController::class)->except(['destroy']);
});

// ✅ TRANSPARENCIA CONTABLE Y CUOTAS (permisos y condominio se validan en el controlador)
Route::middleware(['auth'])->group(function () {
    Route::get('/transparencia', [TransparenciaController::class, 'index'])->name('transparencia.index');
    Route::get('/transparencia/polizas', [TransparenciaController::class, 'polizas'])->name('transparencia.polizas');
    Route::get('/transparencia/polizas/{poliza}', [TransparenciaController::class, 'poliza'])->name('transparencia.poliza');
    Route::get('/transparencia/archivos/{archivo}', [TransparenciaController::class, 'archivo'])->name('transparencia.archivo');
    Route::get('/transparencia/deudores', [TransparenciaController::class, 'deudores'])->name('transparencia.deudores');
    Route::post('/transparencia/polizas/{poliza}/anotaciones', [TransparenciaController::class, 'anotar'])->name('anotaciones.store');
    Route::post('/transparencia/anotaciones/{anotacion}/responder', [TransparenciaController::class, 'responder'])->name('anotaciones.responder');
    Route::post('/transparencia/anotaciones/{anotacion}/resolver', [TransparenciaController::class, 'resolver'])->name('anotaciones.resolver');

    Route::get('/cuotas', [CuotaController::class, 'index'])->name('cuotas.index');
    Route::post('/cuotas/generar', [CuotaController::class, 'generarCargos'])->name('cuotas.generar');
    Route::get('/cuotas/polizas-ingreso', [CuotaController::class, 'polizasIngreso'])->name('cuotas.polizas-ingreso');
    Route::post('/cuotas/casas/{casa}/pagos', [CuotaController::class, 'storePago'])->name('cuotas.pagos.store');
    Route::get('/cuotas/casas/{casa}/estado-cuenta', [CuotaController::class, 'estadoCuenta'])->name('cuotas.estado-cuenta');
    Route::post('/cuotas/{tipo}/{id}/cancelar', [CuotaController::class, 'cancelar'])
        ->whereIn('tipo', ['pago', 'cargo'])->whereNumber('id')->name('cuotas.cancelar');
});

// ✅ MENSAJERÍA INTERNA (casa ↔ administración)
Route::middleware(['auth'])->group(function () {
    Route::get('/mensajes', [MensajeController::class, 'index'])->name('mensajes.index');
    Route::get('/mensajes/conversaciones', [MensajeController::class, 'conversaciones'])->name('mensajes.conversaciones');
    Route::post('/mensajes/conversaciones', [MensajeController::class, 'store'])->name('mensajes.store');
    Route::get('/mensajes/conversaciones/{conversacion}', [MensajeController::class, 'show'])->name('mensajes.show');
    Route::post('/mensajes/conversaciones/{conversacion}/mensajes', [MensajeController::class, 'responder'])->name('mensajes.responder');
    Route::post('/mensajes/conversaciones/{conversacion}/estatus', [MensajeController::class, 'estatus'])->name('mensajes.estatus');
    Route::post('/mensajes/conversaciones/{conversacion}/escribiendo', [MensajeController::class, 'escribiendo'])->name('mensajes.escribiendo');
    Route::get('/mensajes/adjuntos/{mensaje}', [MensajeController::class, 'adjunto'])->name('mensajes.adjunto');
});

// ✅ INCIDENCIAS DE ZONAS COMUNES
Route::middleware(['auth'])->group(function () {
    Route::get('/incidencias', [IncidenciaController::class, 'index'])->name('incidencias.index');
    Route::post('/incidencias', [IncidenciaController::class, 'store'])->name('incidencias.store');
    Route::get('/incidencias/{incidencia}', [IncidenciaController::class, 'show'])->name('incidencias.show');
    Route::post('/incidencias/{incidencia}/seguimientos', [IncidenciaController::class, 'seguimiento'])->name('incidencias.seguimiento');
    Route::post('/incidencias/{incidencia}/asignar', [IncidenciaController::class, 'asignar'])->name('incidencias.asignar');
    Route::get('/incidencias/{incidencia}/polizas-egreso', [IncidenciaController::class, 'polizasEgreso'])->name('incidencias.polizas-egreso');
    Route::post('/incidencias/gastos/{gasto}', [IncidenciaController::class, 'gasto'])->name('incidencias.gasto');
});

// ✅ SEGURIDAD: CASETA, VISITAS Y ALERTAS VECINALES
Route::middleware(['auth'])->group(function () {
    // Pulso global: alertas, visitas por autorizar y mensajes sin leer (lo consulta el layout).
    Route::get('/pulso', PulsoController::class)->name('pulso');

    Route::get('/seguridad/caseta', [VisitaController::class, 'caseta'])->name('seguridad.caseta');
    Route::get('/mis-visitas', [VisitaController::class, 'misVisitas'])->name('seguridad.mis-visitas');
    Route::get('/seguridad/visitas', [VisitaController::class, 'lista'])->name('seguridad.visitas');
    Route::get('/seguridad/visitas/placa/{placa}', [VisitaController::class, 'porPlaca'])->name('seguridad.visitas.placa');
    Route::post('/seguridad/visitas', [VisitaController::class, 'registrarLlegada'])->name('seguridad.visitas.store');
    Route::post('/seguridad/visitas/preautorizar', [VisitaController::class, 'preautorizar'])->name('seguridad.visitas.preautorizar');
    Route::post('/seguridad/visitas/{visita}/responder', [VisitaController::class, 'responder'])->name('seguridad.visitas.responder');
    Route::post('/seguridad/visitas/{visita}/entrada', [VisitaController::class, 'entrada'])->name('seguridad.visitas.entrada');
    Route::post('/seguridad/visitas/{visita}/salida', [VisitaController::class, 'salida'])->name('seguridad.visitas.salida');
    Route::post('/seguridad/visitas/{visita}/cancelar', [VisitaController::class, 'cancelar'])->name('seguridad.visitas.cancelar');
    Route::get('/seguridad/visitas/{visita}/foto', [VisitaController::class, 'foto'])->name('seguridad.visitas.foto');

    // Notificaciones push (app instalable)
    Route::post('/push/suscribir', [\App\Http\Controllers\PushController::class, 'suscribir'])->name('push.suscribir');
    Route::post('/push/desuscribir', [\App\Http\Controllers\PushController::class, 'desuscribir'])->name('push.desuscribir');
    Route::post('/push/prueba', [\App\Http\Controllers\PushController::class, 'prueba'])->middleware('throttle:6,1')->name('push.prueba');

    Route::get('/seguridad/alertas', [AlertaController::class, 'index'])->name('seguridad.alertas');
    Route::get('/seguridad/alertas/lista', [AlertaController::class, 'lista'])->name('seguridad.alertas.lista');
    Route::post('/seguridad/alertas', [AlertaController::class, 'store'])->name('seguridad.alertas.store');
    Route::post('/seguridad/alertas/{alerta}/cerrar', [AlertaController::class, 'cerrar'])->name('seguridad.alertas.cerrar');
    Route::get('/seguridad/alertas/archivos/{archivo}', [AlertaController::class, 'archivo'])->name('seguridad.alertas.archivo');
});

// ✅ SERVICIOS PARTICULARES, ASESORÍA INMOBILIARIA Y DOCUMENTOS (privados casa ↔ administración)
Route::middleware(['auth'])->group(function () {
    Route::get('/servicios', [ServicioController::class, 'index'])->name('servicios.index');
    Route::post('/servicios', [ServicioController::class, 'store'])->name('servicios.store');
    Route::get('/servicios/{solicitud}', [ServicioController::class, 'show'])->whereNumber('solicitud')->name('servicios.show');
    Route::post('/servicios/{solicitud}/seguimientos', [ServicioController::class, 'seguimiento'])->name('servicios.seguimiento');
    Route::post('/servicios/{solicitud}/cotizaciones', [ServicioController::class, 'cotizar'])->name('servicios.cotizar');
    Route::post('/servicios/cotizaciones/{cotizacion}/responder', [ServicioController::class, 'responderCotizacion'])->name('servicios.cotizacion.responder');
    Route::get('/servicios/adjuntos/{tipo}/{id}', [ServicioController::class, 'adjunto'])
        ->whereIn('tipo', ['seguimiento', 'cotizacion'])->whereNumber('id')->name('servicios.adjunto');

    Route::get('/documentos/plantillas', [DocumentoController::class, 'plantillas'])->name('documentos.plantillas');
    Route::post('/documentos/plantillas', [DocumentoController::class, 'guardarPlantilla'])->name('documentos.plantillas.store');
    Route::post('/documentos/plantillas/{plantilla}', [DocumentoController::class, 'guardarPlantilla'])->name('documentos.plantillas.update');
    Route::post('/documentos/previsualizar', [DocumentoController::class, 'previsualizar'])->name('documentos.previsualizar');
    Route::get('/documentos/plantillas/{plantilla}/preparar', [DocumentoController::class, 'preparar'])->name('documentos.preparar');
    Route::post('/documentos/plantillas/{plantilla}/generar', [DocumentoController::class, 'generar'])->name('documentos.generar');
    Route::get('/documentos/{documento}', [DocumentoController::class, 'show'])->whereNumber('documento')->name('documentos.show');
    Route::post('/documentos/{documento}', [DocumentoController::class, 'actualizar'])->whereNumber('documento')->name('documentos.update');
    Route::get('/documentos/{documento}/pdf', [DocumentoController::class, 'pdf'])->name('documentos.pdf');
});

// ✅ INVENTARIO DE INSUMOS Y MATERIALES
Route::middleware(['auth'])->group(function () {
    Route::get('/inventario', [InventarioController::class, 'index'])->name('inventario.index');
    Route::get('/inventario/insumos', [InventarioController::class, 'lista'])->name('inventario.lista');
    Route::get('/inventario/referencias', [InventarioController::class, 'referencias'])->name('inventario.referencias');
    Route::post('/inventario/insumos', [InventarioController::class, 'guardar'])->name('inventario.store');
    Route::post('/inventario/insumos/{insumo}', [InventarioController::class, 'guardar'])->name('inventario.update');
    Route::post('/inventario/insumos/{insumo}/movimientos', [InventarioController::class, 'movimiento'])->name('inventario.movimiento');
    Route::get('/inventario/insumos/{insumo}/kardex', [InventarioController::class, 'kardex'])->name('inventario.kardex');
});

// ✅ REPORTES
Route::middleware(['auth'])->group(function () {
    Route::get('/reportes', [ReporteController::class, 'index'])->name('reportes.index');
    Route::get('/reportes/movimientos', [ReporteController::class, 'getMovimientos'])->name('reportes.movimientos');
    // Reportes del condominio (cobranza, operación, seguridad, almacén y padrón)
    Route::get('/reportes/condominio', [ReporteCondominioController::class, 'catalogo'])->name('reportes.condominio.catalogo');
    Route::get('/reportes/condominio/{tipo}', [ReporteCondominioController::class, 'datos'])->name('reportes.condominio.datos');
    Route::get('/reportes/condominio/{tipo}/excel', [ReporteCondominioController::class, 'excel'])->name('reportes.condominio.excel');
    Route::get('/reportes/condominio/{tipo}/pdf', [ReporteCondominioController::class, 'pdf'])->name('reportes.condominio.pdf');
    Route::get('/reportes/movimientos-cuenta', [ReporteController::class, 'getMovimientosCuenta'])->name('reportes.movimientos.cuenta');
    Route::get('/reportes/export/excel', [ReporteController::class, 'exportExcel'])->name('reportes.export.excel');
    Route::get('/reportes/export/pdf', [ReporteController::class, 'exportPdf'])->name('reportes.export.pdf');
    
    // ✅ Reportes - Exportar PDF de Resultados (RUTA CORRECTA)
    Route::get('/reportes/export/pdf/resultados', [ReporteController::class, 'exportPdfResultados'])
        ->name('reportes.export.pdf.resultados');
});

// Perfil
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Deshabilitar registro
Route::get('/register', function () {
    abort(404);
})->name('register');

require __DIR__.'/auth.php';