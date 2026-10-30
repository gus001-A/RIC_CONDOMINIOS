<template>
    <Head :title="title" />
    
    <div class="min-h-screen bg-white" :class="{ 'con-barra-app': usarBarra }">
        <!-- Navbar -->
        <nav class="bg-white/80 backdrop-blur-md border-b border-gray-200/30 sticky top-0 z-50 shadow-[0_4px_20px_rgba(0,0,0,0.03)]" :class="{ 'max-md:hidden': usarBarra }">
            <div class="max-w-[96rem] mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Logo a la izquierda, opciones al centro, usuario a la derecha -->
                <div class="grid grid-cols-[1fr_auto_1fr] items-center gap-2 h-16">
                    <div class="flex items-center min-w-0">
                        <Link :href="'/dashboard'" class="flex items-center gap-3 group">
                            <div class="relative">
                                <img 
                                    :src="logoImage"
                                    alt="RIC Logo" 
                                    class="h-12 w-auto object-contain transition-transform duration-300 group-hover:scale-105"
                                    @error="handleLogoError"
                                    v-if="logoExists"
                                />
                                <div v-else class="h-12 w-12 bg-gradient-to-br from-[#0A1628] to-blue-600 rounded-xl flex items-center justify-center text-white font-bold text-xl">RIC
                                </div>
                                <div class="absolute -bottom-1 left-0 w-0 h-0.5 bg-gradient-to-r from-[#0A1628] to-blue-600 transition-all duration-300 group-hover:w-full"></div>
                            </div>
                        </Link>
                    </div>

                        <!-- Menú de navegación desktop con PERMISOS -->
                        <div class="hidden xl:flex items-center justify-center gap-0.5">

                            <!-- Movimientos -->
                            <Link 
                                v-if="permisos?.puede_ver"
                                :href="'/movimientos'"
                                class="relative px-3 py-2 rounded-lg text-sm font-medium transition-all duration-300"
                                :class="[
                                    $sinBase($page.url).startsWith('/movimientos') 
                                        ? 'text-[#0A1628] bg-[#0A1628]/5' 
                                        : 'text-gray-600 hover:text-[#0A1628] hover:bg-[#0A1628]/5'
                                ]"
                            >
                                <span class="flex items-center gap-2">
                                    <svg class="w-4 h-4 transition-transform duration-300 group-hover:scale-110" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"/>
                                    </svg>
                                    Movimientos
                                </span>
                                <span v-if="$sinBase($page.url).startsWith('/movimientos')" 
                                      class="absolute bottom-0 left-1/2 -translate-x-1/2 w-6 h-0.5 bg-[#0A1628] rounded-full"></span>
                            </Link>

                            <!-- Condominios: para el personal se agrupa en "Condominio" y "Finanzas" -->
                            <template v-for="entrada in menuEscritorio" :key="entrada.key">
                                <NavGrupo v-if="entrada.items" :label="entrada.label" :icono="entrada.icono" :items="entrada.items" />
                                <Link
                                    v-else
                                    :href="entrada.href"
                                    class="relative px-3 py-2 rounded-lg text-sm font-medium transition-all duration-300"
                                    :class="[
                                        entrada.activo
                                            ? 'text-[#0A1628] bg-[#0A1628]/5'
                                            : 'text-gray-600 hover:text-[#0A1628] hover:bg-[#0A1628]/5'
                                    ]"
                                >
                                    <span class="flex items-center gap-2">
                                        <i :class="entrada.icono" class="text-sm"></i>
                                        {{ entrada.label }}
                                        <span v-if="entrada.globo" class="min-w-[18px] h-[18px] px-1 rounded-full bg-red-600 text-white text-[10px] font-bold inline-flex items-center justify-center">{{ entrada.globo }}</span>
                                    </span>
                                    <span v-if="entrada.activo"
                                          class="absolute bottom-0 left-1/2 -translate-x-1/2 w-6 h-0.5 bg-[#0A1628] rounded-full"></span>
                                </Link>
                            </template>

                            <!-- Personas -->
                            <Link
                                v-if="permisos?.puede_ver_personas"
                                :href="'/personas'"
                                class="relative px-3 py-2 rounded-lg text-sm font-medium transition-all duration-300"
                                :class="[
                                    $sinBase($page.url).startsWith('/personas') 
                                        ? 'text-[#0A1628] bg-[#0A1628]/5' 
                                        : 'text-gray-600 hover:text-[#0A1628] hover:bg-[#0A1628]/5'
                                ]"
                            >
                                <span class="flex items-center gap-2">
                                    <svg class="w-4 h-4 transition-transform duration-300 group-hover:scale-110" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                    </svg>
                                    Personas
                                </span>
                                <span v-if="$sinBase($page.url).startsWith('/personas')" 
                                      class="absolute bottom-0 left-1/2 -translate-x-1/2 w-6 h-0.5 bg-[#0A1628] rounded-full"></span>
                            </Link>

                            <!-- Cuentas -->
                            <Link 
                                v-if="permisos?.puede_ver_cuentas"
                                :href="'/cuentas'"
                                class="relative px-3 py-2 rounded-lg text-sm font-medium transition-all duration-300"
                                :class="[
                                    $sinBase($page.url).startsWith('/cuentas') 
                                        ? 'text-[#0A1628] bg-[#0A1628]/5' 
                                        : 'text-gray-600 hover:text-[#0A1628] hover:bg-[#0A1628]/5'
                                ]"
                            >
                                <span class="flex items-center gap-2">
                                    <svg class="w-4 h-4 transition-transform duration-300 group-hover:scale-110" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                    </svg>
                                    Cuentas
                                </span>
                                <span v-if="$sinBase($page.url).startsWith('/cuentas')" 
                                      class="absolute bottom-0 left-1/2 -translate-x-1/2 w-6 h-0.5 bg-[#0A1628] rounded-full"></span>
                            </Link>

                            <!-- Usuarios - Solo ADMINISTRADOR, AUDITOR, SUPERUSUARIO -->
                            <Link 
                                v-if="permisos?.puede_ver_usuarios"
                                :href="'/usuarios'"
                                class="relative px-3 py-2 rounded-lg text-sm font-medium transition-all duration-300"
                                :class="[
                                    $sinBase($page.url).startsWith('/usuarios') 
                                        ? 'text-[#0A1628] bg-[#0A1628]/5' 
                                        : 'text-gray-600 hover:text-[#0A1628] hover:bg-[#0A1628]/5'
                                ]"
                            >
                                <span class="flex items-center gap-2">
                                    <svg class="w-4 h-4 transition-transform duration-300 group-hover:scale-110" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                                    </svg>
                                    Usuarios
                                    <span class="text-[10px] px-1.5 py-0.5 rounded-full bg-blue-100 text-blue-700 font-bold">Admin</span>
                                </span>
                                <span v-if="$sinBase($page.url).startsWith('/usuarios')" 
                                      class="absolute bottom-0 left-1/2 -translate-x-1/2 w-6 h-0.5 bg-[#0A1628] rounded-full"></span>
                            </Link>


                            <!-- Reportes -->
                            <Link 
                                v-if="permisos?.puede_ver_reportes"
                                :href="'/reportes'"
                                class="relative px-3 py-2 rounded-lg text-sm font-medium transition-all duration-300"
                                :class="[
                                    $sinBase($page.url).startsWith('/reportes') 
                                        ? 'text-[#0A1628] bg-[#0A1628]/5' 
                                        : 'text-gray-600 hover:text-[#0A1628] hover:bg-[#0A1628]/5'
                                ]"
                            >
                                <span class="flex items-center gap-2">
                                    <svg class="w-4 h-4 transition-transform duration-300 group-hover:scale-110" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                                    </svg>
                                    Reportes
                                </span>
                                <span v-if="$sinBase($page.url).startsWith('/reportes')" 
                                      class="absolute bottom-0 left-1/2 -translate-x-1/2 w-6 h-0.5 bg-[#0A1628] rounded-full"></span>
                            </Link>
                        </div>

                    <!-- Usuario y acciones -->
                    <div class="flex items-center justify-end gap-2 col-start-3">
                        <!-- Dropdown de usuario - SOLO CERRAR SESIÓN -->
                        <Dropdown align="right" width="56" class="user-dropdown">
                            <template #trigger>
                                <button class="flex items-center gap-2.5 px-3 py-2 rounded-xl hover:bg-[#0A1628]/5 transition-all duration-300 group border border-transparent hover:border-gray-200/50">
                                    <div class="w-8 h-8 bg-gradient-to-br from-[#0A1628] to-blue-600 rounded-full flex items-center justify-center text-white font-semibold text-sm shadow-md transition-transform duration-300 group-hover:scale-105 ring-2 ring-[#0A1628]/10 group-hover:ring-[#0A1628]/20">
                                        {{ userInitials }}
                                    </div>
                                    <div class="hidden 2xl:block">
                                        <p class="text-sm font-medium text-[#0A1628] leading-tight">
                                            {{ $page.props.auth.user?.nombre_completo || 'Usuario' }}
                                        </p>
                                    </div>
                                    <svg class="w-4 h-4 text-gray-400 transition-all duration-300 group-hover:text-[#0A1628] ml-1" 
                                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                    </svg>
                                </button>
                            </template>

                            <template #content>
                                <div class="py-1 bg-white min-w-[200px]">
                                    <!-- Header del dropdown -->
                                    <div class="px-4 py-4 bg-gradient-to-r from-[#0A1628] to-[#1a2a4a] relative overflow-hidden">
                                        <div class="absolute top-0 right-0 w-32 h-32 bg-blue-500/10 rounded-full blur-2xl -translate-y-1/2 translate-x-1/2"></div>
                                        <div class="absolute bottom-0 left-0 w-24 h-24 bg-blue-400/10 rounded-full blur-2xl translate-y-1/2 -translate-x-1/2"></div>
                                        
                                        <div class="relative flex items-center gap-3">
                                            <div class="w-12 h-12 bg-white/10 backdrop-blur-sm rounded-full flex items-center justify-center text-white font-semibold text-xl border-2 border-white/20 shadow-lg">
                                                {{ userInitials }}
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <p class="text-sm font-semibold text-white truncate">
                                                    {{ $page.props.auth.user?.nombre_completo || 'Usuario' }}
                                                </p>
                                                <p class="text-xs text-blue-200/80 truncate">
                                                    {{ $page.props.auth.user?.email || '' }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- SOLO CERRAR SESIÓN - ELIMINADO MI PERFIL Y CONFIGURACIÓN -->
                                    <div class="py-1">
                                        <!-- Alta y edición de condominios (antes "Empresas"): sólo superusuario -->
                                        <Link v-if="permisos?.puede_ver_empresas" href="/empresas" class="w-full flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-blue-50/80 transition-colors">
                                            <span class="w-8 h-8 bg-slate-100 rounded-lg flex items-center justify-center text-slate-600"><ApartmentOutlined /></span>
                                            <span class="font-medium">Alta de condominios</span>
                                        </Link>
                                        <!-- Instalar la app en el celular / computadora -->
                                        <button v-if="!app.instalada" type="button" class="w-full flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-blue-50/80 transition-colors" @click="instalador?.abrir()">
                                            <span class="w-8 h-8 bg-blue-50 rounded-lg flex items-center justify-center text-[#1a5a96]"><MobileOutlined /></span>
                                            <span class="font-medium">Instalar app</span>
                                        </button>
                                        <!-- Notificaciones push en este dispositivo -->
                                        <button v-if="push.soportado && $page.props.push_llave && !push.requiereInstalar" type="button" class="w-full flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-blue-50/80 transition-colors" @click="alternarPush">
                                            <span class="w-8 h-8 rounded-lg flex items-center justify-center" :class="push.activo ? 'bg-green-50 text-green-600' : 'bg-amber-50 text-amber-600'"><BellOutlined /></span>
                                            <span class="font-medium">{{ push.activo ? 'Notificaciones activadas' : 'Activar notificaciones' }}</span>
                                        </button>
                                        <!-- Cerrar sesión -->
                                        <DropdownLink :href="'/logout'" method="post" as="button" 
                                                      class="text-red-600 hover:bg-red-50/80 mt-1">
                                            <span class="w-8 h-8 bg-red-50 rounded-lg flex items-center justify-center text-red-500 group-hover:bg-red-600 group-hover:text-white transition-all duration-200">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                                                </svg>
                                            </span>
                                            <span class="font-medium group-hover:text-red-700">Cerrar Sesión</span>
                                        </DropdownLink>
                                    </div>
                                </div>
                            </template>
                        </Dropdown>

                        <!-- Menú móvil -->
                        <button @click="mobileMenuOpen = !mobileMenuOpen" 
                                class="xl:hidden p-2 text-gray-500 hover:text-[#0A1628] hover:bg-[#0A1628]/5 rounded-lg transition-all duration-300 ml-1">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path v-if="!mobileMenuOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                                <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Menú móvil con PERMISOS -->
                <transition
                    enter-active-class="transition-all duration-300 ease-out"
                    enter-from-class="max-h-0 opacity-0"
                    enter-to-class="max-h-[500px] opacity-100"
                    leave-active-class="transition-all duration-200 ease-in"
                    leave-from-class="max-h-[500px] opacity-100"
                    leave-to-class="max-h-0 opacity-0"
                >
                    <div v-if="mobileMenuOpen" class="xl:hidden overflow-hidden">
                        <div class="py-3 border-t border-gray-200/30 space-y-1">
                            <!-- Dashboard (oculto en RIC Condominios) -->
                            <Link
                                v-if="!$page.props.modulos_ocultos?.includes('dashboard_contable')"
                                :href="'/dashboard'"
                                class="block px-3 py-2.5 rounded-lg text-sm font-medium transition-all duration-300 hover:translate-x-1"
                                :class="[
                                    $sinBase($page.url).startsWith('/dashboard') 
                                        ? 'text-[#0A1628] bg-[#0A1628]/5' 
                                        : 'text-gray-600 hover:text-[#0A1628] hover:bg-[#0A1628]/5'
                                ]"
                                @click="mobileMenuOpen = false"
                            >
                                <span class="flex items-center gap-3">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-4 0a1 1 0 01-1-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 01-1 1h-2z"/>
                                    </svg>
                                    Dashboard
                                </span>
                            </Link>

                            <!-- Movimientos -->
                            <Link
                                v-if="permisos?.puede_ver"
                                :href="'/movimientos'"
                                class="block px-3 py-2.5 rounded-lg text-sm font-medium transition-all duration-300 hover:translate-x-1"
                                :class="[
                                    $sinBase($page.url).startsWith('/movimientos') 
                                        ? 'text-[#0A1628] bg-[#0A1628]/5' 
                                        : 'text-gray-600 hover:text-[#0A1628] hover:bg-[#0A1628]/5'
                                ]"
                                @click="mobileMenuOpen = false"
                            >
                                <span class="flex items-center gap-3">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"/>
                                    </svg>
                                    Movimientos
                                </span>
                            </Link>

                            <!-- Casas (condominios) -->
                            <Link
                                v-for="item in menuCondominios"
                                :key="item.href"
                                :href="item.href"
                                class="block px-3 py-2.5 rounded-lg text-sm font-medium transition-all duration-300 hover:translate-x-1"
                                :class="[
                                    item.activo
                                        ? 'text-[#0A1628] bg-[#0A1628]/5'
                                        : 'text-gray-600 hover:text-[#0A1628] hover:bg-[#0A1628]/5'
                                ]"
                                @click="mobileMenuOpen = false"
                            >
                                <span class="flex items-center gap-3">
                                    <i :class="item.icono" class="text-sm"></i>
                                    {{ item.label }}
                                    <span v-if="item.globo" class="min-w-[18px] h-[18px] px-1 rounded-full bg-red-600 text-white text-[10px] font-bold inline-flex items-center justify-center">{{ item.globo }}</span>
                                </span>
                            </Link>

                            <!-- Personas -->
                            <Link
                                v-if="permisos?.puede_ver_personas"
                                :href="'/personas'"
                                class="block px-3 py-2.5 rounded-lg text-sm font-medium transition-all duration-300 hover:translate-x-1"
                                :class="[
                                    $sinBase($page.url).startsWith('/personas') 
                                        ? 'text-[#0A1628] bg-[#0A1628]/5' 
                                        : 'text-gray-600 hover:text-[#0A1628] hover:bg-[#0A1628]/5'
                                ]"
                                @click="mobileMenuOpen = false"
                            >
                                <span class="flex items-center gap-3">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                    </svg>
                                    Personas
                                </span>
                            </Link>

                            <!-- Cuentas -->
                            <Link
                                v-if="permisos?.puede_ver_cuentas"
                                :href="'/cuentas'"
                                class="block px-3 py-2.5 rounded-lg text-sm font-medium transition-all duration-300 hover:translate-x-1"
                                :class="[
                                    $sinBase($page.url).startsWith('/cuentas') 
                                        ? 'text-[#0A1628] bg-[#0A1628]/5' 
                                        : 'text-gray-600 hover:text-[#0A1628] hover:bg-[#0A1628]/5'
                                ]"
                                @click="mobileMenuOpen = false"
                            >
                                <span class="flex items-center gap-3">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                    </svg>
                                    Cuentas
                                </span>
                            </Link>

                            <!-- Usuarios -->
                            <Link
                                v-if="permisos?.puede_ver_usuarios"
                                :href="'/usuarios'"
                                class="block px-3 py-2.5 rounded-lg text-sm font-medium transition-all duration-300 hover:translate-x-1"
                                :class="[
                                    $sinBase($page.url).startsWith('/usuarios') 
                                        ? 'text-[#0A1628] bg-[#0A1628]/5' 
                                        : 'text-gray-600 hover:text-[#0A1628] hover:bg-[#0A1628]/5'
                                ]"
                                @click="mobileMenuOpen = false"
                            >
                                <span class="flex items-center gap-3">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                                    </svg>
                                    Usuarios
                                    <span class="text-[10px] px-1.5 py-0.5 rounded-full bg-blue-100 text-blue-700 font-bold ml-auto">Admin</span>
                                </span>
                            </Link>


                            <!-- Reportes -->
                            <Link
                                v-if="permisos?.puede_ver_reportes"
                                :href="'/reportes'"
                                class="block px-3 py-2.5 rounded-lg text-sm font-medium transition-all duration-300 hover:translate-x-1"
                                :class="[
                                    $sinBase($page.url).startsWith('/reportes') 
                                        ? 'text-[#0A1628] bg-[#0A1628]/5' 
                                        : 'text-gray-600 hover:text-[#0A1628] hover:bg-[#0A1628]/5'
                                ]"
                                @click="mobileMenuOpen = false"
                            >
                                <span class="flex items-center gap-3">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                                    </svg>
                                    Reportes
                                </span>
                            </Link>
                        </div>
                    </div>
                </transition>
            </div>
        </nav>

        <!-- Alertas vecinales, visitas por autorizar y mensajes nuevos (en cualquier pantalla) -->
        <AvisosGlobales v-if="permisos?.puede_ver_alertas || permisos?.puede_ver_mi_casa || permisos?.puede_usar_mensajes" />

        <!-- Page Heading -->
        <header v-if="$slots.header" class="bg-white/80 backdrop-blur-sm border-b border-gray-200/30 shadow-sm">
            <div class="max-w-7xl mx-auto py-3 px-4 sm:px-6 lg:px-8">
                <slot name="header" />
            </div>
        </header>

        <!-- Page Content -->
        <!-- En la app del celular deja espacio para la barra inferior -->
        <main class="pt-4" :class="usarBarra ? 'app-sin-barra pb-28 md:pb-4' : 'pb-4'">
            <AvisoNotificaciones v-if="usarBarra" @instalar="instalador?.abrir()" />
            <slot />
        </main>

        <!-- App instalada en el celular: barra inferior por rol (en el navegador se usa el navbar) -->
        <BarraInferior v-if="usarBarra" :items="barraInferior" :todos="menuCompleto" @instalar="instalador?.abrir()" @push="alternarPush" />
        <InstalarApp ref="instalador" />

        <!-- Confirmaciones globales estilo SweetAlert (los avisos usan `message` de Ant Design) -->
        <ConfirmDialog />
    </div>
