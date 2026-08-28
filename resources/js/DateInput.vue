<script setup lang="ts">
defineOptions({ inheritAttrs: false });

defineProps<{ modelValue: string | null }>();
const emit = defineEmits<{ "update:modelValue": [value: string | null] }>();
</script>

<template>
    <span class="date-input">
        <input
            v-bind="$attrs"
            :value="modelValue ?? ''"
            type="date"
            @input="emit('update:modelValue', ($event.target as HTMLInputElement).value || null)"
        />
        <button
            v-if="modelValue"
            type="button"
            class="date-input-clear"
            aria-label="Limpar data"
            title="Limpar data"
            @click="emit('update:modelValue', null)"
        >
            <svg viewBox="0 0 20 20" aria-hidden="true">
                <circle cx="10" cy="10" r="7.25" />
                <path d="m7.4 7.4 5.2 5.2m0-5.2-5.2 5.2" />
            </svg>
        </button>
    </span>
</template>

<style scoped>
.date-input { position: relative; display: block; width: 100%; }
.date-input input { width: 100%; }
.date-input input::-webkit-calendar-picker-indicator { opacity: .58; }
.date-input-clear { position: absolute; z-index: 1; top: 50%; right: 27px; display: grid; width: 18px; height: 18px; padding: 0; border: 0; border-radius: 50%; background: transparent; color: #858c9b; cursor: pointer; transform: translateY(-50%); }
.date-input-clear:hover { color: #5d52c8; background: #efedff; }
.date-input-clear:focus-visible { outline: 2px solid #8679e9; outline-offset: 1px; }
.date-input-clear svg { width: 15px; height: 15px; fill: none; stroke: currentColor; stroke-linecap: round; stroke-width: 1.7; }
</style>
