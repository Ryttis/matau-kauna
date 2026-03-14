<template>
    <div class="landing-hero-dots" aria-hidden="true">
        <span class="landing-hero-dot landing-hero-dot--1" />
        <span class="landing-hero-dot landing-hero-dot--2" />
        <span class="landing-hero-dot landing-hero-dot--3" />
        <span class="landing-hero-dot landing-hero-dot--4" />
        <span class="landing-hero-dot landing-hero-dot--5 landing-hero-dot--center" />
        <span class="landing-hero-dot landing-hero-dot--6 landing-hero-dot--center" />
        <span class="landing-hero-dot landing-hero-dot--7 landing-hero-dot--center" />
        <span class="landing-hero-dot landing-hero-dot--8" />
        <span class="landing-hero-dot landing-hero-dot--9" />
        <span class="landing-hero-dot landing-hero-dot--10" />
        <span class="landing-hero-dot landing-hero-dot--11" />
        <span class="landing-hero-dot landing-hero-dot--12" />
        <span class="landing-hero-dot landing-hero-dot--13" />
    </div>
</template>

<style scoped>
.landing-hero-dots {
    position: absolute;
    inset: 0;
    z-index: 3;
    pointer-events: none;
}

.landing-hero-dot {
    position: absolute;
    border-radius: 50%;
}

.landing-hero-dot--1 { background: #e74c3c; top: 17%; left: 16%; width: 14px; height: 14px; }
.landing-hero-dot--2 { background: #e74c3c; bottom: 25%; left: 20%; width: 8px; height: 8px; }
.landing-hero-dot--3 { background: #27ae60; top: 10%; left: 4%; width: 6px; height: 6px; }
.landing-hero-dot--4 { background: #f1c40f; bottom: 32%; left: 10%; width: 12px; height: 12px; }
.landing-hero-dot--5 { background: #e74c3c; top: 5%; left: 70%; width: 8px; height: 8px; }
.landing-hero-dot--6 { background: #27ae60; top: 53%; left: 52%; width: 10px; height: 10px; }
.landing-hero-dot--7 { background: #f1c40f; bottom: 14%; left: 52%; width: 14px; height: 14px; }
.landing-hero-dot--8 { background: #27ae60; top: 15%; right: 2%; width: 10px; height: 10px; }
.landing-hero-dot--9 { background: #e74c3c; top: 50%; right: 4%; width: 26px; height: 26px; }
.landing-hero-dot--10 { background: #f1c40f; bottom: 18%; right: 8%; width: 14px; height: 14px; }
.landing-hero-dot--11 { background: #e74c3c; bottom: -38px; left: 15%; width: 12px; height: 12px; }
.landing-hero-dot--12 { background: #f1c40f; bottom: -80px; left: 40%; width: 14px; height: 14px; }
.landing-hero-dot--13 { background: #27ae60; bottom: -36px; right: 18%; width: 10px; height: 10px; }

@media (min-width: 900px) {
    .landing-hero-dot--1 { width: 18px; height: 18px; left: 18%; }
    .landing-hero-dot--2 { width: 26px; height: 25px; left: 48%; }
    .landing-hero-dot--3 { width: 24px; height: 24px; left: -1%; }
    .landing-hero-dot--4 { width: 14px; height: 14px; }
    .landing-hero-dot--5 { width: 10px; height: 10px; left: 87%; }
    .landing-hero-dot--6 { width: 42px; height: 42px; left: 78%; }
    .landing-hero-dot--7 { width: 16px; height: 16px; left: 60%; }
    .landing-hero-dot--8 { width: 22px; height: 22px; }
    .landing-hero-dot--9 { width: 28px; height: 28px; right: -1%; }
    .landing-hero-dot--10 { width: 18px; height: 18px; }
    .landing-hero-dot--11 { width: 16px; height: 16px; }
    .landing-hero-dot--12 { width: 18px; height: 18px; }
    .landing-hero-dot--13 { width: 14px; height: 14px; }
}
</style>
