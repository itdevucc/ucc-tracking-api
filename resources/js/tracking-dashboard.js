import { mountMaritimeMap } from './maritime-map';

export default () => {
    let mapController = null;
    return {
    booking: '', carrier: '', shipment: null, selected: '', loading: false, error: '',
    mapError: '',
    destroy() { mapController?.destroy(); },
    get events() {
        if (!this.shipment) return [];
        const container = this.shipment.containers.find(item => String(item.id) === this.selected);
        return container ? container.events : this.shipment.events;
    },
    date(value) {
        if (!value) return '—';
        const date = new Date(value);
        return Number.isNaN(date.getTime()) ? '—' : new Intl.DateTimeFormat('es-GT', { dateStyle: 'medium', timeStyle: 'short' }).format(date);
    },
    async search() {
        if (this.loading) return;
        const booking = this.booking.trim();
        if (!booking) { this.error = 'Ingresa un número de booking.'; return; }
        mapController?.destroy(); mapController = null;
        this.loading = true; this.error = ''; this.mapError = ''; this.shipment = null; this.selected = '';
        try {
            const url = new URL(`${this.$root.dataset.searchUrl}/${encodeURIComponent(booking)}`, window.location.origin);
            if (this.carrier) url.searchParams.set('carrier', this.carrier);
            const response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            if (response.status === 401 || response.status === 419 || response.redirected) throw new Error('Tu sesión expiró. Inicia sesión nuevamente.');
            if (response.status === 403) throw new Error('Verifica tu correo para consultar el tracking.');
            if (response.status === 404) throw new Error('No se encontró tracking guardado para este booking y naviera.');
            if (!response.ok) throw new Error('No se pudo consultar el tracking. Intenta nuevamente.');
            const result = await response.json();
            if (!result.data) throw new Error('No se encontró tracking para este booking.');
            this.shipment = result.data;
            await this.$nextTick();
            try {
                mapController = await mountMaritimeMap(this.$refs.maritimeMap, this.shipment.route_points || [], message => { this.mapError = message; });
            } catch {
                this.mapError = 'No fue posible cargar el mapa. Los eventos siguen disponibles.';
            }
        } catch (error) {
            this.error = error instanceof TypeError ? 'No se pudo conectar. Revisa tu conexión.' : error.message;
        } finally { this.loading = false; }
    },
    };
};