</template>

<script setup>
import { Link, usePage, Head } from '@inertiajs/vue3';
import { sinBase } from '@/base';
import { ref, computed, onMounted } from 'vue';
import Dropdown from '@/Components/Dropdown.vue';
import NavGrupo from '@/Components/NavGrupo.vue';
import AvisosGlobales from '@/Components/AvisosGlobales.vue';
import { pulso } from '@/composables/usePulso';
import DropdownLink from '@/Components/DropdownLink.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import BarraInferior from '@/Components/BarraInferior.vue';
import InstalarApp from '@/Components/InstalarApp.vue';
import AvisoNotificaciones from '@/Components/AvisoNotificaciones.vue';
import { MobileOutlined, BellOutlined, ApartmentOutlined } from '@ant-design/icons-vue';
import { message } from 'ant-design-vue';
import { app } from '@/composables/appInstalable';
import { push, sincronizarPush, activarPush, desactivarPush } from '@/composables/push';
import { useFlashMessages } from '@/composables/useFlashMessages';

// IMPORTAR EL LOGO DESDE resources/js/images/logo.png
import logoImage from '@/images/logo.png';

// Definir props para el título
const props = defineProps({
    title: {
        type: String,
        default: 'RIC - Sistema de Gestión'
    }
});

// Muestra los flash messages de Laravel como toasts (una sola vez, global)
useFlashMessages();

