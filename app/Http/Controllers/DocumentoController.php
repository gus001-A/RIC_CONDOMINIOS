<?php

namespace App\Http\Controllers;

use App\Models\Casa;
use App\Models\DocumentoGenerado;
use App\Models\Empresa;
use App\Models\PlantillaDocumento;
use App\Models\SolicitudServicio;
use App\Services\GeneradorDocumentos;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Generador de documentos: plantillas de contratos / convenios con variables
 * que se llenan con los datos del condominio, la casa y su propietario.
 * La administración administra plantillas y genera documentos; la casa ve y
 * descarga los suyos.
 */
class DocumentoController extends Controller
{
    public function __construct(private GeneradorDocumentos $generador)
    {
    }

    // ======================================================
    // 📄 PLANTILLAS (administración)
    // ======================================================

    public function plantillas()
    {
        abort_unless(Gate::allows('atender-servicios'), 403);
        $usuario = auth()->user();

        return Inertia::render('Documentos/Plantillas', [
            'plantillas' => PlantillaDocumento::disponiblesPara($usuario)
                ->with(['condominio:id,nombre_empresa', 'registro:id_usuario,nombre_usuario', 'modifico:id_usuario,nombre_usuario'])
                ->orderByDesc('activo')->orderBy('nombre')->get(),
            'condominios' => Empresa::whereIn('id', $usuario->idsCondominios())->orderBy('nombre_empresa')->get(['id', 'nombre_empresa']),
            'casas' => Casa::whereIn('id_empresa', $usuario->idsCondominios())->where('activo', true)->orderBy('identificador')->get(['id', 'identificador', 'id_empresa']),
            'tipos' => PlantillaDocumento::TIPOS,
            'variables' => GeneradorDocumentos::VARIABLES,
            'documentos' => DocumentoGenerado::whereIn('id_empresa', $usuario->idsCondominios())
                ->with(['casa:id,identificador', 'registro:id_usuario,nombre_usuario', 'solicitud:id,folio'])
                ->latest()->limit(100)->get(['id', 'id_casa', 'id_solicitud', 'titulo', 'id_usuario_registro', 'created_at', 'updated_at']),
            'esSuperusuario' => $usuario->esSuperUsuario(),
        ]);
    }

