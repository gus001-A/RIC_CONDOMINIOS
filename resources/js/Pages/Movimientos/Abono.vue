<template>
    <AppLayout :title="tituloPagina">
        <!-- Los flash messages se muestran de forma global (AppLayout useFlashMessages) -->

        <template #header>
            <div class="header-wrapper">
                <div class="header-left">
                    <Link :href="route('movimientos.index', { vista: 'diferidas' })" class="btn-back" title="Volver a diferidas">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                        </svg>
                    </Link>
                    <div class="header-content">
                        <h2 class="header-title">{{ tituloPagina }}</h2>
                        <p class="header-subtitle">{{ esIngreso ? 'Registrar un cobro' : 'Registrar un pago' }} de la póliza diferida</p>
                    </div>
                </div>
                <span class="tipo-badge" :class="esIngreso ? 'tipo-ingreso' : 'tipo-egreso'">
                    {{ movimiento.tipo_poliza }}
                </span>
            </div>
        </template>

        <div class="page-content">
            <div class="container-custom">
                <!-- AVISOS QUE BLOQUEAN EL ABONO -->
                <div v-if="sin_fondeadoras" class="aviso aviso-error">
                    <i class="pi pi-lock"></i>
                    <span>
                        No tienes <strong>cuentas fondeadoras asignadas</strong> en esta empresa, por lo que no puedes registrar abonos.
                        Pídele a un superusuario que te las asigne.
                    </span>
                </div>
                <div v-else-if="esEgresoConFondeadora && !saldoFondeadoraAlcanza" class="aviso aviso-warning">
                    <i class="pi pi-exclamation-triangle"></i>
                    <span>
                        <strong>Saldo insuficiente</strong> en la cuenta fondeadora: hay
                        <strong>${{ formatNumber(movimiento.saldo_fondeadora) }}</strong> y este abono es de
                        <strong>${{ formatNumber(totalAbonoFinal) }}</strong>. Agrega fondos o abona un monto menor.
                    </span>
                </div>

                <div class="abono-layout">
                    <!-- ===================== COLUMNA IZQUIERDA ===================== -->
                    <div class="col-main">
                        <!-- DATOS DE LA PÓLIZA -->
                        <section class="card">
                            <div class="card-head">
                                <div>
                                    <span class="card-kicker">Póliza diferida</span>
                                    <h3 class="card-title">{{ movimiento.referencia || 'S/N' }} · {{ movimiento.persona || 'N/A' }}</h3>
                                </div>
                                <span v-if="movimiento.fecha_vencimiento" class="venc-chip" :class="diasRestantesClass">
                                    <i class="pi pi-calendar"></i>
                                    Vence {{ formatFechaYmd(movimiento.fecha_vencimiento) }}
                                    <strong>· {{ textoDiasRestantes }}</strong>
                                </span>
                            </div>

                            <dl class="datos-grid">
                                <div class="dato">
                                    <dt>Cuenta</dt>
                                    <dd>{{ movimiento.cuenta || 'N/A' }}</dd>
                                </div>
                                <div class="dato">
                                    <dt>Cuenta fondeadora</dt>
                                    <dd>
                                        {{ movimiento.cuenta_fondeadora || 'N/A' }}
                                        <span v-if="esEgresoConFondeadora" class="dato-sub" :class="movimiento.saldo_suficiente ? 'ok' : 'mal'">
                                            Saldo: ${{ formatNumber(movimiento.saldo_fondeadora) }}
                                        </span>
                                    </dd>
                                </div>
                                <div class="dato">
                                    <dt>Marcador</dt>
                                    <dd>{{ movimiento.marcador || 'Sin marcador' }}</dd>
                                </div>
                                <div class="dato">
                                    <dt>Estatus</dt>
                                    <dd>{{ movimiento.estatus_texto || movimiento.estatus }}</dd>
                                </div>
                                <div class="dato dato-full" v-if="movimiento.nota && movimiento.nota !== 'Sin nota'">
                                    <dt>Nota</dt>
                                    <dd>{{ movimiento.nota }}</dd>
                                </div>
                            </dl>

                            <!-- Avance del pago -->
                            <div class="avance">
                                <div class="avance-top">
                                    <span>{{ esIngreso ? 'Cobrado' : 'Pagado' }}: <strong>${{ formatNumber(movimiento.total_abonado) }}</strong> de ${{ formatNumber(movimiento.monto_abs) }}</span>
                                    <span class="avance-pct">{{ porcentajePagado }}%</span>
                                </div>
                                <div class="avance-bar">
                                    <div class="avance-fill" :style="{ width: porcentajePagado + '%' }"></div>
                                    <div class="avance-fill avance-fill-nuevo" :style="{ left: porcentajePagado + '%', width: porcentajeEsteAbono + '%' }"></div>
                                </div>
                                <span class="avance-hint" v-if="totalAbonoFinal > 0">
                                    Con este abono quedará en {{ Math.min(100, porcentajePagado + porcentajeEsteAbono) }}%
                                </span>
                            </div>
                        </section>

                        <!-- MONTO DEL ABONO -->
                        <section class="card">
                            <div class="card-head">
                                <div>
                                    <span class="card-kicker">Paso 1</span>
                                    <h3 class="card-title">¿Cuánto vas a {{ esIngreso ? 'cobrar' : 'pagar' }}?</h3>
                                </div>
                                <span class="fecha-pago">
                                    <i class="pi pi-clock"></i>
                                    Fecha de {{ esIngreso ? 'cobro' : 'pago' }}: <strong>{{ fechaHoyTexto }}</strong>
                                </span>
                            </div>

                            <!-- Atajos -->
                            <div class="atajos">
                                <button type="button" class="atajo atajo-liquidar" :class="{ activo: esLiquidacionTotal }" @click="liquidarTodo" :disabled="bloqueado">
                                    <i class="pi pi-check-circle"></i>
                                    Liquidar todo · ${{ formatNumber(saldoPendiente) }}
                                </button>
                                <button type="button" class="atajo" @click="abonarPorcentaje(50)" :disabled="bloqueado">50%</button>
                                <button type="button" class="atajo" @click="abonarPorcentaje(25)" :disabled="bloqueado">25%</button>
                                <button type="button" class="atajo atajo-limpiar" @click="limpiarMontos" :disabled="bloqueado || totalAbonoFinal <= 0">
                                    <i class="pi pi-times"></i> Limpiar
                                </button>
                            </div>

                            <!-- SIN IVA: un solo monto -->
                            <div v-if="!tieneIva" class="monto-unico">
                                <label class="form-label">Monto del abono <span class="required-star">*</span></label>
                                <div class="input-money" :class="{ error: excedeSaldo }">
                                    <span class="input-prefix">$</span>
                                    <MoneyInput
                                        v-model="abonoForm.monto_abonado"
                                        :disabled="bloqueado"
                                        placeholder="0.00"
                                    />
                                </div>
                                <span class="hint-text" :class="{ 'hint-error': excedeSaldo }">
                                    {{ excedeSaldo ? 'El monto no puede ser mayor al saldo pendiente' : `Máximo: $${formatNumber(saldoPendiente)}` }}
                                </span>
                            </div>

                            <!-- CON IVA: monto por tipo de IVA -->
                            <div v-else class="iva-lista">
                                <div v-for="ivaId in ivasSeleccionados" :key="ivaId" class="iva-fila">
                                    <span class="iva-badge" :class="getIvaPorcentaje(ivaId) === 0 ? 'badge-cero' : 'badge-dieciseis'">
                                        IVA {{ getIvaPorcentaje(ivaId) }}%
                                    </span>
                                    <div class="input-money" :class="{ error: (abonoForm.ivas[ivaId]?.monto || 0) > obtenerMontoDisponibleIva(ivaId) + 0.001 }">
                                        <span class="input-prefix">$</span>
                                        <MoneyInput
                                            v-model="abonoForm.ivas[ivaId].monto"
                                            :disabled="bloqueado"
                                            placeholder="0.00"
                                        />
                                        <button type="button" class="btn-max" @click="llenarIva(ivaId)" :disabled="bloqueado" title="Usar el máximo disponible">Máx</button>
                                    </div>
                                    <div class="iva-info">
                                        <span>Disponible: <strong>${{ formatNumber(obtenerMontoDisponibleIva(ivaId)) }}</strong></span>
                                        <span>IVA incluido: ${{ formatNumber(calcularIvaMontoAbono(ivaId)) }}</span>
                                    </div>
                                </div>
                                <span v-if="excedeSaldo" class="hint-text hint-error">La suma de los montos excede el saldo pendiente (${{ formatNumber(saldoPendiente) }})</span>
                            </div>
                        </section>

                        <!-- OBSERVACIONES -->
                        <section class="card">
                            <div class="card-head">
                                <div>
                                    <span class="card-kicker">Paso 2 · opcional</span>
                                    <h3 class="card-title">Observaciones</h3>
                                </div>
                            </div>
                            <textarea
                                v-model="abonoForm.nota"
                                rows="2"
                                class="form-textarea"
                                :disabled="bloqueado"
                                placeholder="Ej. transferencia, número de recibo, comentarios..."
                            ></textarea>
                        </section>

                    </div>

                    <!-- ===================== COLUMNA DERECHA (RESUMEN) ===================== -->
                    <aside class="col-side">
                        <section class="card resumen">
                            <span class="card-kicker">Resumen</span>
                            <div class="resumen-fila">
                                <span>Total de la póliza</span>
                                <span>${{ formatNumber(movimiento.monto_abs) }}</span>
                            </div>
                            <div class="resumen-fila">
                                <span>{{ esIngreso ? 'Cobrado' : 'Pagado' }} antes</span>
                                <span class="txt-verde">− ${{ formatNumber(movimiento.total_abonado) }}</span>
                            </div>
                            <div class="resumen-fila resumen-fila-fuerte">
                                <span>Saldo pendiente</span>
                                <span>${{ formatNumber(saldoPendiente) }}</span>
                            </div>
                            <div class="resumen-fila">
                                <span>Este abono</span>
                                <span class="txt-azul">− ${{ formatNumber(totalAbonoFinal) }}</span>
                            </div>
                            <div v-if="tieneIva && totalAbonoFinal > 0" class="resumen-desglose">
                                <span>Base ${{ formatNumber(totalBaseAbonoCalculado) }}</span>
                                <span>IVA ${{ formatNumber(totalIvaAbonoCalculado) }}</span>
                            </div>

                            <div class="resumen-total" :class="{ liquida: esLiquidacionTotal }">
                                <span>{{ esLiquidacionTotal ? '¡Se liquida la póliza!' : 'Nuevo saldo' }}</span>
                                <strong>${{ formatNumber(nuevoSaldoPendiente) }}</strong>
                            </div>

                            <button
                                type="button"
                                class="btn-submit"
                                :class="{ 'btn-submit-liquidar': esLiquidacionTotal }"
                                :disabled="processing || !puedeEnviar"
                                @click="submitAbono"
                            >
                                <i class="pi" :class="processing ? 'pi-spin pi-spinner' : (esLiquidacionTotal ? 'pi-check-circle' : 'pi-plus')"></i>
                                {{ processing ? 'Registrando...' : (esLiquidacionTotal ? 'Liquidar póliza' : 'Registrar abono') }}
                            </button>
                            <span v-if="motivoNoEnviar && !processing" class="resumen-motivo">{{ motivoNoEnviar }}</span>

                            <Link :href="route('movimientos.index', { vista: 'diferidas' })" class="btn-cancel">
                                Cancelar
                            </Link>
                        </section>

                        <!-- HISTORIAL DE ABONOS -->
                        <section class="card historial-card" v-if="abonosPrevios.length">
                            <div class="card-head">
                                <div>
                                    <span class="card-kicker">Historial</span>
                                    <h3 class="card-title">Abonos anteriores ({{ abonosPrevios.length }})</h3>
                                </div>
                            </div>
                            <div class="historial">
                                <div v-for="(abono, i) in abonosPrevios" :key="abono.id" class="historial-item">
                                    <span class="historial-num">{{ i + 1 }}</span>
                                    <div class="historial-body">
                                        <div class="historial-top">
                                            <strong>${{ formatNumber(abono.monto_abonado) }}</strong>
                                            <span class="historial-fecha">{{ abono.fecha_abono || '—' }}</span>
                                        </div>
                                        <div class="historial-meta">
                                            <span v-if="abono.usuario"><i class="pi pi-user"></i> {{ abono.usuario }}</span>
                                            <span v-if="abono.registrado"><i class="pi pi-clock"></i> {{ abono.registrado }}</span>
                                            <span v-if="abono.referencia"><i class="pi pi-hashtag"></i> {{ abono.referencia }}</span>
                                        </div>
                                        <p v-if="abono.nota" class="historial-nota">{{ abono.nota }}</p>
                                    </div>
                                </div>
                            </div>
                        </section>
                    </aside>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useNotify } from '@/composables/useNotify';
