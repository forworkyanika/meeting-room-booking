<script setup>
import { useToastStore } from '@/stores/toast';

const toast = useToastStore();
</script>

<template>
    <div class="toast-container position-fixed bottom-0 end-0 p-3">
        <div v-for="t in toast.toasts" :key="t.id" class="toast show align-items-center border-0" :class="`text-bg-${t.variant}`" role="status">
            <div class="d-flex">
                <div class="toast-body">{{ t.message }}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" aria-label="Close" @click="toast.dismiss(t.id)"></button>
            </div>
        </div>
    </div>
</template>
