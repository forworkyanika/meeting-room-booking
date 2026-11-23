<script setup>
import { onMounted, ref, watch } from 'vue';
import http from '@/api/http';
import StatusBadge from '@/components/StatusBadge.vue';
import PaginationBar from '@/components/PaginationBar.vue';
import { useBookingActions } from '@/composables/useBookingActions';
import { formatDate, formatTime } from '@/utils/format';

const { busy, cancel } = useBookingActions();

const tab = ref('upcoming');
const bookings = ref([]);
const meta = ref(null);
const loading = ref(true);

async function load(page = 1) {
    loading.value = true;
    const { data } = await http.get('/bookings', { params: { when: tab.value, page } });
    bookings.value = data.data;
    meta.value = data.meta;
    loading.value = false;
}

async function cancelBooking(booking) {
    if (!confirm(`Cancel "${booking.title}"?`)) return;
    if (await cancel(booking)) load(meta.value?.current_page ?? 1);
}

onMounted(load);
watch(tab, () => load());
</script>

<template>
    <h1 class="h3 mb-3">My bookings</h1>

    <ul class="nav nav-tabs mb-3">
        <li class="nav-item"><button class="nav-link" :class="{ active: tab === 'upcoming' }" @click="tab = 'upcoming'">Upcoming</button></li>
        <li class="nav-item"><button class="nav-link" :class="{ active: tab === 'past' }" @click="tab = 'past'">Past</button></li>
    </ul>

    <div v-if="loading" class="text-center py-5"><div class="spinner-border text-primary"></div></div>
    <div v-else-if="!bookings.length" class="text-center text-body-secondary py-5">
        <i class="bi bi-calendar-x fs-1 d-block mb-2"></i>No {{ tab }} bookings.
        <div v-if="tab === 'upcoming'" class="mt-2"><RouterLink :to="{ name: 'calendar' }" class="btn btn-primary btn-sm">Book a room</RouterLink></div>
    </div>

    <div v-else class="list-group shadow-sm">
        <div v-for="b in bookings" :key="b.id" class="list-group-item py-3">
            <div class="d-flex flex-wrap justify-content-between gap-2">
                <div>
                    <div class="fw-semibold">{{ b.title }} <StatusBadge :status="b.status" class="ms-1" /></div>
                    <div class="small text-body-secondary">
                        <span class="room-swatch me-1" :style="{ background: b.room.color }"></span>{{ b.room.name }} ·
                        {{ formatDate(b.start_time) }} · {{ formatTime(b.start_time) }}–{{ formatTime(b.end_time) }} ·
                        {{ b.attendees }} {{ b.attendees === 1 ? 'person' : 'people' }}
                    </div>
                    <div v-if="b.admin_note" class="small mt-1"><i class="bi bi-chat-left-text me-1"></i>{{ b.admin_note }}</div>
                </div>
                <div class="align-self-center">
                    <button v-if="b.can_cancel" class="btn btn-sm btn-outline-danger" :disabled="busy" @click="cancelBooking(b)">Cancel</button>
                </div>
            </div>
        </div>
    </div>

    <PaginationBar :meta="meta" @change="load" />
</template>
