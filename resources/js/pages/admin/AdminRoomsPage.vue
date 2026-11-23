<script setup>
import { onMounted, reactive, ref } from 'vue';
import http, { errorMessage, validationErrors } from '@/api/http';
import BaseModal from '@/components/BaseModal.vue';
import { useToastStore } from '@/stores/toast';

const toast = useToastStore();

const rooms = ref([]);
const loading = ref(true);
const modal = ref(null);
const saving = ref(false);
const errors = ref({});
const editingId = ref(null);
const form = reactive({ name: '', location: '', capacity: 6, description: '', amenitiesText: '', color: '#2563eb', is_active: true });

async function load() {
    loading.value = true;
    const { data } = await http.get('/rooms', { params: { include_inactive: 1 } });
    rooms.value = data;
    loading.value = false;
}

function openForm(room = null) {
    editingId.value = room?.id ?? null;
    Object.assign(form, {
        name: room?.name ?? '',
        location: room?.location ?? '',
        capacity: room?.capacity ?? 6,
        description: room?.description ?? '',
        amenitiesText: (room?.amenities ?? []).join(', '),
        color: room?.color ?? '#2563eb',
        is_active: room?.is_active ?? true,
    });
    errors.value = {};
    modal.value.open();
}

function payload() {
    const { amenitiesText, ...rest } = form;
    return { ...rest, amenities: amenitiesText.split(',').map((a) => a.trim()).filter(Boolean) };
}

async function save() {
    saving.value = true;
    errors.value = {};
    try {
        if (editingId.value) await http.put(`/admin/rooms/${editingId.value}`, payload());
        else await http.post('/admin/rooms', payload());
        toast.success(editingId.value ? 'Room updated.' : 'Room created.');
        modal.value.close();
        load();
    } catch (error) {
        errors.value = validationErrors(error);
        if (!Object.keys(errors.value).length) toast.error(errorMessage(error));
    } finally {
        saving.value = false;
    }
}

async function toggleActive(room) {
    try {
        await http.put(`/admin/rooms/${room.id}`, { ...room, is_active: !room.is_active });
        toast.success(room.is_active ? `${room.name} deactivated.` : `${room.name} reactivated.`);
        load();
    } catch (error) {
        toast.error(errorMessage(error));
    }
}

async function remove(room) {
    if (!confirm(`Delete ${room.name}? This cannot be undone.`)) return;
    try {
        await http.delete(`/admin/rooms/${room.id}`);
        toast.success('Room deleted.');
        load();
    } catch (error) {
        toast.error(errorMessage(error));
    }
}

onMounted(load);
</script>

<template>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Manage rooms</h1>
        <button class="btn btn-primary" @click="openForm()"><i class="bi bi-plus-lg me-1"></i>Add room</button>
    </div>

    <div class="card shadow-sm">
        <div v-if="loading" class="text-center py-5"><div class="spinner-border text-primary"></div></div>
        <div v-else class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr><th>Room</th><th>Location</th><th>Capacity</th><th>Amenities</th><th>Status</th><th class="text-end">Actions</th></tr>
                </thead>
                <tbody>
                    <tr v-for="room in rooms" :key="room.id" :class="{ 'text-body-secondary': !room.is_active }">
                        <td class="fw-semibold text-nowrap"><span class="room-swatch me-2" :style="{ background: room.color }"></span>{{ room.name }}</td>
                        <td>{{ room.location }}</td>
                        <td>{{ room.capacity }}</td>
                        <td class="small">{{ room.amenities.join(', ') }}</td>
                        <td>
                            <span class="badge" :class="room.is_active ? 'text-bg-success' : 'text-bg-secondary'">{{ room.is_active ? 'Active' : 'Inactive' }}</span>
                        </td>
                        <td class="text-end text-nowrap">
                            <button class="btn btn-sm btn-outline-primary me-1" @click="openForm(room)" aria-label="Edit"><i class="bi bi-pencil"></i></button>
                            <button class="btn btn-sm btn-outline-secondary me-1" @click="toggleActive(room)">{{ room.is_active ? 'Deactivate' : 'Activate' }}</button>
                            <button class="btn btn-sm btn-outline-danger" @click="remove(room)" aria-label="Delete"><i class="bi bi-trash"></i></button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <BaseModal ref="modal" :title="editingId ? 'Edit room' : 'Add room'">
        <form id="room-form" novalidate @submit.prevent="save">
            <div class="row g-3">
                <div class="col-8">
                    <label class="form-label" for="r-name">Name</label>
                    <input id="r-name" v-model.trim="form.name" class="form-control" :class="{ 'is-invalid': errors.name }" required />
                    <div class="invalid-feedback">{{ errors.name }}</div>
                </div>
                <div class="col-4">
                    <label class="form-label" for="r-color">Colour</label>
                    <input id="r-color" v-model="form.color" type="color" class="form-control form-control-color w-100" />
                </div>
                <div class="col-8">
                    <label class="form-label" for="r-loc">Location</label>
                    <input id="r-loc" v-model.trim="form.location" class="form-control" placeholder="Floor 5" />
                </div>
                <div class="col-4">
                    <label class="form-label" for="r-cap">Capacity</label>
                    <input id="r-cap" v-model.number="form.capacity" type="number" min="1" class="form-control" :class="{ 'is-invalid': errors.capacity }" />
                    <div class="invalid-feedback">{{ errors.capacity }}</div>
                </div>
                <div class="col-12">
                    <label class="form-label" for="r-amen">Amenities <span class="text-body-secondary">(comma separated)</span></label>
                    <input id="r-amen" v-model="form.amenitiesText" class="form-control" placeholder="Projector, Whiteboard" />
                </div>
                <div class="col-12">
                    <label class="form-label" for="r-desc">Description</label>
                    <textarea id="r-desc" v-model="form.description" class="form-control" rows="2"></textarea>
                </div>
                <div class="col-12 form-check form-switch ms-2">
                    <input id="r-active" v-model="form.is_active" class="form-check-input" type="checkbox" role="switch" />
                    <label class="form-check-label" for="r-active">Available for booking</label>
                </div>
            </div>
        </form>
        <template #footer>
            <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" form="room-form" class="btn btn-primary" :disabled="saving">Save</button>
        </template>
    </BaseModal>
</template>
