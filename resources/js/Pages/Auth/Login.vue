<template>
    <Head title="Iniciar Sesión" />

    <AuthShell title="Bienvenido" subtitle="Accede con tu usuario y contraseña">
        <form @submit.prevent="submit" class="space-y-5">
            <!-- Usuario -->
            <div class="space-y-1.5">
                <label class="text-sm font-semibold text-gray-700">Nombre de Usuario</label>
                <IconField>
                    <InputIcon class="pi pi-user" />
                    <InputText
                        v-model="form.username"
                        placeholder="Ingresa tu usuario"
                        class="w-full auth-input"
                        autocomplete="username"
                    />
                </IconField>
                <Message v-if="form.errors.username" severity="error" size="small" variant="simple">
                    {{ form.errors.username }}
                </Message>
            </div>

            <!-- Contraseña -->
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
                />
                <Message v-if="form.errors.password" severity="error" size="small" variant="simple">
                    {{ form.errors.password }}
                </Message>
            </div>

            <!-- Recordarme y olvidé contraseña -->
            <div class="flex items-center justify-between py-1">
                <label class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer">
                    <Checkbox v-model="form.remember" binary />
                    Recordarme
                </label>
                <Link
                    :href="route('password.request')"
                    class="text-sm text-blue-600 hover:text-blue-800 font-medium transition-colors duration-200 flex items-center gap-1"
                >
                    <i class="pi pi-question-circle text-xs"></i>
                    ¿Olvidaste tu contraseña?
                </Link>
            </div>

            <!-- Botón Iniciar Sesión -->
            <Button
                type="submit"
                :loading="form.processing"
                :label="form.processing ? 'Ingresando...' : 'Iniciar Sesión'"
                :icon="form.processing ? 'pi pi-spin pi-spinner' : 'pi pi-sign-in'"
                class="w-full auth-submit-btn"
            />

            <div class="text-center pt-3">
                <div class="inline-flex items-center gap-2.5 px-5 py-2.5 bg-gray-50/80 backdrop-blur-sm rounded-full border border-gray-200/50 shadow-sm">
                    <i class="pi pi-shield text-gray-400"></i>
                    <span class="text-xs text-gray-500 font-light">Sistema de acceso restringido</span>
                </div>
            </div>
        </form>

        <!-- Accesos de prueba por rol (sólo en desarrollo, como en VenTrack) -->
        <div v-if="demo.length" class="lg-demo">
            <div class="lg-demo-t"><ThunderboltOutlined /> Accesos de demostración (solo desarrollo)</div>
            <div class="lg-demo-lista">
                <button v-for="u in demo" :key="u.usuario" type="button" class="lg-demo-btn" :style="{ '--c': ROLES[u.rol]?.color }"
                    :disabled="form.processing" :title="`Entrar como ${u.nombre}`" @click="demoLogin(u)">
                    <span class="lg-demo-ic"><component :is="ROLES[u.rol]?.icono" /></span>
                    <span class="min-w-0">
                        <b>{{ ROLES[u.rol]?.label || u.rol }}</b>
                        <small>{{ u.usuario }}</small>
                    </span>
                </button>
            </div>
        </div>
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
import Password from 'primevue/password';
import Checkbox from 'primevue/checkbox';
import Button from 'primevue/button';
import Message from 'primevue/message';
import { ThunderboltOutlined, CrownOutlined, HomeOutlined, SafetyOutlined, ToolOutlined } from '@ant-design/icons-vue';

const props = defineProps({
    status: { type: String, default: null },
    canResetPassword: { type: Boolean, default: true },
    // Cuentas de prueba [{ usuario, nombre, rol, password }]; sólo llegan en desarrollo.
    demo: { type: Array, default: () => [] },
});

const ROLES = {
    ADMINISTRADOR: { label: 'Administración', icono: CrownOutlined, color: '#1a5a96' },
    CONDOMINO: { label: 'Condómino', icono: HomeOutlined, color: '#1faf5c' },
    VIGILANCIA: { label: 'Vigilancia', icono: SafetyOutlined, color: '#d9363e' },
    MANTENIMIENTO: { label: 'Mantenimiento', icono: ToolOutlined, color: '#f0a202' },
};


const form = useForm({
    username: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post('/login', {
        onError: (errors) => {
            if (errors.username || errors.password || errors.email) {
                message.error('Usuario o contraseña incorrectos. Verifica tus credenciales.');
            } else {
                const firstError = Object.values(errors)[0];
                if (firstError) {
                    message.warning(firstError);
                }
            }
        },
        onFinish: () => form.reset('password'),
    });
};

// Llena usuario y contraseña de la cuenta de prueba y entra.
const demoLogin = (u) => {
    form.username = u.usuario;
    form.password = u.password;
    submit();
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

.auth-submit-btn {
    height: 3rem;
    font-weight: 600;
    border-radius: 1rem;
    border: 0;
    background: linear-gradient(to right, #2563eb, #1d4ed8, #4338ca);
    box-shadow: 0 10px 15px -3px rgba(59, 130, 246, 0.3);
    transition: all 0.3s ease;
}

.lg-demo {
    margin-top: 22px;
    padding: 14px;
    border: 1.5px dashed #d5dee8;
    border-radius: 14px;
}
.lg-demo-t {
    text-align: center;
    font-size: 12px;
    color: #6b7c90;
    margin-bottom: 10px;
}
.lg-demo-t .anticon {
    color: #f0a202;
}
.lg-demo-lista {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px;
}
.lg-demo-btn {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 10px;
    text-align: left;
    background: #fff;
    border: 1px solid #e1e8f0;
    border-radius: 999px;
    transition: all 0.2s ease;
}
.lg-demo-btn:hover:not(:disabled) {
    border-color: var(--c);
    background: color-mix(in srgb, var(--c) 6%, #fff);
    transform: translateY(-1px);
    box-shadow: 0 6px 14px rgba(15, 42, 71, 0.08);
}
.lg-demo-btn:disabled {
    opacity: 0.6;
    cursor: wait;
}
.lg-demo-ic {
    flex: none;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: grid;
    place-items: center;
    color: #fff;
    background: var(--c);
    font-size: 14px;
}
.lg-demo-btn b {
    display: block;
    font-size: 13px;
    color: #0f2a47;
    line-height: 1.2;
}
.lg-demo-btn small {
    display: block;
    font-size: 11px;
    color: #8193a6;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.auth-submit-btn:hover {
    background: linear-gradient(to right, #1d4ed8, #1e40af, #3730a3);
    box-shadow: 0 20px 25px -5px rgba(59, 130, 246, 0.4);
}
</style>
