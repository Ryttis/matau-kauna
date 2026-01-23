<script setup>
import AppButton from '@/Components/App/AppButton.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head } from '@inertiajs/vue3'
import { ref } from 'vue'

const rows = ref([
    {
        id: 1,
        status: 'Nauja',
        date: '2026-01-02',
        title: 'Sugedęs šviesoforas',
        category: 'Eismas',
        address: 'Gedimino pr. 1, Vilnius',
        description: 'Neveikia šviesoforas sankryžoje'
    },
    {
        id: 2,
        status: 'Sprendžiama',
        date: '2026-01-03',
        title: 'Duobė kelyje',
        category: 'Keliai',
        address: 'Kalvarijų g. 45, Vilnius',
        description: 'Didelė duobė važiuojamojoje dalyje'
    },
    {
        id: 3,
        status: 'Išspręsta',
        date: '2026-01-04',
        title: 'Neveikiantis apšvietimas',
        category: 'Apšvietimas',
        address: 'Antakalnio g. 12, Vilnius',
        description: 'Nedega gatvės šviestuvas'
    },
    {
        id: 4,
        status: 'Nauja',
        date: '2026-01-05',
        title: 'Užtvindyta gatvė',
        category: 'Infrastruktūra',
        address: 'Ozo g. 18, Vilnius',
        description: 'Po lietaus telkšo vanduo'
    },
    {
        id: 5,
        status: 'Sprendžiama',
        date: '2026-01-06',
        title: 'Sugedęs šaligatvis',
        category: 'Pėsčiųjų takai',
        address: 'Savanorių pr. 92, Vilnius',
        description: 'Atskilę plytelės'
    },
    {
        id: 6,
        status: 'Išspręsta',
        date: '2026-01-07',
        title: 'Nelegalus šiukšlynas',
        category: 'Aplinka',
        address: 'Ukmergės g. 220, Vilnius',
        description: 'Paliktos statybinės atliekos'
    },
    {
        id: 7,
        status: 'Nauja',
        date: '2026-01-08',
        title: 'Triukšmas naktį',
        category: 'Viešoji tvarka',
        address: 'Pilies g. 5, Vilnius',
        description: 'Triukšmingi renginiai po 23 val.'
    },
    {
        id: 8,
        status: 'Sprendžiama',
        date: '2026-01-09',
        title: 'Sugedęs suoliukas',
        category: 'Parkai',
        address: 'Vingio parkas',
        description: 'Sulūžusi sėdimoji dalis'
    }
])
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <div class="search-wrp">
            <input
                type="text"
                class="search-input"
                placeholder="Ieškoti"
            />
            <img src="icons/filter-icon.png" />
        </div>

        <table>
            <thead>
                <tr>
                    <th>Nr.</th>
                    <th>Būsena</th>
                    <th>Data</th>
                    <th>Pavadinimas</th>
                    <th>Kategorija</th>
                    <th>Adresas</th>
                    <th>Aprašymas</th>
                </tr>
            </thead>

            <tbody>
                <tr v-for="row in rows" :key="row.id">
                    <td>{{ row.id }}</td>
                    <td>{{ row.status }}</td>
                    <td>{{ row.date }}</td>
                    <td>{{ row.title }}</td>
                    <td>{{ row.category }}</td>
                    <td>{{ row.address }}</td>
                    <td>{{ row.description }}</td>
                </tr>
            </tbody>
        </table>
        <div class="table-footer-wrp">
            <span>Žiūrėti daugiau</span>
            <img src="icons/arrow-icon.png" />
        </div>
        <div class="list-navigation">
            <AppButton text="Priskirti problema" />
            <AppButton text="Atvaizduoti/keisti busena" />
            <AppButton text="Eksportuoti i .xlsx" variant="secondary" />
            <AppButton text="Stebeti" variant="secondary" />
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.search-wrp {
    display: flex;
    gap: 40px;
}

.search-wrp img {
    display: block;
    margin: auto 0;
}

.search-input {
    width: 340px;
    height: 40px;
    border: none;
    border-radius: 20px;
    background-color: #F6F7F9;
    color: #333;
    padding: 0 20px;
}

table {
    width: 100%;
    margin-top: 40px;
    font-family: 'Inter';
    border-collapse: collapse;
}

th {
    font-size: 12px;
    font-weight: 500;
    text-align: left;
    padding: 10px;
    border-bottom: 1px solid #e0e0e0;
}

td {
    padding: 12px 10px;
    font-size: 13px;
    border-bottom: 1px solid #f0f0f0;
}

tbody tr:hover {
    background: #f6f7f9;
}

.table-footer-wrp {
    margin-top: 20px;
    display: flex;
    gap: 20px;
    font-size: 16px;
}

.table-footer-wrp img {
    display: block;
    margin: auto 0;
    transform: rotate(-90deg);
}

.list-navigation {
    margin: 40px auto;
    width: 400px;
}
</style>
