import { startWhatsApp, stopWhatsApp, publishRideToWhatsApp } from './whatsapp.js';
import { config } from './config.js';
import { dashboard, startDashboard } from './dashboard.js';

process.on('unhandledRejection', (error) => dashboard.log.error({ error: error.message }, 'Unhandled promise rejection'));
process.on('uncaughtException', (error) => dashboard.log.error({ error: error.message }, 'Uncaught exception'));

for (const signal of ['SIGINT', 'SIGTERM']) {
  process.once(signal, () => {
    dashboard.log.info({ signal }, 'Closing WhatsApp connection');
    stopWhatsApp();
    process.exit(0);
  });
}

dashboard.setRidePublisher(publishRideToWhatsApp);
startDashboard(config);
startWhatsApp().catch((error) => {
  dashboard.log.error({ error: error.message }, 'Unable to start WhatsApp bridge');
  process.exitCode = 1;
});
