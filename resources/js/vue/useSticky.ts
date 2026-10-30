import { nextTick, onBeforeUnmount, onMounted, ref, watch, type Ref } from 'vue';
import type { TableColumn } from './types';
export function useSticky(root: Ref<HTMLElement | undefined>, columns: Ref<TableColumn[]>, pinned: Ref<string[]>, selection: Ref<boolean>) {
    const offsets = ref<Record<string, number>>({});
    let observer: ResizeObserver | undefined;
    function measure(): void {
        let left = selection.value ? (root.value?.querySelector<HTMLElement>('[data-selection-head]')?.getBoundingClientRect().width ?? 32) : 0;
        const next: Record<string, number> = {};
        for (const column of columns.value) {
            if (!pinned.value.includes(column.attribute)) continue;
            const head = [...(root.value?.querySelectorAll<HTMLElement>('th[data-column]') ?? [])].find(
                (head) => head.dataset.column === column.attribute,
            );
            if (head) {
                next[column.attribute] = left;
                left += head.getBoundingClientRect().width;
            }
        }
        offsets.value = next;
    }
    async function observe(): Promise<void> {
        await nextTick();
        observer?.disconnect();
        if (root.value) {
            observer?.observe(root.value);
            root.value.querySelectorAll('th').forEach((head) => observer?.observe(head));
        }
        measure();
    }
    onMounted(() => {
        observer = new ResizeObserver(measure);
        void observe();
    });
    watch([columns, pinned, selection], observe, { deep: true });
    onBeforeUnmount(() => observer?.disconnect());
    return (attribute: string) =>
        pinned.value.includes(attribute) ? { position: 'sticky' as const, left: `${offsets.value[attribute] ?? 0}px`, zIndex: 10 } : undefined;
}
