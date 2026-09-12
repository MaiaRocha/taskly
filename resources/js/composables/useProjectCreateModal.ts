import { ref } from 'vue';

// Module-scoped singleton: a single Create Project modal instance is
// mounted once by AppShell for the whole authenticated area, so every entry
// point (PageHeader, EmptyState, Sidebar quick-add) shares the same
// open/close state instead of each mounting its own modal.
const isOpen = ref(false);

export function useProjectCreateModal() {
    function open(): void {
        isOpen.value = true;
    }

    function close(): void {
        isOpen.value = false;
    }

    /**
     * Forces the modal closed regardless of current state — called by
     * AppShell on setup and on unmount so this module-scoped UI state never
     * survives across authenticated lifecycles (e.g. a modal left open when
     * the session expires must not reopen on the next login's AppShell).
     */
    function reset(): void {
        isOpen.value = false;
    }

    return { isOpen, open, close, reset };
}
