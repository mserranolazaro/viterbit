/*
 * Small form helpers, all progressive: without JavaScript the forms work the same.
 */

/** After the server rejects a form (422), put the cursor on the first field to fix. */
export function focusFirstInvalidField(root) {
    root.querySelector('[aria-invalid="true"]')?.focus();
}

/** form[data-submit-once]: a double click sends one request (the server refuses duplicates anyway). */
export function preventDoubleSubmit(root) {
    root.querySelectorAll('form[data-submit-once]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (form.dataset.submitting === 'true') {
                event.preventDefault();
                return;
            }
            form.dataset.submitting = 'true';
            form.querySelectorAll('button[type="submit"]').forEach((button) => {
                button.setAttribute('aria-disabled', 'true');
                button.classList.add('is-busy');
            });
        });
    });
}
