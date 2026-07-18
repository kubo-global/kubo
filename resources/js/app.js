import { createApp } from 'vue';
import axios from 'axios';

import OfferingsForSchoolyear from './components/OfferingsForSchoolyear.vue';
import OfferingsForGrade from './components/OfferingsForGrade.vue';
import Offering from './components/Offering.vue';

window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

const app = createApp({});
app.component('offerings-for-schoolyear', OfferingsForSchoolyear);
app.component('offerings-for-grade', OfferingsForGrade);
app.component('offering', Offering);
app.mount('#app');