import AppLayout from '@/Layouts/AppLayout.vue';
import MoneyInput from '@/Components/MoneyInput.vue';
import { Link, useForm } from '@inertiajs/vue3';

const notify = useNotify();

// ============================================
// PROPS
// ============================================
const props = defineProps({
    movimiento: {
        type: Object,
        required: true
    },
    tipos_iva: {
        type: Array,
        default: () => []
    },
    // true cuando el usuario no tiene cuentas fondeadoras asignadas en la empresa
    sin_fondeadoras: {
        type: Boolean,
        default: false
    }
});

const processing = ref(false);
const tiposIva = ref(props.tipos_iva || []);
const ivasSeleccionados = ref([]);

// Todo el dinero se compara en CENTAVOS para evitar errores de punto flotante
// (p. ej. un saldo de 333.32999999 no dejaba abonar 333.33 y la póliza nunca
// se podía liquidar).
const centavos = (v) => Math.round((Number(v) || 0) * 100);
const redondear = (v) => centavos(v) / 100;

// Fecha de HOY en México (toISOString da UTC: después de las 6 p.m. ya era "mañana").
const hoyMexico = () => new Date().toLocaleDateString('en-CA', { timeZone: 'America/Mexico_City' });

// ============================================
// FORMULARIO
// ============================================
const abonoForm = useForm({
    id_poliza: props.movimiento.id_poliza,
    monto_abonado: 0,
    fecha_abono: hoyMexico(),
    referencia: '',
    nota: '',
    id_cuenta_fondeadora: props.movimiento.cuenta_fondeadora_id || '',
    ivas: {}
});

