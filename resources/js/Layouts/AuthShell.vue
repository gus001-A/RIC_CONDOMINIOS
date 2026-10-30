<template>
    <div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-blue-50 via-blue-100/50 to-blue-200/30 p-4">
        <div class="w-full max-w-5xl bg-white/80 backdrop-blur-sm rounded-3xl shadow-2xl overflow-hidden flex flex-col md:flex-row border border-white/20 relative">
            <div class="absolute -inset-0.5 bg-gradient-to-r from-blue-400 via-purple-400 to-blue-400 rounded-3xl blur-xl opacity-20 animate-pulse-slow"></div>

            <!-- Columna Izquierda - Branding -->
            <div class="md:w-1/2 bg-gradient-to-br from-blue-600 via-blue-700 to-indigo-800 p-10 md:p-14 flex flex-col justify-center items-center text-white relative overflow-hidden">
                <div class="absolute -top-20 -right-20 w-72 h-72 bg-gradient-to-br from-blue-400/30 to-purple-400/30 rounded-full blur-3xl animate-float"></div>
                <div class="absolute -bottom-20 -left-20 w-72 h-72 bg-gradient-to-tr from-indigo-400/30 to-blue-400/30 rounded-full blur-3xl animate-float" style="animation-delay: 2s;"></div>
                <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[500px] h-[500px] bg-gradient-to-r from-blue-500/10 to-purple-500/10 rounded-full blur-3xl animate-pulse-slow"></div>

                <div v-for="i in 25" :key="i" class="particle"
                     :style="{
                         left: Math.random() * 100 + '%',
                         top: Math.random() * 100 + '%',
                         width: (Math.random() * 4 + 1) + 'px',
                         height: (Math.random() * 4 + 1) + 'px',
                         animationDelay: (Math.random() * 10) + 's',
                         animationDuration: (Math.random() * 15 + 10) + 's',
                         opacity: Math.random() * 0.3 + 0.1
                     }">
                </div>

                <div class="absolute top-0 left-0 w-full h-0.5 bg-gradient-to-r from-transparent via-white/20 to-transparent"></div>
                <div class="absolute bottom-0 left-0 w-full h-0.5 bg-gradient-to-r from-transparent via-white/20 to-transparent"></div>
                <div class="absolute left-0 top-0 w-0.5 h-full bg-gradient-to-b from-transparent via-white/10 to-transparent"></div>
                <div class="absolute right-0 top-0 w-0.5 h-full bg-gradient-to-b from-transparent via-white/10 to-transparent"></div>

                <div class="absolute top-4 left-4 w-8 h-8 border-t-2 border-l-2 border-white/10 rounded-tl-xl"></div>
                <div class="absolute top-4 right-4 w-8 h-8 border-t-2 border-r-2 border-white/10 rounded-tr-xl"></div>
                <div class="absolute bottom-4 left-4 w-8 h-8 border-b-2 border-l-2 border-white/10 rounded-bl-xl"></div>
                <div class="absolute bottom-4 right-4 w-8 h-8 border-b-2 border-r-2 border-white/10 rounded-br-xl"></div>

                <div class="relative z-10 text-center">
                    <div class="mb-8 flex justify-center group">
                        <div class="relative">
                            <div class="absolute inset-0 bg-gradient-to-r from-blue-400 via-purple-400 to-blue-400 rounded-3xl blur-2xl group-hover:blur-3xl transition-all duration-700 opacity-60 group-hover:opacity-100"></div>
                            <div class="relative bg-white/10 backdrop-blur-2xl rounded-3xl p-6 border border-white/20 shadow-2xl transition-all duration-500 group-hover:scale-105 group-hover:border-white/40 group-hover:shadow-[0_0_60px_rgba(59,130,246,0.3)]">
                                <img
                                    v-if="logoExists"
                                    :src="logoImage"
                                    alt="RIC Logo"
                                    class="h-28 w-auto object-contain drop-shadow-2xl"
                                    @error="logoExists = false"
                                />
                                <div v-else class="w-28 h-28 bg-blue-500/30 rounded-2xl flex items-center justify-center backdrop-blur-sm">
                                    <span class="text-4xl font-bold text-white">RIC</span>
                                </div>
                            </div>
                            <div class="absolute -bottom-2 left-1/2 -translate-x-1/2 w-16 h-1 bg-gradient-to-r from-transparent via-white/50 to-transparent rounded-full"></div>
                        </div>
                    </div>

                    <h1 class="text-5xl md:text-6xl font-bold mb-3 tracking-tight">
                        <span class="bg-gradient-to-r from-white via-blue-200 to-purple-200 bg-clip-text text-transparent">RIC {{ currentYear }}
                        </span>
                    </h1>

                    <div class="h-1 w-20 bg-gradient-to-r from-blue-300/50 to-purple-300/50 mx-auto rounded-full mb-6"></div>

                    <p class="text-blue-100/90 text-lg font-light leading-relaxed">Red <br />
                        <span class="font-medium text-white">Informática Contable</span>
                    </p>

                    <div class="flex items-center justify-center gap-4 mt-10 mb-6">
                        <div class="w-12 h-px bg-gradient-to-r from-transparent via-blue-300/20 to-transparent"></div>
                        <div class="w-1.5 h-1.5 rounded-full bg-blue-300/30"></div>
                        <div class="w-12 h-px bg-gradient-to-r from-transparent via-blue-300/20 to-transparent"></div>
                    </div>

                    <div class="mt-6 text-blue-200/20 text-xs tracking-wider">
                        <p>&copy; RIC {{ currentYear }}</p>
                    </div>
                </div>
            </div>

            <!-- Columna Derecha - Formulario -->
            <div class="md:w-1/2 p-8 md:p-12 flex flex-col justify-center bg-white/90 backdrop-blur-sm relative">
                <div class="absolute -top-20 -right-20 w-48 h-48 bg-gradient-to-br from-blue-200/10 to-purple-200/10 rounded-full blur-2xl"></div>
                <div class="absolute -bottom-20 -left-20 w-48 h-48 bg-gradient-to-tr from-indigo-200/10 to-blue-200/10 rounded-full blur-2xl"></div>

                <div class="absolute top-4 left-4 w-6 h-6 border-t-2 border-l-2 border-blue-100 rounded-tl-lg"></div>
                <div class="absolute top-4 right-4 w-6 h-6 border-t-2 border-r-2 border-blue-100 rounded-tr-lg"></div>
                <div class="absolute bottom-4 left-4 w-6 h-6 border-b-2 border-l-2 border-blue-100 rounded-bl-lg"></div>
                <div class="absolute bottom-4 right-4 w-6 h-6 border-b-2 border-r-2 border-blue-100 rounded-br-lg"></div>

                <div class="max-w-sm mx-auto w-full relative z-10">
                    <div class="mb-10">
                        <div class="flex items-center gap-4 mb-2">
                            <div class="w-1 h-14 bg-gradient-to-b from-blue-500 to-purple-600 rounded-full"></div>
                            <div>
                                <h2 class="text-3xl font-bold text-gray-800 tracking-tight">{{ title }}</h2>
                                <p class="text-sm text-gray-500 mt-0.5 font-light">{{ subtitle }}</p>
                            </div>
                        </div>
                        <div class="w-16 h-0.5 bg-gradient-to-r from-blue-500 to-purple-500 rounded-full ml-[22px]"></div>
                    </div>

                    <slot />
                </div>
            </div>
        </div>
    </div>

