<script setup>
import { onMounted, ref, watch } from 'vue';
import http from '@/api/http';
import BaseModal from '@/components/BaseModal.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import PaginationBar from '@/components/PaginationBar.vue';
import { useBookingActions } from '@/composables/useBookingActions';
import { formatDate, formatTime } from '@/utils/format';

const { busy, approve, reject } = useBookingActions();

const stats = ref(null);
const status = ref('pending');
const bookings = ref([]);
const meta = ref(null);
const loading = ref(true);

const rejectModal = ref(null);
const rejecting = ref(null);
const rejectNote = ref('');

const TABS = [
    { value: 'pending', label: 'Pending' },
    { value: 'approved', label: 'Approved' },
    { value: 'rejected', label: 'Rejected' },
    { value: 'cancelled', label: 'Cancelled' },
    { value: '', label: 'All' },
];

async function loadStats() {
    const { data } = await http.get('/admin/dashboard');
    stats.value = data;
}

async function load(page = 1) {
    loading.value = true;
    const params = { scope: 'all', status: status.value || undefined, when: status.value === 'pending' ? 'upcoming' : undefined, page };
    const { data } = await http.get('/bookings', { params });
    bookings.value = data.data;
    meta.value = data.meta;
    loading.value = false;
}

const refresh = () => Promise.all([load(meta.value?.current_page ?? 1), loadStats()]);

async function approveBooking(booking) {
    if (await approve(booking)) refresh();
}

function openReject(booking) {
    rejecting.value = booking;
    rejectNote.value = '';
    rejectModal.value.open();
}

async function confirmReject() {
    if (await reject(rejecting.value, rejectNote.value)) {
        rejectModal.value.close();
        refresh();
    }
}

onMounted(() => Promise.all([load(), loadStats()]));
watch(status, () => load());
</script>

<template>
    <h1 class="h3 mb-3">Booking approvals</h1>

    <div v-if="stats" class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="card stat-card shadow-sm border-warning"><div class="card-body">
                <div class="small text-body-secondary">Waiting for approval</div><div class="display-6">{{ stats.pending_count }}</div>
            </div></div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card stat-card shadow-sm"><div class="card-body">
                <div class="small text-body-secondary">Meetings today</div><div class="display-6">{{ stats.today_count }}</div>
            </div></div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card stat-card shadow-sm"><div class="card-body">
                <div class="small text-body-secondary">Active rooms</div><div class="display-6">{{ stats.active_rooms }}</div>
            </div></div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card stat-card shadow-sm"><div class="card-body">
                <div class="small text-body-secondary">Users</div><div class="display-6">{{ stats.users }}</div>
            </div></div>
        </div>
    </div>

    <ul class="nav nav-pills mb-3 flex-nowrap overflow-auto">
        <li v-for="t in TABS" :key="t.value" class="nav-item">
            <button class="nav-link text-nowrap" :class="{ active: status === t.value }" @click="status = t.value">
                {{ t.label }}
                <span v-if="t.value === 'pending' && stats?.pending_count" class="badge text-bg-warning ms-1">{{ stats.pending_count }}</span>
            </button>
        </li>
    </ul>

    <div class="card shadow-sm">
        <div v-if="loading" class="text-center py-5"><div class="spinner-border text-primary"></div></div>
        <div v-else-if="!bookings.length" class="text-center text-body-secondary py-5">
            <i class="bi bi-inbox fs-1 d-block mb-2"></i>Nothing here.
        </div>
        <div v-else class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr><th>Meeting</th><th>Room</th><th>When</th><th>Requested by</th><th>Status</th><th class="text-end">Actions</th></tr>
                </thead>
                <tbody>
                    <tr v-for="b in bookings" :key="b.id">
                        <td>
                            <div class="fw-semibold">{{ b.title }}</div>
                            <div class="small text-body-secondary">{{ b.attendees }} attendees</div>
                        </td>
                        <td class="text-nowrap"><span class="room-swatch me-1" :style="{ background: b.room.color }"></span>{{ b.room.name }}</td>
                        <td class="text-nowrap small">{{ formatDate(b.start_time) }}<br />{{ formatTime(b.start_time) }}–{{ formatTime(b.end_time) }}</td>
                        <td class="small">{{ b.user.name }}<br /><span class="text-body-secondary">{{ b.user.department }}</span></td>
                        <td><StatusBadge :status="b.status" /><div v-if="b.admin_note" class="small text-body-secondary mt-1">{{ b.admin_note }}</div></td>
                        <td class="text-end text-nowrap">
                            <template v-if="b.status === 'pending'">
                                <button class="btn btn-sm btn-success me-1" :disabled="busy" @click="approveBooking(b)"><i class="bi bi-check-lg"></i> Approve</button>
                                <button class="btn btn-sm btn-outline-danger" :disabled="busy" @click="openReject(b)"><i class="bi bi-x-lg"></i> Reject</button>
                            </template>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <PaginationBar :meta="meta" @change="load" />

    <BaseModal ref="rejectModal" title="Reject booking">
        <p v-if="rejecting" class="mb-2">Reject <strong>{{ rejecting.title }}</strong> by {{ rejecting.user.name }}?</p>
        <label class="form-label" for="reject-note">Reason (sent to the requester)</label>
        <textarea id="reject-note" v-model.trim="rejectNote" class="form-control" rows="3" maxlength="500" placeholder="e.g. The room is reserved for maintenance."></textarea>
        <template #footer>
            <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Back</button>
            <button class="btn btn-danger" :disabled="busy || !rejectNote" @click="confirmReject">Reject booking</button>
        </template>
    </BaseModal>
</template>
