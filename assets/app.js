/*
 * Entry point loaded on every page through the importmap (no bundler, no framework).
 * The CSS is a plain <link> in base.html.twig: importing it here would become a data: module,
 * which the Content-Security-Policy rightly refuses.
 */
import { initLiveRegions } from './js/live.js';
import { initToasts } from './js/toasts.js';
import { focusFirstInvalidField, preventDoubleSubmit } from './js/forms.js';

initLiveRegions(document);
initToasts(document);
focusFirstInvalidField(document);
preventDoubleSubmit(document);