const page = usePage();
const mobileMenuOpen = ref(false);
const logoExists = ref(true);

// OBTENER PERMISOS DESDE EL BACKEND
const permisos = computed(() => page.props.permisos || {});
const tipoUsuario = computed(() => page.props.auth?.user?.tipo_usuario || '');

const ROLES_INICIO = ['CONDOMINO', 'VIGILANCIA', 'MANTENIMIENTO', 'ADMINISTRADOR', 'SUPERUSUARIO', 'AUDITOR'];

// Menú de condominios (lista plana; el menú móvil la usa tal cual).
// `grupo` sólo aplica al personal en escritorio, para que la barra quepa.
const menuCondominios = computed(() => {
    const url = sinBase(page.url);
    const p = permisos.value || {};
    const items = [];
    const add = (cond, item) => { if (cond) items.push(item); };

    // Inicio: tablero de cada rol (los roles contables de RIC no lo tienen).
    add(ROLES_INICIO.includes(tipoUsuario.value), { label: 'Inicio', href: '/inicio', icono: 'pi pi-th-large', activo: url.startsWith('/inicio') });
    // Alta y edición de condominios (pantalla "empresas" de RIC): superusuario y administrador.
    add(p.puede_ver_empresas, { label: 'Condominios', href: '/empresas', icono: 'pi pi-building', activo: url.startsWith('/empresas'), grupo: 'condominio' });
    add(p.puede_ver_mi_casa, { label: 'Mi casa', href: '/mi-casa', icono: 'pi pi-home', activo: url.startsWith('/mi-casa') || url.startsWith('/casas') });
    add(p.puede_ver_casas, { label: 'Casas', href: '/casas', icono: 'pi pi-home', activo: url.startsWith('/casas'), grupo: 'condominio' });
    add(p.puede_operar_caseta, { label: 'Caseta', href: '/seguridad/caseta', icono: 'pi pi-shield', activo: url.startsWith('/seguridad/caseta'), grupo: 'condominio' });
    add(p.puede_ver_mi_casa, { label: 'Mis visitas', href: '/mis-visitas', icono: 'pi pi-id-card', activo: url.startsWith('/mis-visitas'), globo: pulso.visitasEsperando.length });
    add(p.puede_ver_incidencias, { label: 'Incidencias', href: '/incidencias', icono: 'pi pi-wrench', activo: url.startsWith('/incidencias'), grupo: 'condominio' });
    add(p.puede_ver_alertas, { label: 'Alertas', href: '/seguridad/alertas', icono: 'pi pi-bell', activo: url.startsWith('/seguridad/alertas'), globo: pulso.alertas.length, grupo: 'condominio' });
    add(p.puede_usar_mensajes, { label: 'Mensajes', href: '/mensajes', icono: 'pi pi-comments', activo: url.startsWith('/mensajes'), globo: pulso.listo ? pulso.mensajesNoLeidos : (page.props.mensajes_no_leidos || 0), grupo: 'condominio' });
    add(p.puede_solicitar_servicios || p.puede_atender_servicios, { label: 'Servicios', href: '/servicios', icono: 'pi pi-briefcase', activo: url.startsWith('/servicios'), grupo: 'condominio' });
    // El condómino lo consulta desde Transparencia (enlace), para no saturar su menú.
    add(p.puede_ver_inventario && !p.puede_ver_mi_casa, { label: 'Inventario', href: '/inventario', icono: 'pi pi-box', activo: url.startsWith('/inventario'), grupo: 'condominio' });
    add(p.puede_atender_servicios, { label: 'Documentos', href: '/documentos/plantillas', icono: 'pi pi-file-edit', activo: url.startsWith('/documentos'), grupo: 'condominio' });
    add(p.puede_ver_transparencia, { label: 'Transparencia', href: '/transparencia', icono: 'pi pi-eye', activo: url.startsWith('/transparencia'), grupo: 'finanzas' });
    add(p.puede_gestionar_cuotas, { label: 'Cuotas', href: '/cuotas', icono: 'pi pi-wallet', activo: url.startsWith('/cuotas'), grupo: 'finanzas' });
    return items;
});

