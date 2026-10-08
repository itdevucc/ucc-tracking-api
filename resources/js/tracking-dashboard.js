import { mountMaritimeMap } from './maritime-map';

export default () => {
    let mapController = null;
    let mapVersion = 0;
    let mapRender = Promise.resolve();
    return {
    booking: '', carrier: '', shipment: null, selected: '', loading: false, error: '',
    mapError: '',
    destroy() { mapVersion++; mapController?.destroy(); },
    get selectedContainer() {
        return this.shipment?.containers.find(item => String(item.id) === this.selected) || null;
    },
    get events() {
        if (!this.shipment) return [];
        return this.selectedContainer?.events || [];
    },
    async selectContainer(id) {
        this.selected = String(id);
        await this.renderMap();
    },
    async renderMap() {
        const version = ++mapVersion;
        const points = this.events
            .filter(event => event.location?.lat != null && event.location?.lng != null)
            .map(event => ({
                lat: Number(event.location.lat), lng: Number(event.location.lng),
                date: event.event_date, is_actual: event.is_actual,
                route_type: event.route_type, transport_type: event.transport_type,
                location: event.location,
                event: {
                    id: event.id, code: event.event_code, description: event.description,
                    type: event.event_type, classifier_code: event.event_classifier_code,
                    status: event.status, date: event.event_date, is_actual: event.is_actual,
                    transport_type: event.transport_type, vessel_name: event.vessel?.name,
                    voyage: event.voyage,
                },
            }))
            .filter(point => Number.isFinite(point.lat) && Number.isFinite(point.lng))
            .reduce((points, point) => {
                const previous = points.at(-1);
                if (!previous || previous.location.id !== point.location.id) points.push(point);
                else if (point.route_type === 'SEA' || previous.route_type !== 'SEA') points[points.length - 1] = point;
                return points;
            }, []);
        mapRender = mapRender.then(async () => {
            if (version !== mapVersion) return;
            mapController?.destroy(); mapController = null;
            this.mapError = '';
            await this.$nextTick();
            if (version !== mapVersion) return;
            try {
                const controller = await mountMaritimeMap(this.$refs.maritimeMap, points, message => {
                    if (version === mapVersion) this.mapError = message;
                });
                if (version !== mapVersion) controller.destroy();
                else mapController = controller;
            } catch {
                if (version === mapVersion) this.mapError = 'No fue posible cargar el mapa. Los eventos siguen disponibles.';
            }
        });
        await mapRender;
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
        mapVersion++; mapController?.destroy(); mapController = null;
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
            this.selected = String(this.shipment.containers[0]?.id || '');
            await this.renderMap();
        } catch (error) {
            this.error = error instanceof TypeError ? 'No se pudo conectar. Revisa tu conexión.' : error.message;
        } finally { this.loading = false; }
    },
    };
};
