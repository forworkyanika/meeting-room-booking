<script setup>
import { onMounted, ref, watch } from 'vue';
import http from '@/api/http';
import BookingFormModal from '@/components/BookingFormModal.vue';

const rooms = ref([]);
const minCapacity = ref('');
const loading = ref(true);
const formModal = ref(null);

async function load() {
    loading.value = true;
    const { data } = await http.get('/rooms', { params: { min_capacity: minCapacity.value || undefined } });
    rooms.value = data;
    loading.value = false;
}

onMounted(load);
watch(minCapacity, load);
</script>

<template>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h3 mb-0">Meeting rooms</h1>
        <div class="input-group" style="max-width: 240px">
            <span class="input-group-text"><i class="bi bi-people"></i></span>
            <select v-model="minCapacity" class="form-select" aria-label="Minimum capacity">
                <option value="">Any size</option>
                <option value="4">4+ people</option>
                <option value="8">8+ people</option>
                <option value="12">12+ people</option>
            </select>
        </div>
    </div>

    <div v-if="loading" class="text-center py-5"><div class="spinner-border text-primary"></div></div>
    <p v-else-if="!rooms.length" class="text-body-secondary">No rooms match.</p>

    <div v-else class="row g-3">
        <div v-for="room in rooms" :key="room.id" class="col-sm-6 col-lg-4">
            <div class="card h-100 shadow-sm" :style="{ borderTop: `4px solid ${room.color}` }">
                <div class="card-body d-flex flex-column">
                    <div class="d-flex justify-content-between align-items-start">
                        <h2 class="h5 card-title mb-1">{{ room.name }}</h2>
                        <span class="badge text-bg-light"><i class="bi bi-people me-1"></i>{{ room.capacity }}</span>
                    </div>
                    <p class="small text-body-secondary mb-2"><i class="bi bi-geo-alt me-1"></i>{{ room.location }}</p>
                    <p class="card-text small">{{ room.description }}</p>
                    <div class="mb-3">
                        <span v-for="a in room.amenities" :key="a" class="badge rounded-pill text-bg-secondary-subtle border me-1 fw-normal text-body">{{ a }}</span>
                    </div>
                    <div class="mt-auto d-flex justify-content-between align-items-center">
                        <span class="small text-body-secondary">{{ room.upcoming_bookings_count }} upcoming</span>
                        <button class="btn btn-sm btn-primary" @click="formModal.open({ roomId: room.id })">Book this room</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <BookingFormModal ref="formModal" :rooms="rooms" @created="load" />
</template>