const GRUPOS = {
    condominio: { label: 'Condominio', icono: 'pi pi-building' },
    finanzas: { label: 'Finanzas', icono: 'pi pi-chart-line' },
};

// Escritorio: condómino, vigilancia y mantenimiento ven todas sus opciones en la barra;
// administración, agrupadas en "Condominio" y "Finanzas" para que quepan.
const ROLES_MENU_PLANO = ['CONDOMINO', 'VIGILANCIA', 'MANTENIMIENTO'];
const menuEscritorio = computed(() => {
    if (ROLES_MENU_PLANO.includes(tipoUsuario.value)) {
        return menuCondominios.value.map((i) => ({ ...i, key: i.href }));
    }
    const salida = [];
    const grupos = {};
    for (const item of menuCondominios.value) {
        if (!item.grupo) {
            salida.push({ ...item, key: item.href });
            continue;
        }
        if (!grupos[item.grupo]) {
            grupos[item.grupo] = { key: item.grupo, ...GRUPOS[item.grupo], items: [] };
            salida.push(grupos[item.grupo]);
        }
        grupos[item.grupo].items.push(item);
    }
    // Un grupo de un solo elemento se muestra como enlace normal.
    return salida.map((e) => (e.items && e.items.length === 1 ? { ...e.items[0], key: e.key } : e));
});