</template>

<script setup>
import { ref } from 'vue';
import logoImage from '@/images/logo.png';

defineProps({
    title: { type: String, default: 'Bienvenido' },
    subtitle: { type: String, default: '' },
});

const logoExists = ref(true);
const currentYear = new Date().getFullYear();
</script>

<style scoped>
@keyframes float {
    0%, 100% { transform: translateY(0px) rotate(0deg); }
    50% { transform: translateY(-20px) rotate(5deg); }
}

@keyframes pulse-slow {
    0%, 100% { opacity: 0.2; transform: scale(1); }
    50% { opacity: 0.6; transform: scale(1.1); }
}

@keyframes particleFloat {
    0% { transform: translateY(0px) scale(0); opacity: 0; }
    20% { opacity: 1; }
    80% { opacity: 1; }
    100% { transform: translateY(-200px) scale(1); opacity: 0; }
}

.animate-float {
    animation: float 8s ease-in-out infinite;
}

.animate-pulse-slow {
    animation: pulse-slow 6s ease-in-out infinite;
}

.particle {
    position: absolute;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.3);
    animation: particleFloat linear infinite;
    pointer-events: none;
    backdrop-filter: blur(4px);
}

img {
    filter: drop-shadow(0 8px 32px rgba(59, 130, 246, 0.3));
    transition: filter 0.5s ease;
}

.group:hover img {
    filter: drop-shadow(0 8px 48px rgba(59, 130, 246, 0.5));
}
</style>
