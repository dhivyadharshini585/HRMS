import api from './api';

export const reportService = {
  getAttendanceReport: async (params = {}) => {
    const response = await api.get('/reports/attendance', { params });
    return response.data;
  },

  exportAttendanceReport: async (params = {}) => {
    const response = await api.get('/reports/attendance/export', {
      params,
      responseType: 'blob',
    });
    return response.data;
  },

  getLeaveReport: async (params = {}) => {
    const response = await api.get('/reports/leave', { params });
    return response.data;
  },

  exportLeaveReport: async (params = {}) => {
    const response = await api.get('/reports/leave/export', {
      params,
      responseType: 'blob',
    });
    return response.data;
  },

  getEmployeeRatingsReport: async (params = {}) => {
    const response = await api.get('/reports/performance/employee-ratings', { params });
    return response.data;
  },

  getDepartmentPerformanceReport: async (params = {}) => {
    const response = await api.get('/reports/performance/department-performance', { params });
    return response.data;
  },

  getGoalCompletionReport: async (params = {}) => {
    const response = await api.get('/reports/performance/goal-completion', { params });
    return response.data;
  },

  getTrainingCompletionReport: async (params = {}) => {
    const response = await api.get('/reports/performance/training-completion', { params });
    return response.data;
  },
};

export default reportService;
