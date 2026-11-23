<script setup>
defineProps({ meta: { type: Object, default: null } });
const emit = defineEmits(['change']);
</script>

<template>
    <nav v-if="meta && meta.last_page > 1" class="d-flex justify-content-between align-items-center mt-3" aria-label="Pagination">
        <span class="small text-body-secondary">Showing {{ meta.from }}–{{ meta.to }} of {{ meta.total }}</span>
        <div class="btn-group">
            <button class="btn btn-outline-secondary btn-sm" :disabled="meta.current_page === 1" @click="emit('change', meta.current_page - 1)">
                <i class="bi bi-chevron-left"></i> Prev
            </button>
            <button class="btn btn-outline-secondary btn-sm" :disabled="meta.current_page === meta.last_page" @click="emit('change', meta.current_page + 1)">
                Next <i class="bi bi-chevron-right"></i>
            </button>
        </div>
    </nav>
</template>
