<script setup>
import { computed, ref } from 'vue';
import BaseModal from './BaseModal.vue';
import StatusBadge from './StatusBadge.vue';
import http from '@/api/http';
import { useAuthStore } from '@/stores/auth';
import { useBookingActions } from '@/composables/useBookingActions';
import { formatRange } from '@/utils/format';

const emit = defineEmits(['changed']);

const auth = useAuthStore();
const { busy, cancel, approve, reject } = useBookingActions();

const modal = ref(null);
const summary = ref(null); // what the calendar already knows
const booking = ref(null); // full record, only loaded for the owner or an admin
const note = ref('');

const canReview = computed(() => auth.isAdmin && booking.value?.status === 'pending');

/** @param {{id, title, start, end, status, room, booked_by, attendees, is_mine}} event */
async function open(event) {
    summary.value = event;
    booking.value = null;
    note.value = '';
    modal.value.open();

    if (event.is_mine || auth.isAdmin) {
        const { data } = await http.get(`/bookings/${event.id}`);
        booking.value = data;
    }
}

async function act(action) {
    const updated = await action();
    if (updated) {
        modal.value.close();
        emit('changed', updated);
    }
}

defineExpose({ open });
</script>

<template>
    <BaseModal ref="modal" :title="summary?.title ?? 'Booking'">
        <template v-if="summary">
            <dl class="row mb-0">
                <dt class="col-4">Status</dt>
                <dd class="col-8"><StatusBadge :status="booking?.status ?? summary.status" /></dd>
                <dt class="col-4">Room</dt>
                <dd class="col-8">{{ summary.room }}</dd>
                <dt class="col-4">When</dt>
                <dd class="col-8">{{ formatRange(summary.start, summary.end) }}</dd>
                <dt class="col-4">Booked by</dt>
                <dd class="col-8">{{ summary.booked_by }}</dd>
                <dt class="col-4">Attendees</dt>
                <dd class="col-8">{{ summary.attendees }}</dd>
                <template v-if="booking?.description">
                    <dt class="col-4">Notes</dt>
                    <dd class="col-8">{{ booking.description }}</dd>
                </template>
                <template v-if="booking?.admin_note">
                    <dt class="col-4">Admin note</dt>
                    <dd class="col-8">{{ booking.admin_note }}</dd>
                </template>
            </dl>

            <div v-if="canReview" class="mt-3">
                <label class="form-label" for="review-note">Note to requester <span class="text-body-secondary">(required to reject)</span></label>
                <textarea id="review-note" v-model.trim="note" class="form-control" rows="2" maxlength="500"></textarea>
            </div>
        </template>

        <template #footer>
            <button v-if="booking?.can_cancel" class="btn btn-outline-danger me-auto" :disabled="busy" @click="act(() => cancel(booking))">
                <i class="bi bi-x-lg me-1"></i>Cancel booking
            </button>
            <template v-if="canReview">
                <button class="btn btn-danger" :disabled="busy || !note" @click="act(() => reject(booking, note))">Reject</button>
                <button class="btn btn-success" :disabled="busy" @click="act(() => approve(booking, note || null))">Approve</button>
            </template>
            <button v-else class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
        </template>
    </BaseModal>
</template>