// ============================================
// COMPUTED - DATOS BÁSICOS
// ============================================
const esIngreso = computed(() => props.movimiento.tipo_poliza === 'INGRESO');
const esEgresoConFondeadora = computed(() => props.movimiento.tipo_poliza === 'EGRESO' && !!props.movimiento.cuenta_fondeadora_id);
const saldoPendiente = computed(() => redondear(props.movimiento.saldo_pendiente));
const tieneIva = computed(() => (props.movimiento?.ivas_heredados || []).length > 0);
const abonosPrevios = computed(() => props.movimiento?.abonos || []);
const bloqueado = computed(() => props.sin_fondeadoras);

const tituloPagina = computed(() => `Abono - Póliza ${props.movimiento.referencia || 'S/N'}`);

const fechaHoyTexto = computed(() => new Date().toLocaleDateString('es-MX', {
    timeZone: 'America/Mexico_City', day: '2-digit', month: '2-digit', year: 'numeric'
}));

// ============================================
// IVAS
// ============================================
// Monto disponible por IVA: lo original menos lo ya abonado a ese IVA, y nunca
// más que el saldo pendiente de la póliza.
const ivasDisponibles = computed(() => {
    const ivasHeredados = props.movimiento?.ivas_heredados || [];

    return ivasHeredados.map(iva => {
        let totalAbonadoIva = 0;
        abonosPrevios.value.forEach(abono => {
            (Array.isArray(abono.ivas) ? abono.ivas : []).forEach(ivaAbono => {
                if (ivaAbono.id === iva.id) totalAbonadoIva += parseFloat(ivaAbono.monto) || 0;
            });
        });

        const disponible = Math.max(0, (parseFloat(iva.monto) || 0) - totalAbonadoIva);
        return {
            id: iva.id,
            porcentaje: iva.porcentaje,
            monto_disponible: redondear(Math.min(disponible, saldoPendiente.value)),
        };
    });
});

