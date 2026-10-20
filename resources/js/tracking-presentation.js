export function routePointsForEvents(events) {
    return events
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
}

export function formatTrackingDate(value) {
    if (!value) return '—';
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? '—' : new Intl.DateTimeFormat('es-GT', { dateStyle: 'medium', timeStyle: 'short' }).format(date);
}
