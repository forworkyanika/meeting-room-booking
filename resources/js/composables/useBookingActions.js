import { ref } from 'vue';
import http, { errorMessage } from '@/api/http';
import { useToastStore } from '@/stores/toast';

/** Cancel / approve / reject with one shared loading flag and toast messages. */
export function useBookingActions() {
    const toast = useToastStore();
    const busy = ref(false);

    async function run(request, successMessage) {
        busy.value = true;
        try {
            const { data } = await request();
            toast.success(successMessage);
            return data;
        } catch (error) {
            toast.error(errorMessage(error));
            return null;
        } finally {
            busy.value = false;
        }
    }

    return {
        busy,
        cancel: (booking) => run(() => http.patch(`/bookings/${booking.id}/cancel`), 'Booking cancelled.'),
        approve: (booking, note = null) => run(() => http.patch(`/admin/bookings/${booking.id}/approve`, { note }), 'Booking approved.'),
        reject: (booking, note) => run(() => http.patch(`/admin/bookings/${booking.id}/reject`, { note }), 'Booking rejected.'),
    };
}
