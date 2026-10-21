/*
 * After the server rejects a form (422), put the cursor on the first field to fix.
 */
export function focusFirstInvalidField(root) {
    root.querySelector('[aria-invalid="true"]')?.focus();
}