// ===== Barra inferior del celular =====
// Las 4 pantallas que más usa cada rol; si alguna no le aplica se completa con el resto del menú.
const PRIORIDAD = {
    CONDOMINO: ['/inicio', '/mi-casa', '/mis-visitas', '/mensajes'],
    VIGILANCIA: ['/inicio', '/seguridad/caseta', '/seguridad/alertas', '/casas'],
    MANTENIMIENTO: ['/inicio', '/incidencias', '/inventario', '/seguridad/alertas'],
};
const CORTOS = { 'Mis visitas': 'Visitas', Transparencia: 'Cuentas' };
const barraInferior = computed(() => {
    const menu = menuCondominios.value;
    const orden = PRIORIDAD[tipoUsuario.value] || ['/inicio', '/casas', '/cuotas', '/incidencias'];
    const elegidos = orden.map((h) => menu.find((i) => i.href === h)).filter(Boolean);
    for (const i of menu) if (elegidos.length < 4 && !elegidos.includes(i)) elegidos.push(i);
    return elegidos.slice(0, 4).map((i) => ({ ...i, label: CORTOS[i.label] || i.label }));
});
// Sólo dentro de la app instalada; en el navegador (aunque sea el celular) se usa el navbar normal.
const usarBarra = computed(() => app.abierta && barraInferior.value.length > 0);
const instalador = ref(null);

