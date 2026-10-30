<template>
    <Head title="Recuperar Contraseña - RIC" />

    <AuthShell title="Recuperar Contraseña" subtitle="Te enviaremos un enlace para restablecer tu contraseña">
        <form @submit.prevent="submit" class="space-y-5">
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
                    />
                </IconField>
                <Message v-if="form.errors.email" severity="error" size="small" variant="simple">
                    {{ translateError(form.errors.email) }}
                </Message>
            </div>

            <Button
                type="submit"
                :loading="form.processing"
                :label="form.processing ? 'Enviando...' : 'Enviar enlace'"
                :icon="form.processing ? 'pi pi-spin pi-spinner' : 'pi pi-send'"
                class="w-full auth-submit-btn"
            />

            <div class="text-center pt-3">
                <Link
                    :href="route('login')"
                    class="inline-flex items-center gap-2 text-sm text-blue-600 hover:text-blue-800 font-medium transition-colors duration-200 group/back"
                >
                    <i class="pi pi-arrow-left group-hover/back:-translate-x-1 transition-transform duration-200"></i>
                    Volver al login
                </Link>
            </div>
        </form>
    </AuthShell>
</template>

<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { onMounted } from 'vue';
import { message } from 'ant-design-vue';
import AuthShell from '@/Layouts/AuthShell.vue';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import InputText from 'primevue/inputtext';
import Button from 'primevue/button';
import Message from 'primevue/message';

const props = defineProps({
    status: { type: String, default: null },
});


const form = useForm({
    email: '',
});

const translateError = (error) => {
    const translations = {
        'The email field is required.': 'El correo electrónico es obligatorio.',
        'The email must be a valid email address.': 'El correo electrónico debe ser una dirección válida.',
        'The email has already been taken.': 'Este correo electrónico ya está registrado.',
        'The email must be at least 8 characters.': 'El correo electrónico debe tener al menos 8 caracteres.',
        'The email is too long.': 'El correo electrónico es demasiado largo.',
        'The email format is invalid.': 'El formato del correo electrónico no es válido.',
        'The email does not exist.': 'Este correo electrónico no está registrado en nuestro sistema.',
    };
    return translations[error] || error;
};

const submit = () => {
    form.post(route('password.email'), {
        onError: (errors) => {
            const firstError = Object.values(errors)[0];
            if (firstError) {
                message.error(translateError(firstError));
            }
        },
        onSuccess: () => {
            message.success('Hemos enviado un enlace de recuperación a tu correo. Revisa tu bandeja de entrada.');
            form.email = '';
        },
    });
};

onMounted(() => {
    if (props.status) {
        setTimeout(() => {
            message.success(props.status);
        }, 400);
    }
});
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

.auth-submit-btn {
    height: 3rem;
    font-weight: 600;
    border-radius: 0.75rem;
    border: 0;
    background: linear-gradient(to right, #2563eb, #1d4ed8, #4338ca);
    box-shadow: 0 10px 15px -3px rgba(59, 130, 246, 0.3);
    transition: all 0.3s ease;
}

.auth-submit-btn:hover {
    background: linear-gradient(to right, #1d4ed8, #1e40af, #3730a3);
    box-shadow: 0 20px 25px -5px rgba(59, 130, 246, 0.4);
}
</style>
