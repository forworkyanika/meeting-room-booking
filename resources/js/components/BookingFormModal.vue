<script setup>
import { computed, reactive, ref, watch } from 'vue';
import BaseModal from './BaseModal.vue';
import http, { errorMessage, validationErrors } from '@/api/http';
import { useToastStore } from '@/stores/toast';
import { formatRange, nextHalfHour, toLocalInput } from '@/utils/format';

const props = defineProps({ rooms: { type: Array, required: true } });
const emit = defineEmits(['created']);

const toast = useToastStore();
const modal = ref(null);
const saving = ref(false);
const errors = ref({});
const availability = ref(null); // null = unknown, { available, conflicts }

const form = reactive({ room_id: '', title: '', description: '', attendees: 1, start: '', end: '' });

const selectedRoom = computed(() => props.rooms.find((r) => r.id === Number(form.room_id)));

/** Open the form, optionally prefilled from a calendar selection. */
function open({ start, end, roomId } = {}) {
    const s = start ?? nextHalfHour();
    const e = end ?? new Date(s.getTime() + 60 * 60 * 1000);
    Object.assign(form, {
        room_id: roomId ?? props.rooms[0]?.id ?? '',
        title: '',
        description: '',
        attendees: 1,
        start: toLocalInput(s),
        end: toLocalInput(e),
    });
    errors.value = {};
    availability.value = null;
    modal.value.open();
}

// datetime-local gives local wall time; send ISO (UTC) so the server knows the exact instant.
const payload = () => ({
    room_id: Number(form.room_id),
    title: form.title,
    description: form.description || null,
    attendees: Number(form.attendees),
    start_time: new Date(form.start).toISOString(),
    end_time: new Date(form.end).toISOString(),
});

// Live conflict check: ask the API whenever room or time changes (debounced).
let timer = null;
watch(
    () => [form.room_id, form.start, form.end],
    () => {
        clearTimeout(timer);
        availability.value = null;
        if (!form.room_id || !form.start || !form.end || new Date(form.end) <= new Date(form.start)) return;
        timer = setTimeout(checkAvailability, 300);
    },
);

async function checkAvailability() {
    const { room_id, start_time, end_time } = payload();
    try {
        const { data } = await http.get('/availability', { params: { room_id, start_time, end_time } });
        availability.value = data;
    } catch {
        availability.value = null;
    }
}

async function submit() {
    saving.value = true;
    errors.value = {};
    try {
        const { data } = await http.post('/bookings', payload());
        toast.success(data.status === 'approved' ? 'Booking confirmed.' : 'Booking sent for approval.');
        modal.value.close();
        emit('created', data);
    } catch (error) {
        if (error.response?.status === 409) {
            availability.value = { available: false, conflicts: error.response.data.conflicts };
        } else {
            errors.value = validationErrors(error);
            if (!Object.keys(errors.value).length) toast.error(errorMessage(error));
        }
    } finally {
        saving.value = false;
    }
}

defineExpose({ open });
</script>

<template>
    <BaseModal ref="modal" title="Book a meeting room">
        <form id="booking-form" novalidate @submit.prevent="submit">
            <div class="mb-3">
                <label class="form-label" for="bf-room">Room</label>
                <select id="bf-room" v-model="form.room_id" class="form-select" :class="{ 'is-invalid': errors.room_id }" required>
                    <option v-for="room in rooms" :key="room.id" :value="room.id">{{ room.name }} · {{ room.location }} · up to {{ room.capacity }}</option>
                </select>
                <div class="invalid-feedback">{{ errors.room_id }}</div>
            </div>

            <div class="mb-3">
                <label class="form-label" for="bf-title">Meeting title</label>
                <input id="bf-title" v-model.trim="form.title" class="form-control" :class="{ 'is-invalid': errors.title }" maxlength="150" placeholder="e.g. Sprint planning" required />
                <div class="invalid-feedback">{{ errors.title }}</div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-sm-6">
                    <label class="form-label" for="bf-start">Start</label>
                    <input id="bf-start" v-model="form.start" type="datetime-local" step="900" class="form-control" :class="{ 'is-invalid': errors.start_time }" required />
                    <div class="invalid-feedback">{{ errors.start_time }}</div>
                </div>
                <div class="col-sm-6">
                    <label class="form-label" for="bf-end">End</label>
                    <input id="bf-end" v-model="form.end" type="datetime-local" step="900" class="form-control" :class="{ 'is-invalid': errors.end_time }" required />
                    <div class="invalid-feedback">{{ errors.end_time }}</div>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label" for="bf-attendees">Attendees</label>
                <input id="bf-attendees" v-model="form.attendees" type="number" min="1" :max="selectedRoom?.capacity" class="form-control" :class="{ 'is-invalid': errors.attendees }" />
                <div class="form-text" v-if="selectedRoom">This room holds up to {{ selectedRoom.capacity }} people.</div>
                <div class="invalid-feedback">{{ errors.attendees }}</div>
            </div>

            <div class="mb-3">
                <label class="form-label" for="bf-desc">Notes <span class="text-body-secondary">(optional)</span></label>
                <textarea id="bf-desc" v-model="form.description" class="form-control" rows="2" maxlength="1000"></textarea>
            </div>

            <div v-if="availability?.available" class="alert alert-success py-2 mb-0">
                <i class="bi bi-check-circle me-1"></i>The room is free for this time.
            </div>
            <div v-else-if="availability" class="alert alert-warning py-2 mb-0">
                <div class="fw-semibold"><i class="bi bi-exclamation-triangle me-1"></i>This time clashes with:</div>
                <ul class="mb-0 small ps-3">
                    <li v-for="c in availability.conflicts" :key="c.id">
                        {{ c.title }} ({{ formatRange(c.start_time, c.end_time) }}, {{ c.status }})
                    </li>
                </ul>
            </div>
        </form>

        <template #footer>
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
            <button type="submit" form="booking-form" class="btn btn-primary" :disabled="saving || availability?.available === false">
                <span v-if="saving" class="spinner-border spinner-border-sm me-1"></span>Book room
            </button>
        </template>
    </BaseModal>
</template>
