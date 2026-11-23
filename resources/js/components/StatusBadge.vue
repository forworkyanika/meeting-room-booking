<script setup>
import { computed } from 'vue';
import { STATUS } from '@/utils/format';

const props = defineProps({ status: { type: String, required: true } });
const meta = computed(() => STATUS[props.status] ?? { label: props.status, badge: 'text-bg-light', icon: '' });
</script>

<template>
    <span class="badge" :class="meta.badge"><i class="bi me-1" :class="meta.icon"></i>{{ meta.label }}</span>
</template>
