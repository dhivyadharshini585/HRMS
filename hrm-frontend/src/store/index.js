import { configureStore } from '@reduxjs/toolkit';
import authReducer from './authSlice';
import employeeReducer from './employeeSlice';
import attendanceReducer from './attendanceSlice';
import leaveReducer from './leaveSlice';
import recruitmentReducer from './recruitmentSlice';

const store = configureStore({
  reducer: {
    auth: authReducer,
    employee: employeeReducer,
    attendance: attendanceReducer,
    leave: leaveReducer,
    recruitment: recruitmentReducer,
  },
});

export default store;
