<script setup>
/** Diálogo de confirmación con el estilo de SweetAlert (icono animado, título, texto y botones grandes). */
import { computed } from 'vue';
import { dialogo, responder } from '@/composables/confirmar';

const COLOR = {
    warning: '#f0a202', danger: '#e5484d', success: '#1faf5c', question: '#1a5a96', info: '#1597a5',
};
const color = computed(() => COLOR[dialogo.tipo]);
</script>

<template>
    <a-modal :open="dialogo.open" :footer="null" :closable="false" centered width="min(480px, 94vw)" :mask-closable="true"
        wrap-class-name="vt-swal-wrap" @cancel="responder(false)">
        <div class="vt-swal" :style="{ '--c': color }">
            <div class="vt-swal-icon" :class="dialogo.tipo">
                <svg viewBox="0 0 80 80" aria-hidden="true">
                    <circle class="ring" cx="40" cy="40" r="36" />
                    <template v-if="dialogo.tipo === 'success'">
                        <path class="mark" d="M24 41 L35 52 L57 29" />
                    </template>
                    <template v-else-if="dialogo.tipo === 'danger'">
                        <path class="mark" d="M28 28 L52 52" />
                        <path class="mark m2" d="M52 28 L28 52" />
                    </template>
                    <template v-else-if="dialogo.tipo === 'question'">
                        <path class="mark" d="M31 31 C31 22, 49 22, 49 31 C49 38, 40 38, 40 45" />
                        <circle class="dot" cx="40" cy="55" r="2.6" />
                    </template>
                    <template v-else-if="dialogo.tipo === 'info'">
                        <circle class="dot" cx="40" cy="25" r="2.8" />
                        <path class="mark" d="M40 35 L40 56" />
                    </template>
                    <template v-else>
                        <path class="mark" d="M40 22 L40 46" />
                        <circle class="dot" cx="40" cy="56" r="2.8" />
                    </template>
                </svg>
            </div>
            <h2 class="vt-swal-title">{{ dialogo.titulo }}</h2>
            <p v-if="dialogo.texto" class="vt-swal-text">{{ dialogo.texto }}</p>
            <div class="vt-swal-btns">
                <button v-if="dialogo.cancelar !== false" type="button" class="vt-swal-btn cancel" @click="responder(false)">{{ dialogo.cancelar }}</button>
                <button type="button" class="vt-swal-btn ok" @click="responder(true)">{{ dialogo.confirmar }}</button>
            </div>
        </div>
    </a-modal>
</template>

<style>
.vt-swal-wrap .ant-modal-content {
    border-radius: 20px !important;
    padding: 34px 28px 26px !important;
}
.vt-swal-wrap .ant-modal {
    animation: vt-swal-pop 0.3s cubic-bezier(0.2, 1.4, 0.4, 1) both;
}
.vt-swal {
    text-align: center;
}
.vt-swal-icon {
    width: 92px;
    height: 92px;
    margin: 0 auto 14px;
}
.vt-swal-icon svg {
    width: 100%;
    height: 100%;
    fill: none;
    stroke: var(--c);
    stroke-width: 4;
    stroke-linecap: round;
    stroke-linejoin: round;
}
.vt-swal-icon .ring {
    stroke-opacity: 0.3;
    stroke-dasharray: 227;
    stroke-dashoffset: 227;
    animation: vt-swal-draw 0.5s ease-out forwards;
}
.vt-swal-icon .mark {
    stroke-dasharray: 60;
    stroke-dashoffset: 60;
    animation: vt-swal-draw 0.35s 0.35s ease-out forwards;
}
.vt-swal-icon .m2 {
    animation-delay: 0.5s;
}
.vt-swal-icon .dot {
    fill: var(--c);
    stroke: none;
    opacity: 0;
    animation: vt-swal-fade 0.2s 0.6s forwards;
}
.vt-swal-icon.warning svg,
.vt-swal-icon.question svg {
    animation: vt-swal-pulse 0.6s 0.7s ease-in-out;
}
.vt-swal-title {
    font-size: 24px;
    font-weight: 700;
    color: #3f4a5a;
    margin: 0 0 8px;
}
.vt-swal-text {
    color: #5d6b7c;
    font-size: 15px;
    line-height: 1.5;
    margin: 0 auto 4px;
    max-width: 380px;
}
.vt-swal-btns {
    display: flex;
    justify-content: center;
    gap: 12px;
    margin-top: 24px;
}
.vt-swal-btn {
    border: 0;
    border-radius: 10px;
    padding: 11px 26px;
    font: 600 15px Figtree, sans-serif;
    cursor: pointer;
    transition: transform 0.15s, filter 0.15s, box-shadow 0.15s;
}
.vt-swal-btn:hover {
    transform: translateY(-2px);
}
.vt-swal-btn:active {
    transform: scale(0.95);
}
.vt-swal-btn.ok {
    background: var(--c);
    color: #fff;
    box-shadow: 0 8px 20px color-mix(in srgb, var(--c) 35%, transparent);
}
.vt-swal-btn.ok:hover {
    filter: brightness(1.07);
}
.vt-swal-btn.cancel {
    background: #eef1f5;
    color: #44566c;
}
.vt-swal-btn.cancel:hover {
    background: #e3e8ee;
}
@keyframes vt-swal-draw {
    to { stroke-dashoffset: 0; }
}
@keyframes vt-swal-fade {
    to { opacity: 1; }
}
@keyframes vt-swal-pop {
    from { transform: scale(0.7); opacity: 0; }
    to { transform: none; opacity: 1; }
}
@keyframes vt-swal-pulse {
    50% { transform: scale(1.08); }
}
</style>