const obtenerMontoDisponibleIva = (ivaId) => ivasDisponibles.value.find(i => i.id === ivaId)?.monto_disponible || 0;

const getIvaPorcentaje = (ivaId) => {
    const iva = tiposIva.value.find(i => i.id === ivaId)
        || (props.movimiento?.ivas_heredados || []).find(i => i.id === ivaId);
    return iva ? Number(iva.porcentaje) : 0;
};

const calcularIvaMontoAbono = (ivaId) => {
    const montoConIva = abonoForm.ivas[ivaId]?.monto || 0;
    const porcentaje = getIvaPorcentaje(ivaId);
    if (!montoConIva || porcentaje <= 0) return 0;
    return redondear(montoConIva - montoConIva / (1 + porcentaje / 100));
};

const totalConIvaAbonoCalculado = computed(() =>
    redondear(ivasSeleccionados.value.reduce((t, id) => t + (abonoForm.ivas[id]?.monto || 0), 0))
);

const totalIvaAbonoCalculado = computed(() =>
    redondear(ivasSeleccionados.value.reduce((t, id) => t + calcularIvaMontoAbono(id), 0))
);

const totalBaseAbonoCalculado = computed(() => redondear(totalConIvaAbonoCalculado.value - totalIvaAbonoCalculado.value));

