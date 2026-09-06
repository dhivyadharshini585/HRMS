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
};

export default reportService;
