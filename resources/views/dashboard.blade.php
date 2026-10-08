<x-app-layout>
<x-slot name="header"><p class="text-xs font-semibold uppercase tracking-widest text-red-600">UCC · Tracking marítimo</p><h1 class="mt-1 text-2xl font-semibold text-gray-800">Buscar booking</h1></x-slot>
<div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8" x-data="trackingDashboard" data-search-url="{{ url('/dashboard/tracking') }}">
<section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
<h2 class="text-lg font-semibold text-gray-800">Consulta tu embarque</h2><p class="mt-1 text-sm text-gray-500">Busca el número de booking de la naviera para ver su tracking guardado.</p>
<form class="mt-5 flex flex-wrap items-end gap-4" @submit.prevent="search">
<div class="min-w-0 flex-1"><label for="booking" class="mb-2 block text-sm font-medium">Número de booking</label><input id="booking" x-model="booking" required maxlength="100" autocomplete="off" placeholder="Ej. 070IVA0010718" class="w-full rounded-lg border-gray-300 focus:border-red-500 focus:ring-red-500"></div>
<div><label for="carrier" class="mb-2 block text-sm font-medium">Naviera</label><select id="carrier" x-model="carrier" class="rounded-lg border-gray-300"><option value="">Todas</option><option value="MSCU">MSC</option><option value="HLCU">Hapag-Lloyd</option></select></div>
<button :disabled="loading" class="rounded-lg bg-red-600 px-6 py-2.5 font-semibold text-white hover:bg-red-700 disabled:opacity-50" x-text="loading ? 'Buscando…' : 'Buscar tracking'"></button>
</form></section>
<p x-cloak x-show="error" role="alert" class="mt-5 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700" x-text="error"></p>
<div aria-live="polite" :aria-busy="loading">
<section x-show="!shipment && !loading && !error" class="py-20 text-center"><h2 class="font-semibold text-gray-700">Todo el recorrido en un solo lugar</h2><p class="mt-2 text-sm text-gray-500">Consulta naviera, fechas y eventos de tus contenedores.</p></section>
<template x-if="shipment">
<div class="ucc-tracking-detail mt-6">
    <header class="tracking-header">
        <div>
            <h2 x-text="'Tracking · ' + shipment.booking_number"></h2>
            <div class="tracking-facts">
                <span class="carrier-badge" x-text="shipment.sealine_name || shipment.carrier_name"></span>
                <span>Naviera de salida <strong x-text="shipment.carrier_departure_name || shipment.carrier_name"></strong></span>
                <span>ETD <strong x-text="date(shipment.etd || shipment.pol_etd)"></strong></span>
                <span>ETA <strong x-text="date(shipment.eta || shipment.pod_eta)"></strong></span>
                <span>BL <strong x-text="shipment.transport_document_reference || shipment.bl_number || '—'"></strong></span>
                <span>Nave <strong x-text="shipment.vessel?.name || shipment.vessel_name || '—'"></strong></span>
                <span>Contenedores <strong x-text="shipment.container_count || shipment.containers.length"></strong></span>
            </div>
            <div class="tracking-source" x-text="(shipment.tracking_source || shipment.carrier_name) + ' · Última consulta: ' + date(shipment.last_synced_at)"></div>
        </div>
    </header>
    <div class="tracking-content">
        <div class="container-selector" x-show="shipment.containers.length">
            <span>Contenedor</span>
            <template x-for="container in shipment.containers" :key="container.id">
                <button type="button" class="container-chip" :class="{ active: selected === String(container.id) }" :aria-pressed="selected === String(container.id)" @click="selectContainer(container.id)">
                    <strong x-text="container.container_number || 'Sin número'"></strong>
                    <small x-show="shipment.transport_document_reference" x-text="'BL ' + (shipment.transport_document_reference || '')"></small>
                </button>
            </template>
        </div>
        <div class="maritime-map-wrap"><div x-ref="maritimeMap" class="maritime-map" role="region" aria-label="Mapa de la ruta del contenedor seleccionado"></div></div>
        <p x-cloak x-show="mapError" class="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800" x-text="mapError"></p>
        <div class="map-legend"><span><i class="map-legend-line map-actual"></i>Recorrido real</span><span><i class="map-legend-line map-planned"></i>Previsto</span><span><i class="map-legend-line map-land"></i>Tramo terrestre</span></div>
        <section class="events-section" x-show="selectedContainer">
            <div class="events-title">
                <strong x-text="'Contenedor ' + (selectedContainer?.container_number || '')"></strong>
                <span class="bl-chip" x-show="shipment.transport_document_reference" x-text="'BL ' + (shipment.transport_document_reference || '')"></span>
                <span x-text="(selectedContainer?.equipment_type || '—') + ' · ' + events.length + ' eventos'"></span>
            </div>
            <div class="table-responsive">
                <table class="tracking-events-table">
                    <thead><tr><th>Fecha</th><th>Estado</th><th>Evento</th><th>Lugar</th><th>Tipo</th><th>Nave / viaje</th></tr></thead>
                    <tbody>
                        <template x-for="event in events" :key="event.id">
                            <tr>
                                <td class="whitespace-nowrap" x-text="date(event.event_date)"></td>
                                <td :class="event.is_actual ? 'actual' : 'planned'" x-text="event.is_actual ? 'Real' : 'Previsto'"></td>
                                <td><span x-text="event.description || '—'"></span><small x-text="event.event_code || ''"></small></td>
                                <td><span x-text="[event.location?.name, event.location?.country_code].filter(Boolean).join(', ') || event.location?.locode || '—'"></span><small x-text="event.facility?.name || event.facility?.smdg_code || ''"></small></td>
                                <td><span x-text="event.event_type || event.transport_type || '—'"></span><small x-text="event.transport_type || ''"></small></td>
                                <td x-text="[event.vessel?.name, event.voyage].filter(Boolean).join(' / ') || '—'"></td>
                            </tr>
                        </template>
                        <tr x-show="!events.length"><td colspan="6" class="empty-events">Sin eventos para este contenedor</td></tr>
                    </tbody>
                </table>
            </div>
        </section>
        <p x-show="!shipment.containers.length" class="empty-events py-6">Sin contenedores disponibles</p>
    </div>
</div>
</template>
</div>
</div>
</x-app-layout>
