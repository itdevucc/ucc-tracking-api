import { mountMaritimeMap } from './maritime-map';
import { routePointsForEvents, formatTrackingDate } from './tracking-presentation';

export default () => {
    let mapController = null;
    let mapVersion = 0;
    let mapRender = Promise.resolve();
    return {
    booking: '', carrier: '', shipment: null, selected: '', loading: false, error: '',
    mapError: '', raw: null,
    init() { if (this.$root.dataset.direct === '1') this.carrier = 'MSCU'; },
    destroy() { mapVersion++; mapController?.destroy(); },
    get selectedContainer() {
        return this.shipment?.containers.find(item => String(item.id) === this.selected) || null;
    },
    get events() {
        if (!this.shipment) return [];
        return this.selectedContainer?.events || this.shipment.events || [];
    },
    async selectContainer(id) {
        this.selected = String(id);
        await this.renderMap();
    },
    async renderMap() {
        const version = ++mapVersion;
        const points = routePointsForEvents(this.events);
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
        return formatTrackingDate(value);
    },
    async search() {
        if (this.loading) return;
        const booking = this.booking.trim();
        if (!booking) { this.error = 'Ingresa un número de booking.'; return; }
        mapVersion++; mapController?.destroy(); mapController = null;
        this.loading = true; this.error = ''; this.mapError = ''; this.shipment = null; this.selected = ''; this.raw = null;
        try {
            const url = new URL(`${this.$root.dataset.searchUrl}/${encodeURIComponent(booking)}`, window.location.origin);
            if (this.carrier) url.searchParams.set('carrier', this.carrier);
            const response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            if (response.status === 401 || response.status === 419 || response.redirected) {
                window.location.assign(this.$root.dataset.loginUrl);
                return;
            }
            if (response.status === 403) throw new Error('Verifica tu correo para consultar el tracking.');
            if (this.$root.dataset.direct === '1' && !response.ok) {
                const failure = await response.json().catch(() => ({}));
                throw new Error(response.status === 429 ? 'Demasiadas consultas. Espera un minuto e intenta nuevamente.' : failure.message || 'No fue posible consultar la naviera.');
            }
            if (response.status === 404) throw new Error('No se encontró tracking guardado para este booking y naviera.');
            if (!response.ok) throw new Error('No se pudo consultar el tracking. Intenta nuevamente.');
            const result = await response.json();
            if (!result.data) throw new Error('No se encontró tracking para este booking.');
            this.shipment = result.data;
            this.raw = result.raw ?? null;
            this.selected = String(this.shipment.containers[0]?.id || '');
            await this.renderMap();
        } catch (error) {
            this.error = error instanceof TypeError ? 'No se pudo conectar. Revisa tu conexión.' : error.message;
        } finally { this.loading = false; }
    },
    };
};
