export const AUTOMATION_SECTIONS = [
    { value: 'assignment', label: 'Priskirti įstaigoms' },
    { value: 'publish', label: 'Publikavimo taisyklės' },
    { value: 'resolution_term', label: 'Sprendimo terminų taisyklės' },
    { value: 'inactive_reporter', label: 'Nebenaudojamos pareiškėjo paskyros' },
    { value: 'resolved_problem_privacy', label: 'Išspręstų problemų paslėpimas' },
]

export const INACTIVITY_DAYS_FALLBACK = 180

export const RESOLVED_PRIVACY_DAYS_MIN = 30
export const RESOLVED_PRIVACY_DAYS_MAX = 3650
export const RESOLVED_PRIVACY_DAYS_DEFAULT = 180

export const INACTIVE_REPORTER_DAYS_MIN = 30
export const INACTIVE_REPORTER_DAYS_MAX = 3650
export const INACTIVE_REPORTER_DAYS_DEFAULT = 180

export const AUTOMATION_SAVE_DEBOUNCE_MS = 450

export const AUTOMATION_RESOLVED_PRIVACY_COPY = {
    sectionTitle: 'Išspręstų problemų paslėpimas',
    leadParagraph:
        'Įjungus, po nustatyto dienų skaičiaus nuo išsprendimo gali būti automatiškai nuimamas viešas skelbimas, kai serveryje paleidžiama suplanuota užduotis; lentelėje rodoma numatoma data.',
    switchLabel: 'Automatinis paslėpimas:',
    tableColumnProblem: 'Problema',
    tableColumnResolvedAt: 'Išspręsta data',
    tableColumnHideAt: 'Paslėpimo data',
    daysFieldLabel: 'Terminas nuo išsprendimo (dienomis)',
    searchLabel: 'Ieškoti problemos',
    searchPlaceholder: 'Numeris, adresas ar aprašymas',
    viewProblem: 'Peržiūrėti',
    noSearchMatches: 'Pagal paiešką nieko nerasta.',
    noPublicResolvedProblems: 'Nėra viešai rodomų išspręstų problemų.',
    daysHint: (minDays, maxDays) => `Leistina reikšmė: nuo ${minDays} iki ${maxDays} dienų.`,
    daysErrorEmpty: 'Įveskite terminą dienomis.',
    daysErrorTooLow: (minDays) => `Per mažas terminas. Minimali reikšmė: ${minDays} dienų.`,
    daysErrorTooHigh: (maxDays) => `Per didelis terminas. Maksimali reikšmė: ${maxDays} dienų.`,
}

export const AUTOMATION_INACTIVE_REPORTER_COPY = {
    sectionTitle: 'Nebenaudojamos pareiškėjo paskyros',
    leadParagraph:
        'Automatiškai šalinti pareiškėjų paskyras, jei prie paskyros nebuvo prisijungta ilgiau nei nustatytas terminas. Priminimas siunčiamas prieš pusę termino (apvalinant į didesnę pusę).',
    switchLabel: 'Automatinis šalinimas:',
    daysFieldLabel: 'Neaktyvumo terminas (dienomis)',
    searchLabel: 'Ieškoti vartotojo',
    searchPlaceholder: 'Vardas arba el. paštas',
    tableColumnUser: 'Vartotojas',
    tableColumnLastLogin: 'Paskutinis prisijungimas',
    tableColumnInactiveDays: 'Neaktyvumo dienos',
    noSearchMatches: 'Pagal paiešką nieko nerasta.',
    noInactiveReporters: 'Nėra pareiškėjų arba jie dar neprisijungė.',
    daysHint: (minDays, maxDays) => `Leistina reikšmė: nuo ${minDays} iki ${maxDays} dienų.`,
    daysErrorEmpty: 'Įveskite neaktyvumo terminą dienomis.',
    daysErrorTooLow: (minDays) => `Per mažas terminas. Minimali reikšmė: ${minDays} dienų.`,
    daysErrorTooHigh: (maxDays) => `Per didelis terminas. Maksimali reikšmė: ${maxDays} dienų.`,
}
