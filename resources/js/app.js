

import Alpine from 'alpinejs';
import trackingDashboard from './tracking-dashboard';

window.Alpine = Alpine;
Alpine.data('trackingDashboard', trackingDashboard);

Alpine.start();
