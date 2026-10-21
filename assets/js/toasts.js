/*
 * Flash messages fade out on their own after a few seconds, as in the bvsoft admin.
 */
const VISIBLE_MS = 5000;

export function initToasts(root) {
    root.querySelectorAll('[data-autodismiss]').forEach((toast) => {
        setTimeout(() => {
            toast.classList.add('is-leaving');
            toast.addEventListener('animationend', () => toast.remove(), { once: true });
        }, VISIBLE_MS);
    });
}
