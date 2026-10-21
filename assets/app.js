/*
 * Entry point loaded on every page through the importmap (no bundler, no framework).
 */
import './styles/app.css';
import { initLiveRegions } from './js/live.js';
import { initToasts } from './js/toasts.js';
import { focusFirstInvalidField } from './js/forms.js';

initLiveRegions(document);
initToasts(document);
focusFirstInvalidField(document);
