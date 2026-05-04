<script setup>
defineProps({
    employees: {
        type: Array,
        default: () => []
    }
})
</script>

<template>
    <div class="employee-activity-block">
        <h3 class="section-title">Darbuotojų aktyvumas</h3>
        <div v-if="employees.length === 0" class="empty-state">
            <p>Nėra duomenų</p>
            <p class="empty-state-hint">Rodoma tik tada, kai problemai priskirtas darbuotojas, ne tik įstaiga.</p>
        </div>
        <div v-else class="activity-list">
            <div v-for="(employee, idx) in employees" :key="employee.name || idx" class="activity-item">
                <div class="avatar-placeholder"></div>
                <div class="activity-info">
                    <span class="employee-name">{{ employee.name }}</span>
                    <span class="problem-count">{{ employee.count ?? employee.problems ?? 0 }} problemos</span>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.employee-activity-block {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.section-title {
    font-size: 18px;
    font-weight: 600;
    margin: 0 0 20px 0;
    font-family: 'Open Sans', sans-serif;
    color: #333;
}

.empty-state {
    padding: 32px 20px 40px;
    text-align: center;
    color: #999;
}

.empty-state-hint {
    margin: 10px 0 0;
    font-size: 13px;
    line-height: 1.4;
    color: #777;
}

.activity-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.activity-item {
    display: flex;
    align-items: center;
    gap: 15px;
}

.avatar-placeholder {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #e0e0e0;
    flex-shrink: 0;
}

.activity-info {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.employee-name {
    font-size: 14px;
    color: #333;
    font-family: 'Open Sans', sans-serif;
}

.problem-count {
    font-size: 12px;
    color: #666;
    font-family: 'Open Sans', sans-serif;
}
</style>
