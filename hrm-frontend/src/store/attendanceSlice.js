import { createSlice } from '@reduxjs/toolkit';

const initialState = {
  logs: [],
  shifts: [],
  loading: false,
  error: null,
};

const attendanceSlice = createSlice({
  name: 'attendance',
  initialState,
  reducers: {
    setLogs: (state, action) => {
      state.logs = action.payload;
    },
    setShifts: (state, action) => {
      state.shifts = action.payload;
    },
    setLoading: (state, action) => {
      state.loading = action.payload;
    },
    setError: (state, action) => {
      state.error = action.payload;
    }
  },
});

export const { setLogs, setShifts, setLoading, setError } = attendanceSlice.actions;
export default attendanceSlice.reducer;