// ============================================
// TOTALES
// ============================================
const totalAbonoFinal = computed(() =>
    tieneIva.value ? totalConIvaAbonoCalculado.value : redondear(abonoForm.monto_abonado)
);

const nuevoSaldoPendiente = computed(() => Math.max(0, redondear(saldoPendiente.value - totalAbonoFinal.value)));

const esLiquidacionTotal = computed(() =>
    totalAbonoFinal.value > 0 && centavos(totalAbonoFinal.value) === centavos(saldoPendiente.value)
);

const excedeSaldo = computed(() => centavos(totalAbonoFinal.value) > centavos(saldoPendiente.value));

const saldoFondeadoraAlcanza = computed(() => {
    if (!esEgresoConFondeadora.value || totalAbonoFinal.value <= 0) return true;
    return centavos(totalAbonoFinal.value) <= centavos(props.movimiento.saldo_fondeadora);
});

const porcentajePagado = computed(() => {
    const total = Number(props.movimiento.monto_abs) || 0;
    return total > 0 ? Math.min(100, Math.round((Number(props.movimiento.total_abonado) || 0) / total * 100)) : 0;
});

const porcentajeEsteAbono = computed(() => {
    const total = Number(props.movimiento.monto_abs) || 0;
    if (total <= 0) return 0;
    return Math.min(100 - porcentajePagado.value, Math.round(totalAbonoFinal.value / total * 100));
});

// Motivo por el que no se puede enviar (se muestra bajo el botón).
const motivoNoEnviar = computed(() => {
    if (props.sin_fondeadoras) return 'Sin cuentas fondeadoras asignadas.';
    if (!abonoForm.id_cuenta_fondeadora) return 'La póliza no tiene cuenta fondeadora.';
    if (totalAbonoFinal.value <= 0) return 'Captura el monto del abono.';
    if (excedeSaldo.value) return 'El abono excede el saldo pendiente.';
    if (tieneIva.value && ivasSeleccionados.value.some(id => (abonoForm.ivas[id]?.monto || 0) > obtenerMontoDisponibleIva(id) + 0.001)) {
        return 'Un monto de IVA excede lo disponible.';
    }
    if (!saldoFondeadoraAlcanza.value) return 'Saldo insuficiente en la cuenta fondeadora.';
    return '';
});

const puedeEnviar = computed(() => !motivoNoEnviar.value);

// ============================================
// VENCIMIENTO
// ============================================
// 'YYYY-MM-DD' se interpreta a mano: new Date('YYYY-MM-DD') es medianoche UTC
// y en México se recorría un día.
const formatFechaYmd = (ymd) => {
    if (!ymd) return '—';
    const [y, m, d] = String(ymd).slice(0, 10).split('-');
    return `${d}/${m}/${y}`;
};

const diasRestantes = computed(() => {
    if (!props.movimiento.fecha_vencimiento) return null;
    const [y, m, d] = String(props.movimiento.fecha_vencimiento).slice(0, 10).split('-').map(Number);
    const [hy, hm, hd] = hoyMexico().split('-').map(Number);
    return Math.round((Date.UTC(y, m - 1, d) - Date.UTC(hy, hm - 1, hd)) / 86400000);
});

const textoDiasRestantes = computed(() => {
    const d = diasRestantes.value;
    if (d === null) return '';
    if (d < 0) return `vencida hace ${Math.abs(d)} día${Math.abs(d) === 1 ? '' : 's'}`;
    if (d === 0) return 'vence hoy';
    return `faltan ${d} día${d === 1 ? '' : 's'}`;
});

const diasRestantesClass = computed(() => {
    const d = diasRestantes.value;
    if (d === null) return '';
    if (d < 0) return 'vencido';
    if (d <= 3) return 'pronto';
    return '';
});

// ============================================
// ATAJOS
// ============================================
// Reparte un monto entre los IVAs (en orden) sin pasar lo disponible de cada uno.
const repartirEnIvas = (monto) => {
    let restante = centavos(monto);
    ivasSeleccionados.value.forEach(id => {
        const asignar = Math.min(restante, centavos(obtenerMontoDisponibleIva(id)));
        abonoForm.ivas[id].monto = asignar / 100;
        restante -= asignar;
    });
};

const asignarMonto = (monto) => {
    if (tieneIva.value) {
        repartirEnIvas(monto);
    } else {
        abonoForm.monto_abonado = redondear(monto);
    }
};

