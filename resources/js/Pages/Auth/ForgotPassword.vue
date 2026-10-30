<template>

    <Head title="Recuperar Contraseña - RIC" />

    <div class="login-layout">
        <!-- ================= PANEL IZQUIERDO ================= -->
        <div class="brand-panel">
            <!-- Patrón de puntos -->
            <div class="pattern-dots"></div>

            <!-- Círculo punteado decorativo -->
            <div class="decor-circle"></div>

            <div class="brand-content">
                <!-- Tarjeta del logo -->
                <div class="logo-card floating">
                    <img :src="logoPath" alt="RIC Logo" class="logo-img" />
                </div>

                <!-- Línea divisoria -->
                <div class="separator-line"></div>

                <!-- Texto -->
                <div class="brand-text">
                    <h2>Sistema Integral para la <br><span>Red Integral de Condominios</span></h2>
                </div>

                <!-- Tarjetas de características -->
                <div class="features-grid">
                    <div class="feature-item slide-in-1">
                        <div class="feature-icon"><i class="pi pi-wallet"></i></div>
                        <h4>Contabilidad</h4>
                        <p>Gestión de cuotas, ingresos y estados de cuenta.</p>
                    </div>
                    <div class="feature-item slide-in-2">
                        <div class="feature-icon"><i class="pi pi-wrench"></i></div>
                        <h4>Mantenimiento</h4>
                        <p>Planes preventivos y control de activos.</p>
                    </div>
                    <div class="feature-item slide-in-3">
                        <div class="feature-icon"><i class="pi pi-users"></i></div>
                        <h4>Control de Visitas</h4>
                        <p>Registro de accesos y seguridad del condominio.</p>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="brand-footer">
                <p>© 2026 RIC · Plataforma de Gestión Condominal</p>
            </div>
        </div>

        <!-- ================= PANEL DERECHO ================= -->
        <div class="form-panel">
            <!-- Formulario centrado -->
            <div class="form-wrapper">
                <!-- Encabezado -->
                <div class="form-header anim-fade-up" style="--delay: 0.1s">
                    <div class="header-line"></div>
                    <h1 class="form-title">Recuperar Contraseña</h1>
                    <p class="form-subtitle">Te enviaremos un enlace para restablecer tu contraseña</p>
                </div>

                <form @submit.prevent="submit" class="form-body">
                    <!-- Correo -->
                    <div class="input-group anim-fade-up" style="--delay: 0.2s">
                        <label class="input-label">Correo Electrónico</label>
                        <IconField>
                            <InputIcon class="pi pi-envelope text-gray-400" />
                            <InputText v-model="form.email" type="email" placeholder="ejemplo@correo.com"
                                class="w-full custom-input" autocomplete="email" />
                        </IconField>
                        <Message v-if="form.errors.email" severity="error" size="small" variant="simple" class="mt-1">
                            {{ translateError(form.errors.email) }}
                        </Message>
                    </div>

                    <!-- Botón -->
                    <div class="anim-fade-up" style="--delay: 0.3s">
                        <Button type="submit" :loading="form.processing"
                            :label="form.processing ? 'Enviando...' : 'Enviar enlace'"
                            :icon="form.processing ? 'pi pi-spin pi-spinner' : 'pi pi-send'"
                            class="w-full btn-primary btn-shine" />
                    </div>
                </form>

                <!-- Volver al login -->
                <div class="anim-fade-up" style="--delay: 0.4s">
                    <div class="demo-divider mt-8">
                        <span>o</span>
                    </div>
                    <div class="text-center mt-4">
                        <Link :href="route('login')"
                            class="inline-flex items-center gap-2 text-sm text-[#1b4dd1] hover:text-[#123a9e] font-semibold transition-all duration-200 group/back">
                            <i
                                class="pi pi-arrow-left group-hover/back:-translate-x-1 transition-transform duration-200"></i>
                            Volver al login
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { onMounted } from 'vue';
import { message } from 'ant-design-vue';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import InputText from 'primevue/inputtext';
import Button from 'primevue/button';
import Message from 'primevue/message';

const props = defineProps({
    status: { type: String, default: null },
});

const logoPath = '/logos/logo.png';

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
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');

/* ================= LAYOUT GENERAL ================= */
.login-layout {
    display: flex;
    min-height: 100vh;
    width: 100%;
    background: #ffffff;
    font-family: 'Inter', system-ui, -apple-system, sans-serif;
    overflow: hidden;
}