    public function guardarPlantilla(Request $request, ?PlantillaDocumento $plantilla = null)
    {
        abort_unless(Gate::allows('atender-servicios'), 403);
        $usuario = auth()->user();
        if ($plantilla?->exists) {
            $this->autorizarPlantilla($plantilla, true);
        }

        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:150'],
            'tipo' => ['required', Rule::in(array_keys(PlantillaDocumento::TIPOS))],
            'descripcion' => ['nullable', 'string', 'max:1000'],
            'contenido' => ['required', 'string', 'max:100000'],
            'id_empresa' => ['nullable', 'integer'],
            'activo' => ['boolean'],
        ], ['contenido.required' => 'La plantilla no tiene texto.']);

        // Sólo el superusuario crea plantillas generales (para todos los condominios).
        if (empty($data['id_empresa'])) {
            abort_unless($usuario->esSuperUsuario(), 403, 'Elige el condominio de la plantilla.');
            $data['id_empresa'] = null;
        } else {
            abort_unless($usuario->puedeVerCondominio($data['id_empresa']), 403);
        }

        $data['id_usuario_modifico'] = auth()->id();
        if ($plantilla?->exists) {
            $plantilla->update($data);
        } else {
            PlantillaDocumento::create($data + ['id_usuario_registro' => auth()->id()]);
        }

        return back()->with('success', 'Plantilla guardada.');
    }

    /** Vista previa (HTML) del texto con formato, sin datos de casa. */
    public function previsualizar(Request $request)
    {
        abort_unless(Gate::allows('atender-servicios'), 403);
        $request->validate(['contenido' => ['nullable', 'string', 'max:100000']]);

        return response()->json(['html' => $this->generador->aHtml((string) $request->input('contenido'))]);
    }

    // ======================================================
    // 🧾 DOCUMENTOS GENERADOS
    // ======================================================

    /** Campos libres que pide la plantilla y valores de las variables para esa casa. */
    public function preparar(Request $request, PlantillaDocumento $plantilla)
    {
        $this->autorizarPlantilla($plantilla);
        $casa = Casa::findOrFail($request->integer('id_casa'));
        abort_unless(auth()->user()->puedeVerCondominio($casa->id_empresa), 403);

        return response()->json([
            'campos' => $this->generador->camposLibres($plantilla->contenido),
        ]);
    }

    public function generar(Request $request, PlantillaDocumento $plantilla)
    {
        $this->autorizarPlantilla($plantilla);

        $data = $request->validate([
            'id_casa' => ['required', 'integer', 'exists:casas,id'],
            'id_solicitud' => ['nullable', 'integer', 'exists:solicitudes_servicio,id'],
            'titulo' => ['nullable', 'string', 'max:200'],
            'campos' => ['nullable', 'array'],
            'campos.*' => ['nullable', 'string', 'max:1000'],
        ]);

        $casa = Casa::findOrFail($data['id_casa']);
        abort_unless(auth()->user()->puedeVerCondominio($casa->id_empresa), 403);
        abort_if($plantilla->id_empresa && $plantilla->id_empresa !== $casa->id_empresa, 422, 'La plantilla es de otro condominio.');

        $solicitud = null;
        if (!empty($data['id_solicitud'])) {
            $solicitud = SolicitudServicio::findOrFail($data['id_solicitud']);
            abort_unless($solicitud->id_casa === $casa->id, 422, 'La solicitud es de otra casa.');
        }

        $contenido = $this->generador->llenar($plantilla->contenido, $this->generador->valores($casa, $solicitud), $data['campos'] ?? []);

        $documento = DocumentoGenerado::create([
            'id_empresa' => $casa->id_empresa,
            'id_casa' => $casa->id,
            'id_plantilla' => $plantilla->id,
            'id_solicitud' => $solicitud?->id,
            'titulo' => ($data['titulo'] ?? null) ?: "{$plantilla->nombre} · {$casa->identificador}",
            'contenido' => $contenido,
            'id_usuario_registro' => auth()->id(),
            'id_usuario_modifico' => auth()->id(),
        ]);

        return response()->json(['id' => $documento->id, 'url' => route('documentos.show', $documento->id)]);
    }

    public function show(DocumentoGenerado $documento)
    {
        $this->autorizarDocumento($documento);
        $documento->load(['casa:id,identificador', 'solicitud:id,folio', 'plantilla:id,nombre', 'registro:id_usuario,nombre_usuario', 'modifico:id_usuario,nombre_usuario']);

        return Inertia::render('Documentos/Show', [
            'documento' => $documento,
            'html' => $this->generador->aHtml($documento->contenido),
            'puedeEditar' => Gate::allows('atender-servicios'),
            'faltantes' => preg_match_all('/\[\[.+?\]\]/u', $documento->contenido),
        ]);
    }

    /** La administración ajusta el texto final (p. ej. cláusulas especiales). */
    public function actualizar(Request $request, DocumentoGenerado $documento)
    {
        abort_unless(Gate::allows('atender-servicios'), 403);
        $this->autorizarDocumento($documento);

        $data = $request->validate([
            'titulo' => ['required', 'string', 'max:200'],
            'contenido' => ['required', 'string', 'max:100000'],
        ]);
        $documento->update($data + ['id_usuario_modifico' => auth()->id()]);

        return back()->with('success', 'Documento actualizado.');
    }

    public function pdf(DocumentoGenerado $documento)
    {
        $this->autorizarDocumento($documento);

        $pdf = Pdf::loadView('exports.documento_condominio', [
            'titulo' => $documento->titulo,
            'html' => $this->generador->aHtml($documento->contenido),
        ])->setPaper('letter');

        return $pdf->stream(\Illuminate\Support\Str::slug($documento->titulo) . '.pdf');
    }

    // ======================================================
    // 🔒 HELPERS
    // ======================================================

    private function autorizarPlantilla(PlantillaDocumento $plantilla, bool $editar = false): void
    {
        abort_unless(Gate::allows('atender-servicios'), 403);
        $usuario = auth()->user();
        if ($plantilla->id_empresa === null) {
            // Las generales las usa cualquier administración; sólo el superusuario las edita.
            abort_if($editar && !$usuario->esSuperUsuario(), 403, 'Sólo el superusuario edita las plantillas generales.');
            return;
        }
        abort_unless($usuario->puedeVerCondominio($plantilla->id_empresa), 403);
    }

    /** El documento lo ve la administración del condominio y la casa a la que pertenece. */
    private function autorizarDocumento(DocumentoGenerado $documento): void
    {
        $usuario = auth()->user();
        $condomino = $usuario->esCondomino() ? $usuario->condomino : null;
        if ($condomino && $condomino->activo && $condomino->id_casa === $documento->id_casa) {
            return;
        }
        abort_unless(Gate::allows('atender-servicios') && $usuario->puedeVerCondominio($documento->id_empresa), 403);
    }
}
