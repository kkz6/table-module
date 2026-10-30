<script setup lang="ts">
import { Button, Select } from '@workspace/ui';
import { ChevronLeft, ChevronRight, ChevronsLeft } from 'lucide-vue-next';
import { computed } from 'vue';
import type { TableResults } from './types';
const props = withDefaults(
    defineProps<{ meta: TableResults<object>; options: number[]; perPage: number; busy?: boolean; name: string; type?: string }>(),
    { type: 'full' },
);
const emit = defineEmits<{ visit: [url: string]; change: [perPage: string] }>();
const full = computed(() => props.meta.last_page != null && props.meta.total != null);
const pages = computed(() => {
    const last = props.meta.last_page ?? 1,
        current = props.meta.current_page ?? 1;
    return [...new Set([1, last, current - 2, current - 1, current, current + 1, current + 2])]
        .filter((page) => page >= 1 && page <= last)
        .sort((a, b) => a - b)
        .flatMap((page, index, all) => (index && page - all[index - 1] > 1 ? ['…', page] : [page]));
});
function pageUrl(page: number): string {
    const url = new URL(props.meta.last_page_url || window.location.href, window.location.origin);
    url.searchParams.set(props.name === 'default' ? 'page' : `${props.name}[page]`, String(page));
    return url.pathname + url.search;
}
</script>
<template>
    <div class="it-pagination flex w-full flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div class="flex flex-wrap items-center gap-4">
            <p class="text-muted-foreground text-sm whitespace-nowrap">
                <template v-if="full">{{ meta.from || 0 }}–{{ meta.to || 0 }} of {{ meta.total }}</template>
                <template v-else-if="meta.current_page">Page {{ meta.current_page }} · {{ meta.data.length }} rows</template>
                <template v-else>{{ meta.data.length }} rows on this page</template>
            </p>
            <nav
                v-if="!full || (meta.last_page ?? 1) > 1"
                class="flex items-center gap-1"
                aria-label="Table pagination"
            >
                <Button
                    v-if="type === 'cursor' && meta.first_page_url"
                    variant="ghost"
                    size="icon"
                    aria-label="First page"
                    :disabled="busy || meta.on_first_page"
                    @click="emit('visit', meta.first_page_url)"
                >
                    <ChevronsLeft class="size-4" />
                </Button>
                <Button
                    variant="ghost"
                    size="icon"
                    aria-label="Previous page"
                    :disabled="busy || !meta.prev_page_url"
                    @click="meta.prev_page_url && emit('visit', meta.prev_page_url)"
                >
                    <ChevronLeft class="size-4" />
                </Button>
                <template v-if="full">
                    <template
                        v-for="(page, index) in pages"
                        :key="index"
                    >
                        <span
                            v-if="page === '…'"
                            class="px-2"
                        >
                            …
                        </span>
                        <Button
                            v-else
                            :variant="page === meta.current_page ? 'outline' : 'ghost'"
                            size="icon"
                            :aria-label="`Go to page ${page}`"
                            :aria-current="page === meta.current_page ? 'page' : undefined"
                            :disabled="busy"
                            @click="emit('visit', pageUrl(Number(page)))"
                        >
                            {{ page }}
                        </Button>
                    </template>
                </template>
                <Button
                    variant="ghost"
                    size="icon"
                    aria-label="Next page"
                    :disabled="busy || !meta.next_page_url"
                    @click="meta.next_page_url && emit('visit', meta.next_page_url)"
                >
                    <ChevronRight class="size-4" />
                </Button>
            </nav>
        </div>
        <div
            v-if="options.length"
            class="flex items-center gap-2"
        >
            <span class="text-muted-foreground text-sm whitespace-nowrap">Per page</span>
            <Select
                aria-label="Rows per page"
                class="w-20"
                :model-value="String(perPage)"
                :options="options.map((value) => ({ value: String(value), label: String(value) }))"
                :disabled="busy"
                @update:model-value="(value) => emit('change', String(value ?? perPage))"
            />
        </div>
    </div>
</template>
