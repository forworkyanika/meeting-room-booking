<script setup>
import FullCalendar from '@fullcalendar/vue3';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import interactionPlugin from '@fullcalendar/interaction';
import listPlugin from '@fullcalendar/list';
import { onMounted, reactive, ref, watch } from 'vue';
import http, { errorMessage } from '@/api/http';
import { useToastStore } from '@/stores/toast';
import BookingFormModal from '@/components/BookingFormModal.vue';
import BookingDetailModal from '@/components/BookingDetailModal.vue';

const toast = useToastStore();
const calendar = ref(null);
const formModal = ref(null);
const detailModal = ref(null);

const rooms = ref([]);
const roomFilter = ref('');

onMounted(async () => {
    const { data } = await http.get('/rooms');
    rooms.value = data;
});

const refetch = () => calendar.value?.getApi().refetchEvents();
watch(roomFilter, refetch);

/** FullCalendar calls this whenever the visible date range changes. */
async function loadEvents(info, success, failure) {
    try {
        const { data } = await http.get('/calendar', {
            params: { start: info.startStr, end: info.endStr, room_id: roomFilter.value || undefined },
        });
        success(data);
    } catch (error) {
        toast.error(errorMessage(error, 'Could not load the calendar.'));
        failure(error);
    }
}

const options = reactive({
    plugins: [dayGridPlugin, timeGridPlugin, interactionPlugin, listPlugin],
    initialView: window.innerWidth < 768 ? 'listWeek' : 'timeGridWeek',
    headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek' },
    buttonText: { listWeek: 'list' },
    height: 'auto',
    firstDay: 1,
    nowIndicator: true,
    allDaySlot: false,
    slotMinTime: '07:00:00',
    slotMaxTime: '21:00:00',
    slotDuration: '00:30:00',
    snapDuration: '00:15:00',
    businessHours: { daysOfWeek: [1, 2, 3, 4, 5], startTime: '08:00', endTime: '18:00' },
    eventTimeFormat: { hour: '2-digit', minute: '2-digit', hour12: false },
    slotLabelFormat: { hour: '2-digit', minute: '2-digit', hour12: false },
    selectable: true,
    selectMirror: true,
    // Only block dragging over existing events when one room is shown;
    // with "all rooms" different rooms may legitimately overlap.
    selectOverlap: () => !roomFilter.value,
    selectAllow: (info) => info.start >= new Date(),
    events: loadEvents,
    select(info) {
        calendar.value.getApi().unselect();
        const allDay = info.allDay; // month view: clicked a whole day -> default to 09:00-10:00
        const start = allDay ? new Date(info.start.setHours(9, 0, 0, 0)) : info.start;
        const end = allDay ? new Date(start.getTime() + 3600_000) : info.end;
        if (start < new Date()) return;
        formModal.value.open({ start, end, roomId: roomFilter.value ? Number(roomFilter.value) : undefined });
    },
    eventClick({ event }) {
        detailModal.value.open({
            id: event.id,
            title: event.title,
            start: event.startStr,
            end: event.endStr,
            ...event.extendedProps,
        });
    },
});
</script>

<template>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h1 class="h3 mb-0">Room calendar</h1>
            <p class="text-body-secondary small mb-0">Drag across free time to book. Dashed = waiting for approval.</p>
        </div>
        <div class="d-flex gap-2">
            <select v-model="roomFilter" class="form-select" aria-label="Filter by room">
                <option value="">All rooms</option>
                <option v-for="room in rooms" :key="room.id" :value="room.id">{{ room.name }}</option>
            </select>
            <button class="btn btn-primary text-nowrap" :disabled="!rooms.length" @click="formModal.open({ roomId: roomFilter ? Number(roomFilter) : undefined })">
                <i class="bi bi-plus-lg me-1"></i>New booking
            </button>
        </div>
    </div>

    <div class="d-flex flex-wrap gap-3 mb-3 small">
        <span v-for="room in rooms" :key="room.id"><span class="room-swatch me-1" :style="{ background: room.color }"></span>{{ room.name }}</span>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <FullCalendar ref="calendar" :options="options">
                <template #eventContent="arg">
                    <div class="px-1 overflow-hidden">
                        <div class="fw-semibold text-truncate">{{ arg.timeText }} {{ arg.event.title }}</div>
                        <div class="small text-truncate opacity-75">
                            {{ arg.event.extendedProps.room }}<span v-if="arg.event.extendedProps.status === 'pending' && arg.view.type !== 'listWeek'"> · pending</span>
                        </div>
                    </div>
                </template>
            </FullCalendar>
        </div>
    </div>

    <BookingFormModal ref="formModal" :rooms="rooms" @created="refetch" />
    <BookingDetailModal ref="detailModal" @changed="refetch" />
</template>
