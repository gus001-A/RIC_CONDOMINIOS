<script setup>
import { HomeOutlined } from '@ant-design/icons-vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
</script>

<template>
    <AppLayout>
        <div class="vt-page vt-wrap">
            <PageHeader title="Mi casa" subtitle="Tu propiedad dentro del condominio" :icon="HomeOutlined" />
            <div class="vt-card">
                <a-result status="info" title="Aún no tienes una casa asignada"
                    sub-title="Tu usuario no está ligado a ninguna casa activa. Pide a la administración de tu condominio que te registre como condómino.">
                    <template #icon><HomeOutlined style="color: #f0a202" /></template>
                </a-result>
            </div>
        </div>
    </AppLayout>
</template>