// ===== Notificaciones push =====
// Al entrar: si este dispositivo ya dio permiso, queda registrado para el usuario actual.
onMounted(() => sincronizarPush());
const alternarPush = async () => {
    if (push.activo) {
        await desactivarPush();
        message.info('Este dispositivo ya no recibirá notificaciones.');
    } else if (await activarPush()) {
        message.success('Notificaciones activadas. Te llegará un aviso de prueba.');
    } else if (push.permiso === 'denied') {
        message.warning('Las notificaciones están bloqueadas: permítelas en los ajustes del navegador para este sitio.');
    }
};
// Todo el menú para el panel "Más" (condominio + pantallas contables de RIC según permisos).
const menuCompleto = computed(() => {
    const url = sinBase(page.url);
    const p = permisos.value || {};
    const ocultos = page.props.modulos_ocultos || [];
    const extra = [
        [!ocultos.includes('dashboard_contable'), 'Dashboard', '/dashboard'],
        [p.puede_ver, 'Movimientos', '/movimientos'],
        [p.puede_ver_cuentas, 'Cuentas', '/cuentas'],
        [p.puede_ver_reportes, 'Reportes', '/reportes'],
        [p.puede_ver_usuarios, 'Usuarios', '/usuarios'],
    ].filter(([ok]) => ok).map(([, label, href]) => ({ label, href, activo: url.startsWith(href) }));
    return [...menuCondominios.value, ...extra];
});

