<script setup lang="ts" generic="T extends { key: string }">
import { Button, Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@workspace/ui';
import { useSortableRows } from '@workspace/useSortableRows';
import { GripVertical } from 'lucide-vue-next';
import { computed, ref } from 'vue';
const props = withDefaults(
    defineProps<{
        rows: T[];
        columns: { key: string; label: string; class?: string }[];
        label: string;
        sortable?: boolean;
        disabled?: boolean;
        empty?: string;
    }>(),
    { sortable: false, disabled: false, empty: 'No records found.' },
);
const emit = defineEmits<{ 'update:rows': [rows: T[]] }>();
const root = ref<HTMLElement>();
const container = computed(() => (props.sortable ? (root.value?.querySelector('tbody') ?? undefined) : undefined));
const rows = computed({ get: () => props.rows, set: (value: T[]) => emit('update:rows', value) });
function cellValue(row: T, column: string): unknown {
    return (row as Record<string, unknown>)[column] ?? '—';
}
const { message, keyboard } = useSortableRows(container, rows, { disabled: () => props.disabled || !props.sortable, key: (row) => row.key });
</script>
<template>
    <div
        ref="root"
        class="it-matrix min-w-0 rounded-2xl p-2 pt-0"
    >
        <Table :aria-label="label">
            <TableHeader>
                <TableRow class="hover:bg-transparent">
                    <TableHead
                        v-if="sortable"
                        class="w-10 px-2"
                    >
                        <span class="sr-only">Reorder items</span>
                    </TableHead>
                    <TableHead
                        v-for="column in columns"
                        :key="column.key"
                        class="h-11 px-4 text-[13px] font-medium"
                        :class="column.class"
                    >
                        {{ column.label }}
                    </TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                <TableRow
                    v-for="row in rows"
                    :key="row.key"
                    :data-sortable-item="row.key"
                >
                    <TableCell
                        v-if="sortable"
                        class="w-10 px-2 py-3 align-top"
                    >
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            data-sortable-handle
                            class="cursor-grab touch-none active:cursor-grabbing"
                            :disabled="disabled"
                            :aria-label="`Reorder item ${rows.indexOf(row) + 1}`"
                            @keydown="keyboard($event, row.key)"
                        >
                            <GripVertical class="text-muted-foreground size-4" />
                        </Button>
                    </TableCell>
                    <TableCell
                        v-for="column in columns"
                        :key="column.key"
                        class="px-4 py-3 text-[13px]"
                        :class="column.class"
                    >
                        <slot
                            :name="`cell-${column.key}`"
                            :row="row"
                        >
                            {{ cellValue(row, column.key) }}
                        </slot>
                    </TableCell>
                </TableRow>
                <TableRow v-if="!rows.length">
                    <TableCell
                        :colspan="columns.length + (sortable ? 1 : 0)"
                        class="text-muted-foreground p-10 text-center text-sm"
                    >
                        {{ empty }}
                    </TableCell>
                </TableRow>
            </TableBody>
        </Table>
        <p
            v-if="sortable"
            role="status"
            aria-live="polite"
            class="sr-only"
        >
            {{ message }}
        </p>
    </div>
</template>
<style>
.it-matrix {
    background: var(--workspace-table-chrome, #f0f0f2);
}
.dark .it-matrix {
    background: var(--workspace-table-chrome, #121214);
}
.it-matrix table {
    border-spacing: 0;
    border-collapse: separate;
}
.it-matrix thead tr,
.it-matrix thead th {
    border-bottom: 0;
}
.it-matrix th {
    color: var(--foreground);
}
.it-matrix tbody td {
    background: var(--card);
    border-bottom: 1px solid var(--border);
}
.it-matrix tbody td:first-child {
    border-left: 1px solid var(--border);
}
.it-matrix tbody td:last-child {
    border-right: 1px solid var(--border);
}
.it-matrix tbody tr:first-child td {
    border-top: 1px solid var(--border);
}
.it-matrix tbody tr:first-child td:first-child {
    border-start-start-radius: 0.75rem;
}
.it-matrix tbody tr:first-child td:last-child {
    border-start-end-radius: 0.75rem;
}
.it-matrix tbody tr:last-child td:first-child {
    border-end-start-radius: 0.75rem;
}
.it-matrix tbody tr:last-child td:last-child {
    border-end-end-radius: 0.75rem;
}
</style>
