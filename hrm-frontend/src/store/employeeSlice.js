import { createSlice } from '@reduxjs/toolkit';

const initialState = {
  employees: [],
  currentEmployee: null,
  departments: [],
  designations: [],
  loading: false,
  error: null,
};

const employeeSlice = createSlice({
  name: 'employee',
  initialState,
  reducers: {
    setEmployees: (state, action) => {
      state.employees = action.payload;
    },
    setCurrentEmployee: (state, action) => {
      state.currentEmployee = action.payload;
    },
    setDepartments: (state, action) => {
      state.departments = action.payload;
    },
    setDesignations: (state, action) => {
      state.designations = action.payload;
    },
    setLoading: (state, action) => {
      state.loading = action.payload;
    },
    setError: (state, action) => {
      state.error = action.payload;
    }
  },
});

export const { setEmployees, setCurrentEmployee, setDepartments, setDesignations, setLoading, setError } = employeeSlice.actions;
export default employeeSlice.reducer;