const liquidarTodo = () => asignarMonto(saldoPendiente.value);
const abonarPorcentaje = (pct) => asignarMonto(saldoPendiente.value * pct / 100);
const limpiarMontos = () => asignarMonto(0);

const llenarIva = (ivaId) => {
    const otros = ivasSeleccionados.value
        .filter(id => id !== ivaId)
        .reduce((t, id) => t + centavos(abonoForm.ivas[id]?.monto), 0);
    const maximo = Math.max(0, Math.min(centavos(obtenerMontoDisponibleIva(ivaId)), centavos(saldoPendiente.value) - otros));
    abonoForm.ivas[ivaId].monto = maximo / 100;
};

// ============================================
// UTILIDADES
// ============================================
const formatNumber = (value) => {
    if (!value && value !== 0) return '0.00';
    return Number(value).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

// ============================================
// SUBMIT
// ============================================
const submitAbono = () => {
    if (!puedeEnviar.value) {
        notify.error(motivoNoEnviar.value, 'No se puede registrar');
        return;
    }

    processing.value = true;
    abonoForm.monto_abonado = totalAbonoFinal.value;
    abonoForm.fecha_abono = hoyMexico();

    abonoForm
        .transform((data) => ({
            ...data,
            monto_abonado: totalAbonoFinal.value,
            ivas: ivasSeleccionados.value.map(id => ({ id, monto: abonoForm.ivas[id]?.monto || 0 })),
            total_base: tieneIva.value ? totalBaseAbonoCalculado.value : totalAbonoFinal.value,
            total_iva: tieneIva.value ? totalIvaAbonoCalculado.value : 0,
            total_con_iva: totalAbonoFinal.value,
        }))
        // El servidor redirige a Diferidas al terminar y el aviso de éxito lo
        // muestra el manejador global de flash.
        .post(route('movimientos.abono.store'), {
            preserveScroll: true,
            onError: (errors) => {
                notify.error(Object.values(errors)[0] || 'Error al registrar el abono.', 'Error');
            },
            onFinish: () => {
                processing.value = false;
            },
        });
};

// ============================================
// MOUNTED
// ============================================
onMounted(() => {
    const ivasHeredados = props.movimiento?.ivas_heredados || [];

    if (ivasHeredados.length > 0) {
        abonoForm.ivas = {};
        ivasSeleccionados.value = ivasHeredados.map(iva => {
            abonoForm.ivas[iva.id] = { monto: 0 };
            return iva.id;
        });
    }

    // Por defecto se propone liquidar el saldo completo.
    if (!props.sin_fondeadoras) liquidarTodo();
});
</script>

<style scoped>
/* --- HEADER --- */
.header-wrapper {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    width: 100%;
    padding: 4px 0;
}

.header-left { display: flex; align-items: center; gap: 12px; min-width: 0; }

.btn-back {
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    width: 38px;
    height: 38px;
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.9);
    border: 1px solid #e2e8f0;
    color: #6b7280;
    transition: all 0.2s ease;
}