/* ================= PANEL IZQUIERDO ================= */
.brand-panel {
    position: relative;
    width: 55%;
    background: linear-gradient(135deg, #1b4dd1 0%, #123a9e 100%);
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    padding: 4rem 6rem 4rem 3rem;
    z-index: 2;
}

/* Borde Curvado */
.brand-panel::after {
    content: '';
    position: absolute;
    top: -10%;
    right: -10%;
    width: 25%;
    height: 120%;
    background: #ffffff;
    border-radius: 50%;
    z-index: -1;
    box-shadow: -10px 0 30px rgba(0, 0, 0, 0.05);
}

/* Patrón de puntos */
.pattern-dots {
    position: absolute;
    top: 40px;
    left: 40px;
    width: 130px;
    height: 130px;
    background-image: radial-gradient(rgba(255, 255, 255, 0.2) 2px, transparent 2px);
    background-size: 18px 18px;
    z-index: 1;
}

/* Círculo punteado */
.decor-circle {
    position: absolute;
    top: 50%;
    left: 5%;
    transform: translateY(-50%);
    width: 500px;
    height: 500px;
    border-radius: 50%;
    border: 1.5px dashed rgba(255, 255, 255, 0.15);
    z-index: 1;
    pointer-events: none;
}

.brand-content {
    position: relative;
    z-index: 2;
    display: flex;
    flex-direction: column;
    align-items: center;
    width: 100%;
    max-width: 600px;
}

/* Tarjeta del Logo */
.logo-card {
    background: #ffffff;
    padding: 1.5rem 3.5rem;
    border-radius: 1.25rem;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.4);
    margin-bottom: 2rem;
    width: 100%;
    max-width: 480px;
    display: flex;
    justify-content: center;
}

.logo-img {
    height: 145px;
    width: auto;
    max-width: 100%;
    object-fit: contain;
}

/* Línea divisoria */
.separator-line {
    width: 80px;
    height: 2px;
    background: rgba(255, 255, 255, 0.3);
    margin-bottom: 1.5rem;
    border-radius: 2px;
}

/* Texto de Branding */
.brand-text {
    text-align: center;
    margin-bottom: 2.5rem;
}

.brand-text h2 {
    color: #d0dfff;
    font-size: 1.1rem;
    font-weight: 500;
    line-height: 1.5;
    margin: 0;
}

.brand-text h2 span {
    color: #ffffff;
    font-weight: 800;
    font-size: 1.3rem;
    letter-spacing: -0.02em;
}

/* Grid de Características */
.features-grid {
    display: flex;
    justify-content: space-between;
    gap: 1.5rem;
    width: 100%;
}

.feature-item {
    display: flex;
    flex-direction: column;
    text-align: left;
    flex: 1;
}

.feature-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #ffffff;
    margin-bottom: 0.8rem;
    font-size: 1rem;
}

.feature-item h4 {
    color: #ffffff;
    font-size: 0.9rem;
    font-weight: 700;
    margin: 0 0 0.3rem 0;
}

.feature-item p {
    color: #a9c4e0;
    font-size: 0.75rem;
    line-height: 1.4;
    margin: 0;
}

.brand-footer {
    position: absolute;
    bottom: 25px;
    z-index: 2;
    color: rgba(255, 255, 255, 0.5);
    font-size: 0.8rem;
}

/* ================= PANEL DERECHO (Formulario Centrado) ================= */
.form-panel {
    width: 45%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #ffffff;
    padding: 3rem;
    z-index: 1;
}

.form-wrapper {
    width: 100%;
    max-width: 380px;
    transform: translateY(-40px);
}

/* Encabezado */
.form-header {
    margin-bottom: 2rem;
    position: relative;
    padding-left: 1rem;
}

.header-line {
    position: absolute;
    left: 0;
    top: 5px;
    height: 26px;
    width: 3.5px;
    background: #1b4dd1;
    border-radius: 4px;
}

.form-title {
    font-size: 2rem;
    font-weight: 800;
    color: #0f2a47;
    margin: 0 0 0.2rem 0;
    letter-spacing: -0.02em;
}

.form-subtitle {
    font-size: 0.9rem;
    color: #64748b;
    margin: 0;
}

