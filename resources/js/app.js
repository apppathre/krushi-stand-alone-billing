import './bootstrap';

import Alpine from 'alpinejs';
import './components/form-busy';
import './components/auto-filter';
import './components/invoice-form';
import './components/voucher-number';
import './components/bulk-settle';
import './components/opening-bulk';
import './components/stock-journal-form';
import './components/bulk-items';
import './components/dashboard-board';
import './components/party-outreach';
import { registerUiKit } from './components/ui-kit';
import { registerEnToMr } from './components/en-to-mr';

window.Alpine = Alpine;
registerUiKit(Alpine);
registerEnToMr();
Alpine.start();
