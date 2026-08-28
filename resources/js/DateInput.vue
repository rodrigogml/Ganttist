<script setup lang="ts">
import { ref } from "vue";

defineOptions({ inheritAttrs: false });

defineProps<{ modelValue: string | null }>();
const emit = defineEmits<{ "update:modelValue": [value: string | null] }>();
const input = ref<HTMLInputElement | null>(null);

function openPicker() {
    if (!input.value || input.value.disabled || input.value.readOnly) return;
    if (typeof input.value.showPicker === "function") {
        input.value.showPicker();
        return;
    }
    input.value.focus();
    input.value.click();
}
</script>

<template>
    <span class="date-input">
        <input
            ref="input"
            v-bind="$attrs"
            :value="modelValue ?? ''"
            type="date"
            @input="emit('update:modelValue', ($event.target as HTMLInputElement).value || null)"
        />
        <button
            v-if="modelValue"
            type="button"
            class="date-input-action date-input-clear"
            aria-label="Limpar data"
            title="Limpar data"
            @click="emit('update:modelValue', null)"
        >
            <svg viewBox="0 0 20 20" aria-hidden="true">
                <circle cx="10" cy="10" r="7.25" />
                <path d="m7.4 7.4 5.2 5.2m0-5.2-5.2 5.2" />
            </svg>
        </button>
        <button
            type="button"
            class="date-input-action date-input-calendar"
            aria-label="Abrir calendário"
            title="Abrir calendário"
            @click="openPicker"
        >
            <svg viewBox="0 0 20 20" aria-hidden="true">
                <rect x="3.25" y="4.5" width="13.5" height="12" rx="2" />
                <path d="M6.5 2.75v3.5m7-3.5v3.5M3.5 8.5h13" />
            </svg>
        </button>
    </span>
</template>

<style scoped>
.date-input { position: relative; display: block; width: 100%; }
.date-input input { width: 100%; }
.date-input input::-webkit-calendar-picker-indicator { opacity: 0; pointer-events: none; }
.date-input-action { position: absolute; z-index: 1; top: 50%; display: grid; width: 18px; height: 18px; padding: 0; border: 0; border-radius: 50%; background: transparent; color: #858c9b; cursor: pointer; transform: translateY(-50%); }
.date-input-clear { right: 29px; }
.date-input-calendar { right: 6px; }
.date-input-action:hover { color: #5d52c8; background: #efedff; }
.date-input-action:focus-visible { outline: 2px solid #8679e9; outline-offset: 1px; }
.date-input-action svg { width: 15px; height: 15px; fill: none; stroke: currentColor; stroke-linecap: round; stroke-linejoin: round; stroke-width: 1.7; }
</style>
