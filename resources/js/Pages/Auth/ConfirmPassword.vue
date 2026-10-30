<template>
    <Head title="Confirmar Contraseña - RIC 2026" />

    <AuthShell title="Confirmar Contraseña" subtitle="Por seguridad, confirma tu contraseña para continuar">
        <div class="mb-5 flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 p-3">
            <i class="pi pi-shield mt-0.5 text-amber-600"></i>
            <p class="text-sm text-amber-700">Esta es una zona segura. Confirma tu contraseña para continuar.</p>
        </div>

        <form @submit.prevent="submit" class="space-y-5">
            <div class="space-y-1.5">
                <label class="text-sm font-semibold text-gray-700">Contraseña</label>
                <Password
                    v-model="form.password"
                    placeholder="Ingresa tu contraseña"
                    :feedback="false"
                    toggle-mask
                    fluid
                    input-class="auth-input"
                    class="w-full"
                    autocomplete="current-password"
                    :invalid="!!form.errors.password"
                />
                <Message v-if="form.errors.password" severity="error" size="small" variant="simple">
                    {{ form.errors.password }}
                </Message>
            </div>

            <Button
                type="submit"
                :loading="form.processing"
                :label="form.processing ? 'Confirmando...' : 'Confirmar'"
                :icon="form.processing ? 'pi pi-spin pi-spinner' : 'pi pi-check'"
                class="w-full auth-submit-btn"
            />
        </form>
    </AuthShell>
</template>

<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AuthShell from '@/Layouts/AuthShell.vue';
import Password from 'primevue/password';
import Button from 'primevue/button';
import Message from 'primevue/message';

const form = useForm({
    password: '',
});

const submit = () => {
    form.post(route('password.confirm'), {
        onFinish: () => form.reset(),
    });
};
</script>

<style scoped>
:deep(.auth-input) {
    border-radius: 0.75rem;
    border: 2px solid #e5e7eb;
    background: rgba(249, 250, 251, 0.5);
    padding: 0.75rem 1rem;
    transition: all 0.2s ease;
    width: 100%;
}

:deep(.auth-input:hover) {
    border-color: #3b82f6;
    background: #fff;
}

:deep(.auth-input:focus) {
    border-color: #3b82f6;
    background: #fff;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.12);
}

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