/* Formulario */
.form-body {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

.input-group {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}

.input-label {
    font-size: 0.85rem;
    font-weight: 600;
    color: #334155;
}

/* Inputs */
:deep(.custom-input) {
    border-radius: 0.6rem;
    border: 1.5px solid #e2e8f0;
    background: #f8fafc;
    padding: 0.85rem 1rem;
    width: 100%;
    color: #1e293b;
    font-size: 0.95rem;
    transition: all 0.25s ease;
}

:deep(.custom-input::placeholder) {
    color: #94a3b8;
}

:deep(.custom-input:hover) {
    border-color: #cbd5e1;
}

:deep(.custom-input:focus) {
    border-color: #1b4dd1;
    background: #ffffff;
    box-shadow: 0 0 0 4px rgba(27, 77, 209, 0.1);
    outline: none;
}

/* Botón Principal */
.btn-primary {
    height: 3.1rem;
    font-weight: 600;
    font-size: 0.95rem;
    border-radius: 0.6rem;
    border: 0;
    background: #1b4dd1;
    color: #ffffff;
    box-shadow: 0 8px 15px -5px rgba(27, 77, 209, 0.4);
    transition: all 0.3s ease;
    margin-top: 0.5rem;
    position: relative;
    overflow: hidden;
}

.btn-primary:hover {
    background: #153ea8;
    box-shadow: 0 12px 20px -5px rgba(27, 77, 209, 0.5);
    transform: translateY(-2px);
}

/* Efecto Shine */
.btn-shine::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 50%;
    height: 100%;
    background: linear-gradient(to right, rgba(255, 255, 255, 0) 0%, rgba(255, 255, 255, 0.4) 50%, rgba(255, 255, 255, 0) 100%);
    transform: skewX(-25deg);
    transition: left 0.7s ease;
}

.btn-shine:hover::before {
    left: 150%;
}

/* Divisor para el botón de volver */
.demo-divider {
    display: flex;
    align-items: center;
    text-align: center;
}

.demo-divider::before,
.demo-divider::after {
    content: '';
    flex: 1;
    border-bottom: 1px solid #e2e8f0;
}

.demo-divider span {
    padding: 0 0.8rem;
    color: #94a3b8;
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* ================= ANIMACIONES ================= */
@keyframes floating {
    0% {
        transform: translateY(0);
    }

    50% {
        transform: translateY(-6px);
    }

    100% {
        transform: translateY(0);
    }
}

.floating {
    animation: floating 5s ease-in-out infinite;
}

@keyframes fadeUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.anim-fade-up {
    animation: fadeUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    opacity: 0;
    animation-delay: var(--delay, 0s);
}

@keyframes slideRight {
    from {
        opacity: 0;
        transform: translateX(-30px);
    }

    to {
        opacity: 1;
        transform: translateX(0);
    }
}

.slide-in-1 {
    animation: slideRight 0.6s ease-out forwards;
    animation-delay: 0.2s;
    opacity: 0;
}

.slide-in-2 {
    animation: slideRight 0.6s ease-out forwards;
    animation-delay: 0.4s;
    opacity: 0;
}

.slide-in-3 {
    animation: slideRight 0.6s ease-out forwards;
    animation-delay: 0.6s;
    opacity: 0;
}

/* ================= RESPONSIVE ================= */
@media (max-width: 1100px) {
    .login-layout {
        flex-direction: column;
    }

    .brand-panel {
        width: 100%;
        padding: 4rem 2rem;
        border-radius: 0 0 3rem 3rem;
    }

    .brand-panel::after {
        display: none;
    }

    .form-panel {
        width: 100%;
        padding: 3rem 2rem;
    }

    .brand-footer {
        position: relative;
        bottom: auto;
        margin-top: 2rem;
    }

    .features-grid {
        flex-wrap: wrap;
        justify-content: center;
    }

    .feature-item {
        flex: 1 1 200px;
    }

    .form-wrapper {
        transform: translateY(0);
    }
}

@media (max-width: 600px) {
    .form-title {
        font-size: 1.75rem;
    }

    .logo-card {
        padding: 1.5rem 2rem;
        max-width: 100%;
    }

    .logo-img {
        height: 100px;
    }

    .features-grid {
        flex-direction: column;
        gap: 1.5rem;
    }

    .feature-item {
        flex: 1 1 100%;
    }

    .form-wrapper {
        max-width: 100%;
    }
}
</style>