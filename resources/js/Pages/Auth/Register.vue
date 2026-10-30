<template>
    <Head title="Registro - RIC 2026" />

    <AuthShell title="Crear Cuenta" subtitle="Completa el formulario para registrarte">
        <form @submit.prevent="submit" class="space-y-5">
            <div class="space-y-1.5">
                <label class="text-sm font-semibold text-gray-700">Nombre Completo</label>
                <IconField>
                    <InputIcon class="pi pi-user" />
                    <InputText
                        v-model="form.name"
                        placeholder="Tu nombre completo"
                        class="w-full auth-input"
                        autocomplete="name"
                        :invalid="!!form.errors.name"
                    />
                </IconField>
                <Message v-if="form.errors.name" severity="error" size="small" variant="simple">
                    {{ form.errors.name }}
                </Message>
            </div>

            <div class="space-y-1.5">
                <label class="text-sm font-semibold text-gray-700">Correo Electrónico</label>
                <IconField>
                    <InputIcon class="pi pi-envelope" />
                    <InputText
                        v-model="form.email"
                        type="email"
                        placeholder="ejemplo@correo.com"
                        class="w-full auth-input"
                        autocomplete="email"
                        :invalid="!!form.errors.email"
                    />
                </IconField>
                <Message v-if="form.errors.email" severity="error" size="small" variant="simple">
                    {{ form.errors.email }}
                </Message>
            </div>

            <div class="space-y-1.5">
                <label class="text-sm font-semibold text-gray-700">Contraseña</label>
                <Password
                    v-model="form.password"
                    placeholder="Mínimo 8 caracteres"
                    :feedback="false"
                    toggle-mask
                    fluid
                    input-class="auth-input"
                    class="w-full"
                    :invalid="!!form.errors.password"
                />
                <Message v-if="form.errors.password" severity="error" size="small" variant="simple">
                    {{ form.errors.password }}
                </Message>
                <div class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-xs">
                    <span
                        v-for="req in passwordRequirements"
                        :key="req.label"
                        class="flex items-center gap-1"
                        :class="req.ok ? 'text-green-600' : 'text-gray-400'"
                    >
                        <i class="pi text-[10px]" :class="req.ok ? 'pi-check-circle' : 'pi-circle'"></i>
                        {{ req.label }}
                    </span>
                </div>
            </div>

            <div class="space-y-1.5">
                <label class="text-sm font-semibold text-gray-700">Confirmar Contraseña</label>
                <Password
                    v-model="form.password_confirmation"
                    placeholder="Repite tu contraseña"
                    :feedback="false"
                    toggle-mask
                    fluid
                    input-class="auth-input"
                    class="w-full"
                    :invalid="!!form.errors.password_confirmation"
                />
                <Message v-if="form.errors.password_confirmation" severity="error" size="small" variant="simple">
                    {{ form.errors.password_confirmation }}
                </Message>
            </div>

            <label class="flex items-start gap-2 text-sm text-gray-600 cursor-pointer">
                <Checkbox v-model="acceptTerms" binary class="mt-0.5" />
                <span>Acepto los <a href="#" class="text-blue-600 hover:underline">términos y condiciones</a></span>
            </label>

            <Button
                type="submit"
                :loading="form.processing"
                :disabled="!acceptTerms"
                :label="form.processing ? 'Registrando...' : 'Crear Cuenta'"
                :icon="form.processing ? 'pi pi-spin pi-spinner' : 'pi pi-user-plus'"
                class="w-full auth-submit-btn"
            />

            <p class="text-center text-sm text-gray-600">¿Ya tienes cuenta?
                <Link :href="route('login')" class="font-medium text-blue-600 hover:text-blue-800 hover:underline">
                    Inicia sesión aquí
                </Link>
            </p>
        </form>
    </AuthShell>
</template>

<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AuthShell from '@/Layouts/AuthShell.vue';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import InputText from 'primevue/inputtext';
import Password from 'primevue/password';
import Checkbox from 'primevue/checkbox';
import Button from 'primevue/button';
import Message from 'primevue/message';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const acceptTerms = ref(false);

const passwordRequirements = computed(() => [
    { label: '8+ caracteres', ok: form.password.length >= 8 },
    { label: 'Mayúscula', ok: /[A-Z]/.test(form.password) },
    { label: 'Minúscula', ok: /[a-z]/.test(form.password) },
    { label: 'Número', ok: /[0-9]/.test(form.password) },
]);

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<style scoped>
::selection {
    background: linear-gradient(to right, #3b82f6, #1a3a5c);
    color: white;
}

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
