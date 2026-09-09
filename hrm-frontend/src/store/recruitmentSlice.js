import { createSlice } from '@reduxjs/toolkit';

const initialState = {
  jobOpenings: [],
  candidates: [],
  currentCandidate: null,
  loading: false,
  error: null,
};

const recruitmentSlice = createSlice({
  name: 'recruitment',
  initialState,
  reducers: {
    setJobOpenings: (state, action) => {
      state.jobOpenings = action.payload;
    },
    setCandidates: (state, action) => {
      state.candidates = action.payload;
    },
    setCurrentCandidate: (state, action) => {
      state.currentCandidate = action.payload;
    },
    setLoading: (state, action) => {
      state.loading = action.payload;
    },
    setError: (state, action) => {
      state.error = action.payload;
    }
  },
});

export const { setJobOpenings, setCandidates, setCurrentCandidate, setLoading, setError } = recruitmentSlice.actions;
export default recruitmentSlice.reducer;
