import { createSlice } from '@reduxjs/toolkit';

const initialState = {
  requests: [],
  balances: [],
  leaveTypes: [],
  loading: false,
  error: null,
};

const leaveSlice = createSlice({
  name: 'leave',
  initialState,
  reducers: {
    setRequests: (state, action) => {
      state.requests = action.payload;
    },
    setBalances: (state, action) => {
      state.balances = action.payload;
    },
    setLeaveTypes: (state, action) => {
      state.leaveTypes = action.payload;
    },
    setLoading: (state, action) => {
      state.loading = action.payload;
    },
    setError: (state, action) => {
      state.error = action.payload;
    }
  },
});

export const { setRequests, setBalances, setLeaveTypes, setLoading, setError } = leaveSlice.actions;
export default leaveSlice.reducer;