const handleLogoError = () => {
    logoExists.value = false;
};

// Iniciales del usuario para el avatar
const userInitials = computed(() => {
    const name = page.props.auth?.user?.nombre_completo || '';
    return name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2);
});
</script>

<style scoped>
/* Estilos personalizados */
@keyframes pulse {
    0%, 100% {
        transform: scale(1);
    }
    50% {
        transform: scale(1.1);
    }
}

.animate-pulse {
    animation: pulse 2s ease-in-out infinite;
}

/* Efecto de glassmorphism mejorado */
.backdrop-blur-md {
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
}

/* Transición suave para el menú móvil */
.max-h-0 {
    max-height: 0;
}

.max-h-\[500px\] {
    max-height: 500px;
}

/* Efecto hover en los items del menú */
.hover\:translate-x-1:hover {
    transform: translateX(0.25rem);
}

/* Mejoras para el dropdown - mejor alineación */
:deep(.user-dropdown) {
    position: relative;
}

:deep(.user-dropdown .absolute) {
    margin-top: 0.5rem;
    right: 0;
    transform-origin: top right;
}

:deep(.user-dropdown .rounded-2xl) {
    border-radius: 1rem;
    overflow: hidden;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
}

/* Mejora el hover de los items */
:deep(.dropdown-link) {
    padding: 0.625rem 1rem;
    transition: all 0.2s ease;
}

:deep(.dropdown-link:hover) {
    background-color: rgba(10, 22, 40, 0.05);
}

/* Decoración del header del dropdown */
:deep(.bg-gradient-to-r) {
    position: relative;
    overflow: hidden;
}

/* Mejora la apariencia general */
:deep(.shadow-2xl) {
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
}

/* Estilos para los íconos SVG en el dropdown */
:deep(.dropdown-link svg) {
    transition: all 0.2s ease;
}

:deep(.dropdown-link:hover svg) {
    transform: scale(1.1);
}

/* Mejora el espaciado del trigger */
:deep(.user-dropdown .cursor-pointer) {
    display: flex;
    align-items: center;
}

/* Sombra sutil en el trigger al hacer hover */
:deep(.user-dropdown .group:hover) {
    box-shadow: 0 2px 8px rgba(10, 22, 40, 0.06);
}

/* Animación del icono de flecha */
:deep(.user-dropdown .group svg:last-child) {
    transition: transform 0.3s ease;
}

:deep(.user-dropdown .group:hover svg:last-child) {
    transform: rotate(180deg);
}
</style>