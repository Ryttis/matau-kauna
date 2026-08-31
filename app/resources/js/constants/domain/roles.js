export const ROLE_SYSTEM_ADMIN = 'ROLE_SYSTEM_ADMIN'
export const ROLE_PROBLEM_ADMIN = 'ROLE_PROBLEM_ADMIN'
export const ROLE_INSTITUTION_ADMIN = 'ROLE_INSTITUTION_ADMIN'
export const ROLE_MUNICIPALITY_REP = 'ROLE_MUNICIPALITY_REP'
export const ROLE_EMPLOYEE = 'ROLE_EMPLOYEE'
export const ROLE_REPORTER = 'ROLE_REPORTER'

// Vidinė rolė — bet kuri, išskyrus pareiškėją. Sąrašas nėra uždaras: per RBAC
// sukūrtos rolės taip pat yra vidinės.
export const isStaffRole = (role) => Boolean(role) && role !== ROLE_REPORTER
