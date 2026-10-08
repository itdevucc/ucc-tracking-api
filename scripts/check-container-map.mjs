import fs from 'node:fs';
import assert from 'node:assert/strict';

const draws = [];
let destroyed = 0;
globalThis.testMount = async (_, points) => {
    draws.push(points);
    return { destroy() { destroyed++; } };
};
const source = fs.readFileSync(new URL('../resources/js/tracking-dashboard.js', import.meta.url), 'utf8')
    .replace("import { mountMaritimeMap } from './maritime-map';", 'const mountMaritimeMap = globalThis.testMount;');
const { default: create } = await import('data:text/javascript;base64,' + Buffer.from(source).toString('base64'));
const event = (id, locationId, type = 'SEA') => ({
    id, event_code: 'DISC', route_type: type, is_actual: true,
    location: {id: locationId, lat: 10, lng: 20},
});
const state = create();
state.$nextTick = async () => {};
state.$refs = { maritimeMap: {} };
state.shipment = {containers: [
    {id: 1, events: [event(11, 100), event(12, 100, 'LAND'), {id: 13, location: null}]},
    {id: 2, events: [event(21, 200)]},
]};
await state.selectContainer(1);
assert.deepEqual(draws.at(-1).map(point => point.event.id), [11]);
assert.equal(state.events.length, 3);
await state.selectContainer(2);
assert.deepEqual(draws.at(-1).map(point => point.event.id), [21]);
assert.equal(state.events[0].id, 21);
await Promise.all([state.selectContainer(1), state.selectContainer(2)]);
assert.deepEqual(draws.at(-1).map(point => point.event.id), [21]);
await state.selectContainer(999);
assert.deepEqual(draws.at(-1), []);
assert.deepEqual(state.events, []);
state.destroy();
assert(destroyed >= 3);
console.log('OK: rutas separadas por contenedor, prioridad SEA, coordenadas ausentes, cambios rápidos y selección vacía.');
