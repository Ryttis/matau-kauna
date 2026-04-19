<script setup>
import { computed, ref } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
    problem: { type: Object, required: true },
    canPublish: { type: Boolean, default: false },
    showPublishToggle: { type: Boolean, default: true },
})

const showSwitch = computed(() =>
    Boolean(props.canPublish && props.problem?.id && props.showPublishToggle)
)

const isOn = computed(() => Boolean(props.problem?.is_published_publicly))

const patching = ref(false)

function handleToggle(e) {
    e.stopPropagation()
    if (!showSwitch.value || patching.value) {
        return
    }
    patching.value = true
    router.patch(route('problems.updatePublish', props.problem.id), {
        is_published_publicly: !isOn.value,
    }, {
        preserveScroll: true,
        onFinish: () => {
            patching.value = false
        },
    })
}
</script>

<template>
    <label v-if="showSwitch" class="publish-cell" :class="{ 'publish-cell--busy': patching }" @click.stop>
        <input
            type="checkbox"
            class="publish-cell-input"
            :checked="isOn"
            :disabled="patching"
            @change="handleToggle"
        />
        <span class="publish-cell-slider" />
    </label>
</template>

<style scoped>
.publish-cell {
    display: inline-flex;
    align-items: center;
    cursor: pointer;
}

.publish-cell--busy {
    opacity: 0.65;
    pointer-events: none;
}

.publish-cell-input {
    position: absolute;
    opacity: 0;
    width: 0;
    height: 0;
}

.publish-cell-slider {
    position: relative;
    display: inline-block;
    width: 40px;
    height: 22px;
    background: #d1d5db;
    border-radius: 11px;
    transition: background 0.2s;
}

.publish-cell-slider::after {
    content: '';
    position: absolute;
    top: 2px;
    left: 2px;
    width: 18px;
    height: 18px;
    background: #fff;
    border-radius: 50%;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
    transition: transform 0.2s;
}

.publish-cell-input:checked + .publish-cell-slider {
    background: #1946A0;
}

.publish-cell-input:checked + .publish-cell-slider::after {
    transform: translateX(18px);
}
</style>
