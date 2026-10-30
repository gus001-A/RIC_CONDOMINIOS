<template>
    <Head title="Verificar Correo - RIC 2026" />

    <AuthShell title="Verifica tu correo" subtitle="Antes de continuar, verifica tu dirección de correo">
        <div class="mb-4 flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 p-4">
            <i class="pi pi-envelope mt-0.5 text-amber-600"></i>
            <p class="text-sm text-amber-700">
                Hemos enviado un enlace de verificación a tu correo electrónico.
                Haz clic en el enlace para activar tu cuenta.
            </p>
        </div>

        <div v-if="verificationLinkSent" class="mb-4 flex items-start gap-2 rounded-lg border border-green-200 bg-green-50 p-3">
            <i class="pi pi-check-circle mt-0.5 text-green-600"></i>
            <p class="text-sm text-green-700">Se ha enviado un nuevo enlace de verificación.</p>
        </div>

        <div class="space-y-3">
            <Button
                type="button"
                :loading="form.processing"
                :label="form.processing ? 'Enviando...' : 'Reenviar correo de verificación'"
                :icon="form.processing ? 'pi pi-spin pi-spinner' : 'pi pi-send'"
                class="w-full auth-submit-btn"
                @click="submit"
            />

            <Link
                :href="route('logout')"
                method="post"
                as="button"
                class="flex w-full items-center justify-center gap-2 rounded-xl bg-red-50 px-4 py-3 font-semibold text-red-600 transition hover:bg-red-100"
            >
                <i class="pi pi-sign-out"></i>
                Cerrar Sesión
            </Link>
        </div>

        <div class="mt-4 flex items-start gap-2 rounded-lg bg-blue-50 p-3">
            <i class="pi pi-info-circle mt-0.5 text-blue-600"></i>
            <p class="text-xs text-blue-700">
                Si no recibiste el correo, revisa tu carpeta de spam o solicita un nuevo enlace.
            </p>
        </div>
    </AuthShell>
</template>

<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthShell from '@/Layouts/AuthShell.vue';
import Button from 'primevue/button';

defineProps({
    status: { type: String, default: null },
    verificationLinkSent: { type: Boolean, default: false },
});

const form = useForm({});

const submit = () => {
    form.post(route('verification.send'));
};
</script>

<style scoped>
.auth-submit-btn {
    height: 3rem;
    font-weight: 600;
    border-radius: 0.75rem;
    border: 0;
    background: linear-gradient(to right, #2563eb, #1d4ed8, #4338ca);
    box-shadow: 0 10px 15px -3px rgba(59, 130, 246, 0.3);
    transition: all 0.3s ease;
}

.auth-submit-btn:hover:not(:disabled) {
    background: linear-gradient(to right, #1d4ed8, #1e40af, #3730a3);
    box-shadow: 0 20px 25px -5px rgba(59, 130, 246, 0.4);
}
</style>
