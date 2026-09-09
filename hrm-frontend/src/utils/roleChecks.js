export const ROLES = {
  SUPER_ADMIN: 'Super Admin',
  HR_ADMIN: 'HR Admin',
  HR_EXECUTIVE: 'HR Executive',
  FINANCE_ADMIN: 'Finance / Payroll Admin',
  MANAGER: 'Manager / Team Lead',
  EMPLOYEE: 'Employee',
};

export const ADMIN_ROLES = [
  ROLES.SUPER_ADMIN,
  ROLES.HR_ADMIN,
  ROLES.HR_EXECUTIVE
];

export const isSuperAdmin = (roles) => roles?.includes(ROLES.SUPER_ADMIN);
export const isHrAdmin = (roles) => roles?.includes(ROLES.HR_ADMIN);
export const isHrExecutive = (roles) => roles?.includes(ROLES.HR_EXECUTIVE);
export const isManager = (roles) => roles?.includes(ROLES.MANAGER);
export const isEmployee = (roles) => roles?.includes(ROLES.EMPLOYEE);
