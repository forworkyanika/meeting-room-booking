<script setup>
import { Modal } from 'bootstrap';
import { onBeforeUnmount, onMounted, ref } from 'vue';

defineProps({
    title: { type: String, required: true },
    size: { type: String, default: '' }, // '', 'modal-lg', 'modal-sm'
});
const emit = defineEmits(['hidden']);

const el = ref(null);
let modal = null;

onMounted(() => {
    modal = Modal.getOrCreateInstance(el.value);
    el.value.addEventListener('hidden.bs.modal', () => emit('hidden'));
});
onBeforeUnmount(() => modal?.dispose());

defineExpose({
    open: () => modal.show(),
    close: () => modal.hide(),
});
</script>

<template>
    <div ref="el" class="modal fade" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down" :class="size">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ title }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <slot />
                </div>
                <div v-if="$slots.footer" class="modal-footer">
                    <slot name="footer" />
                </div>
            </div>
        </div>
    </div>
</template>
