import api from './api';

export const timesheetService = {
  getTimesheets: async () => {
    const response = await api.get('/timesheets');
    return response.data.data || response.data; // Handles paginated or non-paginated
  },
  createTimesheet: async (data) => {
    const endpoint = data.entries ? '/timesheets/weekly' : '/timesheets';
    const response = await api.post(endpoint, data);
    return response.data;
  },
  getProjects: async () => {
    const response = await api.get('/projects');
    return response.data;
  },
  getUtilization: async () => {
    const response = await api.get('/projects-utilization');
    return response.data;
  }
};
