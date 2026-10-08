// Adaptado del componente OpenStreetMaritimeRoute de Sailor.
let seaRouteLoader = null;
let leafletLoader = null;
function loadLeaflet() {
  if (window.L) return Promise.resolve();
  if (leafletLoader) return leafletLoader;
  leafletLoader = new Promise((resolve, reject) => {
    const css = document.createElement('link');
    css.rel = 'stylesheet'; css.href = new URL('vendor/leaflet/leaflet.css', document.baseURI).href;
    document.head.appendChild(css);
    const script = document.createElement('script');
    script.src = new URL('vendor/leaflet/leaflet.js', document.baseURI).href;
    script.onload = resolve; script.onerror = () => { leafletLoader = null; reject(new Error('No fue posible cargar el mapa.')); };
    document.head.appendChild(script);
  });
  return leafletLoader;
}
export async function mountMaritimeMap(element, points, notify) {
  await loadLeaflet();
  const L = window.L;
  let disposed = false;
  const state = {
    $refs: { map: element }, $nextTick: callback => callback(),
    ports: points.map(point => ({ ...point, name: point.location?.name || point.location?.locode })),
    vesselPoint: null, map: null, routeLayer: null, portLayer: null,
    vesselLayer: null, mapBounds: null, resizeObserver: null, resizeTimers: [],
    _routeError: '',
    get routeError() { return this._routeError; },
    set routeError(value) { this._routeError = value; notify(value); },
    ensureSeaRouteLoaded() {
      if (window.SeaRouteTS) return Promise.resolve();
      if (seaRouteLoader) return seaRouteLoader;

      seaRouteLoader = new Promise((resolve, reject) => {
        const script = document.createElement("script");
        script.src = new URL("vendor/searoute.min.js", document.baseURI).href;
        script.async = true;
        script.onload = resolve;
        script.onerror = reject;
        document.head.appendChild(script);
      });

      return seaRouteLoader;
    },

    initMap() {
      this.map = L.map(this.$refs.map, {
        zoomControl: true,
        worldCopyJump: true,
      }).setView([10, -40], 2);

      L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
        maxZoom: 18,
        attribution: "&copy; OpenStreetMap contributors",
      }).addTo(this.map);

      this.portLayer = L.layerGroup().addTo(this.map);
      this.vesselLayer = L.layerGroup().addTo(this.map);
    },

    observeMapSize() {
      if (typeof ResizeObserver === "undefined") return;

      this.resizeObserver = new ResizeObserver(() => this.scheduleFit());
      this.resizeObserver.observe(this.$refs.map);
    },

    scheduleFit() {
      this.resizeTimers.forEach((timer) => clearTimeout(timer));
      this.resizeTimers = [0, 150, 350].map((delay) =>
        setTimeout(() => this.fitRoute(), delay)
      );
    },

    fitRoute() {
      if (!this.map || !this.mapBounds || !this.mapBounds.isValid()) return;

      const element = this.$refs.map;
      if (!element || element.clientWidth === 0 || element.clientHeight === 0)
        return;

      this.map.invalidateSize(false);
      this.map.fitBounds(this.mapBounds, {
        padding: [35, 35],
        maxZoom: 8,
        animate: false,
      });
    },

    draw() {
      if (!this.map) return;

      this.routeError = "";
      this.portLayer.clearLayers();

      if (this.routeLayer) {
        this.routeLayer.remove();
        this.routeLayer = null;
      }

      const ports = this.normalizedPorts();
      const bounds = L.latLngBounds(ports.map((port) => [port.lat, port.lng]));

      ports.forEach((port, index) => {
        const marker = L.circleMarker([port.lat, port.lng], {
          radius: index === 0 || index === ports.length - 1 ? 7 : 5,
          color: "#ffffff",
          weight: 2,
          fillColor: port.isActual ? "#168247" : "#d18b00",
          fillOpacity: 1,
        }).addTo(this.portLayer);

        const eventInfo = this.eventTooltip(port, index);
        marker.bindTooltip(eventInfo, {
          direction: "top",
          opacity: 0.96,
          className: "maritime-event-tooltip",
        });
        marker.bindPopup(eventInfo.cloneNode(true), {
          className: "maritime-event-popup",
          maxWidth: 320,
        });
      });

      if (ports.length >= 2) {
        this.routeLayer = L.layerGroup().addTo(this.map);
        let usesFallback = false;

        for (let index = 1; index < ports.length; index += 1) {
          const from = ports[index - 1];
          const to = ports[index];
          const isLand = to.routeType === "LAND";
          const segment = this.segmentCoordinates(from, to, isLand);
          const coordinates = segment.coordinates;

          usesFallback = usesFallback || segment.usesFallback;

          L.polyline(coordinates, {
            color: isLand ? "#64748b" : to.isActual ? "#168247" : "#d18b00",
            weight: 4,
            opacity: 0.95,
            dashArray: isLand || !to.isActual ? "8 8" : null,
            lineJoin: "round",
          }).addTo(this.routeLayer);

          coordinates.forEach((point) => bounds.extend(point));
        }

        if (usesFallback) {
          this.routeError =
            "Uno de los tramos no pudo conectarse a la red marítima y se muestra de forma aproximada.";
        }
      } else {
        this.routeError =
          "Se necesitan al menos dos puertos con coordenadas para calcular la ruta.";
      }

      if (bounds.isValid()) {
        this.mapBounds = bounds;
      } else {
        this.mapBounds = null;
      }

      this.drawVessel();
      this.$nextTick(() => this.scheduleFit());
    },

    segmentCoordinates(from, to, isLand) {
      if (isLand) {
        return {
          coordinates: [
            [from.lat, from.lng],
            [to.lat, to.lng],
          ],
          usesFallback: false,
        };
      }

      try {
        const route = window.SeaRouteTS.seaRoute(
          [from.lng, from.lat],
          [to.lng, to.lat],
          {
            appendOriginDestination: true,
            allowArctic: true,
          }
        );
        const routeCoordinates = route.geometry.coordinates.map((point) => [
          point[1],
          point[0],
        ]);

        if (routeCoordinates.length >= 2) {
          return { coordinates: routeCoordinates, usesFallback: false };
        }
      } catch (error) {
        // El fallback permite conservar visibles los demás tramos del booking.
      }

      return {
        coordinates: this.greatCircleCoordinates(from, to),
        usesFallback: true,
      };
    },

    greatCircleCoordinates(from, to, steps = 32) {
      const radians = (value) => (value * Math.PI) / 180;
      const degrees = (value) => (value * 180) / Math.PI;
      const lat1 = radians(from.lat);
      const lng1 = radians(from.lng);
      const lat2 = radians(to.lat);
      const lng2 = radians(to.lng);
      const distance = 2 * Math.asin(
        Math.sqrt(
          Math.sin((lat2 - lat1) / 2) ** 2 +
            Math.cos(lat1) *
              Math.cos(lat2) *
              Math.sin((lng2 - lng1) / 2) ** 2
        )
      );

      if (!Number.isFinite(distance) || distance === 0) {
        return [
          [from.lat, from.lng],
          [to.lat, to.lng],
        ];
      }

      return Array.from({ length: steps + 1 }, (_, index) => {
        const fraction = index / steps;
        const a = Math.sin((1 - fraction) * distance) / Math.sin(distance);
        const b = Math.sin(fraction * distance) / Math.sin(distance);
        const x =
          a * Math.cos(lat1) * Math.cos(lng1) +
          b * Math.cos(lat2) * Math.cos(lng2);
        const y =
          a * Math.cos(lat1) * Math.sin(lng1) +
          b * Math.cos(lat2) * Math.sin(lng2);
        const z = a * Math.sin(lat1) + b * Math.sin(lat2);

        return [degrees(Math.atan2(z, Math.sqrt(x * x + y * y))), degrees(Math.atan2(y, x))];
      });
    },

    drawVessel() {
      if (!this.map || !this.vesselLayer) return;

      this.vesselLayer.clearLayers();

      if (!this.isValidPoint(this.vesselPoint)) return;

      L.circleMarker(
        [Number(this.vesselPoint.lat), Number(this.vesselPoint.lng)],
        {
          radius: 8,
          color: "#ffffff",
          weight: 3,
          fillColor: "#dc3545",
          fillOpacity: 1,
        }
      )
        .bindTooltip("Posición actual del buque")
        .addTo(this.vesselLayer);
    },

    normalizedPorts() {
      const result = [];

      (this.ports || []).forEach((port) => {
        if (!this.isValidPoint(port)) return;

        const normalized = {
          lat: Number(port.lat),
          lng: Number(port.lng),
          name: port.name || port.locode || "Puerto",
          routeType: String(
            port.routeType || port.route_type || "SEA"
          ).toUpperCase(),
          isActual:
            port.isActual !== undefined
              ? Boolean(port.isActual)
              : Boolean(port.is_actual),
          event: port.event || null,
        };
        const previous = result[result.length - 1];

        if (
          !previous ||
          previous.lat !== normalized.lat ||
          previous.lng !== normalized.lng
        ) {
          result.push(normalized);
        }
      });

      return result;
    },

    eventTooltip(port, index) {
      const event = port.event || {};
      const content = document.createElement("div");
      const title = document.createElement("strong");
      const location = document.createElement("div");
      const meta = document.createElement("div");

      content.className = "maritime-event-info";
      title.textContent = event.description || event.code || `Punto ${index + 1}`;
      location.textContent = port.name || "Ubicación sin nombre";
      location.className = "maritime-event-location";
      meta.className = "maritime-event-meta";

      const details = [
        event.code ? `Código: ${event.code}` : null,
        event.date ? `Fecha: ${this.formatEventDate(event.date)}` : null,
        `Estado: ${port.isActual ? "Real" : "Previsto"}`,
        event.type ? `Evento: ${event.type}` : null,
        event.transport_type ? `Transporte: ${event.transport_type}` : null,
        event.vessel_name ? `Nave: ${event.vessel_name}` : null,
        event.voyage ? `Viaje: ${event.voyage}` : null,
      ].filter(Boolean);

      details.forEach((detail) => {
        const line = document.createElement("div");
        line.textContent = detail;
        meta.appendChild(line);
      });

      content.appendChild(title);
      content.appendChild(location);
      content.appendChild(meta);

      return content;
    },

    formatEventDate(value) {
      const date = new Date(value);

      if (Number.isNaN(date.getTime())) return value;

      return new Intl.DateTimeFormat("es", {
        year: "numeric",
        month: "2-digit",
        day: "2-digit",
        hour: "2-digit",
        minute: "2-digit",
      }).format(date);
    },

    isValidPoint(point) {
      if (!point || point.lat === null || point.lng === null || point.lat === "" || point.lng === "") return false;

      const lat = Number(point.lat);
      const lng = Number(point.lng);

      return (
        Number.isFinite(lat) &&
        Number.isFinite(lng) &&
        lat >= -90 &&
        lat <= 90 &&
        lng >= -180 &&
        lng <= 180
      );
    },  };
  state.initMap(); state.observeMapSize();
  const controller = {
    destroy() {
      disposed = true;
      state.resizeObserver?.disconnect();
      state.resizeTimers.forEach(clearTimeout);
      state.map?.remove(); state.map = null;
    },
  };
  // Dibuja de inmediato los puntos y usa respaldo mientras carga la red marítima.
  state.draw();
  state.ensureSeaRouteLoaded().then(() => { if (!disposed) state.draw(); })
    .catch(() => { if (!disposed) state.routeError = 'No fue posible cargar la red marítima. La ruta se muestra de forma aproximada.'; });
  return controller;
}