.btn-back:hover { background: white; color: #1f2937; transform: translateX(-2px); }

.header-content { display: flex; flex-direction: column; min-width: 0; }
.header-title { font-size: 1.2rem; font-weight: 700; color: #0f172a; margin: 0; line-height: 1.3; }
.header-subtitle { font-size: 0.8rem; color: #64748b; margin: 0; }

.tipo-badge {
    padding: 4px 14px;
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.04em;
}
.tipo-ingreso { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }
.tipo-egreso { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }

/* --- PAGE --- */
.page-content { padding: 0.5rem 0 2rem; }
.container-custom { max-width: 80rem; margin: 0 auto; padding: 0 1.5rem; }

/* --- AVISOS --- */
.aviso {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 16px;
    border-radius: 12px;
    font-size: 0.85rem;
    margin-bottom: 14px;
}
.aviso i { font-size: 1.1rem; }
.aviso-error { background: #fef2f2; border: 1px solid #fca5a5; color: #991b1b; box-shadow: inset 4px 0 0 #dc2626; }
.aviso-warning { background: #fff7ed; border: 1px solid #fdba74; color: #9a3412; box-shadow: inset 4px 0 0 #ea580c; }

/* --- LAYOUT --- */
.abono-layout {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 340px;
    gap: 16px;
    align-items: start;
}

.col-main { display: flex; flex-direction: column; gap: 16px; min-width: 0; }
.col-side { display: flex; flex-direction: column; gap: 16px; position: sticky; top: 16px; }

/* --- CARD --- */
.card {
    background: #fff;
    border: 1px solid #eef2f7;
    border-radius: 16px;
    box-shadow: 0 2px 14px rgba(15, 23, 42, 0.05);
    padding: 18px 20px;
}

.card-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 14px;
}

.card-kicker {
    display: block;
    font-size: 0.68rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #94a3b8;
}

.card-title { margin: 2px 0 0; font-size: 1rem; font-weight: 700; color: #0f172a; }

/* --- DATOS --- */
.datos-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 12px 16px;
    margin: 0 0 16px;
}
.dato dt { font-size: 0.7rem; font-weight: 600; color: #64748b; margin-bottom: 2px; }
.dato dd { margin: 0; font-size: 0.85rem; font-weight: 600; color: #1e293b; word-break: break-word; }
.dato-full { grid-column: 1 / -1; }
.dato-sub { display: block; font-size: 0.72rem; font-weight: 600; margin-top: 2px; }
.dato-sub.ok { color: #047857; }
.dato-sub.mal { color: #dc2626; }

.venc-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 12px;
    border-radius: 999px;
    font-size: 0.75rem;
    background: #f1f5f9;
    color: #334155;
}
.venc-chip.pronto { background: #fef3c7; color: #92400e; }
.venc-chip.vencido { background: #fee2e2; color: #991b1b; }

/* --- AVANCE --- */
.avance { border-top: 1px dashed #e2e8f0; padding-top: 14px; }
.avance-top { display: flex; justify-content: space-between; font-size: 0.8rem; color: #475569; margin-bottom: 6px; }
.avance-pct { font-weight: 700; color: #0f172a; }
.avance-bar { position: relative; height: 10px; border-radius: 999px; background: #e2e8f0; overflow: hidden; }
.avance-fill { position: absolute; top: 0; left: 0; height: 100%; background: linear-gradient(90deg, #10b981, #059669); transition: all 0.3s ease; }
.avance-fill-nuevo { background: repeating-linear-gradient(45deg, #60a5fa, #60a5fa 6px, #3b82f6 6px, #3b82f6 12px); }
.avance-hint { display: block; font-size: 0.72rem; color: #2563eb; margin-top: 6px; }

/* --- MONTO --- */
.fecha-pago {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 0.78rem;
    color: #475569;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    padding: 5px 12px;
    border-radius: 999px;
}

.atajos { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 16px; }

.atajo {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    height: 36px;
    padding: 0 14px;
    border-radius: 10px;
    border: 1px solid #cbd5e1;
    background: #fff;
    color: #334155;
    font-size: 0.8rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
}
.atajo:hover:not(:disabled) { border-color: #1a3a5c; color: #1a3a5c; }
.atajo:disabled { opacity: 0.5; cursor: not-allowed; }
.atajo-liquidar { border-color: #6ee7b7; color: #047857; background: #ecfdf5; }
.atajo-liquidar.activo { background: linear-gradient(135deg, #10b981, #059669); color: #fff; border-color: transparent; }
.atajo-limpiar { color: #64748b; }

.form-label { display: block; font-size: 0.78rem; font-weight: 600; color: #334155; margin-bottom: 6px; }
.required-star { color: #dc2626; }

.input-money {
    display: flex;
    align-items: center;
    height: 46px;
    border: 2px solid #e5e7eb;
    border-radius: 10px;
    background: #fff;
    overflow: hidden;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}
.input-money:focus-within { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12); }
.input-money.error { border-color: #dc2626; }
.input-prefix { padding: 0 4px 0 14px; font-weight: 700; color: #64748b; }
.input-money input {
    flex: 1;
    min-width: 0;
    height: 100%;
    border: none;
    outline: none;
    box-shadow: none;
    font-size: 1.1rem;
    font-weight: 700;
    color: #0f172a;
    background: transparent;
    padding: 0 10px 0 4px;
}
.input-money input:focus { box-shadow: none; }

.btn-max {
    height: 100%;
    padding: 0 14px;
    border: none;
    border-left: 1px solid #e5e7eb;
    background: #f8fafc;
    color: #1a3a5c;
    font-size: 0.75rem;
    font-weight: 700;
    cursor: pointer;
}
.btn-max:hover:not(:disabled) { background: #e2e8f0; }

.hint-text { display: block; font-size: 0.75rem; color: #64748b; margin-top: 6px; }
.hint-error { color: #dc2626; font-weight: 600; }

.monto-unico { max-width: 360px; }

.iva-lista { display: flex; flex-direction: column; gap: 12px; }
.iva-fila {
    display: grid;
    grid-template-columns: 90px minmax(0, 260px) 1fr;
    align-items: center;
    gap: 12px;
    padding: 10px 12px;
    border: 1px solid #eef2f7;
    border-radius: 12px;
    background: #fafbfc;
}
.iva-badge { justify-self: start; padding: 4px 10px; border-radius: 8px; font-size: 0.75rem; font-weight: 700; }
.badge-cero { background: #f1f5f9; color: #475569; }
.badge-dieciseis { background: #dbeafe; color: #1e40af; }
.iva-info { display: flex; flex-direction: column; gap: 2px; font-size: 0.75rem; color: #64748b; }
.iva-info strong { color: #0f172a; }

.form-textarea {
    width: 100%;
    border: 2px solid #e5e7eb;
    border-radius: 10px;
    padding: 10px 12px;
    font-size: 0.85rem;
    resize: vertical;
    outline: none;
}
.form-textarea:focus { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12); }

/* --- HISTORIAL --- */
.historial { display: flex; flex-direction: column; gap: 10px; max-height: 360px; overflow-y: auto; }
.historial-item { display: flex; gap: 12px; padding: 10px 12px; border-radius: 12px; background: #f8fafc; }
.historial-num {
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: #d1fae5;
    color: #065f46;
    font-size: 0.75rem;
    font-weight: 700;
}
.historial-body { flex: 1; min-width: 0; }
.historial-top { display: flex; justify-content: space-between; gap: 8px; font-size: 0.9rem; color: #0f172a; }
.historial-fecha { font-size: 0.78rem; color: #64748b; }
.historial-meta { display: flex; flex-wrap: wrap; gap: 4px 14px; font-size: 0.72rem; color: #64748b; margin-top: 2px; }
.historial-meta i { font-size: 0.7rem; margin-right: 2px; }
.historial-nota { margin: 4px 0 0; font-size: 0.78rem; color: #334155; }

/* --- RESUMEN --- */
.resumen { display: flex; flex-direction: column; gap: 10px; }
.resumen-fila { display: flex; justify-content: space-between; font-size: 0.85rem; color: #475569; }
.resumen-fila span:last-child { font-weight: 600; color: #0f172a; font-variant-numeric: tabular-nums; }
.resumen-fila-fuerte { padding-top: 8px; border-top: 1px solid #f1f5f9; font-weight: 700; color: #0f172a; }
.txt-verde { color: #047857 !important; }
.txt-azul { color: #2563eb !important; }
.resumen-desglose { display: flex; justify-content: flex-end; gap: 12px; font-size: 0.72rem; color: #94a3b8; margin-top: -6px; }

.resumen-total {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 14px;
    border-radius: 12px;
    background: #f1f5f9;
    color: #334155;
    font-size: 0.85rem;
    font-weight: 600;
}
.resumen-total strong { font-size: 1.3rem; color: #0f172a; font-variant-numeric: tabular-nums; }
.resumen-total.liquida { background: #ecfdf5; color: #047857; border: 1px solid #6ee7b7; }
.resumen-total.liquida strong { color: #047857; }

.btn-submit {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    height: 46px;
    border: none;
    border-radius: 12px;
    background: linear-gradient(135deg, #1a3a5c, #2c5282);
    color: #fff;
    font-size: 0.95rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s ease;
}
.btn-submit-liquidar { background: linear-gradient(135deg, #10b981, #059669); }
.btn-submit:hover:not(:disabled) { transform: translateY(-1px); box-shadow: 0 6px 16px rgba(15, 23, 42, 0.18); }
.btn-submit:disabled { opacity: 0.5; cursor: not-allowed; }

.resumen-motivo { font-size: 0.75rem; color: #dc2626; text-align: center; margin-top: -4px; }

.btn-cancel {
    display: block;
    text-align: center;
    padding: 8px;
    font-size: 0.85rem;
    font-weight: 600;
    color: #64748b;
    border-radius: 10px;
}
.btn-cancel:hover { background: #f1f5f9; color: #1a3a5c; }

/* --- RESPONSIVE --- */
@media (max-width: 1024px) {
    .abono-layout { grid-template-columns: 1fr; }
    .col-side { position: static; }
    .datos-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}

@media (max-width: 640px) {
    .container-custom { padding: 0 1rem; }
    .datos-grid { grid-template-columns: 1fr; }
    .iva-fila { grid-template-columns: 1fr; }
    .monto-unico { max-width: none; }
    .header-subtitle { display: none; }
}
</style>
