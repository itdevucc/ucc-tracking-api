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
<template x-if="shipment"><div class="mt-6 space-y-6">
<section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
<div class="flex flex-wrap justify-between gap-4"><div><span class="rounded bg-red-50 px-3 py-1 text-sm font-semibold text-red-600" x-text="shipment.carrier_name"></span><h2 class="mt-3 text-xl font-semibold" x-text="'Tracking · ' + shipment.booking_number"></h2></div><div class="text-sm text-gray-500"><p class="font-semibold text-gray-700" x-text="shipment.shipping_status"></p><p x-text="'Última consulta: ' + date(shipment.last_synced_at)"></p></div></div>
<dl class="mt-6 grid grid-cols-2 gap-5 border-t pt-5 lg:grid-cols-5">
<div><dt class="text-xs text-gray-500">ETD</dt><dd class="mt-1 font-semibold" x-text="date(shipment.etd)"></dd></div>
<div><dt class="text-xs text-gray-500">ETA</dt><dd class="mt-1 font-semibold" x-text="date(shipment.eta)"></dd></div>
<div><dt class="text-xs text-gray-500">BL</dt><dd class="mt-1 break-all font-semibold" x-text="shipment.bl_number || '—'"></dd></div>
<div><dt class="text-xs text-gray-500">Nave</dt><dd class="mt-1 font-semibold" x-text="shipment.vessel_name || '—'"></dd></div>
<div><dt class="text-xs text-gray-500">Contenedores</dt><dd class="mt-1 font-semibold" x-text="shipment.container_count"></dd></div></dl>
<div class="mt-5 flex flex-wrap gap-3 rounded-lg bg-gray-50 p-4 text-sm font-semibold"><span x-text="shipment.pol_name || 'Origen sin informar'"></span><span class="text-red-600">→</span><span x-text="shipment.pod_name || 'Destino sin informar'"></span></div>
</section>
<section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
<div class="mb-4"><h2 class="font-semibold text-gray-800">Ruta del contenedor</h2><p class="mt-1 text-sm text-gray-500" x-text="selectedContainer ? selectedContainer.container_number : 'Sin contenedores disponibles'"></p><div class="mt-3 flex flex-wrap gap-2"><template x-for="container in shipment.containers" :key="container.id"><button type="button" @click="selectContainer(container.id)" :aria-pressed="selected === String(container.id)" :class="selected === String(container.id) ? 'bg-red-600 text-white' : 'text-gray-600'" class="rounded-lg border px-4 py-2 text-sm font-semibold" x-text="container.container_number"></button></template></div></div>
<div class="maritime-map-wrap"><div x-ref="maritimeMap" class="maritime-map" role="region" aria-label="Mapa de la ruta marítima"></div></div>
<p x-cloak x-show="mapError" class="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800" x-text="mapError"></p>
<div class="mt-4 flex flex-wrap gap-5 text-xs text-gray-600"><span><i class="map-legend-line map-actual"></i>Recorrido real</span><span><i class="map-legend-line map-planned"></i>Previsto</span><span><i class="map-legend-line map-land"></i>Tramo terrestre</span></div>
<p class="mt-3 text-xs text-gray-400">Los tramos marítimos siguen una red de navegación; no representan la posición ni la derrota exacta del buque. Los tramos terrestres son esquemáticos.</p>
</section>
<section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
<div class="border-b p-6"><h2 class="font-semibold">Eventos del embarque</h2><div class="mt-4 flex flex-wrap gap-2">
<template x-for="container in shipment.containers" :key="container.id"><button type="button" @click="selectContainer(container.id)" :aria-pressed="selected === String(container.id)" :class="selected === String(container.id) ? 'bg-red-600 text-white' : 'text-gray-600'" class="rounded-lg border px-4 py-2 text-sm font-semibold" x-text="container.container_number"></button></template></div><p class="mt-3 text-sm text-gray-500" x-text="events.length + ' eventos · Fechas en tu zona horaria'"></p></div>
<div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead class="bg-gray-50 text-xs uppercase text-gray-500"><tr><th class="px-6 py-4">Fecha</th><th class="px-4 py-4">Condición</th><th class="px-4 py-4">Evento</th><th class="px-4 py-4">Lugar</th><th class="px-4 py-4">Transporte</th><th class="px-4 py-4">Nave / viaje</th></tr></thead><tbody class="divide-y">
<template x-for="event in events" :key="event.id"><tr class="hover:bg-gray-50"><td class="whitespace-nowrap px-6 py-4" x-text="date(event.event_date)"></td><td class="px-4 py-4"><span class="rounded-full px-2 py-1 text-xs font-semibold" :class="event.is_actual ? 'bg-green-50 text-green-700' : 'bg-amber-50 text-amber-700'" x-text="event.is_actual ? 'Real' : (event.event_classifier_code === 'EST' ? 'Estimado' : (event.event_classifier_code === 'PLN' ? 'Planificado' : event.event_classifier_code || 'Sin clasificar'))"></span></td><td class="px-4 py-4"><span class="font-medium" x-text="event.description || event.event_code || '—'"></span><small class="mt-1 block text-gray-400" x-text="event.event_code"></small></td><td class="px-4 py-4"><span x-text="event.location?.name || '—'"></span><small class="mt-1 block text-gray-400" x-text="event.facility?.name || event.location?.locode || ''"></small></td><td class="px-4 py-4" x-text="event.transport_type || '—'"></td><td class="px-4 py-4" x-text="[event.vessel?.name, event.voyage].filter(Boolean).join(' / ') || '—'"></td></tr></template>
<tr x-show="!events.length"><td colspan="6" class="px-6 py-12 text-center text-gray-500">Todavía no hay eventos guardados.</td></tr>
</tbody></table></div></section></div></template></div></div>
</x-app-layout>
