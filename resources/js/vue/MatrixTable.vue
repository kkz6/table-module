<script setup lang="ts" generic="T extends { key: string }">
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@workspace/ui';
defineProps<{ rows: T[]; columns: { key: string; label: string; class?: string }[]; label: string }>();
</script>
<template>
    <div class="it-matrix min-w-0 rounded-2xl p-2 pt-0">
        <Table :aria-label="label">
            <TableHeader>
                <TableRow class="hover:bg-transparent">
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
                >
                    <TableCell
                        v-for="column in columns"
                        :key="column.key"
                        class="px-4 py-3 text-[13px]"
                        :class="column.class"
                    >
                        <slot
                            :name="`cell-${column.key}`"
                            :row="row"
                        />
                    </TableCell>
                </TableRow>
                <TableRow v-if="!rows.length">
                    <TableCell
                        :colspan="columns.length"
                        class="text-muted-foreground p-10 text-center text-sm"
                    >
                        No records found.
                    </TableCell>
                </TableRow>
            </TableBody>
        </Table>
    </div>
</template>
<style>
.it-matrix {
    background: #f0f0f2;
}
.dark .it-matrix {
    background: #121214;
}
.it-matrix table {
    border-spacing: 0;
    border-collapse: separate;
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
