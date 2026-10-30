<template>
    <Head title="Restablecer Contraseña - RIC 2026" />

    <AuthShell title="Nueva Contraseña" subtitle="Ingresa tu nueva contraseña">
        <form @submit.prevent="submit" class="space-y-5">
            <!-- Correo (bloqueado) -->
            <div class="space-y-1.5">
                <label class="text-sm font-semibold text-gray-700">Correo Electrónico</label>
                <IconField>
                    <InputIcon class="pi pi-envelope" />
                    <InputText v-model="form.email" disabled class="w-full auth-input" />
                </IconField>
                <Message v-if="form.errors.email" severity="error" size="small" variant="simple">
                    {{ form.errors.email }}
                </Message>
            </div>

            <!-- Nueva Contraseña -->
            <div class="space-y-1.5">
                <label class="text-sm font-semibold text-gray-700">Nueva Contraseña</label>
                <Password
                    v-model="form.password"
                    placeholder="Mínimo 8 caracteres"
                    toggle-mask
                    fluid
                    input-class="auth-input"
                    class="w-full"
                    :class="{ 'p-invalid': form.errors.password }"
                >
                    <template #footer>
                        <div class="text-xs text-gray-400 mt-1 flex items-center gap-1">
                            <i class="pi pi-info-circle"></i>
                            <span>La contraseña debe tener al menos 8 caracteres</span>
                        </div>
                    </template>
                </Password>
                <Message v-if="form.errors.password" severity="error" size="small" variant="simple">
                    {{ form.errors.password }}
                </Message>
                <div v-if="form.password.length > 0" class="mt-1 flex items-center gap-2">
                    <div class="flex-1 h-1 rounded-full bg-gray-200 overflow-hidden">
                        <div class="h-full transition-all duration-300 rounded-full" :class="passwordStrengthClass" :style="{ width: passwordStrength + '%' }"></div>
                    </div>
                    <span class="text-xs font-medium" :class="passwordStrengthColor">{{ passwordStrengthText }}</span>
                </div>
            </div>

            <!-- Confirmar -->
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
                    :class="{ 'p-invalid': passwordConfirmationError }"
                />
                <div v-if="form.password_confirmation.length > 0" class="text-xs flex items-center gap-1.5 mt-1">
                    <span v-if="passwordsMatch" class="text-green-600 flex items-center gap-1">
                        <i class="pi pi-check-circle"></i> Las contraseñas coinciden
                    </span>
                    <span v-else class="text-red-500 flex items-center gap-1">
                        <i class="pi pi-times-circle"></i> Las contraseñas no coinciden
                    </span>
                </div>
            </div>

            <Button
                type="submit"
                :loading="form.processing"
                :disabled="!isFormValid"
                :label="form.processing ? 'Guardando...' : 'Restablecer Contraseña'"
                :icon="form.processing ? 'pi pi-spin pi-spinner' : 'pi pi-key'"
                class="w-full auth-submit-btn"
            />

            <div class="text-center pt-1">
                <Link :href="route('login')" class="text-sm text-gray-500 hover:text-blue-600 font-medium transition-colors duration-200 flex items-center justify-center gap-1.5">
                    <i class="pi pi-arrow-left"></i>
                    Volver al inicio de sesión
                </Link>
            </div>
        </form>
    </AuthShell>
</template>

<script setup>
import { conBase } from '@/base';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { message } from 'ant-design-vue';
import AuthShell from '@/Layouts/AuthShell.vue';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import InputText from 'primevue/inputtext';
import Password from 'primevue/password';
import Button from 'primevue/button';
import Message from 'primevue/message';

const props = defineProps({
    email: { type: String, required: true },
    token: { type: String, required: true },
});


const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const passwordConfirmationError = computed(() => {
    if (form.password_confirmation.length === 0) return '';
    return form.password !== form.password_confirmation ? 'Las contraseñas no coinciden' : '';
});

const passwordsMatch = computed(() =>
    form.password.length > 0 &&
    form.password_confirmation.length > 0 &&
    form.password === form.password_confirmation,
);

const passwordStrength = computed(() => {
    const password = form.password;
    if (password.length === 0) return 0;
    let strength = 0;
    if (password.length >= 8) strength += 25;
    if (password.length >= 12) strength += 25;
    if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength += 25;
    if (/\d/.test(password) && /[^a-zA-Z0-9]/.test(password)) strength += 25;
    return Math.min(strength, 100);
});

const passwordStrengthClass = computed(() => {
    const s = passwordStrength.value;
    if (s < 30) return 'bg-red-500';
    if (s < 60) return 'bg-yellow-500';
    if (s < 80) return 'bg-blue-500';
    return 'bg-green-500';
});

const passwordStrengthColor = computed(() => {
    const s = passwordStrength.value;
    if (s < 30) return 'text-red-500';
    if (s < 60) return 'text-yellow-600';
    if (s < 80) return 'text-blue-500';
    return 'text-green-500';
});

const passwordStrengthText = computed(() => {
    const s = passwordStrength.value;
    if (s === 0) return '';
    if (s < 30) return 'Débil';
    if (s < 60) return 'Regular';
    if (s < 80) return 'Fuerte';
    return 'Muy fuerte';
});

const isFormValid = computed(() =>
    form.password.length >= 8 &&
    form.password === form.password_confirmation &&
    form.password_confirmation.length > 0,
);

const submit = () => {
    if (!isFormValid.value) {
        if (form.password.length < 8) {
            message.warning('Debe tener al menos 8 caracteres.');
            return;
        }
        if (form.password !== form.password_confirmation) {
            message.warning('Verifica que ambas contraseñas sean iguales.');
            return;
        }
    }

    form.post(route('password.store'), {
        onError: (errors) => {
            const firstError = Object.values(errors)[0];
            if (firstError) {
                message.error(firstError);
            }
        },
        onSuccess: () => {
            message.success('Tu contraseña ha sido actualizada. Redirigiendo al inicio de sesión...');
            setTimeout(() => { window.location.href = conBase('/login'); }, 3000);
        },
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
    border-radius: 1rem;
    border: 2px solid #e5e7eb;
    background: rgba(249, 250, 251, 0.5);
    padding: 0.875rem 1rem;
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

:deep(.p-invalid .auth-input) {
    border-color: #ef4444;
}

.auth-submit-btn {
    height: 3rem;
    font-weight: 600;
    border-radius: 1rem;
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
