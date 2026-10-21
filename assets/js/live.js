/*
 * Live regions: parts of a page that refresh themselves with HTML rendered by the server.
 *
 * - [data-live-region]: its content is the fragment the same URL returns to a fetch()
 *   carrying "X-Requested-With: fetch". While the fragment contains [data-pending]
 *   (an application the AI has not finished), it is fetched again every POLL_MS.
 * - form[data-live-filters="<region id>"]: a plain GET form. Typing or changing a field
 *   reloads the region with the new filters and updates the URL, so it can be shared.
 *   Without JavaScript the form still works: it submits and the page reloads.
 * - a[data-live-link] inside a region (pagination) loads in place too.
 * - [data-live-status]: a visually hidden aria-live element next to the region. Screen readers
 *   hear the region's [data-live-summary] when it changes, not the whole list on every poll.
 */

const POLL_MS = 2000;
// After ~5 minutes something is wrong (a worker down?): stop and say so instead of polling forever.
const MAX_POLLS = 150;
const DEBOUNCE_MS = 250;

class LiveRegion {
    constructor(element) {
        this.element = element;
        this.url = window.location.href;
        this.controller = null;
        this.timer = null;
        this.polls = 0;
        this.status = document.getElementById(element.dataset.liveStatus ?? '');
        this.lastSummary = this.summary();

        element.addEventListener('click', (event) => {
            if (event.target.closest('[data-live-retry]')) {
                this.load();
                return;
            }
            const link = event.target.closest('a[data-live-link]');
            if (link && !event.metaKey && !event.ctrlKey) {
                event.preventDefault();
                this.navigate(link.href);
            }
        });

        this.schedulePoll();
    }

    navigate(url) {
        window.history.replaceState(null, '', url);
        this.polls = 0;
        return this.load(url);
    }

    async load(url = this.url) {
        this.url = url;
        clearTimeout(this.timer);
        // A newer request (another keystroke) cancels the one in flight, so results never arrive out of order.
        this.controller?.abort();
        const controller = new AbortController();
        this.controller = controller;
        this.element.setAttribute('aria-busy', 'true');

        try {
            const response = await fetch(url, {
                headers: { 'X-Requested-With': 'fetch' },
                signal: controller.signal,
            });
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            this.element.innerHTML = await response.text();
            this.syncTitle();
            this.announce();
        } catch (error) {
            if (error.name === 'AbortError') {
                return;
            }
            console.warn('Could not refresh the page section:', error);
            this.showError();
        } finally {
            // Only the latest request owns the busy state; an aborted one must not clear it.
            if (this.controller === controller) {
                this.element.removeAttribute('aria-busy');
            }
        }

        this.schedulePoll();
    }

    /**
     * What is on screen may no longer match the filters (or the latest status): say so,
     * instead of silently showing stale results.
     */
    showError() {
        this.element.querySelector('[data-live-error]')?.remove();
        this.element.insertAdjacentHTML(
            'afterbegin',
            '<div class="alert alert--error m-5" role="alert" data-live-error>'
                + '<p class="alert__text">Could not update this section, so it may be out of date. '
                + 'Check your connection.</p>'
                + '<button type="button" class="btn btn--sm" data-live-retry>Try again</button></div>',
        );
    }

    /** A fragment may carry the page title (the detail page learns the candidate's name once filed). */
    syncTitle() {
        const title = this.element.querySelector('[data-page-title]')?.dataset.pageTitle;
        if (title) {
            // A function, not a string: a name such as "$1" must not be read as a replacement pattern.
            document.title = document.title.replace(/^.*?( · )/, (match, separator) => title + separator);
            document.querySelector('.topbar__title')?.replaceChildren(title);
        }
    }

    summary() {
        return this.element.querySelector('[data-live-summary]')?.textContent.replace(/\s+/g, ' ').trim() ?? '';
    }

    announce() {
        const summary = this.summary();
        if (this.status && summary !== this.lastSummary) {
            this.status.textContent = summary;
        }
        this.lastSummary = summary;
    }

    schedulePoll() {
        clearTimeout(this.timer);
        if (!this.element.querySelector('[data-pending]')) {
            return;
        }

        if (this.polls >= MAX_POLLS) {
            this.element.querySelector('[data-pending]').insertAdjacentHTML(
                'afterbegin',
                '<p class="alert alert--warning m-5" role="status">This is taking longer than usual. '
                    + 'Refresh the page in a while to see the result.</p>',
            );
            return;
        }

        this.timer = setTimeout(() => {
            if (document.hidden) {
                this.schedulePoll();
                return;
            }
            this.polls += 1;
            this.load();
        }, POLL_MS);
    }
}

function filtersUrl(form) {
    const params = new URLSearchParams();
    for (const [name, value] of new FormData(form)) {
        if (String(value).trim() !== '') {
            params.set(name, String(value).trim());
        }
    }
    const query = params.toString();

    return form.action + (query ? `?${query}` : '');
}

function initLiveFilters(form, region) {
    let debounce = null;
    const refresh = () => region.navigate(filtersUrl(form));

    form.addEventListener('input', (event) => {
        clearTimeout(debounce);
        debounce = setTimeout(refresh, event.target.type === 'search' ? DEBOUNCE_MS : 0);
    });
    form.addEventListener('submit', (event) => {
        event.preventDefault();
        clearTimeout(debounce);
        refresh();
    });
    form.querySelector('[data-live-reset]')?.addEventListener('click', (event) => {
        event.preventDefault();
        form.reset();
        form.querySelectorAll('input, select').forEach((field) => { field.value = ''; });
        refresh();
    });
}

export function initLiveRegions(root) {
    const regions = new Map();
    root.querySelectorAll('[data-live-region]').forEach((element) => {
        regions.set(element.id, new LiveRegion(element));
    });

    root.querySelectorAll('form[data-live-filters]').forEach((form) => {
        const region = regions.get(form.dataset.liveFilters);
        if (region) {
            initLiveFilters(form, region);
        }
    });
}
