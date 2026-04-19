const MAP = {
    ą: 'a',
    č: 'c',
    ę: 'e',
    ė: 'e',
    į: 'i',
    š: 's',
    ų: 'u',
    ū: 'u',
    ž: 'z',
}

const RE = /[ąčęėįšųūž]/g

export function foldLithuanianForSearch(value) {
    const s = value == null ? '' : String(value)
    return s.toLowerCase().replace(RE, (ch) => MAP[ch] ?? ch)
}
